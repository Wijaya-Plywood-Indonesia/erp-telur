<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use App\Models\Penjualan;
use Carbon\CarbonInterface;

/**
 * Nota dari app satpam baru "masuk pembukuan" pada saat setorannya ditutup.
 * Service ini memberi nota-nota itu tanggal pembukuan = waktu tutup buku, lalu menyamakan
 * tanggal jurnalnya, supaya buku besar, neraca, dan laba rugi jatuh ke tanggal/bulan yang benar.
 *
 *  - penjualans.tanggal_pembukuan       : diisi waktu tutup buku
 *  - jurnal_pembantu_headers (draft & diposting, bukan jurnal balik) : tgl_transaksi = tanggal tutup
 *  - jurnal_umums yang berasal dari jurnal pembantu itu              : tgl = tanggal tutup
 *
 * Tanggal nota (penjualans.tanggal) tidak diubah, jadi struk tetap menampilkan jam penjualan asli.
 */
class TanggalPembukuanService
{
    /**
     * @param bool $denganJurnal false = hanya isi penjualans.tanggal_pembukuan (untuk Preview/laporan),
     *                           jurnal dibiarkan. Dipakai untuk mengisi setoran lama.
     */
    public function terapkan(iterable $penjualanIds, CarbonInterface $waktu, bool $denganJurnal = true): void
    {
        $ids = collect($penjualanIds)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        Penjualan::whereIn('id', $ids)->update(['tanggal_pembukuan' => $waktu]);

        if (! $denganJurnal) {
            return;
        }

        $tgl = $waktu->toDateString();
        $notas = Penjualan::whereIn('id', $ids)->pluck('no_nota');

        foreach ($notas->chunk(500) as $chunk) {
            $headers = JurnalPembantuHeader::query()
                ->whereIn('no_dokumen', $chunk)
                ->where('modul_asal', 'penjualan_telur')
                ->where('adalah_jurnal_balik', false)
                ->whereIn('status', [
                    JurnalPembantuHeader::STATUS_DRAFT,
                    JurnalPembantuHeader::STATUS_DIPOSTING,
                ])
                ->get(['id', 'jurnal', 'status']);

            if ($headers->isEmpty()) {
                continue;
            }

            JurnalPembantuHeader::whereIn('id', $headers->pluck('id'))
                ->update(['tgl_transaksi' => $tgl]);

            // Yang sudah diposting: baris jurnal umumnya (nama = no nota) ikut dipindah tanggalnya.
            $nomorDiposting = $headers
                ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
                ->pluck('jurnal')
                ->filter()
                ->unique();

            if ($nomorDiposting->isNotEmpty()) {
                JurnalUmum::query()
                    ->whereIn('jurnal', $nomorDiposting)
                    ->whereIn('nama', $chunk)
                    ->update(['tgl' => $tgl]);
            }
        }
    }
}