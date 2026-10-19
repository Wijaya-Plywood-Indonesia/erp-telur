<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DetailProduksiTelur;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\JurnalUmum;
use App\Models\ProduksiTelur;
use App\Models\SubAnakAkun;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProduksiTelurService
{
    const KODE_HUTANG_GAJI   = '2210-01';
    const NILAI_HUTANG_GAJI  = 650000;
    const KODE_SELISIH       = '5800-02';

    /**
     * Buat jurnal harian (konsolidasi) dari data hasil produksi telur & pemakaian pakan.
     */
    public function buatJurnalDariProduksi(ProduksiTelur $produksi, int $userId): void
    {
        $tgl  = $produksi->tanggal->toDateString();
        $nota = "PRODTELUR-{$produksi->id}-{$tgl}";
        $ket  = "Produksi Telur Harian | Tgl: {$tgl}";

        DB::transaction(function () use ($produksi, $userId, $tgl, $nota, $ket) {
            // 1. Bersihkan draft jurnal lama jika ada
            $this->bersihkanJurnalSebelumnya($produksi);
            // 1b. Nota baru, dibuat SETELAH draft dibersihkan
            $nota = $this->buatNotaBaru($produksi);
            // 2. Siapkan data telur hasil korektor (Debet)
            $telurItems = $this->prepareTelurItems($produksi);
            $totalDebetTelur = array_sum(array_column($telurItems, 'total'));

            if ($totalDebetTelur <= 0) {
                throw new Exception('Total nilai telur 0 (cek korektor dan harga jual barang), jurnal tidak dapat dibuat.');
            }

            // 3. Siapkan data pemakaian pakan dari kandang (Kredit)
            $pakanGrouped = $this->preparePakanItems($produksi->id);
            $totalKreditPakan = array_sum(array_column($pakanGrouped, 'total_nilai'));

            // 4. Hitung selisih penyeimbang (Pendapatan Kelebihan Produksi Telur)
            $totalKreditLain = self::NILAI_HUTANG_GAJI;
            $selisih = $totalDebetTelur - $totalKreditPakan - $totalKreditLain;

            // 5. Generate nomor jurnal grup yang aman dari concurrency
            $nextJurnal = $this->generateNextJurnalNumber();

            // 6. Record Jurnal Debet (Aset Telur)
            foreach ($telurItems as $item) {
                $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, $item['kode'], $item['nama'], 'd', $userId, [
                    'nama_barang' => $item['nama'],
                    'keterangan'  => "Hasil Korektor: {$item['qty']} {$item['satuan']}",
                    'banyak'      => $item['qty'],
                    'harga'       => $item['harga'],
                ]);
            }

            // 7. Record Jurnal Kredit (Persediaan Pakan)
            $urut = 1;
            foreach ($pakanGrouped as $pakan) {
                $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, $pakan['no_akun'], $pakan['nama_akun'], 'k', $userId, [
                    'nama_barang' => $pakan['nama_barang'],
                    'keterangan'  => "Pemakaian pakan kandang",
                    'banyak'      => $pakan['banyak'],
                    'harga'       => $pakan['harga'],
                    'urut'        => $urut++,
                ]);
            }

            // 7b. Record Jurnal Kredit Hutang Gaji
            $gajiNama = SubAnakAkun::where('kode_sub_anak_akun', self::KODE_HUTANG_GAJI)
                ->value('nama_sub_anak_akun') ?? 'Hutang Gaji Pegawai Kandang';

            $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, self::KODE_HUTANG_GAJI, $gajiNama, 'k', $userId, [
                'nama_barang' => $gajiNama,
                'keterangan'  => 'Akrual gaji pegawai kandang telur',
                'banyak'      => 1,
                'harga'       => self::NILAI_HUTANG_GAJI,
            ]);

            // 7c. Jurnal Peti (khusus telur PETIAN): D Peti Isi Telur | K Peti Kosong
            $qtyPeti = 0.0;
            foreach ($telurItems as $item) {
                if ($item['kode'] === '1411-00') {
                    $qtyPeti = (float) $item['qty'];
                }
            }
            if ($qtyPeti > 0) {
                $this->buatJurnalPeti($nextJurnal, $tgl, $nota, $ket, $qtyPeti, $userId);
            }

            // 8. Record selisih penyeimbang (Kredit jika lebih, Debet jika kurang)
            if (abs($selisih) > 0.001) {
                $mapSelisih      = $selisih > 0 ? 'k' : 'd';
                $selisihSubAkun  = SubAnakAkun::where('kode_sub_anak_akun', self::KODE_SELISIH)->first();
                $namaSelisihAkun = $selisihSubAkun?->nama_sub_anak_akun ?? 'Beban Kelebihan Produksi';

                $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, self::KODE_SELISIH, $namaSelisihAkun, $mapSelisih, $userId, [
                    'nama_barang' => $namaSelisihAkun,
                    'keterangan'  => 'Selisih penyeimbang produksi telur vs pakan & gaji',
                    'banyak'      => 1,
                    'harga'       => abs($selisih),
                ]);
            }

            Log::info("[ProduksiTelurService] Jurnal berhasil dibukukan untuk nota {$nota}. Balance: Rp {$totalDebetTelur}");
        });
    }

    /** Awalan nota untuk satu produksi, contoh: PRODTELUR-12-2026-10-18 */
    private function prefixNota(ProduksiTelur $produksi): string
    {
        return "PRODTELUR-{$produksi->id}-{$produksi->tanggal->toDateString()}";
    }

    /** Query dasar: semua jurnal ASLI (bukan jurnal balik) milik produksi ini. */
    private function queryJurnalAsli(ProduksiTelur $produksi)
    {
        return JurnalPembantuHeader::where('modul_asal', 'produksi_telur')
            ->where('no_dokumen', 'like', $this->prefixNota($produksi) . '%')
            ->where(function ($q) {
                $q->where('adalah_jurnal_balik', false)
                    ->orWhereNull('adalah_jurnal_balik');
            });
    }

    /**
     * Dipanggil sebelum batal validasi, bukaKunci, dan validasi ulang.
     * - Menolak kalau ada jurnal diposting yang BELUM dibalik.
     * - Menghapus jurnal draft saja. Jurnal posting dan jurnal balik tidak disentuh.
     */
    public function bersihkanJurnalSebelumnya(ProduksiTelur $produksi): void
    {
        $belumDibalik = $this->queryJurnalAsli($produksi)
            ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
            ->whereDoesntHave('jurnalBalik', function ($q) {
                $q->where('status', '!=', JurnalPembantuHeader::STATUS_DIBATALKAN);
            })
            ->exists();

        if ($belumDibalik) {
            throw new Exception(
                'Jurnal produksi ini sudah diposting ke Buku Besar. ' .
                    'Buat Jurnal Balik terlebih dahulu (tidak perlu menunggu jurnal balik diposting).'
            );
        }

        $drafts = $this->queryJurnalAsli($produksi)
            ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
            ->get();

        foreach ($drafts as $header) {
            $header->items()->delete();
            $header->delete();
        }
    }

    /** Nota baru: pertama tanpa akhiran, berikutnya -R1, -R2, dst. */
    private function buatNotaBaru(ProduksiTelur $produksi): string
    {
        $prefix = $this->prefixNota($produksi);
        $jumlah = $this->queryJurnalAsli($produksi)->distinct()->count('no_dokumen');

        return $jumlah > 0 ? "{$prefix}-R{$jumlah}" : $prefix;
    }

    /**
     * Menyusun item telur berdasarkan hasil penimbangan korektor.
     */
    private function prepareTelurItems(ProduksiTelur $produksi): array
    {
        $korektor = \App\Models\ProduksiTelurKorektor::where('id_produksi_telur', $produksi->id)->first();

        $map = [
            '1411-00' => [
                'qty' => (float) ($korektor?->korektor_peti ?? 0),
                'fallback' => 'telur petian Ruko',
                'satuan' => 'PETI',
            ],
            '1412-00' => [
                'qty' => (float) (($korektor?->korektor_kiloan ?? 0) + ($korektor?->korektor_sisa ?? 0)),
                'fallback' => 'telur kiloan Ruko',
                'satuan' => 'KG',
            ],
            '1413-00' => [
                'qty' => (float) ($korektor?->korektor_bentes ?? 0),
                'fallback' => 'telur bentes Ruko',
                'satuan' => 'KG',
            ],
        ];

        $items = [];
        foreach ($map as $kodeAkun => $cfg) {
            if ($cfg['qty'] <= 0) continue;

            $barang = Barang::whereHas('subAnakAkun', fn($q) => $q->where('kode_sub_anak_akun', $kodeAkun))->first();
            $harga  = $barang ? (float) $barang->harga_jual : 0;

            $items[] = [
                'kode'   => $kodeAkun,
                'nama'   => $barang?->nama_barang ?? $cfg['fallback'],
                'qty'    => $cfg['qty'],
                'harga'  => $harga,
                'total'  => $cfg['qty'] * $harga,
                'satuan' => $cfg['satuan'],
            ];
        }

        return $items;
    }

    /**
     * Mengelompokkan pakan terpakai berdasarkan detail produksi kandang.
     */
    private function preparePakanItems(int $produksiTelurId): array
    {
        $details = DetailProduksiTelur::with('pakanCampuran.barang.subAnakAkun')
            ->where('id_produksi_telur', $produksiTelurId)
            ->whereNotNull('id_produksi_pakan_campuran')
            ->get();

        $grouped = [];
        foreach ($details as $detail) {
            $pakanCampuran = $detail->pakanCampuran;
            if (!$pakanCampuran || !$pakanCampuran->barang) continue;

            $barang   = $pakanCampuran->barang;
            $barangId = $barang->id;
            $qtyPakan = (float) ($pakanCampuran->keluar_pullet + $pakanCampuran->keluar_l1 + $pakanCampuran->keluar_l2);

            if ($qtyPakan <= 0) continue;

            if (!isset($grouped[$barangId])) {
                $grouped[$barangId] = [
                    'no_akun'     => $barang->subAnakAkun?->kode_sub_anak_akun ?? '1500-00',
                    'nama_akun'   => $barang->subAnakAkun?->nama_sub_anak_akun   ?? $barang->nama_barang,
                    'nama_barang' => $barang->nama_barang,
                    'banyak'      => $qtyPakan,
                    'harga'       => (float) $barang->harga_jual,
                    'total_nilai' => $qtyPakan * (float) $barang->harga_jual,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Jurnal peti produksi petian:
     *   D Peti Isi Telur (1600-02) | K Peti Kosong (1600-01)
     * Kedua baris memakai harga (HPP) yang SAMA, jadi tidak mengubah balance jurnal.
     */
    private function buatJurnalPeti(int $nextJurnal, string $tgl, string $nota, string $ket, float $qty, int $userId): void
    {
        $brgKosong = Barang::with('subAnakAkun')->where('nama_barang', 'like', '%peti%kosong%')->first();
        $brgIsi    = Barang::with('subAnakAkun')->where('nama_barang', 'like', '%peti%isi%telur%')->first();

        $hpp = (float) ($brgKosong?->harga_beli ?? 0);
        if ($hpp <= 0) {
            $hpp = 6000; // fallback jika HPP peti kosong belum diisi
        }

        $kodeKosong = $brgKosong?->subAnakAkun?->kode_sub_anak_akun ?? '1600-01';
        $kodeIsi    = $brgIsi?->subAnakAkun?->kode_sub_anak_akun ?? '1600-02';

        $namaKosong = SubAnakAkun::where('kode_sub_anak_akun', $kodeKosong)->value('nama_sub_anak_akun') ?? 'Peti Kosong';
        $namaIsi    = SubAnakAkun::where('kode_sub_anak_akun', $kodeIsi)->value('nama_sub_anak_akun') ?? 'Peti Isi Telur';

        // D: Peti Isi Telur bertambah
        $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, $kodeIsi, $namaIsi, 'd', $userId, [
            'nama_barang' => $brgIsi?->nama_barang ?? 'Peti Isi Telur',
            'keterangan'  => "Masuk stok peti isi telur {$qty} peti (Produksi petian)",
            'banyak'      => $qty,
            'harga'       => $hpp,
        ]);

        // K: Peti Kosong berkurang
        $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, $kodeKosong, $namaKosong, 'k', $userId, [
            'nama_barang' => $brgKosong?->nama_barang ?? 'Peti Kosong',
            'keterangan'  => "Keluar stok peti kosong {$qty} peti (Produksi petian)",
            'banyak'      => $qty,
            'harga'       => $hpp,
        ]);
    }

    /**
     * Membuat satu set Header + Item Jurnal Pembantu.
     */
    private function createJurnalEntry(
        int $nextJurnal,
        string $tgl,
        string $nota,
        string $ket,
        string $noAkun,
        string $namaAkun,
        string $map,
        int $userId,
        array $itemData
    ): void {
        $nextNoJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;

        $header = JurnalPembantuHeader::create([
            'no_jurnal_pembantu' => $nextNoJP,
            'tgl_transaksi'      => $tgl,
            'jenis_transaksi'    => 'pk',
            'modul_asal'         => 'produksi_telur',
            'jurnal'             => $nextJurnal,
            'no_akun'            => $noAkun,
            'nama_akun'          => $namaAkun,
            'map'                => $map,
            'keterangan'         => $ket,
            'no_dokumen'         => $nota,
            'status'             => JurnalPembantuHeader::STATUS_DRAFT,
            'dibuat_oleh'        => $userId,
        ]);

        JurnalPembantuItem::create([
            'jurnal_pembantu_header_id' => $header->id,
            'urut'                      => $itemData['urut'] ?? 1,
            'nama_barang'               => $itemData['nama_barang'],
            'no_dokumen'                => $nota,
            'keterangan'                => $itemData['keterangan'],
            'banyak'                    => $itemData['banyak'],
            'harga'                     => $itemData['harga'],
            'status'                    => true,
            'created_by'                => $userId,
            'updated_by'                => $userId,
        ]);
    }

    /**
     * Generate nomor urut grup jurnal berikutnya secara atomic.
     */
    private function generateNextJurnalNumber(): int
    {
        $maxJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0);
        $maxJU = (int) (JurnalUmum::lockForUpdate()->max('jurnal') ?? 0);

        return max($maxJP, $maxJU) + 1;
    }
}