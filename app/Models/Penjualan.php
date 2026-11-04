<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    //
    protected $fillable = [
        'no_nota',
        'tanggal',
        'tanggal_pembukuan',
        'nama_customer',
        'is_member',
        'alamat',
        'metode_pembayaran',
        'bank',
        'no_rekening',
        'kendaraan',
        'nama_sopir',
        'total',
        'bayar',
        'kembalian',
        'user_id',
        'validated_by',
        'plat_kendaraan',
        'status_transaksi',
        'keterangan',
        'keterangan_pembayaran',
        'toko_id',
        'bayar_tunai',
        'bayar_transfer',
        'satpam_id',
        'satpam_setoran_id',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'tanggal_pembukuan' => 'datetime',
        'total' => 'decimal:2',
        'bayar' => 'decimal:2',
        'bayar_tunai' => 'decimal:2',
        'bayar_transfer' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'is_member' => 'boolean',
        'user_id' => 'integer',
        'validated_by' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPE: tanggal pembukuan & status setor (nota dari app satpam)
    |--------------------------------------------------------------------------
    | Nota satpam baru "masuk pembukuan" saat setorannya ditutup. Sampai saat itu
    | tanggal_pembukuan kosong. Nota POS web tidak punya satpam, jadi selalu dihitung
    | dan tanggalnya tidak berubah.
    */

    /** Nota satpam yang belum disetor tidak ikut; nota non-satpam selalu ikut. */
    public function scopeSudahDisetor($query)
    {
        return $query->where(function ($w) {
            $w->whereNull('penjualans.satpam_id')
                ->orWhereNotNull('penjualans.satpam_setoran_id');
        });
    }

    /** Rentang berdasarkan tanggal pembukuan; kalau kosong memakai created_at (perilaku lama). */
    public function scopeTanggalPembukuanAntara($query, string $dari, string $sampai)
    {
        return $query->whereRaw(
            'COALESCE(penjualans.tanggal_pembukuan, penjualans.created_at) BETWEEN ? AND ?',
            [$dari, $sampai]
        );
    }

    /** Tanggal yang dipakai laporan: pembukuan kalau ada, kalau tidak tanggal nota. */
    public function getTanggalLaporanAttribute()
    {
        return $this->tanggal_pembukuan ?? $this->tanggal;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function details()
    {
        return $this->hasMany(DetailPenjualan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function user_return()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function satpam()
    {
        return $this->belongsTo(Satpam::class, 'satpam_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
    public function rekeningPerusahaan()
    {
        return $this->belongsTo(
            RekeningPerusahaan::class,
            'no_rekening',   // kolom di penjualans
            'no_rekening'    // kolom di rekening_perusahaan
        );
    }
    public function toko()
    {
        return $this->belongsTo(IdentitasToko::class, 'toko_id');
    }

    // Tambahkan di dalam class Penjualan
    public function returns()
    {
        // Sesuaikan 'penjualan_id' dengan foreign key di tabel penjualan_return Anda
        return $this->hasMany(ReturnPenjualan::class, 'no_nota', 'no_nota');
    }

    // Di dalam class ReturnPenjualan
}