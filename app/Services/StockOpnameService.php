<?php

namespace App\Services;

use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use Illuminate\Support\Facades\DB;
use Exception;

class StockOpnameService
{
    /* =========================
     |  SUBMIT KE APPROVAL
     ========================= */

    /**
     * Ubah status dari draft → menunggu.
     *
     * Aturan stok aktual kosong:
     * Jika petugas tidak mengisi stok_aktual pada suatu barang,
     * dianggap stok aktual = stok sistem (tidak ada selisih).
     * Ini mendorong petugas hanya mengisi barang yang memang ada perbedaan.
     */
    public function submitUntukApproval(StockOpname $opname, int $userId): void
    {
        if (!$opname->isDraft()) {
            throw new Exception('Hanya opname berstatus draft yang bisa disubmit.');
        }

        DB::transaction(function () use ($opname) {
            foreach ($opname->details as $detail) {

                // Jika tidak diisi → anggap sama dengan stok sistem (selisih = 0)
                $stokAktual = $detail->stok_aktual !== null
                    ? (float) $detail->stok_aktual
                    : (float) $detail->stok_sistem;

                $detail->update([
                    'stok_aktual' => $stokAktual,
                    'selisih'     => $stokAktual - (float) $detail->stok_sistem,
                ]);
            }

            $opname->update(['status' => 'menunggu']);
        });
    }

    /* =========================
     |  APPROVE
     ========================= */

    /**
     * Approve opname: ubah status → disetujui.
     *
     * Penyesuaian stok ditangani sepenuhnya melalui Jurnal Pembantu
     * yang dibuat langsung di StockOpnamePage::approve().
     * Service ini hanya bertanggung jawab mengubah status dokumen opname.
     *
     * Kolom toko_id sudah dihapus dari stock_opnames (opname sekarang
     * bersifat global, tidak per toko), sehingga StokBarangToko dan
     * StokLog tidak lagi digunakan di sini.
     */
    public function approve(StockOpname $opname, int $approverId, ?string $catatanApproval = null): void
    {
        if (!$opname->isMenunggu()) {
            throw new Exception('Hanya opname berstatus menunggu yang bisa disetujui.');
        }

        $opname->update([
            'status'           => 'disetujui',
            'approved_by'      => $approverId,
            'approved_at'      => now(),
            'catatan_approval' => $catatanApproval,
        ]);
    }

    /* =========================
     |  TOLAK
     ========================= */

    public function tolak(StockOpname $opname, int $approverId, ?string $catatanApproval = null): void
    {
        if (!$opname->isMenunggu()) {
            throw new Exception('Hanya opname berstatus menunggu yang bisa ditolak.');
        }

        $opname->update([
            'status'           => 'ditolak',
            'approved_by'      => $approverId,
            'approved_at'      => now(),
            'catatan_approval' => $catatanApproval,
        ]);
    }
}
