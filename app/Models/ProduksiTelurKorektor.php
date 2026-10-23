<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiTelurKorektor extends Model
{
    protected $table = 'produksi_telur_korektors';

    protected $fillable = [
        'id_produksi_telur',
        'korektor_peti',
        'korektor_kiloan',
        'korektor_sisa',
        'korektor_bentes',
        'korektor_catatan',
        'created_by',
        'updated_by',
        'is_locked',
        'saved_by',      // ✅ Tambahkan ini
        'saved_at',      // ✅ Tambahkan ini
        'is_diperbaiki',
    ];

    protected $casts = [
        'korektor_peti'   => 'integer',
        'korektor_kiloan' => 'float',
        'korektor_sisa'   => 'float',
        'korektor_bentes' => 'float',
        'is_locked'       => 'boolean',
        'saved_at' => 'datetime',       // ✅ Agar otomatis jadi objek Carbon
        'is_diperbaiki' => 'boolean',
    ];

    public function produksiTelur()
    {
        return $this->belongsTo(ProduksiTelur::class, 'id_produksi_telur');
    }

    public function pencatat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pengubah()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function savedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'saved_by');
    }
}
