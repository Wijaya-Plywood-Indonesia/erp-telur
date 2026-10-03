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
 *   Kredit : Telur {petian|kiloan|bentes} Ruko
 *
 * Akun TIDAK di-hardcode: barang dicari di Master Barang berdasarkan nama
 * (memuat "telur" + jenis + lokasi), lalu akun diambil dari sub akun barang tsb.
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

    private const LOKASI_ASAL = 'ruko';

    /** Cache pencarian barang selama satu request. */
    private array $cacheBarang = [];

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
                // Asal (Ruko)
                $barangRuko = $this->wajibBarang($jenis, self::LOKASI_ASAL);
                $akunRuko   = $barangRuko->subAnakAkun;

                // Validasi stok Ruko (dikurangi draft mutasi lain yang belum diposting)
                $stokRuko = $this->stokAkun($akunRuko->kode_sub_anak_akun)
                    - $this->draftKeluar($akunRuko->kode_sub_anak_akun);

                if ($stokRuko + 0.0001 < $qty) {
                    throw new Exception(sprintf(
                        'Stok %s tidak cukup (sudah dikurangi draft mutasi lain). Stok: %s, diminta: %s.',
                        $akunRuko->nama_sub_anak_akun,
                        rtrim(rtrim(number_format($stokRuko, 2, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')
                    ));
                }

                // Tujuan (Wijaya / Wahana)
                $barangTujuan = $this->wajibBarang($jenis, $tujuan);
                $akunTujuan   = $barangTujuan->subAnakAkun;

                $harga = (float) ($barangRuko->harga_jual ?: $barangRuko->harga_beli ?: 0);

                // Debet tujuan, Kredit Ruko (nilai sama agar balance)
                $lines[] = $this->line($akunTujuan, 'd', $qty, $harga);
                $lines[] = $this->line($akunRuko, 'k', $qty, $harga);
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

    /**
     * Cari barang telur di Master Barang berdasarkan nama.
     * Nama harus memuat "telur" + jenis + lokasi. Nama persis "Telur {Jenis} {Lokasi}" diprioritaskan.
     * Hanya barang yang sudah punya akun (id_sub_anak_akun) yang dipakai.
     */
    public function cariBarang(string $jenis, string $lokasi): ?Barang
    {
        $key = "{$jenis}|{$lokasi}";
        if (array_key_exists($key, $this->cacheBarang)) {
            return $this->cacheBarang[$key];
        }

        $base = fn() => Barang::with('subAnakAkun')
            ->whereNotNull('id_sub_anak_akun')
            ->whereHas('subAnakAkun');

        $barang = $base()
            ->whereRaw('LOWER(nama_barang) = ?', [strtolower("telur {$jenis} {$lokasi}")])
            ->orderBy('id')
            ->first();

        if (!$barang) {
            $barang = $base()
                ->whereRaw('LOWER(nama_barang) LIKE ?', ['%telur%'])
                ->whereRaw('LOWER(nama_barang) LIKE ?', ['%' . strtolower($jenis) . '%'])
                ->whereRaw('LOWER(nama_barang) LIKE ?', ['%' . strtolower($lokasi) . '%'])
                ->orderBy('id')
                ->first();
        }

        return $this->cacheBarang[$key] = $barang;
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

        if (!$jenis || !isset(self::JENIS[$jenis])) {
            return $info;
        }

        $ruko = $this->cariBarang($jenis, self::LOKASI_ASAL);
        $info['stok_ruko'] = $ruko
            ? $this->fmt($this->stokAkun($ruko->subAnakAkun->kode_sub_anak_akun))
            : 'Akun belum ada';

        if ($tujuan && isset(self::TUJUAN[$tujuan])) {
            $barang = $this->cariBarang($jenis, $tujuan);
            $info['stok_tujuan'] = $barang
                ? $this->fmt($this->stokAkun($barang->subAnakAkun->kode_sub_anak_akun))
                : 'Akun belum ada';
        }

        return $info;
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function wajibBarang(string $jenis, string $lokasi): Barang
    {
        $barang = $this->cariBarang($jenis, $lokasi);

        if (!$barang) {
            throw new Exception(sprintf(
                'Barang "%s %s" belum ada di Master Barang atau belum punya akun. '
                . 'Buat barangnya (nama memuat "telur", "%s", "%s") dan hubungkan ke akun persediaannya.',
                self::JENIS[$jenis],
                ucfirst($lokasi),
                $jenis,
                $lokasi
            ));
        }

        return $barang;
    }

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