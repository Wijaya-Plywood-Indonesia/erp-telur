@php
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

    $logoFile = $firstExisting(['images/logo-telur.png', 'images/logo-telur.webp']);
    $bgFile = $firstExisting([
        'images/telur-bg.webp', 'images/telur-bg.jpg', 'images/telur-bg.jpeg', 'images/telur-bg.png',
        'images/login-bg.webp', 'images/login-bg.jpg', 'images/login-bg.png',
    ]);
    $bgUrl = $bgFile ? asset($bgFile) . '?v=' . filemtime(public_path($bgFile)) : null;
    $loggedIn = auth()->check();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $namaBrand }} - Sistem ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Inter", system-ui, sans-serif;
            min-height: 100vh; min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem; color: #fff;
            background-color: #0f172a;
            @if ($bgUrl)
            background-image:
                linear-gradient(135deg, rgba(15,23,42,.74) 0%, rgba(15,23,42,.42) 100%),
                url("{{ $bgUrl }}");
            @endif
            background-size: cover; background-position: center; background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        .panel {
            width: 100%; max-width: 30rem; text-align: center; padding: 2.75rem 2.25rem;
            background: rgba(15,23,42,.50);
            -webkit-backdrop-filter: blur(22px) saturate(160%); backdrop-filter: blur(22px) saturate(160%);
            border: 1px solid rgba(255,255,255,.18); border-radius: 1.75rem;
            box-shadow: 0 30px 60px -15px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.2);
        }
        .brand { display: flex; flex-direction: column; align-items: center; gap: .35rem; margin-bottom: 1.25rem; }
        .brand svg { width: 3.6rem; height: 4.3rem; filter: drop-shadow(0 4px 12px rgba(0,0,0,.45)); }
        .brand .nm { font-size: 1.8rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; line-height: 1; margin-top: .25rem; }
        .brand .sb { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.7); }
        .brand img { height: 6rem; width: auto; max-width: 85%; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,.45)); }
        .badge {
            display: inline-block; padding: .35rem .9rem; border-radius: 999px; font-size: .72rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase; color: #fcd34d;
            background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.35); margin-bottom: 1rem;
        }
        h1 { font-size: 1.9rem; font-weight: 800; line-height: 1.2; letter-spacing: -.01em; margin-bottom: .6rem; }
        p { color: rgba(255,255,255,.78); font-size: .98rem; margin-bottom: 2rem; }
        .btn {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%; padding: .85rem 1.25rem; border-radius: .75rem;
            background: #fbbf24; color: #1f2937; font-weight: 700; font-size: 1rem;
            box-shadow: 0 10px 25px -8px rgba(245,158,11,.6); transition: transform .15s, box-shadow .15s, background .2s;
        }
        .btn:hover { background: #fcd34d; transform: translateY(-2px); box-shadow: 0 14px 30px -8px rgba(245,158,11,.8); }
        .foot { margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,.12); font-size: .78rem; color: rgba(255,255,255,.55); }
        @media (max-width: 768px) {
            body { background-attachment: scroll; }
            .panel { padding: 2.25rem 1.5rem; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <main class="panel">
        <div class="brand">
            @if ($logoFile)
                <img src="{{ asset($logoFile) }}" alt="Logo {{ $namaBrand }}" id="brandLogo">
            @else
                <svg viewBox="-40 -46 80 98" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 -40 C 23 -40, 35 -8, 35 14 C 35 35, 20 47, 0 47 C -20 47, -35 35, -35 14 C -35 -8, -23 -40, 0 -40 Z" fill="#fbbf24"/>
                    <path d="M0 -40 C 23 -40, 35 -8, 35 14 C 35 35, 20 47, 0 47 C -20 47, -35 35, -35 14 C -35 -8, -23 -40, 0 -40 Z" fill="none" stroke="#d97706" stroke-width="2.5"/>
                    <ellipse cx="-13" cy="-8" rx="6" ry="14" transform="rotate(16 -13 -8)" fill="#ffffff" fill-opacity=".55"/>
                </svg>
                <div class="nm">{{ $namaBrand }}</div>
                <div class="sb">{{ $subjudul }}</div>
            @endif
        </div>

        <span class="badge">Sistem Internal</span>
        <h1>Sistem ERP Peternakan</h1>
        <p>Masuk untuk mengakses data produksi pakan, produksi telur, stok, dan akuntansi.</p>

        <a href="{{ $loggedIn ? url('/admin') : route('filament.admin.auth.login') }}" class="btn">
            {{ $loggedIn ? 'Buka Dashboard' : 'Masuk ke Sistem' }}
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

        <div class="foot">&copy; {{ date('Y') }} {{ $namaBrand }}</div>
    </main>

    @if ($logoFile)
    <script>
        (function () {
            var img = document.getElementById('brandLogo');
            if (!img) return;
            function run() {
                try {
                    var scale = Math.min(1, 800 / img.naturalWidth);
                    var w = Math.round(img.naturalWidth * scale), h = Math.round(img.naturalHeight * scale);
                    var c = document.createElement('canvas'); c.width = w; c.height = h;
                    var ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0, w, h);
                    var data = ctx.getImageData(0, 0, w, h), p = data.data;
                    var minX = w, minY = h, maxX = 0, maxY = 0;
                    for (var y = 0; y < h; y++) for (var x = 0; x < w; x++) {
                        var i = (y * w + x) * 4;
                        var lum = p[i] * 0.299 + p[i + 1] * 0.587 + p[i + 2] * 0.114;
                        var a = Math.max(0, Math.min(255, (255 - lum) * 1.6)) * (p[i + 3] / 255);
                        p[i] = 255; p[i + 1] = 255; p[i + 2] = 255; p[i + 3] = a;
                        if (a > 40) { if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
                    }
                    ctx.putImageData(data, 0, 0);
                    if (maxX > minX && maxY > minY) {
                        var pad = 6; minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
                        maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
                        var cw = maxX - minX + 1, ch = maxY - minY + 1;
                        var c2 = document.createElement('canvas'); c2.width = cw; c2.height = ch;
                        c2.getContext('2d').drawImage(c, minX, minY, cw, ch, 0, 0, cw, ch);
                        img.src = c2.toDataURL('image/png');
                    } else { img.src = c.toDataURL('image/png'); }
                } catch (e) { console.warn('Logo tidak bisa diproses', e); }
            }
            if (img.complete && img.naturalWidth) run(); else img.addEventListener('load', run, { once: true });
        })();
    </script>
    @endif
</body>
</html>