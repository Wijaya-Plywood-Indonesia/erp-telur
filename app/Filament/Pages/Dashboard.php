<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PenjualanTerakhirWidget;
use App\Filament\Widgets\PosButton;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    /**
     * User dengan role dashboard_analisis langsung diarahkan ke Analisis Telur.
     * User lain tetap melihat dashboard biasa.
     */
    public function mount(): void
    {
        if (Auth::user()?->hasRole(AnalisisTelur::ROLE)) {
            $this->redirect(AnalisisTelur::getUrl(), navigate: true);
        }
    }

    public function getWidgets(): array
    {

        return [
            PosButton::class,
            PenjualanTerakhirWidget::class,
        ];
    }
}