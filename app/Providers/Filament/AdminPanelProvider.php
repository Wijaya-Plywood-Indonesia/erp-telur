<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectDashboardAnalisis;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ============================================================
        // IDENTITAS
        // ============================================================
        $namaBrand = 'Telur';
        $subjudul  = 'Pakan · Telur · Akuntansi';

        $firstExisting = function (array $candidates) {
            foreach ($candidates as $file) {
                if (file_exists(public_path($file))) {
                    return $file;
                }
            }
            return null;
        };

        // Logo: kalau ada public/images/logo-telur.png dipakai, kalau tidak pakai ikon telur + teks
        $logoFile = $firstExisting(['images/logo-telur.png', 'images/logo-telur.webp']);

        // Background foto kandang
        $bgFile = $firstExisting([
            'images/telur-bg.webp',
            'images/telur-bg.jpg',
            'images/telur-bg.jpeg',
            'images/telur-bg.png',
            'images/login-bg.webp',
            'images/login-bg.jpg',
            'images/login-bg.png',
        ]);
        $bgUrl = $bgFile ? asset($bgFile) . '?v=' . filemtime(public_path($bgFile)) : null;

        $bgCss = 'background-color: #0f172a !important;'
            . ($bgUrl
                ? 'background-image:
                        linear-gradient(135deg, rgba(15,23,42,0.74) 0%, rgba(15,23,42,0.42) 100%),
                        url("' . $bgUrl . '") !important;'
                : '')
            . 'background-size: cover !important;
               background-position: center !important;
               background-repeat: no-repeat !important;
               background-attachment: fixed !important;';

        // ------------------------------------------------------------
        // CSS login (kaca gelap seperti Wijaya / Wahana)
        // ------------------------------------------------------------
        $css = <<<CSS
/* ===== BACKGROUND ===== */
.fi-simple-layout {
    {$bgCss}
    min-height: 100vh;
    min-height: 100dvh;
}

/* ===== CARD GLASS ===== */
.fi-simple-main {
    background: rgba(15, 23, 42, 0.50) !important;
    -webkit-backdrop-filter: blur(22px) saturate(160%);
    backdrop-filter: blur(22px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    border-radius: 1.75rem !important;
    box-shadow:
        0 30px 60px -15px rgba(0, 0, 0, 0.6),
        inset 0 1px 0 rgba(255, 255, 255, 0.20) !important;
    --tw-ring-shadow: 0 0 #0000 !important;
    --tw-ring-color: transparent !important;
    padding-top: 2.5rem !important;
    padding-bottom: 2.5rem !important;
}

/* ===== BRAND ===== */
.login-brand { display: flex; flex-direction: column; align-items: center; gap: .35rem; margin: 0 0 1rem 0; text-align: center; }
.login-brand svg { width: 3.4rem; height: 4rem; filter: drop-shadow(0 4px 12px rgba(0,0,0,.45)); }
.login-brand .nm { font-size: 1.7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #ffffff; line-height: 1; margin-top: .25rem; }
.login-brand .sb { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.7); }
.login-brand img { display: block; height: 5.5rem; width: auto; max-width: 85%; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,.45)); }
.fi-simple-main .fi-logo { display: none !important; }

/* Header */
.fi-simple-main .fi-simple-header {
    margin-top: 0 !important;
    padding-top: 0 !important;
    margin-bottom: 1rem !important;
}
.fi-simple-main .fi-simple-header-heading { margin-top: 0 !important; }

/* ===== TEKS ===== */
.fi-simple-main .fi-simple-header-heading,
.fi-simple-main h1,
.fi-simple-main label,
.fi-simple-main label span,
.fi-simple-main .fi-fo-field-wrp-label,
.fi-simple-main .fi-fo-field-wrp-label span {
    color: #ffffff !important;
}
.fi-simple-main .fi-simple-header-subheading,
.fi-simple-main .fi-simple-header-subheading span {
    color: rgba(255, 255, 255, 0.75) !important;
}
.fi-simple-main a { color: #fbbf24 !important; }
.fi-simple-main a:hover { color: #fde68a !important; }

/* ===== INPUT ===== */
.fi-simple-main .fi-input-wrp {
    background-color: rgba(255, 255, 255, 0.12) !important;
    border-radius: 0.75rem !important;
    --tw-ring-color: rgba(255, 255, 255, 0.25) !important;
    box-shadow: none !important;
    transition: all 0.2s ease;
}
.fi-simple-main .fi-input-wrp:focus-within {
    background-color: rgba(255, 255, 255, 0.20) !important;
    --tw-ring-color: #fbbf24 !important;
    box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.25) !important;
}
.fi-simple-main input.fi-input {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}
.fi-simple-main input.fi-input::placeholder { color: rgba(255, 255, 255, 0.5) !important; }
.fi-simple-main input:-webkit-autofill {
    -webkit-text-fill-color: #ffffff !important;
    transition: background-color 9999s ease-in-out 0s;
}
.fi-simple-main .fi-input-wrp button,
.fi-simple-main .fi-input-wrp svg { color: rgba(255, 255, 255, 0.8) !important; }

/* ===== CHECKBOX ===== */
.fi-simple-main input[type="checkbox"] {
    background-color: rgba(255, 255, 255, 0.15) !important;
    border-color: rgba(255, 255, 255, 0.4) !important;
}

/* ===== TOMBOL ===== */
.fi-simple-main .fi-btn,
.fi-simple-main button[type="submit"] {
    background-color: #fbbf24 !important;
    color: #1f2937 !important;
    border-radius: 0.75rem !important;
    font-weight: 700;
    box-shadow: 0 10px 25px -8px rgba(245, 158, 11, 0.6);
    transition: transform .15s ease, box-shadow .15s ease, background-color .2s ease;
}
.fi-simple-main .fi-btn:hover,
.fi-simple-main button[type="submit"]:hover {
    background-color: #fcd34d !important;
    transform: translateY(-2px);
    box-shadow: 0 14px 30px -8px rgba(245, 158, 11, 0.8);
}

/* ===== ERROR ===== */
.fi-simple-main .fi-fo-field-wrp-error-message { color: #fca5a5 !important; }

/* ===== HP ===== */
@media (max-width: 768px) {
    .fi-simple-layout { background-attachment: scroll !important; }
    .fi-simple-main-ctn { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
    .fi-simple-main { padding: 1.75rem 1.25rem !important; border-radius: 1.4rem !important; }
    .login-brand svg { width: 2.9rem; height: 3.4rem; }
    .login-brand .nm { font-size: 1.45rem; }
    .login-brand img { height: 4.5rem; }
    .fi-simple-main .fi-simple-header-heading { font-size: 1.35rem !important; }
}
CSS;

        // ------------------------------------------------------------
        // JS: brand dipindah ke atas "Sign in" (+ proses logo bila pakai gambar)
        // ------------------------------------------------------------
        $js = <<<'JS'
(function () {
    function processLogo(img) {
        if (!img || img.dataset.done) return;
        img.dataset.done = "1";
        function run() {
            try {
                var scale = Math.min(1, 800 / img.naturalWidth);
                var w = Math.round(img.naturalWidth * scale);
                var h = Math.round(img.naturalHeight * scale);
                var c = document.createElement("canvas");
                c.width = w; c.height = h;
                var ctx = c.getContext("2d");
                ctx.drawImage(img, 0, 0, w, h);
                var data = ctx.getImageData(0, 0, w, h);
                var p = data.data;
                var minX = w, minY = h, maxX = 0, maxY = 0;
                for (var y = 0; y < h; y++) {
                    for (var x = 0; x < w; x++) {
                        var i = (y * w + x) * 4;
                        var lum = p[i] * 0.299 + p[i + 1] * 0.587 + p[i + 2] * 0.114;
                        var a = Math.max(0, Math.min(255, (255 - lum) * 1.6));
                        a = a * (p[i + 3] / 255);
                        p[i] = 255; p[i + 1] = 255; p[i + 2] = 255; p[i + 3] = a;
                        if (a > 40) {
                            if (x < minX) minX = x;
                            if (x > maxX) maxX = x;
                            if (y < minY) minY = y;
                            if (y > maxY) maxY = y;
                        }
                    }
                }
                ctx.putImageData(data, 0, 0);
                if (maxX > minX && maxY > minY) {
                    var pad = 6;
                    minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
                    maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
                    var cw = maxX - minX + 1, ch = maxY - minY + 1;
                    var c2 = document.createElement("canvas");
                    c2.width = cw; c2.height = ch;
                    c2.getContext("2d").drawImage(c, minX, minY, cw, ch, 0, 0, cw, ch);
                    img.src = c2.toDataURL("image/png");
                } else {
                    img.src = c.toDataURL("image/png");
                }
            } catch (e) {
                console.warn("Logo tidak bisa diproses", e);
            }
        }
        if (img.complete && img.naturalWidth) run();
        else img.addEventListener("load", run, { once: true });
    }

    function init() {
        var brand = document.querySelector(".login-brand");
        var heading = document.querySelector(".fi-simple-main h1, .fi-simple-header-heading");
        if (brand && heading) {
            var target = heading.closest(".fi-simple-header") || heading.parentElement;
            if (target && target.parentNode && brand.nextElementSibling !== target) {
                target.parentNode.insertBefore(brand, target);
            }
            processLogo(brand.querySelector("img"));
        }
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
    document.addEventListener("livewire:navigated", init);
})();
JS;

        // ------------------------------------------------------------
        // HTML brand
        // ------------------------------------------------------------
        if ($logoFile) {
            $brandHtml = '<div class="login-brand"><img src="' . asset($logoFile) . '" alt="Logo ' . e($namaBrand) . '"></div>';
        } else {
            $brandHtml = '<div class="login-brand">
                <svg viewBox="-40 -46 80 98" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 -40 C 23 -40, 35 -8, 35 14 C 35 35, 20 47, 0 47 C -20 47, -35 35, -35 14 C -35 -8, -23 -40, 0 -40 Z" fill="#fbbf24"/>
                    <path d="M0 -40 C 23 -40, 35 -8, 35 14 C 35 35, 20 47, 0 47 C -20 47, -35 35, -35 14 C -35 -8, -23 -40, 0 -40 Z" fill="none" stroke="#d97706" stroke-width="2.5"/>
                    <ellipse cx="-13" cy="-8" rx="6" ry="14" transform="rotate(16 -13 -8)" fill="#ffffff" fill-opacity=".55"/>
                </svg>
                <div class="nm">' . e($namaBrand) . '</div>
                <div class="sb">' . e($subjudul) . '</div>
            </div>';
        }

        return $panel
            ->default()
            // ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->spa()
            ->id('admin')
            ->defaultThemeMode(ThemeMode::Light)
            ->brandName($namaBrand)
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/app.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])

            // Brand di dalam form login
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => $brandHtml
            )

            // Script
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => '<script>' . $js . '</script>'
            )

            // Style login
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>' . $css . '</style>'
            )

            ->navigationGroups([
                NavigationGroup::make(label: 'Produksi & Kandang')
                    ->icon('heroicon-o-building-office-2')
                    ->collapsed(),

                NavigationGroup::make(label: 'Transaksi')
                    ->icon('heroicon-o-arrows-right-left')
                    ->collapsed(),

                NavigationGroup::make(label: 'Akuntansi')
                    ->icon('heroicon-o-banknotes')
                    ->collapsed(),

                NavigationGroup::make(label: 'Master Data')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),

                NavigationGroup::make(label: 'Stock Barang')
                    ->icon('heroicon-o-archive-box')
                    ->collapsed(),
            ])
            ->authMiddleware([
                Authenticate::class,
                RedirectDashboardAnalisis::class,
            ]);
    }
}