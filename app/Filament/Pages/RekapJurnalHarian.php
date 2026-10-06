<?php

namespace App\Filament\Pages;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Rekap Jurnal Umum per hari (read-only).
 * Menampilkan seluruh transaksi pada satu tanggal, dikelompokkan per nomor jurnal,
 * lengkap dengan total debet/kredit dan status balance.
 */
class RekapJurnalHarian extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.rekap-jurnal-harian';

    protected static UnitEnum|string|null $navigationGroup = 'Akuntansi';

    protected static ?string $navigationLabel = 'Rekap Jurnal Harian';

    protected static ?string $title = 'Rekap Jurnal Harian';

    protected static ?int $navigationSort = 5;

    /**
     * Awalan kode akun untuk menghitung mutasi kas & bank.
     * 1121-00 Kas Tunai Mut, 1122-00 Kas Tunai Penjualan Lain, 1131-00 Kas B.Melani => '11'
     * 1212-00 Bank PT INTAN => '12'
     * Ubah di sini bila ada akun kas/bank baru dengan kode di luar pola ini.
     */
    protected const KAS_PREFIX = ['11'];

    protected const BANK_PREFIX = ['12'];

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public string $tanggal = '';

    public string $sumber = '';

    public string $cari = '';

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
    }

    // ── Navigasi tanggal ──────────────────────────────────────────────────

    public function hariSebelumnya(): void
    {
        $this->geserHari(-1);
    }

    public function hariBerikutnya(): void
    {
        $this->geserHari(1);
    }

    public function hariIni(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->sumber = '';
    }

    protected function geserHari(int $selisih): void
    {
        $this->tanggal = Carbon::parse($this->tanggal ?: now())->addDays($selisih)->format('Y-m-d');
        $this->sumber = '';
    }

    public function updatedTanggal(): void
    {
        // Daftar sumber berbeda tiap hari, jadi filter sumber di-reset
        $this->sumber = '';
    }

    // ── Helper ────────────────────────────────────────────────────────────

    /** Sama dengan perhitungan di halaman Jurnal Umum: banyak kosong => harga langsung. */
    private function hitungTotal($banyak, $harga): float
    {
        $harga = (float) $harga;

        if ($banyak === null || $banyak === '' || (float) $banyak <= 0) {
            return $harga;
        }

        return (float) $banyak * $harga;
    }

    private function labelSumber(?JurnalPembantuHeader $header): string
    {
        if (! $header) {
            return 'Jurnal Manual';
        }

        if ($header->modul_asal) {
            return JurnalPembantuHeader::MODUL_ASAL[$header->modul_asal]
                ?? Str::headline($header->modul_asal);
        }

        return JurnalPembantuHeader::JENIS[$header->jenis_transaksi] ?? 'Lainnya';
    }

    private function tipeKasBank(string $kode): ?string
    {
        foreach (self::KAS_PREFIX as $awal) {
            if (str_starts_with($kode, $awal)) {
                return 'kas';
            }
        }
        foreach (self::BANK_PREFIX as $awal) {
            if (str_starts_with($kode, $awal)) {
                return 'bank';
            }
        }

        return null;
    }

    /**
     * Mutasi bersih kas & bank = total debet (uang masuk) - total kredit (uang keluar)
     * dari seluruh jurnal yang sedang tampil. Contoh: masuk 2.000.000, beli jagung 500.000 => 1.500.000.
     */
    private function ringkasKasBank(Collection $groups): array
    {
        $per = ['kas' => [], 'bank' => []];

        foreach ($groups as $g) {
            foreach ($g['lines'] as $l) {
                $kode = (string) $l['no_akun'];
                $tipe = $this->tipeKasBank($kode);
                if (! $tipe) {
                    continue;
                }

                $per[$tipe][$kode] ??= ['kode' => $kode, 'nama' => $l['nama_akun'], 'masuk' => 0.0, 'keluar' => 0.0];
                $per[$tipe][$kode]['masuk'] += $l['debet'];
                $per[$tipe][$kode]['keluar'] += $l['kredit'];
            }
        }

        $hasil = [];
        foreach ($per as $tipe => $akun) {
            ksort($akun);
            $akun = collect($akun)->map(fn ($a) => $a + ['net' => $a['masuk'] - $a['keluar']])->values();

            $hasil[$tipe] = [
                'masuk' => $akun->sum('masuk'),
                'keluar' => $akun->sum('keluar'),
                'net' => $akun->sum('net'),
                'akun' => $akun,
            ];
        }

        $hasil['total'] = [
            'masuk' => $hasil['kas']['masuk'] + $hasil['bank']['masuk'],
            'keluar' => $hasil['kas']['keluar'] + $hasil['bank']['keluar'],
            'net' => $hasil['kas']['net'] + $hasil['bank']['net'],
        ];

        return $hasil;
    }

    // ── Data untuk view ───────────────────────────────────────────────────

    protected function getViewData(): array
    {
        $tanggal = $this->tanggal ?: now()->format('Y-m-d');

        $rows = JurnalUmum::whereDate('tgl', $tanggal)
            ->orderBy('jurnal')
            ->orderBy('map')
            ->orderBy('no_akun')
            ->get();

        // Label sumber transaksi berdasarkan nomor jurnal pembantu yang sama
        $headers = JurnalPembantuHeader::whereIn('jurnal', $rows->pluck('jurnal')->filter()->unique())
            ->get(['jurnal', 'modul_asal', 'jenis_transaksi'])
            ->unique('jurnal')
            ->keyBy('jurnal');

        /** @var Collection $groups */
        $groups = $rows->groupBy(fn ($r) => $r->jurnal ?? 0)->map(function ($items, $noJurnal) use ($headers) {
            $debet = 0.0;
            $kredit = 0.0;

            $lines = $items->map(function ($r) use (&$debet, &$kredit) {
                $nilai = $this->hitungTotal($r->banyak, $r->harga);
                $map = strtolower((string) $r->map);
                $isDebet = in_array($map, ['d', 'debit'], true);
                $isKredit = in_array($map, ['k', 'kredit'], true);

                if ($isDebet) {
                    $debet += $nilai;
                }
                if ($isKredit) {
                    $kredit += $nilai;
                }

                $banyak = ($r->banyak !== null && (float) $r->banyak > 0) ? (float) $r->banyak : null;

                return [
                    'tgl' => $r->tgl ? $r->tgl->locale('id')->translatedFormat('d/M') : '',
                    'jurnal' => $r->jurnal,
                    'no_akun' => $r->no_akun,
                    'nama_akun' => $r->nama_akun,
                    'nama' => $r->nama,
                    'keterangan' => $r->keterangan,
                    'map' => $isDebet ? 'd' : ($isKredit ? 'k' : $map),
                    'banyak' => $banyak,
                    'harga' => (float) $r->harga,
                    'total' => $nilai,
                    'byk_d' => $isDebet ? $banyak : null,
                    'byk_k' => $isKredit ? $banyak : null,
                    'debet' => $isDebet ? $nilai : 0,
                    'kredit' => $isKredit ? $nilai : 0,
                ];
            });

            // Keterangan & nama yang paling sering muncul dijadikan deskripsi jurnal (ditampilkan sekali saja)
            $ket = $lines->pluck('keterangan')->filter()->countBy()->sortDesc()->keys()->first();
            $nama = $lines->pluck('nama')->filter()->countBy()->sortDesc()->keys()->first();

            return [
                'jurnal' => $noJurnal,
                'sumber' => $this->labelSumber($headers->get($noJurnal)),
                'ket' => $ket,
                'nama' => $nama,
                'lines' => $lines,
                'debet' => $debet,
                'kredit' => $kredit,
                'balance' => abs($debet - $kredit) < 0.01,
            ];
        })->values();

        // Daftar pilihan sumber (dihitung sebelum filter supaya dropdown tidak ikut menyempit)
        $daftarSumber = $groups->pluck('sumber')->unique()->sort()->values();

        if ($this->sumber !== '') {
            $groups = $groups->where('sumber', $this->sumber)->values();
        }

        // Pencarian: kelompok ditampilkan utuh jika ada baris yang cocok
        $kata = trim(mb_strtolower($this->cari));
        if ($kata !== '') {
            $groups = $groups->filter(function ($g) use ($kata) {
                return $g['lines']->contains(function ($l) use ($kata) {
                    $teks = mb_strtolower(implode(' ', [
                        $l['no_akun'], $l['nama_akun'], $l['nama'], $l['keterangan'],
                    ]));

                    return str_contains($teks, $kata);
                }) || str_contains((string) $g['jurnal'], $kata);
            })->values();
        }

        $totalDebet = $groups->sum('debet');
        $totalKredit = $groups->sum('kredit');

        return [
            'groups' => $groups,
            'daftarSumber' => $daftarSumber,
            'totalDebet' => $totalDebet,
            'totalKredit' => $totalKredit,
            'jumlahJurnal' => $groups->count(),
            'kasBank' => $this->ringkasKasBank($groups),
            'jumlahTidakBalance' => $groups->where('balance', false)->count(),
            'tanggalLabel' => Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y'),
        ];
    }
}