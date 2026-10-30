<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use App\Models\Satpam;
use App\Models\SatpamSetoran;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

/**
 * Menu "Rekap Setoran" untuk direksi (hanya baca).
 * Wijaya & Wahana ditampilkan berdampingan; tiap setoran punya rekap per satpam
 * dan dropdown daftar nota selama satu siklus.
 */
class RekapSetoran extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';
    protected static ?string $navigationLabel = 'Rekap Setoran';
    protected static ?string $title = 'Rekap Setoran';
    protected static ?string $slug = 'rekap-setoran';
    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.rekap-setoran';

    public ?string $dari = null;     // filter tanggal tutup buku
    public ?string $sampai = null;
    public const PER_HALAMAN = 5;    // jumlah setoran per halaman, per pabrik
    public array $halaman = ['wijaya' => 1, 'wahana' => 1];
    public array $terbuka = [];      // kunci dropdown yang sedang dibuka

    public const PABRIK = ['wijaya' => 'Wijaya', 'wahana' => 'Wahana'];

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // Batasi akses di sini kalau perlu, contoh:
    // public static function canAccess(): bool
    // {
    //     return auth()->user()?->hasAnyRole(['super_admin', 'direksi']) ?? false;
    // }

    public function toggle(string $key): void
    {
        $this->terbuka[$key] = ! ($this->terbuka[$key] ?? false);
    }

    public function updatedDari(): void { $this->resetHalaman(); }
    public function updatedSampai(): void { $this->resetHalaman(); }

    private function resetHalaman(): void
    {
        $this->halaman = ['wijaya' => 1, 'wahana' => 1];
        $this->terbuka = [];
    }

    public function halamanSebelumnya(string $pabrik): void
    {
        $this->halaman[$pabrik] = max(1, ($this->halaman[$pabrik] ?? 1) - 1);
    }

    public function halamanBerikutnya(string $pabrik): void
    {
        $this->halaman[$pabrik] = ($this->halaman[$pabrik] ?? 1) + 1;
    }

    /** Data untuk view: ['wijaya' => [...], 'wahana' => [...], 'namaSatpam' => ...] */
    public function data(): array
    {
        $namaSatpam = Satpam::pluck('nama', 'id');
        $out = ['namaSatpam' => $namaSatpam, 'pabrik' => []];

        foreach (array_keys(self::PABRIK) as $p) {
            $out['pabrik'][$p] = $this->riwayat($p, $namaSatpam);
        }

        return $out;
    }

    private function riwayat(string $pabrik, $namaSatpam): array
    {
        $base = SatpamSetoran::query()
            ->where('status', 'SELESAI')
            ->where('pabrik', $pabrik)
            ->when($this->dari, fn ($q, $d) => $q->whereDate('ditutup_pada', '>=', $d))
            ->when($this->sampai, fn ($q, $d) => $q->whereDate('ditutup_pada', '<=', $d));

        $jumlah = (clone $base)->count();
        $totalHalaman = max(1, (int) ceil($jumlah / self::PER_HALAMAN));
        $hal = min(max(1, $this->halaman[$pabrik] ?? 1), $totalHalaman);
        $this->halaman[$pabrik] = $hal;

        $setorans = $base
            ->orderByDesc('ditutup_pada')
            ->offset(($hal - 1) * self::PER_HALAMAN)
            ->limit(self::PER_HALAMAN)
            ->get();

        $rekapPerSetoran = Penjualan::query()
            ->whereIn('satpam_setoran_id', $setorans->pluck('id'))
            ->selectRaw('satpam_setoran_id, satpam_id, COUNT(*) as nota, SUM(total) as total, MIN(tanggal) as awal, MAX(tanggal) as akhir')
            ->groupBy('satpam_setoran_id', 'satpam_id')
            ->get()
            ->groupBy('satpam_setoran_id');

        $items = $setorans->map(function ($s) use ($rekapPerSetoran, $namaSatpam) {
            $rows = $rekapPerSetoran->get($s->id, collect());

            return [
                'kunci'        => 's:' . $s->id,
                'total'        => (float) $s->total,
                'nota'         => (int) $s->jumlah_nota,
                'awal'         => $rows->min('awal'),
                'akhir'        => $rows->max('akhir'),
                'ditutup_pada' => $s->ditutup_pada,
                'rekap'        => $rows->sortByDesc('total')->map(fn ($r) => [
                    'nama'  => $r->satpam_id ? ($namaSatpam[$r->satpam_id] ?? 'Satpam #' . $r->satpam_id) : 'Tanpa satpam',
                    'nota'  => (int) $r->nota,
                    'total' => (float) $r->total,
                ])->values()->all(),
            ];
        })->all();

        return ['items' => $items, 'halaman' => $hal, 'total_halaman' => $totalHalaman, 'jumlah' => $jumlah];
    }

    /** Daftar nota untuk satu dropdown. Hanya dipanggil kalau dropdown dibuka. */
    public function notaUntuk(string $kunci, $namaSatpam): array
    {
        return Penjualan::where('satpam_setoran_id', (int) substr($kunci, 2))
            ->orderBy('tanggal')
            ->get(['id', 'no_nota', 'tanggal', 'nama_customer', 'satpam_id', 'total'])
            ->map(fn ($n) => [
                'no_nota'  => $n->no_nota,
                'tanggal'  => $n->tanggal,
                'customer' => $n->nama_customer,
                'satpam'   => $namaSatpam[$n->satpam_id ?? 0] ?? '-',
                'total'    => (float) $n->total,
            ])->all();
    }
}