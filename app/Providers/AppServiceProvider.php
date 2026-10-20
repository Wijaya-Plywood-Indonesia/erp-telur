<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tandai login baru agar RedirectDashboardAnalisis mengalihkan
        // role dashboard_analisis ke Analisis Telur satu kali saja.
        Event::listen(Login::class, function () {
            if (app()->bound('session')) {
                session()->put('landing_analisis', true);
            }
        });
    }
}