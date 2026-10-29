<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatpamSetoran extends Model
{
    protected $table = 'satpam_setorans';

    protected $fillable = [
        'pabrik',
        'status',
        'sampai_penjualan_id',
        'total',
        'jumlah_nota',
        'dibuka_oleh',
        'dibuka_pada',
        'ditutup_pada',
        'ditutup_satpam_id',
    ];

    protected $casts = [
        'total'        => 'decimal:2',
        'dibuka_pada'  => 'datetime',
        'ditutup_pada' => 'datetime',
    ];

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(Satpam::class, 'ditutup_satpam_id');
    }

    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    /** Rincian per satpam: nota yang sudah masuk setoran ini, atau (kalau masih DIBUKA) yang akan ikut. */
    public function rincianTeks(): string
    {
        if ($this->status === 'SELESAI') {
            return Satpam::rincianTeks(Penjualan::query()->where('satpam_setoran_id', $this->id));
        }

        if ($this->status === 'DIBUKA') {
            return Satpam::rincianTeks(
                Satpam::penjualanTerbukaPabrik((string) $this->pabrik)
                    ->where('id', '<=', $this->sampai_penjualan_id)
            );
        }

        return '-';
    }
}