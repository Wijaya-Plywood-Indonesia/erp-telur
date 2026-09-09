<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\ProduksiPakan;
use App\Models\SubAnakAkun;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProduksiPakanService
{
    // ── Akun Hardcoded ──────────────────────────────────────────────────────
    const KODE_HUTANG_GAJI    = '2210-01';
    const KODE_HUTANG_LISTRIK = '2210-02';

    // Akun Penyesuaian Selisih Produksi
    const KODE_PENDAPATAN_KELEBIHAN_PAKAN = '4400-01';
    const KODE_PENDAPATAN_KELEBIHAN_TELUR = '4400-02';

    // Akun Debet Telur (Proses 2) — hardcoded
    const AKUN_TELUR = [
        ['kode' => '1411-00', 'nama' => 'Telur Petian',  'nilai' => 19976],
        ['kode' => '1412-00', 'nama' => 'Telur Kiloan',  'nilai' => 49],
        ['kode' => '1413-00', 'nama' => 'Telur Bentes',  'nilai' => 10000],
    ];

    const NILAI_HUTANG_GAJI    = 650000;
    const NILAI_HUTANG_LISTRIK = 50000;

    private array $akunCache     = [];
    private array $konversiCache = [];

    /* ═══════════════════════════════════════════════════════════════════════
    |  ENTRY POINT — dipanggil saat validasi
    ═══════════════════════════════════════════════════════════════════════ */

    public function buatJurnalDariProduksi(ProduksiPakan $produksi, int $userId): void
    {
        $produksi->loadMissing([
            'pakanMentahs.barang.subAnakAkun',
            'pakanMentahs.barang.satuan',
            'pakanCampurans.barang.subAnakAkun',
            'pakanCampurans.barang.satuan',
        ]);

        $adaMentah = $produksi->pakanMentahs->contains(
            fn($i) => (float)$i->keluar_pullet > 0
                || (float)$i->keluar_l1 > 0
                || (float)$i->keluar_l2 > 0
        );

        $tgl  = $produksi->tanggal_produksi->toDateString();
        $nota = 'PROD-' . $produksi->id . '-' . $tgl;

        DB::transaction(function () use ($produksi, $userId, $adaMentah, $tgl, $nota) {

            // 1. Bersihkan draft jurnal lama jika ada (cegah duplikasi saat re-validasi)
            $this->hapusJurnalLama($nota);

            if ($adaMentah) {
                $this->buatJurnalProses1($produksi, $userId, $tgl, $nota);
            } else {
                Log::info("[ProduksiPakan] Tidak ada data mentah terisi, jurnal tidak dibuat.");
            }
        });
    }

    /**
     * Bersihkan draft jurnal lama untuk nota tertentu sebelum jurnal baru dibuat.
     * Jika jurnal lama sudah diposting ke Buku Besar, tolak dan lempar Exception —
     * regenerasi otomatis tidak boleh menimpa jurnal yang sudah diposting.
     */
    public function hapusJurnalLama(string $nota): void
    {
        $isPosted = JurnalPembantuHeader::where('modul_asal', 'produksi_pakan')
            ->where('no_dokumen', $nota)
            ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
            ->exists();

        if ($isPosted) {
            throw new Exception("Jurnal produksi pakan ini ({$nota}) sudah diposting ke Buku Besar. Batalkan posting terlebih dahulu!");
        }

        $headers = JurnalPembantuHeader::where('modul_asal', 'produksi_pakan')
            ->where('no_dokumen', $nota)
            ->get();

        foreach ($headers as $header) {
            $header->items()->delete();
            $header->delete();
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
    |  PROSES 1 — Bahan Mentah → Pakan Campuran (SATU JURNAL)
    |
    |  Struktur jurnal (mengikuti foto):
    |  D: Setiap Pakan Campuran yang dihasilkan (Layer 1, Layer 2, Pullet)
    |  K: Setiap Bahan Mentah yang terpakai (total semua kandang)
    |  K: Hutang Gaji
    |  K: Hutang Listrik
    |  K/D: Selisih balancing jika ada
    ═══════════════════════════════════════════════════════════════════════ */

    private function buatJurnalProses1(ProduksiPakan $produksi, int $userId, string $tgl, string $nota): void
    {
        $ket      = "Produksi Pakan | Tgl: {$tgl}";
        $noJurnal = $this->nextNoJurnal() ?? 0;

        /**
         * LANGKAH 1: Hitung total per bahan mentah (gabungkan semua kandang)
         */
        $totalPerMentah = [];
        foreach ($produksi->pakanMentahs as $mentah) {
            $jumlahKg = (float)$mentah->keluar_pullet
                + (float)$mentah->keluar_l1
                + (float)$mentah->keluar_l2;

            if ($jumlahKg <= 0) continue;

            $barang   = $mentah->barang;
            $id       = $barang->id;
            $harga    = (float)($barang->harga_jual ?? 0);

            $nilaiTambahan = round($jumlahKg * $harga, 2);

            if (!isset($totalPerMentah[$id])) {
                $totalPerMentah[$id] = [
                    'barang'     => $barang,
                    'totalKg'    => 0.0,
                    'totalNilai' => 0.0,
                    'harga'      => $harga,
                ];
            }

            $totalPerMentah[$id]['totalKg']    += $jumlahKg;
            $totalPerMentah[$id]['totalNilai'] += $nilaiTambahan;
        }

        /**
         * LANGKAH 2: Hitung total nilai per pakan campuran yang dihasilkan
         */
        $kandangs = [
            'pullet' => ['field' => 'keluar_pullet', 'label' => 'Pullet'],
            'l1'     => ['field' => 'keluar_l1',     'label' => 'Layer 1'],
            'l2'     => ['field' => 'keluar_l2',     'label' => 'Layer 2'],
        ];

        $hasilPerKandang = [];

        foreach ($kandangs as $kandangKey => $kandang) {
            $field = $kandang['field'];

            $campuranKandang = $produksi->pakanCampurans->first(function ($item) use ($kandangKey) {
                $nama = strtoupper($item->barang?->nama_barang ?? '');
                return match ($kandangKey) {
                    'pullet' => str_contains($nama, 'PULLET') || str_contains($nama, 'PULET'),
                    'l1'     => str_contains($nama, 'LAYER 1') || str_contains($nama, 'L1'),
                    'l2'     => str_contains($nama, 'LAYER 2') || str_contains($nama, 'L2'),
                    default  => false,
                };
            });

            $totalMasukKandang = $produksi->pakanMentahs->sum(
                fn($i) => (float)$i->$field
            );

            if ($totalMasukKandang <= 0) continue;

            $hasilPerKandang[$kandangKey] = [
                'campuran'      => $campuranKandang,
                'totalKgMasuk'  => $totalMasukKandang,
                'label'         => $kandang['label'],
            ];
        }

        if (empty($hasilPerKandang)) return;

        $totalDebit  = 0.0;
        $totalKredit = 0.0;
        $urutItem    = 1;

        /* ─── DEBIT: Pakan Campuran yang dihasilkan ─── */
        foreach ($hasilPerKandang as $kandangKey => $hasil) {
            $barangCampuran   = $hasil['campuran']?->barang;
            $kodeAkunCampuran = $barangCampuran?->subAnakAkun?->kode_sub_anak_akun ?? '1500-00';
            $namaAkunCampuran = $this->getNamaAkun($kodeAkunCampuran)
                ?: ($barangCampuran?->nama_barang ?? "Pakan Campuran {$hasil['label']}");
            $hargaCampuran    = (float)($barangCampuran?->harga_jual ?? 0);
            $nilaiCampuran    = $hasil['totalKgMasuk'] * $hargaCampuran;

            $hDebit = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => $kodeAkunCampuran,
                'nama_akun'          => $namaAkunCampuran,
                'map'                => 'd',
                'keterangan'         => $ket,
                'no_dokumen'         => $nota,
                'total_nilai'        => $nilaiCampuran,
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hDebit->id, [
                'urut'        => $urutItem++,
                'nama_barang' => $barangCampuran?->nama_barang ?? "Pakan {$hasil['label']}",
                'no_dokumen'  => $nota,
                'keterangan'  => "Hasil produksi {$hasil['label']}",
                'banyak'      => $hasil['totalKgMasuk'],
                'harga'       => $hargaCampuran,
                'jumlah'      => $nilaiCampuran,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $totalDebit += $nilaiCampuran;
        }

        /* ─── KREDIT: Bahan Mentah yang terpakai ─── */
        $urutItem = 1;
        foreach ($totalPerMentah as $data) {
            $barang         = $data['barang'];
            $kodeAkunMentah = $barang->subAnakAkun?->kode_sub_anak_akun ?? '1500-01';
            $namaAkunMentah = $this->getNamaAkun($kodeAkunMentah) ?: ($barang->nama_barang ?? 'Bahan Mentah');
            $qtyJurnal = $data['totalKg'];

            $hKredit = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => $kodeAkunMentah,
                'nama_akun'          => $namaAkunMentah,
                'map'                => 'k',
                'keterangan'         => $ket,
                'no_dokumen'         => $nota,
                'total_nilai'        => $data['totalNilai'],
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hKredit->id, [
                'urut'        => $urutItem++,
                'nama_barang' => $barang->nama_barang,
                'no_dokumen'  => $nota,
                'keterangan'  => "Bahan {$barang->nama_barang} → semua kandang",
                'banyak'      => $qtyJurnal,
                'harga'       => $data['harga'],
                'jumlah'      => $data['totalNilai'],
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $totalKredit += $data['totalNilai'];
        }

        /* ─── KREDIT: Hutang Gaji ─── */
        $hGaji = $this->buatHeader([
            'no_jurnal_pembantu' => $this->nextNomorPembantu(),
            'tgl_transaksi'      => $tgl,
            'jenis_transaksi'    => 'pk',
            'modul_asal'         => 'produksi_pakan',
            'jurnal'             => $noJurnal,
            'no_akun'            => self::KODE_HUTANG_GAJI,
            'nama_akun'          => $this->getNamaAkun(self::KODE_HUTANG_GAJI) ?: 'Hutang Gaji Pegawai Kandang',
            'map'                => 'k',
            'keterangan'         => "Hutang Gaji Pegawai | {$ket}",
            'no_dokumen'         => $nota,
            'total_nilai'        => self::NILAI_HUTANG_GAJI,
            'dibuat_oleh'        => $userId,
        ]);

        $this->buatItem($hGaji->id, [
            'urut'        => 1,
            'no_dokumen'  => $nota,
            'keterangan'  => 'Akrual gaji pegawai kandang',
            'banyak'      => 1,
            'harga'       => self::NILAI_HUTANG_GAJI,
            'jumlah'      => self::NILAI_HUTANG_GAJI,
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]);

        $totalKredit += self::NILAI_HUTANG_GAJI;

        /* ─── KREDIT: Hutang Listrik ─── */
        $hListrik = $this->buatHeader([
            'no_jurnal_pembantu' => $this->nextNomorPembantu(),
            'tgl_transaksi'      => $tgl,
            'jenis_transaksi'    => 'pk',
            'modul_asal'         => 'produksi_pakan',
            'jurnal'             => $noJurnal,
            'no_akun'            => self::KODE_HUTANG_LISTRIK,
            'nama_akun'          => $this->getNamaAkun(self::KODE_HUTANG_LISTRIK) ?: 'Hutang Listrik Kandang',
            'map'                => 'k',
            'keterangan'         => "Hutang Listrik | {$ket}",
            'no_dokumen'         => $nota,
            'total_nilai'        => self::NILAI_HUTANG_LISTRIK,
            'dibuat_oleh'        => $userId,
        ]);

        $this->buatItem($hListrik->id, [
            'urut'        => 1,
            'no_dokumen'  => $nota,
            'keterangan'  => 'Akrual beban listrik kandang',
            'banyak'      => 1,
            'harga'       => self::NILAI_HUTANG_LISTRIK,
            'jumlah'      => self::NILAI_HUTANG_LISTRIK,
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]);

        $totalKredit += self::NILAI_HUTANG_LISTRIK;

        /* ─── BALANCING: Selisih Debit vs Kredit ─── */
        $selisih = $totalDebit - $totalKredit;

        if (abs($selisih) > 0.001) {
            $mapSelisih   = $selisih > 0 ? 'k' : 'd';
            $nilaiSelisih = abs($selisih);
            $namaSelisih  = $this->getNamaAkun(self::KODE_PENDAPATAN_KELEBIHAN_PAKAN)
                ?: 'Pendapatan kelebihan produksi pakan';

            $hSelisih = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => self::KODE_PENDAPATAN_KELEBIHAN_PAKAN,
                'nama_akun'          => $namaSelisih,
                'map'                => $mapSelisih,
                'keterangan'         => "Penyesuaian Selisih Produksi Pakan | {$ket}",
                'no_dokumen'         => $nota,
                'total_nilai'        => $nilaiSelisih,
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hSelisih->id, [
                'urut'        => 1,
                'no_dokumen'  => $nota,
                'keterangan'  => 'Balancing Jurnal Produksi Pakan',
                'banyak'      => 1,
                'harga'       => $nilaiSelisih,
                'jumlah'      => $nilaiSelisih,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);
        }

        Log::info("[ProduksiPakan] Proses 1 selesai (1 jurnal). ID: {$produksi->id}, No Jurnal: {$noJurnal}");
    }

    /* ═══════════════════════════════════════════════════════════════════════
    |  PROSES 2 — Pakan Campuran Keluar → Telur (SATU JURNAL)
    |  Catatan: method ini tidak dipanggil dari buatJurnalDariProduksi().
    |  Jika suatu saat diaktifkan, jangan lupa hitung $nota-nya sendiri
    |  ('PRODC-...') dan panggil hapusJurnalLama($nota) untuk nota tersebut
    |  sebelum membuat header, sama seperti Proses 1.
    ═══════════════════════════════════════════════════════════════════════ */

    private function buatJurnalProses2(ProduksiPakan $produksi, int $userId): void
    {
        $tgl      = $produksi->tanggal_produksi->toDateString();
        $nota     = 'PRODC-' . $produksi->id . '-' . $tgl;
        $ket      = "Produksi Pakan Campuran | Tgl: {$tgl}";
        $noJurnal = $this->nextNoJurnal() ?? 0;

        $this->hapusJurnalLama($nota);

        $kandangs = [
            'pullet' => ['field' => 'keluar_pullet', 'label' => 'Pullet'],
            'l1'     => ['field' => 'keluar_l1',     'label' => 'Layer 1'],
            'l2'     => ['field' => 'keluar_l2',     'label' => 'Layer 2'],
        ];

        $campuranKeluar = [];

        foreach ($produksi->pakanCampurans as $campuran) {
            $nama = strtoupper($campuran->barang?->nama_barang ?? '');

            $kandangKey = match (true) {
                str_contains($nama, 'PULLET') || str_contains($nama, 'PULET') => 'pullet',
                str_contains($nama, 'LAYER 1') || str_contains($nama, 'L1')   => 'l1',
                str_contains($nama, 'LAYER 2') || str_contains($nama, 'L2')   => 'l2',
                default                                                       => null,
            };

            if (!$kandangKey) continue;

            $field        = $kandangs[$kandangKey]['field'];
            $jumlahKeluar = (float)$campuran->$field;

            if ($jumlahKeluar <= 0) continue;

            $campuranKeluar[] = [
                'campuran'    => $campuran,
                'kandangKey'  => $kandangKey,
                'label'       => $kandangs[$kandangKey]['label'],
                'jumlah'      => $jumlahKeluar,
            ];
        }

        if (empty($campuranKeluar)) return;

        $totalDebit  = 0.0;
        $totalKredit = 0.0;
        $urutItem    = 1;

        foreach (self::AKUN_TELUR as $telur) {
            $hTelur = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => $telur['kode'],
                'nama_akun'          => $telur['nama'],
                'map'                => 'd',
                'keterangan'         => $ket,
                'no_dokumen'         => $nota,
                'total_nilai'        => $telur['nilai'],
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hTelur->id, [
                'urut'        => $urutItem++,
                'nama_barang' => $telur['nama'],
                'no_dokumen'  => $nota,
                'keterangan'  => "Hasil panen {$telur['nama']}",
                'banyak'      => 1,
                'harga'       => $telur['nilai'],
                'jumlah'      => $telur['nilai'],
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $totalDebit += $telur['nilai'];
        }

        $urutItem = 1;
        foreach ($campuranKeluar as $data) {
            $barangCampuran   = $data['campuran']->barang;
            $kodeAkunCampuran = $barangCampuran?->subAnakAkun?->kode_sub_anak_akun ?? '1500-00';
            $namaAkunCampuran = $this->getNamaAkun($kodeAkunCampuran)
                ?: ($barangCampuran?->nama_barang ?? "Pakan {$data['label']}");
            $hargaCampuran    = (float)($barangCampuran?->harga_jual ?? 0);
            $nilaiCampuran    = $data['jumlah'] * $hargaCampuran;

            $hCampuran = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => $kodeAkunCampuran,
                'nama_akun'          => $namaAkunCampuran,
                'map'                => 'k',
                'keterangan'         => $ket,
                'no_dokumen'         => $nota,
                'total_nilai'        => $nilaiCampuran,
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hCampuran->id, [
                'urut'        => $urutItem++,
                'nama_barang' => $barangCampuran?->nama_barang,
                'no_dokumen'  => $nota,
                'keterangan'  => "Pakan {$data['label']} keluar ke kandang",
                'banyak'      => $data['jumlah'],
                'harga'       => $hargaCampuran,
                'jumlah'      => $nilaiCampuran,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $totalKredit += $nilaiCampuran;
        }

        $hGaji = $this->buatHeader([
            'no_jurnal_pembantu' => $this->nextNomorPembantu(),
            'tgl_transaksi'      => $tgl,
            'jenis_transaksi'    => 'pk',
            'modul_asal'         => 'produksi_pakan',
            'jurnal'             => $noJurnal,
            'no_akun'            => self::KODE_HUTANG_GAJI,
            'nama_akun'          => $this->getNamaAkun(self::KODE_HUTANG_GAJI) ?: 'Hutang Gaji Karyawan',
            'map'                => 'k',
            'keterangan'         => "Hutang Gaji Karyawan | {$ket}",
            'no_dokumen'         => $nota,
            'total_nilai'        => self::NILAI_HUTANG_GAJI,
            'dibuat_oleh'        => $userId,
        ]);

        $this->buatItem($hGaji->id, [
            'urut'        => 1,
            'no_dokumen'  => $nota,
            'keterangan'  => 'Akrual gaji karyawan kandang',
            'banyak'      => 1,
            'harga'       => self::NILAI_HUTANG_GAJI,
            'jumlah'      => self::NILAI_HUTANG_GAJI,
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]);

        $totalKredit += self::NILAI_HUTANG_GAJI;

        $selisih = $totalDebit - $totalKredit;

        if (abs($selisih) > 0.001) {
            $mapSelisih   = $selisih > 0 ? 'k' : 'd';
            $nilaiSelisih = abs($selisih);
            $namaSelisih  = $this->getNamaAkun(self::KODE_PENDAPATAN_KELEBIHAN_TELUR)
                ?: 'Pendapatan kelebihan produksi telur';

            $hSelisih = $this->buatHeader([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi'      => $tgl,
                'jenis_transaksi'    => 'pk',
                'modul_asal'         => 'produksi_pakan',
                'jurnal'             => $noJurnal,
                'no_akun'            => self::KODE_PENDAPATAN_KELEBIHAN_TELUR,
                'nama_akun'          => $namaSelisih,
                'map'                => $mapSelisih,
                'keterangan'         => "Penyesuaian Selisih Produksi Telur | {$ket}",
                'no_dokumen'         => $nota,
                'total_nilai'        => $nilaiSelisih,
                'dibuat_oleh'        => $userId,
            ]);

            $this->buatItem($hSelisih->id, [
                'urut'        => 1,
                'no_dokumen'  => $nota,
                'keterangan'  => 'Balancing Jurnal Produksi Telur',
                'banyak'      => 1,
                'harga'       => $nilaiSelisih,
                'jumlah'      => $nilaiSelisih,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);
        }

        Log::info("[ProduksiPakan] Proses 2 selesai (1 jurnal). ID: {$produksi->id}, No Jurnal: {$noJurnal}");
    }

    /* ═══════════════════════════════════════════════════════════════════════
    |  HELPERS
    ═══════════════════════════════════════════════════════════════════════ */
    private function getNamaAkun(string $kode): string
    {
        if (isset($this->akunCache[$kode])) return $this->akunCache[$kode];

        return $this->akunCache[$kode] = SubAnakAkun::where('kode_sub_anak_akun', $kode)
            ->value('nama_sub_anak_akun') ?? '';
    }

    private function nextNoJurnal(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;
    }

    private function nextNomorPembantu(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;
    }

    private function buatHeader(array $data): JurnalPembantuHeader
    {
        return JurnalPembantuHeader::create(array_merge([
            'status'              => JurnalPembantuHeader::STATUS_DRAFT,
            'adalah_jurnal_balik' => false,
            'total_nilai'         => 0,
        ], $data));
    }

    private function buatItem(int $headerId, array $data): JurnalPembantuItem
    {
        return JurnalPembantuItem::create(array_merge([
            'jurnal_pembantu_header_id' => $headerId,
            'status'                    => true,
            'jumlah'                    => 0,
        ], $data));
    }
}
