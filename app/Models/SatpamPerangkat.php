<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatpamPerangkat extends Model
{
    protected $table = 'satpam_perangkats';

    protected $fillable = [
        'satpam_id',
        'token_hash',
        'aktivasi_pada',
        'terakhir_dipakai',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'aktivasi_pada'    => 'datetime',
        'terakhir_dipakai' => 'datetime',
    ];

    public function satpam(): BelongsTo
    {
        return $this->belongsTo(Satpam::class, 'satpam_id');
    }
}