<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // ============================================================
        // IDENTITAS (ubah sesuai kebutuhan)
        // ============================================================
        $namaBrand = 'Telur';
        $subjudul  = 'Pakan · Telur · Penjualan';

        // Logo (opsional): public/images/logo-telur.(png|webp)
        $logoFile = null;
        foreach (['images/logo-telur.png', 'images/logo-telur.webp'] as $f) {
            if (file_exists(public_path($f))) {
                $logoFile = $f;
                break;
            }
        }

        // ------------------------------------------------------------
        // LATAR: KONSEP DASHBOARD, WARNA ARANG
        // ------------------------------------------------------------
        $warna = [
            '{{GLOW1}}' => '#f59e0b',
            '{{GLOW2}}' => '#a3b18a',
            '{{A}}'     => '#d97706',
            '{{BAR}}'   => '#84a07c',
            '{{VIG}}'   => '#fdfbf5',
            '{{INK}}'   => '#1c1917',
        ];

        $defs = <<<'DEFS'
<radialGradient id="gGlow1" cx="50%" cy="50%" r="50%">
  <stop offset="0%" stop-color="{{GLOW1}}" stop-opacity=".30"/>
  <stop offset="100%" stop-color="{{GLOW1}}" stop-opacity="0"/>
</radialGradient>
<radialGradient id="gGlow2" cx="50%" cy="50%" r="50%">
  <stop offset="0%" stop-color="{{GLOW2}}" stop-opacity=".30"/>
  <stop offset="100%" stop-color="{{GLOW2}}" stop-opacity="0"/>
</radialGradient>
<radialGradient id="gVig" cx="50%" cy="50%" r="75%">
  <stop offset="52%" stop-color="{{VIG}}" stop-opacity="0"/>
  <stop offset="100%" stop-color="{{VIG}}" stop-opacity=".65"/>
</radialGradient>
<linearGradient id="gArea" x1="0" x2="0" y1="0" y2="1">
  <stop offset="0%"   stop-color="{{A}}" stop-opacity=".32"/>
  <stop offset="100%" stop-color="{{A}}" stop-opacity="0"/>
</linearGradient>
<filter id="fB" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation="2.2"/></filter>
DEFS;

        $isi = <<<'DASH'
<circle cx="1300" cy="150" r="560" fill="url(#gGlow1)"/>
<circle cx="200"  cy="780" r="600" fill="url(#gGlow2)"/>

<g transform="translate(-90 -50) rotate(-2.5) scale(1.52)" filter="url(#fB)">
  <rect width="1200" height="700" rx="22" fill="{{INK}}" fill-opacity=".05" stroke="{{INK}}" stroke-opacity=".14"/>
  <rect width="210" height="700" rx="22" fill="{{INK}}" fill-opacity=".06"/>

  <circle cx="46" cy="46" r="16" fill="{{A}}" fill-opacity=".7"/>
  <rect x="74" y="38" width="90" height="16" rx="8" fill="{{INK}}" fill-opacity=".28"/>
  <rect x="28" y="110" width="154" height="30" rx="10" fill="{{A}}" fill-opacity=".30"/>
  <g fill="{{INK}}" fill-opacity=".16">
    <rect x="48" y="118" width="110" height="12" rx="6" fill="{{A}}" fill-opacity=".55"/>
    <rect x="48" y="180" width="110" height="12" rx="6"/>
    <rect x="48" y="226" width="96"  height="12" rx="6"/>
    <rect x="48" y="272" width="118" height="12" rx="6"/>
    <rect x="48" y="318" width="90"  height="12" rx="6"/>
    <rect x="48" y="364" width="104" height="12" rx="6"/>
    <rect x="48" y="410" width="84"  height="12" rx="6"/>
  </g>

  <rect x="240" y="26" width="300" height="30" rx="15" fill="{{INK}}" fill-opacity=".09"/>
  <circle cx="1146" cy="41" r="16" fill="{{INK}}" fill-opacity=".18"/>
  <rect x="1040" y="34" width="70" height="14" rx="7" fill="{{INK}}" fill-opacity=".14"/>

  <g fill="{{INK}}" fill-opacity=".07">
    <rect x="240" y="96" width="220" height="110" rx="16"/>
    <rect x="480" y="96" width="220" height="110" rx="16"/>
    <rect x="720" y="96" width="220" height="110" rx="16"/>
    <rect x="960" y="96" width="220" height="110" rx="16"/>
  </g>
  <g fill="{{INK}}" fill-opacity=".26">
    <rect x="258" y="116" width="70" height="8" rx="4"/>
    <rect x="498" y="116" width="70" height="8" rx="4"/>
    <rect x="738" y="116" width="70" height="8" rx="4"/>
    <rect x="978" y="116" width="70" height="8" rx="4"/>
  </g>
  <g fill="{{INK}}" fill-opacity=".42">
    <rect x="258" y="140" width="120" height="22" rx="6" fill="{{A}}" fill-opacity=".55"/>
    <rect x="498" y="140" width="110" height="22" rx="6"/>
    <rect x="738" y="140" width="130" height="22" rx="6" fill="{{A}}" fill-opacity=".55"/>
    <rect x="978" y="140" width="100" height="22" rx="6"/>
  </g>
  <g fill="none" stroke="{{BAR}}" stroke-opacity=".6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
    <polyline points="350,194 366,188 382,191 398,182 414,185 440,174"/>
    <polyline points="590,192 606,186 622,189 638,180 654,184 680,176"/>
    <polyline points="830,194 846,189 862,192 878,181 894,184 920,172"/>
    <polyline points="1070,193 1086,187 1102,190 1118,181 1134,185 1160,175"/>
  </g>

  <rect x="240" y="230" width="600" height="270" rx="16" fill="{{INK}}" fill-opacity=".06"/>
  <g stroke="{{INK}}" stroke-opacity=".08">
    <line x1="264" y1="290" x2="816" y2="290"/>
    <line x1="264" y1="350" x2="816" y2="350"/>
    <line x1="264" y1="410" x2="816" y2="410"/>
    <line x1="264" y1="470" x2="816" y2="470"/>
  </g>
  <path d="M264 450 C340 430 380 380 450 390 S560 320 620 330 S740 270 816 260 L816 480 L264 480 Z" fill="url(#gArea)"/>
  <path d="M264 450 C340 430 380 380 450 390 S560 320 620 330 S740 270 816 260" fill="none" stroke="{{A}}" stroke-width="3" stroke-linecap="round"/>

  <rect x="860" y="230" width="320" height="270" rx="16" fill="{{INK}}" fill-opacity=".06"/>
  <g fill="{{BAR}}" fill-opacity=".45">
    <rect x="884"  y="380" width="26" height="90"  rx="8"/>
    <rect x="924"  y="330" width="26" height="140" rx="8"/>
    <rect x="964"  y="360" width="26" height="110" rx="8"/>
    <rect x="1004" y="300" width="26" height="170" rx="8"/>
    <rect x="1044" y="340" width="26" height="130" rx="8"/>
    <rect x="1084" y="280" width="26" height="190" rx="8"/>
    <rect x="1124" y="320" width="26" height="150" rx="8" fill="{{A}}" fill-opacity=".55"/>
  </g>

  <rect x="240" y="524" width="940" height="150" rx="16" fill="{{INK}}" fill-opacity=".06"/>
  <g stroke="{{INK}}" stroke-opacity=".08">
    <line x1="264" y1="574" x2="1156" y2="574"/>
    <line x1="264" y1="612" x2="1156" y2="612"/>
    <line x1="264" y1="650" x2="1156" y2="650"/>
  </g>
  <g fill="{{INK}}" fill-opacity=".22">
    <circle cx="276" cy="556" r="8"/><circle cx="276" cy="594" r="8"/><circle cx="276" cy="632" r="8"/>
    <rect x="302" y="551" width="140" height="10" rx="5"/>
    <rect x="302" y="589" width="120" height="10" rx="5"/>
    <rect x="302" y="627" width="150" height="10" rx="5"/>
    <rect x="560" y="551" width="100" height="10" rx="5" fill-opacity=".14"/>
    <rect x="560" y="589" width="80"  height="10" rx="5" fill-opacity=".14"/>
    <rect x="560" y="627" width="110" height="10" rx="5" fill-opacity=".14"/>
    <rect x="780" y="551" width="100" height="10" rx="5" fill-opacity=".14"/>
    <rect x="780" y="589" width="90"  height="10" rx="5" fill-opacity=".14"/>
    <rect x="780" y="627" width="100" height="10" rx="5" fill-opacity=".14"/>
    <rect x="1040" y="551" width="70" height="10" rx="5" fill="{{A}}" fill-opacity=".5"/>
    <rect x="1040" y="589" width="70" height="10" rx="5"/>
    <rect x="1040" y="627" width="70" height="10" rx="5"/>
  </g>
</g>

<rect width="1600" height="900" fill="{{VIG}}" fill-opacity=".30"/>
<rect width="1600" height="900" fill="url(#gVig)"/>
DASH;

        $svg = '<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg"><defs>'
            . $defs . '</defs>' . $isi . '</svg>';
        $bgHtml = '<div class="abs-bg" aria-hidden="true">' . strtr($svg, $warna) . '</div>';

        // ------------------------------------------------------------
        // CSS LOGIN (tema gelap, warna Arang)
        // ------------------------------------------------------------
        $css = <<<'CSS'
.fi-simple-layout {
    --bg: linear-gradient(135deg, #fdfbf5 0%, #f6f0e2 55%, #efe8d6 100%);
    --card-bg: rgba(255,255,255,0.90);
    --card-border: rgba(15,23,42,0.08);
    --card-shadow: 0 30px 60px -22px rgba(120,90,30,0.30), inset 0 1px 0 rgba(255,255,255,0.9);
    --text: #1c1917;
    --muted: #78716c;
    --input-bg: #faf8f3;
    --input-ring: #d6d3d1;
    --link: #b45309;
    --link-hover: #92400e;
    --icon: #78716c;
    --check-bg: #ffffff;
    --check-border: #a8a29e;
    --error: #dc2626;
    --logo-shadow: drop-shadow(0 2px 6px rgba(15,23,42,0.15));
}

/* ===== LATAR ===== */
.fi-simple-layout {
    background: var(--bg) !important;
    min-height: 100vh;
    min-height: 100dvh;
}
.abs-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}
.abs-bg svg { width: 100%; height: 100%; display: block; }
.fi-simple-main-ctn { position: relative; z-index: 1; }

/* ===== CARD ===== */
.fi-simple-main {
    background: var(--card-bg) !important;
    -webkit-backdrop-filter: blur(22px) saturate(150%);
    backdrop-filter: blur(22px) saturate(150%);
    border: 1px solid var(--card-border) !important;
    border-radius: 1.6rem !important;
    box-shadow: var(--card-shadow) !important;
    --tw-ring-shadow: 0 0 #0000 !important;
    --tw-ring-color: transparent !important;
    padding-top: 2.5rem !important;
    padding-bottom: 2.5rem !important;
}

/* ===== LOGO / BRAND ===== */
.login-brand { display: flex; flex-direction: column; align-items: center; gap: .35rem; margin: 0 0 1rem 0; text-align: center; }
.login-brand svg { width: 3.4rem; height: 4rem; filter: var(--logo-shadow); }
.login-brand .nm { font-size: 1.7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--text); line-height: 1; margin-top: .25rem; }
.login-brand .sb { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
.login-brand img { display: block; height: 5.5rem; width: auto; max-width: 85%; object-fit: contain; filter: var(--logo-shadow); }
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
    color: var(--text) !important;
}
.fi-simple-main .fi-simple-header-subheading,
.fi-simple-main .fi-simple-header-subheading span {
    color: var(--muted) !important;
}
.fi-simple-main a { color: var(--link) !important; }
.fi-simple-main a:hover { color: var(--link-hover) !important; }

/* ===== INPUT ===== */
.fi-simple-main .fi-input-wrp {
    background-color: var(--input-bg) !important;
    border-radius: 0.75rem !important;
    box-shadow: inset 0 0 0 1px var(--input-ring) !important;
    transition: all 0.2s ease;
}
.fi-simple-main .fi-input-wrp:focus-within {
    box-shadow: inset 0 0 0 2px #f59e0b, 0 0 0 4px rgba(251, 191, 36, 0.25) !important;
}
.fi-simple-main input.fi-input {
    color: var(--text) !important;
    -webkit-text-fill-color: var(--text) !important;
    background: transparent !important;
}
.fi-simple-main input.fi-input::placeholder { color: var(--muted) !important; }
.fi-simple-main input:-webkit-autofill {
    -webkit-text-fill-color: var(--text) !important;
    transition: background-color 9999s ease-in-out 0s;
}
.fi-simple-main .fi-input-wrp button,
.fi-simple-main .fi-input-wrp svg { color: var(--icon) !important; }

/* ===== CHECKBOX ===== */
.fi-simple-main input[type="checkbox"] {
    background-color: var(--check-bg) !important;
    border-color: var(--check-border) !important;
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
.fi-simple-main .fi-fo-field-wrp-error-message { color: var(--error) !important; }

/* ===== RESPONSIVE (HP / layar sempit) ===== */
@media (max-width: 768px) {
    .fi-simple-layout { background-attachment: scroll !important; }
    .fi-simple-main-ctn { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
    .fi-simple-main {
        padding: 1.75rem 1.25rem !important;
        border-radius: 1.4rem !important;
    }
    .login-brand svg { width: 2.9rem; height: 3.4rem; }
    .login-brand .nm { font-size: 1.45rem; }
    .login-brand img { height: 4.5rem; }
    .fi-simple-main .fi-simple-header-heading { font-size: 1.35rem !important; }
    .fi-simple-main input.fi-input { font-size: 16px !important; }
}
@media (max-width: 380px) {
    .fi-simple-main { padding: 1.5rem 1rem !important; }
}
CSS;

        // ------------------------------------------------------------
        // JS: pindahkan brand ke atas "Sign in" + proses logo gambar
        // ------------------------------------------------------------
        $js = <<<'JS'
(function () {
    function processLogo(img) {
        if (!img || img.dataset.done) return;
        img.dataset.done = "1";
        var rgb = (img.dataset.rgb || "255,255,255").split(",").map(Number);
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
                        p[i] = rgb[0]; p[i + 1] = rgb[1]; p[i + 2] = rgb[2]; p[i + 3] = a;
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
        }
        document.querySelectorAll("img[data-rgb]").forEach(processLogo);
    }

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
    else init();
    document.addEventListener("livewire:navigated", init);
})();
JS;

        // ------------------------------------------------------------
        // HTML brand di dalam form (logo gambar kalau ada, kalau tidak ikon telur + teks)
        // ------------------------------------------------------------
        if ($logoFile) {
            $brandHtml = '<div class="login-brand"><img data-rgb="28,25,23" src="' . asset($logoFile) . '" alt="Logo ' . e($namaBrand) . '"></div>';
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

            // Brand (logo / ikon telur) di dalam form login
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => $brandHtml
            )

            // Latar dashboard (hanya untuk tamu / halaman login)
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => auth()->check() ? '' : $bgHtml
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
            ]);
    }
}