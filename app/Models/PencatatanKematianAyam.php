<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PencatatanKematianAyam extends Model
{
    protected $fillable = [
        'id_ayam',
        'tanggal',
        'jumlah_mati',
        'jumlah_afkir',
        'penyebab',
        'catatan',
        'is_validated',
        'validated_by',
        'validated_at',
        'created_by',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'validated_at' => 'datetime',
        'jumlah_mati'  => 'integer',
        'jumlah_afkir' => 'integer',
        'is_validated' => 'boolean',
    ];

    public function ayam()
    {
        return $this->belongsTo(Ayam::class, 'id_ayam');
    }

    /**
     * Relasi ke User Pembuat Record
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke User Validator
     */
    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }


    /**
     * Total Keseluruhan Ayam Berkurang (Mati + Afkir)
     */
    public function getTotalBerkurangAttribute(): int
    {
        return ($this->jumlah_mati ?? 0) + ($this->jumlah_afkir ?? 0);
    }
}
