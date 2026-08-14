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
            $this->hapusJurnalLama($nota);

            // 2. Siapkan data telur hasil korektor (Debet)
            $telurItems = $this->prepareTelurItems($produksi);
            $totalDebetTelur = array_sum(array_column($telurItems, 'total'));

            if ($totalDebetTelur <= 0) {
                Log::info("[ProduksiTelurService] Total nilai telur 0, pembuatan jurnal dilewati. Nota: {$nota}");
                return;
            }

            // 3. Siapkan data pemakaian pakan dari kandang (Kredit)
            $pakanGrouped = $this->preparePakanItems($produksi->id);
            $totalKreditPakan = array_sum(array_column($pakanGrouped, 'total_nilai'));

            // 4. Hitung selisih penyeimbang (Pendapatan Kelebihan Produksi Telur)
            $selisih = $totalDebetTelur - $totalKreditPakan;

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

            // 8. Record Jurnal Kredit Selisih (Penyeimbang Balance)
            if ($selisih != 0) {
                $selisihSubAkun  = SubAnakAkun::where('kode_sub_anak_akun', '4400-00')->first();
                $namaSelisihAkun = $selisihSubAkun?->nama_sub_anak_akun ?? 'Pendapatan Kelebihan Produksi Telur';

                $this->createJurnalEntry($nextJurnal, $tgl, $nota, $ket, '4400-00', $namaSelisihAkun, 'k', $userId, [
                    'nama_barang' => $namaSelisihAkun,
                    'keterangan'  => "Selisih penyeimbang produksi telur vs pakan",
                    'banyak'      => 1,
                    'harga'       => $selisih,
                ]);
            }

            Log::info("[ProduksiTelurService] Jurnal berhasil dibukukan untuk nota {$nota}. Balance: Rp {$totalDebetTelur}");
        });
    }

    /**
     * Hapus jurnal lama (draft) untuk re-validasi.
     */
    public function hapusJurnalLama(string $nota): void
    {
        $isPosted = JurnalPembantuHeader::where('modul_asal', 'produksi_telur')
            ->where('no_dokumen', $nota)
            ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
            ->exists();

        if ($isPosted) {
            throw new Exception("Jurnal produksi ini sudah diposting ke Buku Besar. Batalkan posting terlebih dahulu!");
        }

        $headers = JurnalPembantuHeader::where('modul_asal', 'produksi_telur')
            ->where('no_dokumen', $nota)
            ->get();

        foreach ($headers as $header) {
            $header->items()->delete();
            $header->delete();
        }
    }

    // ─── PRIVATE HELPER METHODS ──────────────────────────────────────────────

    /**
     * Menyusun item telur berdasarkan hasil penimbangan korektor.
     */
    private function prepareTelurItems(ProduksiTelur $produksi): array
    {
        $korektor = \App\Models\ProduksiTelurKorektor::where('id_produksi_telur', $produksi->id)->first();

        $map = [
            '1400-11' => [
                'qty' => (float) ($korektor?->korektor_peti ?? 0),
                'fallback' => 'telur petian Ruko',
                'satuan' => 'PETI',
            ],
            '1400-12' => [
                'qty' => (float) (($korektor?->korektor_kiloan ?? 0) + ($korektor?->korektor_sisa ?? 0)),
                'fallback' => 'telur kiloan Ruko',
                'satuan' => 'KG',
            ],
            '1400-13' => [
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
