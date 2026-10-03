<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanMutasiTelur extends Model
{
    protected $table = 'pengajuan_mutasi_telurs';

    public const MENUNGGU    = 'menunggu_validasi';
    public const TERVALIDASI = 'tervalidasi';
    public const DITOLAK     = 'ditolak';
    public const DIBATALKAN  = 'dibatalkan';

    protected $fillable = [
        'no_dokumen',
        'tanggal',
        'tujuan',
        'items',
        'keterangan',
        'status',
        'created_by',
        'validated_by',
        'validated_at',
        'alasan_tolak',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'items'        => 'array',
        'validated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}