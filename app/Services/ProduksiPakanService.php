<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\ProduksiPakan;
use App\Models\Satuan;
use App\Models\SatuanKonversi;
use App\Models\SubAnakAkun;
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

        $adaCampuranKeluar = $produksi->pakanCampurans->contains(
            fn($i) => (float)$i->keluar_pullet > 0
                || (float)$i->keluar_l1 > 0
                || (float)$i->keluar_l2 > 0
        );

        DB::transaction(function () use ($produksi, $userId, $adaMentah, $adaCampuranKeluar) {

            // Kondisi A & C — ada bahan mentah → jalankan Proses 1
            if ($adaMentah) {
                $this->buatJurnalProses1($produksi, $userId);
            }

            // Kondisi A & B — ada pakan campuran keluar → jalankan Proses 2
            if ($adaCampuranKeluar) {
                $this->buatJurnalProses2($produksi, $userId);
            }

            if (!$adaMentah && !$adaCampuranKeluar) {
                Log::info("[ProduksiPakan] Tidak ada data terisi, jurnal tidak dibuat.");
            }
        });
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

    private function buatJurnalProses1(ProduksiPakan $produksi, int $userId): void
    {
        $tgl      = $produksi->tanggal_produksi->toDateString();
        $nota     = 'PROD-' . $produksi->id . '-' . $tgl;
        $ket      = "Produksi Pakan | Tgl: {$tgl}";
        $noJurnal = $this->nextNoJurnal() ?? 0;

        /**
         * LANGKAH 1: Hitung total per bahan mentah (gabungkan semua kandang)
         *
         * Kenapa kita gabung dulu sebelum buat jurnal?
         * Karena kita ingin 1 baris kredit per bahan mentah, bukan
         * 3 baris (pullet + l1 + l2) untuk bahan yang sama.
         *
         * Contoh hasil $totalPerMentah:
         * [
         *   barang_id_1 => ['barang' => ..., 'totalKg' => 2600, 'totalNilai' => 3.380.000],
         *   barang_id_2 => ['barang' => ..., 'totalKg' => 800,  'totalNilai' => 320.000],
         * ]
         */
        // ... di dalam buatJurnalProses1 ...

        $totalPerMentah = [];
        foreach ($produksi->pakanMentahs as $mentah) {
            $jumlahKg = (float)$mentah->keluar_pullet
                + (float)$mentah->keluar_l1
                + (float)$mentah->keluar_l2;

            if ($jumlahKg <= 0) continue;

            $barang   = $mentah->barang;
            $id       = $barang->id;
            $harga    = (float)($barang->harga_jual ?? 0);
            $konversi = $this->getKonversiSak($id); // Sekarang mengembalikan 50 jika berupa Sak

            // Menggunakan round() untuk menghindari miss desimal di pembukuan
            $nilaiTambahan = round($jumlahKg * $harga, 2);

            if (!isset($totalPerMentah[$id])) {
                $totalPerMentah[$id] = [
                    'barang'     => $barang,
                    'totalKg'    => 0.0,
                    'totalNilai' => 0.0,
                    'harga'      => $harga,
                    'konversi'   => $konversi,
                ];
            }

            $totalPerMentah[$id]['totalKg']    += $jumlahKg;
            $totalPerMentah[$id]['totalNilai'] += $nilaiTambahan;
        }

        /**
         * LANGKAH 2: Hitung total nilai per pakan campuran yang dihasilkan
         *
         * Setiap kandang (pullet/l1/l2) menghasilkan pakan campuran.
         * Total bahan mentah yang masuk = nilai debit pakan campuran itu.
         */
        $kandangs = [
            'pullet' => ['field' => 'keluar_pullet', 'label' => 'Pullet'],
            'l1'     => ['field' => 'keluar_l1',     'label' => 'Layer 1'],
            'l2'     => ['field' => 'keluar_l2',     'label' => 'Layer 2'],
        ];

        // Map: kandangKey => ['campuran' => model, 'totalKgMasuk' => float]
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

            // Hitung total bahan mentah yang masuk ke kandang ini (dalam kg)
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

        // ── Penampung total debit & kredit untuk balancing ──────────────
        $totalDebit  = 0.0;
        $totalKredit = 0.0;
        $urutItem    = 1;

        /* ─────────────────────────────────────────────────────────────────
        |  DEBIT: Pakan Campuran yang dihasilkan (Layer 1, Layer 2, Pullet)
        |  Setiap kandang → satu baris debit
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  KREDIT: Bahan Mentah yang terpakai (total semua kandang digabung)
        |  Satu baris kredit per jenis bahan mentah
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  KREDIT: Hutang Gaji
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  KREDIT: Hutang Listrik
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  BALANCING: Selisih Debit vs Kredit
        |
        |  Kenapa bisa ada selisih?
        |  Karena harga pakan campuran (debit) dihitung dari harga_jual
        |  barang campuran, sedangkan kredit dihitung dari harga_jual
        |  bahan mentah. Keduanya bisa berbeda.
        |
        |  Aturan: Total D harus = Total K
        |  Jika D > K → pasang selisih di sisi K (kredit pendapatan)
        |  Jika K > D → pasang selisih di sisi D (kurangi pendapatan)
        ───────────────────────────────────────────────────────────────── */
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
    |
    |  Struktur jurnal (mengikuti foto):
    |  D: Telur Petian, Telur Kiloan, Telur Bentes (hardcoded, nilai per kandang)
    |  K: Setiap Pakan Campuran yang keluar (Layer 1, Layer 2, Pullet)
    |  K: Hutang Gaji
    |  K/D: Selisih balancing jika ada
    |
    |  PERBEDAAN dengan Proses 1:
    |  Di foto, Proses 2 tetap per jurnal per kandang (jurnal 93 hanya L1+L2).
    |  Tapi kita gabungkan juga agar konsisten — semua dalam 1 jurnal.
    ═══════════════════════════════════════════════════════════════════════ */

    private function buatJurnalProses2(ProduksiPakan $produksi, int $userId): void
    {
        $tgl      = $produksi->tanggal_produksi->toDateString();
        $nota     = 'PRODC-' . $produksi->id . '-' . $tgl;
        $ket      = "Produksi Pakan Campuran | Tgl: {$tgl}";
        $noJurnal = $this->nextNoJurnal() ?? 0;

        $kandangs = [
            'pullet' => ['field' => 'keluar_pullet', 'label' => 'Pullet'],
            'l1'     => ['field' => 'keluar_l1',     'label' => 'Layer 1'],
            'l2'     => ['field' => 'keluar_l2',     'label' => 'Layer 2'],
        ];

        /**
         * LANGKAH 1: Kumpulkan pakan campuran yang keluar ke kandang
         *
         * Kita kumpulkan dulu semua yang ada nilainya, baru buat jurnal.
         * Ini memastikan kita tahu total debit telur sebelum nulis baris kredit.
         */
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

        // ── Penampung total untuk balancing ─────────────────────────────
        $totalDebit  = 0.0;
        $totalKredit = 0.0;
        $urutItem    = 1;

        /* ─────────────────────────────────────────────────────────────────
        |  DEBIT: Telur (hardcoded per jenis telur)
        |
        |  Dari foto: nilai telur tampaknya adalah nilai total (bukan per kandang).
        |  Kita pasang setiap jenis telur sebagai 1 baris debit.
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  KREDIT: Pakan Campuran keluar per kandang
        |  Setiap jenis pakan yang keluar → 1 baris kredit
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  KREDIT: Hutang Gaji
        ───────────────────────────────────────────────────────────────── */
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

        /* ─────────────────────────────────────────────────────────────────
        |  BALANCING: Selisih Debit vs Kredit
        ───────────────────────────────────────────────────────────────── */
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

    private function getKonversiSak(?int $barangId): float
    {
        if (!$barangId) return 1;
        if (isset($this->konversiCache[$barangId])) return $this->konversiCache[$barangId];

        $satuanSak = Satuan::whereRaw('LOWER(nama_satuan) = ?', ['sak'])->first();
        if (!$satuanSak) return $this->konversiCache[$barangId] = 1;

        // Baca nilai aktual dari DB, bukan exists() saja
        $konversi = SatuanKonversi::where('id_barang', $barangId)
            ->where('id_satuan_asal', $satuanSak->id)
            ->aktif()
            ->first();

        return $this->konversiCache[$barangId] = $konversi
            ? (float) $konversi->nilai_konversi  // ← nilai real dari DB
            : 1;
    }
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
