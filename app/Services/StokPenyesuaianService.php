<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Penjualan;
use App\Models\ReturnPenjualan;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokPenyesuaianService
{
    /**
     * Stok Opname / Penyesuaian Manual Langsung ke Global
     */
    public function sesuaikan(
        int $barangId,
        int $tokoId, // Dipertahankan agar tidak mematahkan controller lama, tapi tidak digunakan
        int $stokFisik,
        int $userId,
        ?string $catatan
    ): void {
        DB::transaction(function () use ($barangId, $stokFisik) {

            $barang = Barang::lockForUpdate()->find($barangId);

            if (!$barang) {
                return;
            }

            $stokSebelum = (float) $barang->stok_buku_besar;

            if ($stokSebelum === (float) $stokFisik) {
                return;
            }

            // Timpa langsung nilai stok global dengan angka fisik baru
            $barang->update([
                'stok_buku_besar' => $stokFisik,
            ]);
        });
    }

    /**
     * Transaksi Penjualan Lunas -> Potong Stok Global
     */
    public function lunas(int $id_penjualan): void
    {
        DB::transaction(function () use ($id_penjualan) {

            $details = DB::table('penjualan_details')
                ->where('penjualan_id', $id_penjualan)
                ->select(['barang_id', 'qty', 'nama_barang'])
                ->get();

            foreach ($details as $detail) {
                $barang = Barang::lockForUpdate()->find($detail->barang_id);
                $stokSebelum = $barang ? (float) $barang->stok_buku_besar : 0;

                // Validasi kecukupan stok global
                if ($stokSebelum - (float) $detail->qty < 0) {
                    throw ValidationException::withMessages([
                        'stok' => "Stok {$detail->nama_barang} tidak mencukupi"
                    ]);
                }

                $stokSesudah = $stokSebelum - (float) $detail->qty;

                // Potong langsung di tabel barang utama
                $barang->update([
                    'stok_buku_besar' => $stokSesudah,
                ]);

                Notification::make()
                    ->title("Stok {$detail->nama_barang} berkurang")
                    ->body("Sisa stok: $stokSesudah")
                    ->success()
                    ->send();
            }

            Notification::make()
                ->title('Transaksi Lunas')
                ->success()
                ->send();
        });
    }

    /**
     * Transaksi Batal Lunas -> Kembalikan Stok ke Global
     */
    public function batalLunas(int $id_penjualan): void
    {
        DB::transaction(function () use ($id_penjualan) {

            $details = DB::table('penjualan_details')
                ->where('penjualan_id', $id_penjualan)
                ->select(['barang_id', 'qty', 'nama_barang'])
                ->get();

            foreach ($details as $detail) {
                $barang = Barang::lockForUpdate()->find($detail->barang_id);

                if (!$barang) {
                    continue;
                }

                $stokSebelum = (float) $barang->stok_buku_besar;
                $stokSesudah = $stokSebelum + (float) $detail->qty;

                // Tambahkan kembali ke stok global barang
                $barang->update([
                    'stok_buku_besar' => $stokSesudah,
                ]);

                Notification::make()
                    ->title("Stok {$detail->nama_barang} dikembalikan")
                    ->body("Total stok: $stokSesudah")
                    ->success()
                    ->send();
            }

            Notification::make()
                ->title('Transaksi dibatalkan')
                ->success()
                ->send();
        });
    }

    /**
     * Retur Selesai -> Menambah Stok Global
     * (Pengecekan berbasis model StokLog telah dihapus)
     */
    public function selesai(int $id_return): void
    {
        DB::transaction(function () use ($id_return) {
            $return = ReturnPenjualan::with('details_return')->findOrFail($id_return);

            foreach ($return->details_return as $detail) {
                $barang = Barang::lockForUpdate()->find($detail->id_barang);

                if (!$barang) {
                    continue;
                }

                $stokSebelum = (float) $barang->stok_buku_besar;
                $stokSesudah = $stokSebelum + (float) $detail->qty;

                // Masukkan barang retur langsung ke stok global utama
                $barang->update([
                    'stok_buku_besar' => $stokSesudah,
                ]);

                Notification::make()
                    ->title("Stok {$detail->nama_barang} bertambah (Retur)")
                    ->body("Total stok: $stokSesudah")
                    ->success()
                    ->send();
            }

            Notification::make()
                ->title('Return Selesai/Diterima')
                ->success()
                ->send();
        });
    }

    /**
     * Batal Retur -> Mengurangi Kembali Stok Global
     * (Pengecekan berbasis model StokLog telah dihapus)
     */
    public function validasi_batal_dari_selesai(int $id_return): void
    {
        DB::transaction(function () use ($id_return) {
            $return = ReturnPenjualan::with('details_return')->findOrFail($id_return);

            foreach ($return->details_return as $detail) {
                $barang = Barang::lockForUpdate()->find($detail->id_barang);

                if (!$barang) {
                    throw ValidationException::withMessages([
                        'stok' => "Stok {$detail->nama_barang} tidak ditemukan"
                    ]);
                }

                $stokSebelum = (float) $barang->stok_buku_besar;
                $stokSesudah = $stokSebelum - (float) $detail->qty;

                if ($stokSesudah < 0) {
                    throw ValidationException::withMessages([
                        'stok' => "Gagal batal retur: Stok {$detail->nama_barang} akan menjadi negatif"
                    ]);
                }

                // Tarik kembali barang dari stok global utama
                $barang->update([
                    'stok_buku_besar' => $stokSesudah,
                ]);

                Notification::make()
                    ->title("Stok {$detail->nama_barang} berkurang (Batal Retur)")
                    ->body("Total stok: $stokSesudah")
                    ->success()
                    ->send();
            }

            Notification::make()
                ->title('Return dibatalkan')
                ->success()
                ->send();
        });
    }

    /**
     * Query Menampilkan Barang Berdasarkan Stok Utama Global (Tanpa Filter Toko)
     */
    public static function queryBarangByToko(int $tokoId, int $penjualanId): Builder
    {
        // Parameter $tokoId dipertahankan agar tidak merusak kode frontend, tetapi filternya diubah ke stok global
        return Barang::query()
            ->where('stok_buku_besar', '>', 0)
            ->whereDoesntHave('penjualanDetails', function ($q) use ($penjualanId) {
                $q->where('penjualan_id', $penjualanId);
            });
    }

    public static function calculate_subtotal(
        float|int|null $hargaJual,
        int|null $qty,
        float|int|null $potongan = 0
    ): float {
        $subtotal = ((float) $hargaJual * (int) $qty) - (float) $potongan;
        return max($subtotal, 0);
    }

    public static function validateSubtotal(
        float|int|null $hargaJual,
        int|null $qty,
        float|int|null $potongan = 0
    ): void {
        if (self::calculate_subtotal($hargaJual, $qty, $potongan) <= 0) {
            throw ValidationException::withMessages([
                'subtotal' => 'Subtotal harus lebih dari 0.',
            ]);
        }
    }
}
