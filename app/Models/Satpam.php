<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Satpam extends Model
{
    protected $table = 'satpams';

    protected $fillable = [
        'pabrik',
        'nama',
        'kode_akses',
        'aktif',
        'maks_perangkat',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function perangkats(): HasMany
    {
        return $this->hasMany(SatpamPerangkat::class, 'satpam_id');
    }

    /** maks_perangkat = 0 berarti tanpa batas. */
    public function bolehTambahPerangkat(): bool
    {
        return (int) $this->maks_perangkat === 0
            || $this->perangkats()->count() < (int) $this->maks_perangkat;
    }

    public function penjualans(): HasMany
    {
        return $this->hasMany(Penjualan::class, 'satpam_id');
    }

    /** Riwayat setoran milik pabrik satpam ini (dipakai bersama semua satpam di pabrik yang sama). */
    public function setorans(): HasMany
    {
        return $this->hasMany(SatpamSetoran::class, 'pabrik', 'pabrik');
    }

    // ------------------------------------------------------------------
    // Nota yang belum disetor
    // ------------------------------------------------------------------

    private static function bukanBatal(Builder $q): void
    {
        $q->where(function (Builder $w) {
            $w->whereNull('status_transaksi')->orWhere('status_transaksi', '!=', 'DIBATALKAN');
        });
    }

    /** Nota SEMUA satpam di pabrik ini yang belum disetor. Dasar angka setoran. */
    public static function penjualanTerbukaPabrik(string $pabrik): Builder
    {
        $q = Penjualan::query()
            ->whereIn('satpam_id', static::query()->where('pabrik', $pabrik)->select('id'))
            ->whereNull('satpam_setoran_id');
        self::bukanBatal($q);

        return $q;
    }

    public function penjualanTerbukaPabrikIni(): Builder
    {
        return static::penjualanTerbukaPabrik($this->pabrik);
    }

    /** Nota satpam ini saja yang belum disetor. */
    public function penjualanTerbuka(): Builder
    {
        $q = Penjualan::query()
            ->where('satpam_id', $this->id)
            ->whereNull('satpam_setoran_id');
        self::bukanBatal($q);

        return $q;
    }

    public function totalPabrik(): float
    {
        return round((float) $this->penjualanTerbukaPabrikIni()->sum('total'), 2);
    }

    public function jumlahNotaPabrik(): int
    {
        return (int) $this->penjualanTerbukaPabrikIni()->count();
    }

    public function totalBerjalan(): float
    {
        return round((float) $this->penjualanTerbuka()->sum('total'), 2);
    }

    public function jumlahNotaBerjalan(): int
    {
        return (int) $this->penjualanTerbuka()->count();
    }

    // ------------------------------------------------------------------
    // Rincian per satpam
    // ------------------------------------------------------------------

    /** "Toni Rp 610.000 (8 nota) • Iswandi Rp 400.000 (2 nota)" dari kumpulan nota. */
    public static function rincianTeks(Builder $penjualan): string
    {
        $rows = $penjualan
            ->selectRaw('satpam_id, SUM(total) as jumlah, COUNT(*) as nota')
            ->groupBy('satpam_id')
            ->get();

        if ($rows->isEmpty()) {
            return '-';
        }

        $nama = static::query()->whereIn('id', $rows->pluck('satpam_id'))->pluck('nama', 'id');

        return $rows
            ->map(fn ($r) => ($nama[$r->satpam_id] ?? 'Tanpa satpam')
                . ' Rp ' . number_format((float) $r->jumlah, 0, ',', '.') . ' (' . $r->nota . ' nota)')
            ->implode(' • ');
    }

    public function rincianBerjalanTeks(): string
    {
        return static::rincianTeks($this->penjualanTerbukaPabrikIni());
    }

    /** Porsi satpam ini pada setoran terakhir yang sudah ditutup di pabriknya. */
    public function setoranTerakhirTeks(): string
    {
        $last = SatpamSetoran::where('pabrik', $this->pabrik)
            ->where('status', 'SELESAI')
            ->latest('id')
            ->first();

        if (! $last) {
            return '-';
        }

        $q = Penjualan::where('satpam_setoran_id', $last->id)->where('satpam_id', $this->id);
        $nota = (int) $q->count();

        if ($nota === 0) {
            return '-';
        }

        return 'Rp ' . number_format((float) $q->sum('total'), 0, ',', '.') . ' (' . $nota . ' nota) • '
            . $last->ditutup_pada?->format('d/m H:i');
    }

    // ------------------------------------------------------------------
    // Buka kunci tutup buku (dipanggil orang kantor)
    // ------------------------------------------------------------------

    /** Pembukaan kunci yang masih menunggu ditekan di HP, atau null. Berlaku untuk seluruh pabrik. */
    public function setoranDibuka(): ?SatpamSetoran
    {
        return SatpamSetoran::where('pabrik', $this->pabrik)
            ->where('status', 'DIBUKA')
            ->latest('id')
            ->first();
    }

    /**
     * Mengunci angka setoran seluruh pabrik sampai nota terakhir saat ini, jadi penjualan
     * yang terjadi sesudahnya tidak ikut ditutup.
     */
    public function bukaTutupBuku(?int $userId): ?SatpamSetoran
    {
        if ($ada = $this->setoranDibuka()) {
            return $ada;
        }

        $q = $this->penjualanTerbukaPabrikIni();
        $maxId = (clone $q)->max('id');

        if (! $maxId) {
            return null; // belum ada penjualan yang bisa disetor
        }

        return SatpamSetoran::create([
            'pabrik'              => $this->pabrik,
            'status'              => 'DIBUKA',
            'sampai_penjualan_id' => $maxId,
            'total'               => (clone $q)->where('id', '<=', $maxId)->sum('total'),
            'jumlah_nota'         => (clone $q)->where('id', '<=', $maxId)->count(),
            'dibuka_oleh'         => $userId,
            'dibuka_pada'         => now(),
        ]);
    }
}