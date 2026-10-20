<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AnalisisTelur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User ber-role dashboard_analisis diarahkan ke Analisis Telur HANYA sekali,
 * yaitu tepat setelah login (flag session 'landing_analisis' diset oleh
 * listener Login di AppServiceProvider).
 *
 * Setelah itu menu "Dashboard" di sidebar bisa dibuka seperti biasa.
 */
class RedirectDashboardAnalisis
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $request->isMethod('GET')
            && ! $request->header('X-Livewire')
        ) {
            // flag sekali pakai: dikonsumsi pada request halaman pertama setelah login
            $landing = $request->session()->pull('landing_analisis', false);

            if (
                $landing
                && ($request->is('admin') || $request->routeIs('filament.admin.pages.dashboard'))
                && $user->hasRole(AnalisisTelur::ROLE)
            ) {
                return redirect()->to(AnalisisTelur::getUrl());
            }
        }

        return $next($request);
    }
}