<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use App\Services\AnalisisTelurService;
use App\Services\HargaPatokService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Page;
use UnitEnum;

class AnalisisTelur extends Page
{
    /** Hanya user dengan role ini yang boleh membuka halaman (menu & URL). */
    public const ROLE = 'dashboard_analisis';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(self::ROLE) ?? false;
    }

    protected static string|UnitEnum|null $navigationGroup = 'Analisis';
    protected static ?string $title           = 'Analisis Telur';
    protected static ?string $navigationLabel = 'Analisis Telur';
    protected static ?int $navigationSort     = 1;
    protected string $view                    = 'filament.pages.analisis-telur';

    // ── Filter ──
    public string $preset       = '30h';
    public string $granularitas = 'harian';
    public string $dari         = '';
    public string $sampai       = '';

    // ── Harga patok vs asli ──
    public string $hargaTab     = 'pakan'; // pakan | telur

    public function mount(): void
    {
        $this->terapkanPreset('30h');
    }

    public function terapkanPreset(string $preset): void
    {
        $today = Carbon::today();

        switch ($preset) {
            case '7h':
                $dari = $today->copy()->subDays(6);
                $gran = 'harian';
                break;
            case '90h':
                $dari = $today->copy()->subDays(89);
                $gran = 'harian';
                break;
            case '12b':
                $dari = $today->copy()->subYear()->addDay();
                $gran = 'bulanan';
                break;
            case 'semua':
                $awal = Penjualan::min('tanggal');
                $dari = $awal ? Carbon::parse($awal)->startOfDay() : $today->copy()->subDays(29);
                $gran = 'bulanan';
                break;
            default:
                $preset = '30h';
                $dari   = $today->copy()->subDays(29);
                $gran   = 'harian';
        }

        $this->preset       = $preset;
        $this->dari         = $dari->toDateString();
        $this->sampai       = $today->toDateString();
        $this->granularitas = $gran;
    }

    public function setGranularitas(string $gran): void
    {
        if (in_array($gran, ['harian', 'mingguan', 'bulanan'], true)) {
            $this->granularitas = $gran;
        }
    }

    public function setHargaTab(string $tab): void
    {
        $this->hargaTab = in_array($tab, ['pakan', 'telur'], true) ? $tab : 'pakan';
    }

    /** Harga beli & laba bersifat sensitif: hanya admin & pemegang role dashboard_analisis. */
    protected function bolehLihatHarga(): bool
    {
        return Auth::user()?->hasAnyRole(['admin', 'super_admin', self::ROLE]) ?? false;
    }

    public function updatedDari(): void
    {
        $this->preset = 'kustom';
    }

    public function updatedSampai(): void
    {
        $this->preset = 'kustom';
    }

    protected function getViewData(): array
    {
        // Tanggal tidak valid / kosong -> kembali ke default 30 hari
        try {
            Carbon::parse($this->dari);
            Carbon::parse($this->sampai);
        } catch (\Throwable $e) {
            $this->terapkanPreset('30h');
        }

        if ($this->dari === '' || $this->sampai === '') {
            $this->terapkanPreset('30h');
        }

        $boleh = $this->bolehLihatHarga();

        return [
            // laba bersih (dari jurnal) hanya dihitung untuk yang berhak melihat
            'd' => app(AnalisisTelurService::class)->build($this->granularitas, $this->dari, $this->sampai, $boleh),
            'hargaData' => $boleh
                ? app(HargaPatokService::class)->build($this->dari, $this->sampai)
                : null,
            // tautan ke menu Laba Rugi Telur (hanya bila user boleh membukanya)
            'urlLabaRugi' => LabaRugiTelur::canAccess() ? LabaRugiTelur::getUrl() : null,
        ];
    }
}