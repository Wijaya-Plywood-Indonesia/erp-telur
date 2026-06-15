<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ayam extends Model
{
    protected $fillable = [
        'id_kandang',
        'nama_batch',
        'tanggal_masuk',
        'jumlah_awal',
        'usia'
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
    ];

    public function kandang()
    {
        return $this->belongsTo(Kandang::class, 'id_kandang');
    }

    public function logKematians()
    {
        return $this->hasMany(LogKematianAyam::class, 'id_ayam');
    }

    // public function produksiTelurs()
    // {
    //     return $this->hasMany(ProduksiTelur::class, 'id_ayam');
    // }

    // Umur dalam hari
    public function getUmurHariAttribute(): int
    {
        $selisihHari = (int) $this->tanggal_masuk->startOfDay()->diffInDays(now()->startOfDay());
        return $this->usia + $selisihHari;
    }
    // Umur format tampilan
    public function getUmurFormatAttribute(): string
    {
        $hari = $this->umur_hari;

        if ($hari < 7) {
            return "0 minggu"; // atau "belum 1 minggu"
        }

        $minggu = intdiv($hari, 7);

        return "{$minggu} minggu";
    }

    // Konversi minggu → hari untuk disimpan
    public static function mingguKeHari(float $minggu): int
    {
        return (int) round($minggu * 7);
    }

    // Konversi hari → minggu untuk ditampilkan di form
    public function getUsiaMingguAttribute(): float
    {
        return round($this->usia / 7, 2);
    }
}
