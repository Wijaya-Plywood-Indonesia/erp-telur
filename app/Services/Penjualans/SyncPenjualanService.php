<?php

namespace App\Services\Penjualans;

use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use Illuminate\Support\Facades\DB;

class SyncPenjualanService
{
    /**
     * Kalkulasi total dari detail_penjualans (Return float untuk decimal 15,2)
     */
    public static function calculateCurrentTotal(int $penjualanId): float
    {
        return (float) DetailPenjualan::where('penjualan_id', $penjualanId)
            ->sum('subtotal');
    }

    /**
     * Kalkulasi kembalian awal saat modal dibuka
     */
    public static function calculateKembalian(int $penjualanId, float $total_kemarin): float
    {
        $total_hari_ini = self::calculateCurrentTotal($penjualanId);

        // Jika harga turun, kembalikan selisihnya ke pelanggan
        if ($total_kemarin > $total_hari_ini) {
            return $total_kemarin - $total_hari_ini;
        }

        return 0;
    }

    /**
     * Proses Sinkronisasi ke Database
     */
    public static function syncPenjualan(int $penjualanId, array $data): void
    {
        DB::transaction(function () use ($penjualanId, $data) {
            $penjualan = Penjualan::findOrFail($penjualanId);

            $bayar = (float) $data['bayar'];
            $metode = strtoupper((string) $penjualan->metode_pembayaran);

            // bayar_tunai / bayar_transfer ikut diselaraskan dengan bayar baru,
            // karena dipakai oleh jurnal kas dan rekap penjualan (sama seperti saat input di POS).
            if ($metode === 'TRANSFER') {
                $bayarTunai = 0;
                $bayarTransfer = $bayar;
            } elseif ($metode === 'TUNAI & TRANSFER') {
                // Bagian transfer dianggap tetap (sudah masuk bank), selisihnya di sisi tunai
                $bayarTransfer = (float) $penjualan->bayar_transfer;
                $bayarTunai = max(0, $bayar - $bayarTransfer);
            } else {
                $bayarTunai = $bayar;
                $bayarTransfer = 0;
            }

            $penjualan->update([
                'total' => $data['total'],
                'bayar' => $bayar,
                'kembalian' => $data['kembalian'],
                'bayar_tunai' => $bayarTunai,
                'bayar_transfer' => $bayarTransfer,
                'keterangan_pembayaran' => $data['keterangan'],
            ]);
            
            // Logika tambahan seperti input kas keluar jika ada kembalian 
            // bisa ditambahkan di sini.
        });
    }
}