<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PenjualanTerakhirWidget;
use App\Filament\Widgets\PosButton;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    // Redirect otomatis ke Analisis Telur untuk role dashboard_analisis
    // sekarang hanya terjadi sekali setelah login (lihat RedirectDashboardAnalisis),
    // sehingga menu Dashboard tetap bisa dibuka.

    public function getWidgets(): array
    {
        return [
            PosButton::class,
            PenjualanTerakhirWidget::class,
        ];
    }
}