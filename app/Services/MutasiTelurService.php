<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mutasi telur: Ruko -> Pabrik Wijaya / Wahana.
 *
 * Jurnal (per jenis telur):
 *   Debet  : Telur {petian|kiloan|bentes} {Wijaya|Wahana}
 *   Kredit : Telur {petian|kiloan|bentes} Ruko   (1400-11 / 1400-12 / 1400-13)
 *
 * Mutasi disimpan sebagai DRAFT di Jurnal Pembantu. Stok (dihitung dari Jurnal Umum)
 * baru berubah setelah jurnal diposting manual dari menu Jurnal Pembantu.
 */
class MutasiTelurService
{
    public const MODUL = 'mutasi_telur';

    public const TUJUAN = [
        'wijaya' => 'Pabrik Wijaya',
        'wahana' => 'Pabrik Wahana',
    ];

    public const JENIS = [
        'petian' => 'Telur Petian',
        'kiloan' => 'Telur Kiloan',
        'bentes' => 'Telur Bentes',
    ];

    /** Akun persediaan telur Ruko (sesuai Stok Matrix). */
    public const KODE_RUKO = [
        'petian' => '1400-11',
        'kiloan' => '1400-12',
        'bentes' => '1400-13',
    ];

    /** Akun persediaan telur di Wijaya / Wahana (sesuai Stok Matrix). */
    public const KODE_TUJUAN = [
        'wijaya' => ['petian' => '1400-14', 'kiloan' => '1400-15', 'bentes' => null],
        'wahana' => ['petian' => '1400-16', 'kiloan' => '1400-17', 'bentes' => '1400-18'],
    ];

    /**
     * @param  array<int, array{jenis:string, qty:float|int|string}>  $items
     * @return string nomor dokumen mutasi
     */
    public function mutasi(string $tanggal, string $tujuan, array $items, ?string $keterangan, int $userId): string
    {
        if (!isset(self::TUJUAN[$tujuan])) {
            throw new Exception('Tujuan mutasi tidak valid.');
        }

        // Gabungkan baris jenis yang sama & buang qty 0
        $qtyPerJenis = [];
        foreach ($items as $row) {
            $jenis = $row['jenis'] ?? null;
            $qty   = (float) ($row['qty'] ?? 0);
            if (!isset(self::JENIS[$jenis]) || $qty <= 0) {
                continue;
            }
            $qtyPerJenis[$jenis] = ($qtyPerJenis[$jenis] ?? 0) + $qty;
        }

        if (empty($qtyPerJenis)) {
            throw new Exception('Isi minimal satu jenis telur dengan jumlah lebih dari 0.');
        }

        return DB::transaction(function () use ($tanggal, $tujuan, $qtyPerJenis, $keterangan, $userId) {
            $lines = [];

            foreach ($qtyPerJenis as $jenis => $qty) {
                $kodeRuko = self::KODE_RUKO[$jenis];
                $ruko     = $this->resolveAkun($kodeRuko);
                $barang   = Barang::with('subAnakAkun')
                    ->whereHas('subAnakAkun', fn($q) => $q->where('kode_sub_anak_akun', $kodeRuko))
                    ->first();

                // Validasi stok Ruko (dikurangi draft mutasi lain yang belum diposting)
                $stokRuko = ($barang ? (float) $barang->stok_buku_besar : 0.0) - $this->draftKeluar($kodeRuko);
                if ($stokRuko + 0.0001 < $qty) {
                    throw new Exception(sprintf(
                        'Stok %s tidak cukup (sudah dikurangi draft mutasi lain). Stok: %s, diminta: %s.',
                        $ruko->nama_sub_anak_akun,
                        rtrim(rtrim(number_format($stokRuko, 2, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')
                    ));
                }

                $tujuanAkun = $this->resolveAkunTujuan($tujuan, $jenis);
                $harga      = (float) ($barang?->harga_jual ?: $barang?->harga_beli ?: 0);

                // Debet tujuan, Kredit Ruko (nilai sama agar balance)
                $lines[] = $this->line($tujuanAkun, 'd', $qty, $harga);
                $lines[] = $this->line($ruko, 'k', $qty, $harga);
            }

            $nota = $this->generateNota($tanggal);
            $ket  = trim('Mutasi Telur Ruko ke ' . self::TUJUAN[$tujuan] . ($keterangan ? " | {$keterangan}" : ''));

            $this->posting($tanggal, $nota, $ket, $lines, $userId, false);

            Log::info("[MutasiTelurService] Mutasi {$nota} ke {$tujuan} disimpan sebagai draft.");

            return $nota;
        });
    }

    /**
     * Batalkan mutasi.
     * - Masih draft  : draft jurnal pembantu dihapus (belum ada efek ke stok).
     * - Sudah posting: dibuat jurnal pembalik (debet/kredit ditukar).
     */
    public function batal(string $nota, int $userId): string
    {
        return DB::transaction(function () use ($nota, $userId) {
            $headers = JurnalPembantuHeader::where('modul_asal', self::MODUL)
                ->where('no_dokumen', $nota)
                ->get();

            if ($headers->isEmpty()) {
                throw new Exception("Mutasi {$nota} tidak ditemukan.");
            }

            if ($headers->every(fn($h) => $h->status === JurnalPembantuHeader::STATUS_DRAFT)) {
                foreach ($headers as $h) {
                    $h->items()->delete();
                    $h->delete();
                }
                return "Draft {$nota} dihapus";
            }

            $notaBatal = "BATAL-{$nota}";

            if (JurnalUmum::where('nama', $notaBatal)->exists()) {
                throw new Exception("Mutasi {$nota} sudah pernah dibatalkan.");
            }

            $rows = JurnalUmum::where('nama', $nota)->get();
            if ($rows->isEmpty()) {
                throw new Exception("Jurnal mutasi {$nota} tidak ditemukan di Jurnal Umum.");
            }

            $tanggal = Carbon::parse($rows->first()->tgl)->toDateString();

            $lines = $rows->map(fn($r) => [
                'no_akun'     => $r->no_akun,
                'nama_akun'   => $r->nama_akun,
                'nama_barang' => $r->nama_akun,
                'map'         => strtolower($r->map) === 'd' ? 'k' : 'd',
                'qty'         => (float) $r->banyak,
                'harga'       => (float) $r->harga,
            ])->all();

            $this->posting($tanggal, $notaBatal, "Pembatalan {$nota}", $lines, $userId, true);

            return $notaBatal;
        });
    }

    /** Total qty yang akan keluar dari akun lewat mutasi yang masih draft. */
    public function draftKeluar(string $kodeAkun): float
    {
        return (float) JurnalPembantuItem::query()
            ->whereHas('header', fn($q) => $q
                ->where('modul_asal', self::MODUL)
                ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
                ->where('map', 'k')
                ->where('no_akun', $kodeAkun))
            ->sum('banyak');
    }

    /** Cari akun tujuan tanpa melempar error (null bila belum ada). */
    public function cariAkunTujuan(string $tujuan, string $jenis): ?SubAnakAkun
    {
        $kode = self::KODE_TUJUAN[$tujuan][$jenis] ?? null;
        if ($kode) {
            return SubAnakAkun::where('kode_sub_anak_akun', $kode)->first();
        }

        return null;
    }

    /** Stok akun = total banyak debet - kredit di Jurnal Umum (sama dengan Stok Matrix). */
    public function stokAkun(?string $kodeAkun): float
    {
        if (!$kodeAkun) {
            return 0.0;
        }

        $rows = JurnalUmum::where('no_akun', $kodeAkun)
            ->select('map', DB::raw('SUM(COALESCE(banyak, 0)) as total'))
            ->groupBy('map')
            ->get();

        $total = 0.0;
        foreach ($rows as $r) {
            $total += in_array(strtolower($r->map), ['d', 'debit']) ? (float) $r->total : -(float) $r->total;
        }

        return $total;
    }

    /**
     * Info stok untuk ditampilkan di form: stok Ruko dan stok tujuan.
     */
    public function infoJenis(?string $jenis, ?string $tujuan): array
    {
        $info = ['stok_ruko' => '', 'stok_tujuan' => ''];

        if (!$jenis || !isset(self::KODE_RUKO[$jenis])) {
            return $info;
        }

        $info['stok_ruko'] = $this->fmt($this->stokAkun(self::KODE_RUKO[$jenis]));

        if ($tujuan && isset(self::TUJUAN[$tujuan])) {
            $akun = $this->cariAkunTujuan($tujuan, $jenis);
            $info['stok_tujuan'] = $akun
                ? $this->fmt($this->stokAkun($akun->kode_sub_anak_akun))
                : 'Akun belum ada';
        }

        return $info;
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function posting(string $tanggal, string $nota, string $ket, array $lines, int $userId, bool $langsungPosting): void
    {
        $nomorJurnal = $this->generateNextJurnalNumber();
        $urut = 1;

        foreach ($lines as $l) {
            $nextNoJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;

            $header = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $nextNoJP,
                'tgl_transaksi'      => $tanggal,
                'jenis_transaksi'    => 'lain',
                'modul_asal'         => self::MODUL,
                'jurnal'             => $nomorJurnal,
                'no_akun'            => $l['no_akun'],
                'nama_akun'          => $l['nama_akun'],
                'map'                => $l['map'],
                'keterangan'         => $ket,
                'no_dokumen'         => $nota,
                'status'             => $langsungPosting
                    ? JurnalPembantuHeader::STATUS_DIPOSTING
                    : JurnalPembantuHeader::STATUS_DRAFT,
                'dibuat_oleh'        => $userId,
                'diposting_oleh'     => $langsungPosting ? $userId : null,
                'tgl_posting'        => $langsungPosting ? now() : null,
            ]);

            JurnalPembantuItem::create([
                'jurnal_pembantu_header_id' => $header->id,
                'urut'                      => $urut++,
                'nama_barang'               => $l['nama_barang'],
                'no_dokumen'                => $nota,
                'keterangan'                => $ket,
                'banyak'                    => $l['qty'],
                'harga'                     => $l['harga'],
                'status'                    => true,
                'created_by'                => $userId,
                'updated_by'                => $userId,
            ]);

            if (!$langsungPosting) {
                continue; // draft: masuk Jurnal Umum saat diposting manual
            }

            JurnalUmum::create([
                'tgl'        => $tanggal,
                'jurnal'     => $nomorJurnal,
                'no_akun'    => $l['no_akun'],
                'nama_akun'  => $l['nama_akun'],
                'nama'       => $nota,
                'keterangan' => $ket,
                'banyak'     => round($l['qty'], 2),
                'harga'      => round($l['harga'], 2),
                'map'        => $l['map'],
            ]);
        }
    }

    private function line(SubAnakAkun $akun, string $map, float $qty, float $harga): array
    {
        return [
            'no_akun'     => $akun->kode_sub_anak_akun,
            'nama_akun'   => $akun->nama_sub_anak_akun,
            'nama_barang' => $akun->nama_sub_anak_akun,
            'map'         => $map,
            'qty'         => $qty,
            'harga'       => $harga,
        ];
    }

    private function resolveAkun(string $kode): SubAnakAkun
    {
        $akun = SubAnakAkun::where('kode_sub_anak_akun', $kode)->first();
        if (!$akun) {
            throw new Exception("Akun {$kode} tidak ditemukan di Chart of Accounts.");
        }
        return $akun;
    }

    private function resolveAkunTujuan(string $tujuan, string $jenis): SubAnakAkun
    {
        $akun = $this->cariAkunTujuan($tujuan, $jenis);

        if (!$akun) {
            throw new Exception(sprintf(
                'Akun %s %s belum ada. Buat akunnya di Chart of Accounts lalu isi kodenya di MutasiTelurService::KODE_TUJUAN.',
                self::JENIS[$jenis], self::TUJUAN[$tujuan]
            ));
        }

        return $akun;
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') ?: '0';
    }

    private function generateNota(string $tanggal): string
    {
        $prefix = 'MUTASITELUR-' . Carbon::parse($tanggal)->format('Ymd') . '-';
        $urut = JurnalPembantuHeader::where('modul_asal', self::MODUL)
            ->where('no_dokumen', 'LIKE', $prefix . '%')
            ->distinct()
            ->count('no_dokumen') + 1;

        return $prefix . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }

    private function generateNextJurnalNumber(): int
    {
        $maxJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0);
        $maxJU = (int) (JurnalUmum::lockForUpdate()->max('jurnal') ?? 0);

        return max($maxJP, $maxJU) + 1;
    }
}