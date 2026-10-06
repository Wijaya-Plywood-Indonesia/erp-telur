<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\JurnalUmum;
use App\Models\PengajuanMutasiTelur;
use App\Models\SubAnakAkun;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mutasi telur: Ruko -> Pabrik Wijaya / Wahana.
 *
 * Alur:
 *  1. ajukan()   : tersimpan di pengajuan_mutasi_telurs (Menunggu Validasi), belum ada jurnal.
 *     ubah()     : selama masih Menunggu Validasi, pengajuan boleh diedit.
 *  2. validasi() : oleh akun BERBEDA dari pembuat (kecuali super_admin) -> draft Jurnal Pembantu.
 *  3. Posting manual dari menu Jurnal Pembantu -> stok berubah.
 *
 * Jurnal (per jenis telur):
 *   Debet  : Telur {petian|kiloan|bentes} {Wijaya|Wahana}
 *   Kredit : Telur {petian|kiloan|bentes} Ruko
 *
 * Akun TIDAK di-hardcode: barang dicari di Master Barang berdasarkan nama
 * (memuat "telur" + jenis + lokasi), lalu akun diambil dari sub akun barang tsb.
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

    // ══════════════════════════════════════════════════════════════════════════
    // ALUR UTAMA
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Tahap 1: ajukan mutasi (belum masuk jurnal).
     *
     * @param  array<int, array{jenis:string, qty:float|int|string}>  $items
     * @return string nomor dokumen
     */
    public function ajukan(string $tanggal, string $tujuan, array $items, ?string $keterangan, int $userId): string
    {
        if (!isset(self::TUJUAN[$tujuan])) {
            throw new Exception('Tujuan mutasi tidak valid.');
        }

        $qtyPerJenis = $this->normalisasi($items);

        return DB::transaction(function () use ($tanggal, $tujuan, $qtyPerJenis, $keterangan, $userId) {
            // Validasi dini: barang/akun ada & stok Ruko cukup
            $this->susunBaris($tujuan, $qtyPerJenis, null);

            $nota = $this->generateNota($tanggal);

            PengajuanMutasiTelur::create([
                'no_dokumen' => $nota,
                'tanggal'    => $tanggal,
                'tujuan'     => $tujuan,
                'items'      => $this->itemsUntukSimpan($qtyPerJenis),
                'keterangan' => $keterangan,
                'status'     => PengajuanMutasiTelur::MENUNGGU,
                'created_by' => $userId,
            ]);

            Log::info("[MutasiTelurService] Pengajuan {$nota} ke {$tujuan} menunggu validasi.");

            return $nota;
        });
    }

    /**
     * Edit pengajuan. Hanya selama masih Menunggu Validasi,
     * dan hanya oleh pembuat atau super_admin.
     */
    public function ubah(int $id, string $tanggal, string $tujuan, array $items, ?string $keterangan, User $user): string
    {
        if (!isset(self::TUJUAN[$tujuan])) {
            throw new Exception('Tujuan mutasi tidak valid.');
        }

        $qtyPerJenis = $this->normalisasi($items);

        return DB::transaction(function () use ($id, $tanggal, $tujuan, $qtyPerJenis, $keterangan, $user) {
            $p = PengajuanMutasiTelur::lockForUpdate()->findOrFail($id);

            if ($p->status !== PengajuanMutasiTelur::MENUNGGU) {
                throw new Exception('Pengajuan yang sudah divalidasi/ditolak/dibatalkan tidak bisa diedit.');
            }

            if ((int) $p->created_by !== (int) $user->id && !$user->hasRole('super_admin')) {
                throw new Exception('Hanya pembuat pengajuan atau super admin yang boleh mengedit.');
            }

            // Cek ulang stok dengan data baru (pengajuan ini sendiri tidak dihitung dobel)
            $this->susunBaris($tujuan, $qtyPerJenis, $p->id);

            $p->update([
                'tanggal'    => $tanggal,
                'tujuan'     => $tujuan,
                'items'      => $this->itemsUntukSimpan($qtyPerJenis),
                'keterangan' => $keterangan,
            ]);

            return $p->no_dokumen;
        });
    }

    /**
     * Tahap 2: validasi -> buat draft Jurnal Pembantu.
     */
    public function validasi(int $id, User $user): string
    {
        return DB::transaction(function () use ($id, $user) {
            $p = PengajuanMutasiTelur::lockForUpdate()->findOrFail($id);

            if ($p->status !== PengajuanMutasiTelur::MENUNGGU) {
                throw new Exception('Pengajuan ini tidak lagi menunggu validasi.');
            }

            $this->pastikanBolehValidasi($p, $user);

            $qtyPerJenis = collect($p->items)
                ->mapWithKeys(fn($i) => [$i['jenis'] => (float) $i['qty']])
                ->all();

            // Cek ulang stok & akun saat validasi
            $lines = $this->susunBaris($p->tujuan, $qtyPerJenis, $p->id);

            $ket = trim('Mutasi Telur Ruko ke ' . self::TUJUAN[$p->tujuan]
                . ($p->keterangan ? " | {$p->keterangan}" : ''));

            $this->posting($p->tanggal->toDateString(), $p->no_dokumen, $ket, $lines, $user->id, false);

            $p->update([
                'status'       => PengajuanMutasiTelur::TERVALIDASI,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            Log::info("[MutasiTelurService] {$p->no_dokumen} divalidasi user {$user->id}, draft jurnal dibuat.");

            return $p->no_dokumen;
        });
    }

    /**
     * Tolak pengajuan (aturan akun sama dengan validasi).
     */
    public function tolak(int $id, User $user, string $alasan): string
    {
        return DB::transaction(function () use ($id, $user, $alasan) {
            $p = PengajuanMutasiTelur::lockForUpdate()->findOrFail($id);

            if ($p->status !== PengajuanMutasiTelur::MENUNGGU) {
                throw new Exception('Pengajuan ini tidak lagi menunggu validasi.');
            }

            $this->pastikanBolehValidasi($p, $user);

            $p->update([
                'status'       => PengajuanMutasiTelur::DITOLAK,
                'alasan_tolak' => $alasan,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            return $p->no_dokumen;
        });
    }

    /**
     * Batalkan.
     * - Menunggu validasi : hanya pembuat / super_admin.
     * - Sudah tervalidasi : hanya admin / super_admin. Draft jurnal dihapus,
     *                       atau dibuat jurnal pembalik bila sudah diposting.
     */
    public function batalkan(int $id, User $user): string
    {
        return DB::transaction(function () use ($id, $user) {
            $p = PengajuanMutasiTelur::lockForUpdate()->findOrFail($id);

            if ($p->status === PengajuanMutasiTelur::MENUNGGU) {
                if ((int) $p->created_by !== (int) $user->id && !$user->hasRole('super_admin')) {
                    throw new Exception('Hanya pembuat pengajuan atau super admin yang boleh membatalkan.');
                }

                $p->update(['status' => PengajuanMutasiTelur::DIBATALKAN]);

                return "Pengajuan {$p->no_dokumen} dibatalkan";
            }

            if ($p->status === PengajuanMutasiTelur::TERVALIDASI) {
                if (!$user->hasAnyRole(['admin', 'super_admin'])) {
                    throw new Exception('Hanya admin atau super admin yang boleh membatalkan mutasi yang sudah divalidasi.');
                }

                $hasil = $this->batal($p->no_dokumen, $user->id);
                $p->update(['status' => PengajuanMutasiTelur::DIBATALKAN]);

                return $hasil;
            }

            throw new Exception('Pengajuan ini tidak bisa dibatalkan.');
        });
    }

    /**
     * Pembatalan jurnal:
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
                // Draft jurnal sudah dihapus manual dari Jurnal Pembantu
                return "Mutasi {$nota} dibatalkan";
            }

            if ($headers->every(fn($h) => $h->status === JurnalPembantuHeader::STATUS_DRAFT)) {
                foreach ($headers as $h) {
                    $h->items()->delete();
                    $h->delete();
                }
                return "Draft jurnal {$nota} dihapus";
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

    // ══════════════════════════════════════════════════════════════════════════
    // STOK & PENCARIAN AKUN
    // ══════════════════════════════════════════════════════════════════════════

    /** Total qty yang akan keluar dari akun lewat draft jurnal mutasi (belum diposting). */
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

    /** Total qty jenis tertentu dari Ruko yang masih menunggu validasi. */
    public function menungguKeluar(string $jenis, ?int $exceptId = null): float
    {
        return (float) PengajuanMutasiTelur::where('status', PengajuanMutasiTelur::MENUNGGU)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->sum(fn($p) => collect($p->items)
                ->where('jenis', $jenis)
                ->sum(fn($i) => (float) $i['qty']));
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

    // ══════════════════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════════════════

    /** Pembuat tidak boleh memvalidasi sendiri, kecuali super_admin. */
    private function pastikanBolehValidasi(PengajuanMutasiTelur $p, User $user): void
    {
        if ((int) $p->created_by === (int) $user->id && !$user->hasRole('super_admin')) {
            throw new Exception('Mutasi harus divalidasi oleh akun lain. Anda tidak bisa memvalidasi pengajuan sendiri.');
        }
    }

    /** Gabungkan baris jenis yang sama & buang qty 0. */
    private function normalisasi(array $items): array
    {
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

        return $qtyPerJenis;
    }

    private function itemsUntukSimpan(array $qtyPerJenis): array
    {
        return collect($qtyPerJenis)
            ->map(fn($qty, $jenis) => ['jenis' => $jenis, 'qty' => $qty])
            ->values()
            ->all();
    }

    /**
     * Susun baris jurnal (D tujuan, K Ruko) sekaligus validasi akun & stok Ruko.
     * $exceptId = pengajuan yang sedang divalidasi/diedit (agar tidak menghitung dirinya sendiri).
     */
    private function susunBaris(string $tujuan, array $qtyPerJenis, ?int $exceptId): array
    {
        $lines = [];

        foreach ($qtyPerJenis as $jenis => $qty) {
            $barangRuko = $this->wajibBarang($jenis, self::LOKASI_ASAL);
            $akunRuko   = $barangRuko->subAnakAkun;

            $stokRuko = $this->stokAkun($akunRuko->kode_sub_anak_akun)
                - $this->draftKeluar($akunRuko->kode_sub_anak_akun)
                - $this->menungguKeluar($jenis, $exceptId);

            if ($stokRuko + 0.0001 < $qty) {
                throw new Exception(sprintf(
                    'Stok %s tidak cukup (sudah dikurangi pengajuan/draft lain). Stok: %s, diminta: %s.',
                    $akunRuko->nama_sub_anak_akun,
                    rtrim(rtrim(number_format($stokRuko, 2, '.', ''), '0'), '.') ?: '0',
                    rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')
                ));
            }

            $barangTujuan = $this->wajibBarang($jenis, $tujuan);
            $akunTujuan   = $barangTujuan->subAnakAkun;

            $harga = (float) ($barangRuko->harga_jual ?: $barangRuko->harga_beli ?: 0);

            // Debet tujuan, Kredit Ruko (nilai sama agar balance)
            $lines[] = $this->line($akunTujuan, 'd', $qty, $harga);
            $lines[] = $this->line($akunRuko, 'k', $qty, $harga);
        }

        return $lines;
    }

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
        $urut = PengajuanMutasiTelur::where('no_dokumen', 'LIKE', $prefix . '%')->count() + 1;

        return $prefix . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }

    private function generateNextJurnalNumber(): int
    {
        $maxJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0);
        $maxJU = (int) (JurnalUmum::lockForUpdate()->max('jurnal') ?? 0);

        return max($maxJP, $maxJU) + 1;
    }
}