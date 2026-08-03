<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;

class ProduksiTelur extends Model
{
    protected $fillable = [
        'id_kandang',
        'tanggal',
        'jumlah_telur_butir',
        'jumlah_telur_retak',
        'jumlah_telur_pecah',
        'hen_day_production',
        'created_by',
        'is_validated',
        'validated_by',
        'validated_at',
        'keterangan',
        'is_locked',

        // ===========
        'korektor_peti',
        'korektor_kiloan',
        'korektor_sisa',
        'korektor_bentes',
        'korektor_catatan',
    ];

    protected $casts = [
        'tanggal'       => 'date',
        'validated_at'  => 'datetime',
        'korektor_peti'    => 'integer',
        'korektor_kiloan'  => 'decimal:2',
        'korektor_sisa'    => 'decimal:2',
        'korektor_bentes'  => 'decimal:2',
        'is_locked'    => 'boolean',
    ];

    private const KG_PER_PETI = 10.0;

    public function kandang()
    {
        return $this->belongsTo(Kandang::class, 'id_kandang');
    }

    public function detailProduksi()
    {
        return $this->hasMany(DetailProduksiTelur::class, 'id_produksi_telur');
    }


    /**
     * Telur layak jual = butir - retak - afkir
     */
    public function getTelurBaikAttribute(): int
    {
        return max(0, $this->jumlah_telur_butir
            - $this->jumlah_telur_retak
            - $this->jumlah_telur_pecah);
    }


    /**
     * Hen Day Production = (telur hari ini / jumlah ayam saat ini) * 100
     */
    public function hitungHDP(int $jumlahTelur): float
    {
        if ($this->jumlah_saat_ini <= 0) return 0;
        return round(($jumlahTelur / $this->jumlah_saat_ini) * 100, 2);
    }

    public function getHdpBadgeColorAttribute(): string
    {
        return match (true) {
            $this->hen_day_production > 80  => 'success',
            $this->hen_day_production >= 70 => 'warning',
            $this->hen_day_production >= 60 => 'info',
            default                         => 'danger',
        };
    }

    public function getKorektorTotalPetiKgAttribute(): float
    {
        return round(($this->korektor_peti ?? 0) * self::KG_PER_PETI, 2);
    }

    public function getKorektorTotalKgAttribute(): float
    {
        return round(
            $this->korektor_total_peti_kg
                + ($this->korektor_kiloan ?? 0)
                + ($this->korektor_sisa ?? 0)
                + ($this->korektor_bentes ?? 0),
            2
        );
    }

    /** Total Kg dari SEMUA kandang di tanggal yang sama (via DetailProduksiTelur) */
    public function getTotalDrKdKgAttribute(): float
    {
        return round(
            (float) DetailProduksiTelur::whereHas('produksiTelur', function ($q) {
                $q->whereDate('tanggal', $this->tanggal);
            })->sum('jumlah_telur_kilo'),
            2
        );
    }

    public function getSelisihKgAttribute(): float
    {
        return round($this->korektor_total_kg - $this->total_dr_kd_kg, 2);
    }

    public function getStatusKorektorBadgeAttribute(): array
    {
        $abs = abs($this->selisih_kg);

        return match (true) {
            $abs == 0.0 => [
                'status' => 'MATCH',
                'label' => 'Sesuai (Presisi)',
                'color' => 'success',
                'message' => 'Tidak ada perbedaan penimbangan.',
            ],
            $abs <= 2.0 => [
                'status' => 'TOLERANCE',
                'label' => 'Selisih Wajar',
                'color' => 'warning',
                'message' => "Selisih {$this->selisih_kg} Kg dalam batas toleransi (< 2.0 Kg).",
            ],
            default => [
                'status' => 'MISMATCH',
                'label' => 'Selisih Tinggi',
                'color' => 'danger',
                'message' => "Perlu pemeriksaan ulang! Selisih {$this->selisih_kg} Kg melebihi batas.",
            ],
        };
    }

    public function isBarisKorektorAktif(): bool
    {
        return !is_null($this->korektor_peti)
            || !is_null($this->korektor_kiloan)
            || !is_null($this->korektor_sisa)
            || !is_null($this->korektor_bentes);
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->isBarisKorektorAktif()) {
                $duplikat = static::whereDate('tanggal', $model->tanggal)
                    ->where('id', '!=', $model->id ?? 0)
                    ->whereNotNull('korektor_peti')
                    ->exists();

                if ($duplikat) {
                    throw new Exception('Data korektor untuk tanggal ini sudah diisi di baris lain.');
                }
            }
        });
    }

    public function korektor()
    {
        return $this->hasOne(ProduksiTelurKorektor::class, 'id_produksi_telur');
    }
}
