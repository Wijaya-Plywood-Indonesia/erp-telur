<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AnalisisTelur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User ber-role dashboard_analisis yang membuka halaman dashboard utama panel
 * (/admin, termasuk setelah login) langsung dialihkan ke Analisis Telur.
 * User lain tidak terpengaruh.
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
            && ($request->is('admin') || $request->routeIs('filament.admin.pages.dashboard'))
            && $user->hasRole(AnalisisTelur::ROLE)
        ) {
            return redirect()->to(AnalisisTelur::getUrl());
        }

        return $next($request);
    }
}