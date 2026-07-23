<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Ayam extends Model
{
    protected $fillable = [
        'id_kandang',
        'id_sub_anak_akun',
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

    public function subAnakAkun()
    {
        return $this->belongsTo(SubAnakAkun::class, 'id_sub_anak_akun');
    }

    public function kematian()
    {
        return $this->hasMany(PencatatanKematianAyam::class, 'id_ayam');
    }

    public function logKematians()
    {
        return $this->hasMany(LogKematianAyam::class, 'id_ayam');
    }


    public function getJumlahSaatIniAttribute(): int
    {
        $subAkun = $this->subAnakAkun;
        $kodeAkun = $subAkun?->kode_sub_anak_akun;

        // Jika belum ada CoA yang terhubung, gunakan kalkulasi lokal
        if (!$kodeAkun) {
            return $this->jumlah_awal - $this->total_berkurang;
        }

        // Kueri akumulasi Debit - Kredit dari General Ledger (Jurnal Umum)
        $transaksis = JurnalUmum::where('no_akun', $kodeAkun)
            ->select('map', DB::raw('SUM(COALESCE(banyak, 0)) as total_banyak'))
            ->groupBy('map')
            ->get();

        // Fallback jika belum pernah ada posting transaksi sama sekali di Jurnal Umum
        if ($transaksis->isEmpty()) {
            return $this->jumlah_awal - $this->total_berkurang;
        }

        $totalQty = 0;
        foreach ($transaksis as $trx) {
            $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
            $qty = (int) $trx->total_banyak;
            if ($isDebit) {
                $totalQty += $qty;
            } else {
                $totalQty -= $qty;
            }
        }

        return $totalQty;
    }
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

    // Kebutuhan Mutasi dan Penamaan Kandang
    public function getNamaBatchAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        // Fallback otomatis ke nama SubAnakAkun jika nama_batch kosong
        return $this->subAnakAkun?->nama_sub_anak_akun ?? 'Batch Ayam';
    }

    /**
     * Total Ayam Mati (Hanya yang Divalidasi)
     */
    public function getTotalMatiAttribute(): int
    {
        return (int) $this->kematian()
            ->where('is_validated', true)
            ->sum('jumlah_mati');
    }
}
