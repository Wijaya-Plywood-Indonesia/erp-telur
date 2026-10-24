<x-filament-panels::page>
    @php
        $rp  = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        // rupiah bertanda (untuk laba/rugi): −Rp 1.000.000
        $rps = fn ($v) => ((float) $v < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $v), 0, ',', '.');
        $rpk = function ($v) {
            $v = (float) $v;
            $a = abs($v);
            if ($a >= 1e9) return rtrim(rtrim(number_format($v / 1e9, 1, ',', '.'), '0'), ',') . ' M';
            if ($a >= 1e6) return rtrim(rtrim(number_format($v / 1e6, 1, ',', '.'), '0'), ',') . ' jt';
            if ($a >= 1e3) return number_format($v / 1e3, 0, ',', '.') . ' rb';
            return number_format($v, 0, ',', '.');
        };
        $chip = function ($v) {
            if ($v === null) return '<span class="at-chip at-flat">—</span>';
            $cls = $v > 0 ? 'at-up' : ($v < 0 ? 'at-down' : 'at-flat');
            $ic  = $v > 0 ? '▲' : ($v < 0 ? '▼' : '•');
            return '<span class="at-chip ' . $cls . '">' . $ic . ' ' . number_format(abs($v), 1, ',', '.') . '%</span>';
        };
    @endphp

    <style>
        :root {
            --at-card: #ffffff; --at-bd: #e5e7eb; --at-tx: #111827; --at-mu: #6b7280;
            --at-soft: #f3f4f6; --at-grid: #eceff3; --at-amber: #f59e0b;
        }
        .dark {
            --at-card: #18181b; --at-bd: #27272a; --at-tx: #f4f4f5; --at-mu: #a1a1aa;
            --at-soft: #27272a; --at-grid: #2c2c32;
        }

        .at-card { background: var(--at-card); border: 1px solid var(--at-bd); border-radius: 14px; padding: 18px; color: var(--at-tx); }
        .at-title { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--at-mu); margin: 0 0 12px; }
        .at-mu { color: var(--at-mu); font-size: 12px; }
        .at-stack { display: flex; flex-direction: column; gap: 16px; }

        /* filter */
        .at-filter { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .at-seg { display: inline-flex; background: var(--at-soft); border-radius: 10px; padding: 3px; gap: 2px; flex-wrap: wrap; }
        .at-seg button { border: 0; background: transparent; color: var(--at-mu); font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 8px; cursor: pointer; }
        .at-seg button.on { background: var(--at-card); color: var(--at-tx); box-shadow: 0 1px 2px rgba(0,0,0,.12); }
        .at-dates { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--at-mu); }
        .at-dates input { background: var(--at-card); color: var(--at-tx); border: 1px solid var(--at-bd); border-radius: 8px; padding: 6px 8px; font-size: 12px; }

        /* kpi */
        .at-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
        .at-kpi .lb { font-size: 12px; color: var(--at-mu); margin-bottom: 6px; }
        .at-kpi .vl { font-size: 22px; font-weight: 800; line-height: 1.15; }
        .at-kpi .sb { margin-top: 8px; font-size: 12px; color: var(--at-mu); display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        .at-chip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 999px; white-space: nowrap; }
        .at-up { background: rgba(16,185,129,.14); color: #059669; }
        .at-down { background: rgba(239,68,68,.14); color: #dc2626; }
        .at-flat { background: var(--at-soft); color: var(--at-mu); }
        .dark .at-up { color: #34d399; } .dark .at-down { color: #f87171; }

        /* chart */
        .at-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .at-lg { display: inline-flex; align-items: center; gap: 7px; border: 1px solid var(--at-bd); background: transparent; color: var(--at-mu); font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 999px; cursor: pointer; }
        .at-lg i { width: 9px; height: 9px; border-radius: 99px; display: inline-block; opacity: .35; }
        .at-lg.on { color: var(--at-tx); background: var(--at-soft); }
        .at-lg.on i { opacity: 1; }
        .at-chartwrap { position: relative; width: 100%; min-width: 0; overflow: hidden; cursor: pointer; user-select: none; -webkit-user-select: none; }
        .at-chartwrap svg { display: block; }
        .at-ax { fill: var(--at-mu); font-size: 11px; }
        .at-grid { stroke: var(--at-grid); stroke-width: 1; }
        .at-guide { stroke: var(--at-mu); stroke-width: 1; stroke-dasharray: 4 4; opacity: .55; }
        .at-hdot { stroke: var(--at-card); stroke-width: 2.5; }
        .at-dot { stroke: var(--at-card); stroke-width: 1.5; }
        .at-peak { fill: none; stroke: var(--at-amber); stroke-width: 2; }
        .at-peaktx { fill: var(--at-amber); font-size: 10px; font-weight: 800; }

        .at-tip { position: absolute; top: 10px; z-index: 5; pointer-events: none; min-width: 190px; max-width: 250px; background: var(--at-card); border: 1px solid var(--at-bd); border-radius: 12px; padding: 10px 12px; box-shadow: 0 10px 30px -8px rgba(0,0,0,.35); font-size: 12px; }
        .at-tip-h { color: var(--at-mu); font-size: 11px; margin-bottom: 2px; }
        .at-tip-big { font-size: 17px; font-weight: 800; margin-bottom: 7px; }
        .at-tip-r { display: flex; align-items: center; gap: 7px; padding: 2px 0; }
        .at-tip-r i { width: 8px; height: 8px; border-radius: 99px; flex: none; }
        .at-tip-r span { flex: 1; }
        .at-tip-r em { font-style: normal; color: var(--at-mu); font-size: 11px; }
        .at-tip-r b { min-width: 42px; text-align: right; }
        .at-tip-f { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--at-bd); color: #0284c7; font-weight: 700; }
        .at-tip-hint { margin-top: 6px; font-size: 10px; color: var(--at-mu); }

        /* modal */
        .at-ov { position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; padding: 16px; }
        .at-modal { width: 100%; max-width: 480px; max-height: 90vh; overflow: auto; background: var(--at-card); color: var(--at-tx); border: 1px solid var(--at-bd); border-radius: 18px; padding: 20px; box-shadow: 0 30px 70px -20px rgba(0,0,0,.6); }
        .at-mh { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .at-mh h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--at-mu); }
        .at-x { border: 0; background: var(--at-soft); color: var(--at-mu); width: 28px; height: 28px; border-radius: 99px; cursor: pointer; font-size: 16px; line-height: 1; flex: none; }
        .at-mbig { font-size: 28px; font-weight: 800; margin: 6px 0 4px; }
        .at-msub { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--at-mu); }
        .at-sec { margin: 18px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--at-mu); }
        .at-row { margin-bottom: 12px; }
        .at-row .t { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .at-row .t i { width: 9px; height: 9px; border-radius: 99px; flex: none; }
        .at-row .t span { flex: 1; font-weight: 600; }
        .at-row .t b { font-size: 15px; }
        .at-track { height: 7px; border-radius: 99px; background: var(--at-soft); margin: 6px 0 4px; overflow: hidden; }
        .at-track div { height: 100%; border-radius: 99px; }
        .at-row .s { display: flex; justify-content: space-between; gap: 8px; font-size: 11px; color: var(--at-mu); align-items: center; }
        .at-note { background: var(--at-soft); border-radius: 10px; padding: 9px 11px; font-size: 12px; margin-top: 6px; }
        .at-kv { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }

        /* insight & grid */
        .at-ins { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .at-ins li { font-size: 13px; padding-left: 16px; position: relative; line-height: 1.5; }
        .at-ins li::before { content: ''; position: absolute; left: 0; top: 7px; width: 7px; height: 7px; border-radius: 99px; background: var(--at-amber); }
        .at-cols { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .at-bar { display: grid; grid-template-columns: 62px 1fr 64px; gap: 8px; align-items: center; font-size: 12px; margin-bottom: 8px; }
        .at-bar .tr { height: 9px; border-radius: 99px; background: var(--at-soft); overflow: hidden; }
        .at-bar .tr i { display: block; height: 100%; border-radius: 99px; background: #fcd34d; }
        .at-bar .tr i.top { background: var(--at-amber); }
        .at-bar b { text-align: right; font-size: 12px; }
        .at-bar.k { grid-template-columns: 1fr; margin-bottom: 14px; }

        .at-lain { margin-top: 8px; padding: 8px 10px; background: var(--at-soft); border-radius: 10px; font-size: 11px; color: var(--at-mu); }
        .at-lain div { display: flex; justify-content: space-between; gap: 8px; padding: 2px 0; }
        .at-lain b { color: var(--at-tx); font-weight: 600; }
        .at-sub { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--at-mu); margin: 14px 0 8px; }

        /* stok */
        .at-stoks { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
        .at-stok { display: flex; align-items: center; gap: 12px; border: 1px solid var(--at-bd); border-radius: 12px; padding: 12px; }
        .at-stok > i { width: 6px; align-self: stretch; border-radius: 99px; flex: none; }
        .at-stok .nm { font-size: 12px; color: var(--at-mu); }
        .at-stok .qty { font-size: 20px; font-weight: 800; }
        .at-stok .qty small { font-size: 12px; font-weight: 600; color: var(--at-mu); }
        .at-stok .tg { display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap; }
        .at-stok.low { border-color: rgba(239,68,68,.5); }

        /* laba / rugi */
        .at-pos { color: #059669; } .at-neg { color: #dc2626; }
        .dark .at-pos { color: #34d399; } .dark .at-neg { color: #f87171; }
        .at-zero { stroke: var(--at-mu); stroke-width: 1.3; opacity: .65; }
        .at-tip-l { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--at-bd); font-weight: 700; display: flex; justify-content: space-between; gap: 8px; }
        .at-kpi .brk { margin-top: 8px; font-size: 11px; color: var(--at-mu); line-height: 1.6; }
        .at-kpi a.lnk { color: var(--at-amber); font-weight: 700; font-size: 12px; text-decoration: none; }
        .at-kpi a.lnk:hover { text-decoration: underline; }
        .at-lb { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
        .at-lbk { border: 1px solid var(--at-bd); border-radius: 12px; padding: 12px 14px; }
        .at-lbk .nm { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--at-mu); font-weight: 600; }
        .at-lbk .nm i { width: 9px; height: 9px; border-radius: 99px; flex: none; }
        .at-lbk .big { font-size: 20px; font-weight: 800; margin: 6px 0 4px; }
        .at-lbk .kv { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; color: var(--at-mu); padding: 1px 0; }
        .at-lbk .kv b { color: var(--at-tx); font-weight: 600; }

        @media (max-width: 900px) { .at-cols { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .at-kpis { grid-template-columns: 1fr; } .at-harga-wrap { order: -1; } }
        @media (max-width: 480px) { .at-kpi .vl { font-size: 18px; } .at-mbig { font-size: 24px; } }
    </style>

    <div class="at-stack">

        {{-- ════════════ FILTER ════════════ --}}
        <div class="at-card">
            <div class="at-filter">
                <div class="at-seg">
                    @foreach (['7h' => '7 Hari', '30h' => '30 Hari', '90h' => '90 Hari', '12b' => '12 Bulan', 'semua' => 'Semua'] as $k => $l)
                        <button type="button" wire:click="terapkanPreset('{{ $k }}')" class="{{ $preset === $k ? 'on' : '' }}">{{ $l }}</button>
                    @endforeach
                </div>

                <div class="at-dates">
                    <input type="date" wire:model.live="dari" max="{{ now()->toDateString() }}">
                    <span>s/d</span>
                    <input type="date" wire:model.live="sampai" max="{{ now()->toDateString() }}">
                    <span wire:loading class="at-mu">Memuat…</span>
                </div>

                <div class="at-seg">
                    @foreach (['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'] as $k => $l)
                        <button type="button" wire:click="setGranularitas('{{ $k }}')" class="{{ $granularitas === $k ? 'on' : '' }}">{{ $l }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ════════════ KPI ════════════ --}}
        @php
            $lab    = $d['laba'];
            $lb     = $d['laba_bersih'];
            $lbNeg  = $lb && $lb['laba_bersih'] < 0;
            $lbDel  = $lb ? $lb['delta'] : 0;
        @endphp
        <div class="at-kpis">
            <div class="at-card at-kpi">
                <div class="lb">Omset periode</div>
                <div class="vl">{{ $rp($d['kpi']['total']) }}</div>
                <div class="sb">{!! $chip($d['kpi']['growth']) !!} <span>vs periode sebelumnya</span></div>
            </div>

            {{-- Laba kotor penjualan = omset − HPP --}}
            <div class="at-card at-kpi">
                <div class="lb">{{ $lab['total'] < 0 ? 'Rugi kotor penjualan' : 'Laba kotor penjualan' }}</div>
                <div class="vl {{ $lab['total'] < 0 ? 'at-neg' : '' }}">{{ $rps($lab['total']) }}</div>
                <div class="sb">
                    @if ($lab['margin'] !== null)
                        <span class="at-chip {{ $lab['margin'] < 0 ? 'at-down' : 'at-flat' }}">Margin {{ number_format($lab['margin'], 1, ',', '.') }}%</span>
                    @endif
                    {!! $chip($lab['growth']) !!} <span>vs sebelumnya</span>
                </div>
                <div class="brk">Omset − HPP barang terjual ({{ $rp($lab['hpp']) }}). Belum termasuk beban operasional.</div>
            </div>

            {{-- Laba / rugi bersih (dari jurnal) --}}
            @if ($lb)
                <div class="at-card at-kpi">
                    <div class="lb">{{ $lbNeg ? 'Rugi bersih' : 'Laba bersih' }} (setelah beban)</div>
                    <div class="vl {{ $lbNeg ? 'at-neg' : 'at-pos' }}">{{ $rps($lb['laba_bersih']) }}</div>
                    <div class="sb">
                        <span class="at-chip {{ $lbDel > 0 ? 'at-up' : ($lbDel < 0 ? 'at-down' : 'at-flat') }}">{{ $lbDel > 0 ? '▲' : ($lbDel < 0 ? '▼' : '•') }} {{ $rpk(abs($lbDel)) }}</span>
                        <span>vs periode sebelumnya</span>
                    </div>
                    <div class="brk">
                        Pendapatan {{ $rp($lb['pendapatan'] + $lb['pendapatan_lain']) }} − HPP {{ $rp($lb['hpp']) }} − Beban {{ $rp($lb['beban']) }}<br>
                        Dari jurnal yang sudah diposting.
                        @if ($urlLabaRugi)
                            <a class="lnk" href="{{ $urlLabaRugi }}">Lihat rincian di Laba Rugi Telur →</a>
                        @endif
                    </div>
                </div>
            @endif

            <div class="at-card at-kpi">
                <div class="lb">Rata-rata per hari</div>
                <div class="vl">{{ $rp($d['kpi']['rata_harian']) }}</div>
                <div class="sb">{{ $d['jumlah_hari'] }} hari</div>
            </div>

            <div class="at-card at-kpi">
                <div class="lb">Omset harian tertinggi</div>
                @if ($d['kpi']['hari_terbaik'])
                    <div class="vl">{{ $rp($d['kpi']['hari_terbaik']['omset']) }}</div>
                    <div class="sb">{{ $d['kpi']['hari_terbaik']['label'] }}</div>
                @else
                    <div class="vl">—</div>
                    <div class="sb">Belum ada penjualan</div>
                @endif
            </div>
        </div>

        {{-- ════════════ GRAFIK GARIS ════════════ --}}
        <div class="at-card" wire:key="chart-{{ md5(json_encode($d['chart'])) }}"
            x-data="{
                d: @js($d['chart']),
                w: 900, h: 380,
                show: { omset: true, laba: false, kiloan: false, petian: false, bentes: false, stok: false },
                hover: null, modal: null,

                init() {
                    const el = this.$refs.wrap;
                    const fit = () => { this.w = Math.max(320, el.clientWidth); this.h = this.w < 560 ? 300 : 380; };
                    fit();
                    if (window.ResizeObserver) new ResizeObserver(fit).observe(el);
                },

                get n() { return this.d.points.length },
                get keys() { return ['omset', 'laba', 'kiloan', 'petian', 'bentes'].filter(k => this.show[k]) },
                get mp() { return this.modal === null ? null : this.d.points[this.modal] },
                get hp() { return this.hover === null ? null : this.d.points[this.hover] },

                nice(m) {
                    if (!(m > 0)) return 4;
                    const raw = m / 4, p = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / p;
                    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p * 4;
                },
                val(k, p) { return k === 'omset' ? p.omset : k === 'laba' ? p.laba : p.jenis[k].omset },
                col(k) { return k === 'omset' ? '#f59e0b' : k === 'laba' ? '#65a30d' : k === 'stok' ? '#0ea5e9' : this.d.jenis[k].color },

                nstep(span, div) {
                    if (!(span > 0)) return 1;
                    const raw = span / div, p = Math.pow(10, Math.floor(Math.log10(raw))), f = raw / p;
                    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p;
                },

                geom() {
                    const s = this.w < 560;
                    const P = { l: s ? 52 : 70, r: this.show.stok ? (s ? 48 : 66) : 16, t: 20, b: 34 };
                    const iw = this.w - P.l - P.r, ih = this.h - P.t - P.b, n = this.n;
                    let hi = 0, lo = 0, ms = 0;
                    this.d.points.forEach(p => {
                        this.keys.forEach(k => { const v = this.val(k, p); hi = Math.max(hi, v); lo = Math.min(lo, v); });
                        ms = Math.max(ms, p.stok || 0);
                    });
                    const step = this.nstep(hi - lo, 4);
                    hi = Math.ceil(hi / step) * step;
                    lo = Math.floor(lo / step) * step;
                    if (hi === lo) hi = lo + step;
                    const ticks = [];
                    for (let v = lo; v <= hi + step / 2; v += step) ticks.push(Math.round(v * 100) / 100);
                    const div = Math.max(1, ticks.length - 1);
                    ms = this.nstep(ms, div) * div;
                    return {
                        P, iw, ih, n, lo, hi, ticks, div, ms,
                        X: i => n < 2 ? P.l + iw / 2 : P.l + iw * i / (n - 1),
                        YL: v => P.t + ih * (1 - (v - lo) / (hi - lo)),
                        YS: v => P.t + ih * (1 - v / ms)
                    };
                },

                smooth(pts) {
                    const n = pts.length;
                    if (!n) return '';
                    const f = v => Math.round(v * 100) / 100;
                    if (n === 1) return 'M' + f(pts[0][0]) + ',' + f(pts[0][1]);
                    const dx = [], m = [];
                    for (let i = 0; i < n - 1; i++) { dx[i] = pts[i + 1][0] - pts[i][0]; m[i] = (pts[i + 1][1] - pts[i][1]) / dx[i]; }
                    const t = [m[0]];
                    for (let i = 1; i < n - 1; i++) t[i] = (m[i - 1] * m[i] <= 0) ? 0 : (m[i - 1] + m[i]) / 2;
                    t[n - 1] = m[n - 2];
                    for (let i = 0; i < n - 1; i++) {
                        if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
                        const a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
                        if (s > 9) { const q = 3 / Math.sqrt(s); t[i] = q * a * m[i]; t[i + 1] = q * b * m[i]; }
                    }
                    let out = 'M' + f(pts[0][0]) + ',' + f(pts[0][1]);
                    for (let i = 0; i < n - 1; i++) {
                        const hh = dx[i] / 3;
                        out += ' C' + f(pts[i][0] + hh) + ',' + f(pts[i][1] + t[i] * hh) + ' ' + f(pts[i + 1][0] - hh) + ',' + f(pts[i + 1][1] - t[i + 1] * hh) + ' ' + f(pts[i + 1][0]) + ',' + f(pts[i + 1][1]);
                    }
                    return out;
                },

                get svgInner() {
                    const g = this.geom(), P = g.P, W = this.w;
                    let s = `<defs><linearGradient id='atg' x1='0' y1='0' x2='0' y2='1'><stop offset='0%' stop-color='#f59e0b' stop-opacity='.30'/><stop offset='100%' stop-color='#f59e0b' stop-opacity='0'/></linearGradient></defs>`;

                    g.ticks.forEach((t, i) => {
                        const y = g.YL(t);
                        s += `<line class='${t === 0 && g.lo < 0 ? 'at-zero' : 'at-grid'}' x1='${P.l}' x2='${W - P.r}' y1='${y}' y2='${y}'/>`;
                        if (this.keys.length) s += `<text class='at-ax' x='${P.l - 8}' y='${y + 4}' text-anchor='end'>${this.rpk(t)}</text>`;
                        if (this.show.stok) s += `<text class='at-ax' x='${W - P.r + 8}' y='${y + 4}'>${this.num(g.ms * i / g.div)}</text>`;
                    });

                    const step = Math.max(1, Math.ceil(g.n / (this.w < 560 ? 5 : 9)));
                    this.d.points.forEach((p, i) => {
                        if (i % step === 0) s += `<text class='at-ax' x='${g.X(i)}' y='${this.h - 10}' text-anchor='middle'>${p.short}</text>`;
                    });

                    const pts = k => this.d.points.map((p, i) => [g.X(i), k === 'stok' ? g.YS(p.stok || 0) : g.YL(this.val(k, p))]);

                    if (this.show.omset) {
                        const o = pts('omset'), base = g.YL(0);
                        s += `<path d='${this.smooth(o)} L${o[o.length - 1][0]},${base} L${o[0][0]},${base} Z' fill='url(#atg)'/>`;
                    }
                    this.keys.forEach(k => {
                        s += `<path d='${this.smooth(pts(k))}' fill='none' stroke='${this.col(k)}' stroke-width='${k === 'omset' ? 2.6 : 2}' stroke-linecap='round' stroke-linejoin='round'/>`;
                    });
                    if (this.show.stok) {
                        s += `<path d='${this.smooth(pts('stok'))}' fill='none' stroke='#0ea5e9' stroke-width='2' stroke-dasharray='6 5' stroke-linecap='round'/>`;
                    }

                    if (g.n <= 45) {
                        this.keys.forEach(k => {
                            pts(k).forEach(c => { s += `<circle class='at-dot' cx='${c[0]}' cy='${c[1]}' r='3.2' fill='${this.col(k)}'/>`; });
                        });
                    }

                    if (this.show.omset && g.n > 1) {
                        let bi = -1, bv = 0;
                        this.d.points.forEach((p, i) => { if (p.omset > bv) { bv = p.omset; bi = i; } });
                        if (bi >= 0) {
                            const cx = g.X(bi), cy = g.YL(bv);
                            s += `<circle class='at-peak' cx='${cx}' cy='${cy}' r='7'/><text class='at-peaktx' x='${cx}' y='${cy - 12}' text-anchor='middle'>Puncak</text>`;
                        }
                    }
                    return s;
                },

                get hoverInner() {
                    if (this.hover === null) return '';
                    const g = this.geom(), p = this.d.points[this.hover], x = g.X(this.hover);
                    let s = `<line class='at-guide' x1='${x}' x2='${x}' y1='${g.P.t}' y2='${g.P.t + g.ih}'/>`;
                    this.keys.forEach(k => { s += `<circle class='at-hdot' cx='${x}' cy='${g.YL(this.val(k, p))}' r='5.5' fill='${this.col(k)}'/>`; });
                    if (this.show.stok && p.stok !== null) s += `<circle class='at-hdot' cx='${x}' cy='${g.YS(p.stok)}' r='5' fill='#0ea5e9'/>`;
                    return s;
                },

                move(e) {
                    if (!this.n) return;
                    const t = e.touches ? e.touches[0] : e;
                    const r = this.$refs.wrap.getBoundingClientRect(), g = this.geom();
                    const i = this.n < 2 ? 0 : Math.round((t.clientX - r.left - g.P.l) / g.iw * (this.n - 1));
                    this.hover = Math.max(0, Math.min(this.n - 1, i));
                },
                tipStyle() {
                    if (this.hover === null) return '';
                    const x = this.geom().X(this.hover);
                    return { left: x + 'px', transform: x > this.w * 0.58 ? 'translateX(calc(-100% - 16px))' : 'translateX(16px)' };
                },
                rows(p) {
                    return ['kiloan', 'petian', 'bentes', 'lainnya']
                        .map(k => ({ k, label: this.d.jenis[k].label, color: this.d.jenis[k].color, omset: p.jenis[k].omset, persen: p.jenis[k].persen, qty: p.jenis[k].qty, dp: p.jenis[k].delta_persen }))
                        .filter(r => r.omset > 0)
                        .sort((a, b) => b.omset - a.omset);
                },
                stokRows(p) {
                    if (!p || !p.stok_jenis) return [];
                    return ['kiloan', 'petian', 'bentes', 'lainnya']
                        .filter(k => k !== 'lainnya' || p.stok_jenis[k] !== 0)
                        .map(k => ({ k, label: this.d.jenis[k].label, color: this.d.jenis[k].color, v: p.stok_jenis[k] }));
                },

                rp(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID') },
                rps(v) { return (v < 0 ? '−' : '') + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID') },
                labaRows(p) {
                    return ['kiloan', 'petian', 'bentes', 'lainnya']
                        .map(k => ({ k, label: this.d.jenis[k].label, color: this.d.jenis[k].color, omset: p.jenis[k].omset, hpp: p.jenis[k].hpp, laba: p.jenis[k].laba, margin: p.jenis[k].margin }))
                        .filter(r => r.omset > 0)
                        .sort((a, b) => b.laba - a.laba);
                },
                num(v) { return Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 }) },
                pc(v) { return Number(v).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' },
                pcs(v) { return (v === null || v === undefined) ? '—' : (v > 0 ? '▲ ' : v < 0 ? '▼ ' : '') + Math.abs(v).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%' },
                cls(v) { return (v === null || v === undefined || v === 0) ? 'at-flat' : (v > 0 ? 'at-up' : 'at-down') },
                rpk(v) {
                    const a = Math.abs(v), f = (x, d) => x.toLocaleString('id-ID', { maximumFractionDigits: d });
                    if (a >= 1e9) return f(v / 1e9, 1) + ' M';
                    if (a >= 1e6) return f(v / 1e6, 1) + ' jt';
                    if (a >= 1e3) return f(v / 1e3, 0) + ' rb';
                    return f(v, 0);
                }
            }"
            @keydown.escape.window="modal = null">

            <h2 class="at-title">Tren Omset &amp; Penjualan per Jenis Telur</h2>

            <div class="at-legend">
                <button type="button" class="at-lg" :class="{ on: show.omset }" @click="show.omset = !show.omset">
                    <i style="background:#f59e0b"></i> Omset
                </button>
                <button type="button" class="at-lg" :class="{ on: show.laba }" @click="show.laba = !show.laba">
                    <i style="background:#65a30d"></i> Laba kotor
                </button>
                <template x-for="k in ['kiloan', 'petian', 'bentes']" :key="k">
                    <button type="button" class="at-lg" :class="{ on: show[k] }" @click="show[k] = !show[k]">
                        <i :style="'background:' + d.jenis[k].color"></i> <span x-text="d.jenis[k].label"></span>
                    </button>
                </template>
                <button type="button" class="at-lg" :class="{ on: show.stok }" x-show="d.hasStok" @click="show.stok = !show.stok">
                    <i style="background:#0ea5e9"></i> Stok telur
                </button>
            </div>

            <div class="at-chartwrap" x-ref="wrap"
                @mousemove="move($event)" @mouseleave="hover = null"
                @click="if (hover !== null) modal = hover"
                @touchstart.passive="move($event)" @touchmove.passive="move($event)">

                <svg :viewBox="'0 0 ' + w + ' ' + h" :width="w" :height="h">
                    <g x-html="svgInner"></g>
                    <g x-html="hoverInner"></g>
                </svg>

                <div class="at-tip" x-show="hover !== null" x-cloak :style="tipStyle()">
                    <template x-if="hp">
                        <div>
                            <div class="at-tip-h" x-text="hp.label"></div>
                            <div class="at-tip-big" x-text="rp(hp.omset)"></div>
                            <template x-for="r in rows(hp)" :key="r.k">
                                <div class="at-tip-r">
                                    <i :style="'background:' + r.color"></i>
                                    <span x-text="r.label"></span>
                                    <em x-text="rpk(r.omset)"></em>
                                    <b x-text="pc(r.persen)"></b>
                                </div>
                            </template>
                            <div class="at-tip-l">
                                <span>Laba kotor</span>
                                <span :class="hp.laba < 0 ? 'at-neg' : 'at-pos'" x-text="rps(hp.laba) + (hp.margin !== null ? ' · ' + pc(hp.margin) : '')"></span>
                            </div>
                            <div class="at-tip-f" x-show="show.stok &amp;&amp; hp.stok !== null" x-text="'Stok telur: ' + num(hp.stok)"></div>
                            <div class="at-tip-hint">Klik titik untuk detail</div>
                        </div>
                    </template>
                </div>
            </div>

            <p class="at-mu" style="margin:10px 0 0">Arahkan kursor ke grafik untuk melihat komposisi, klik untuk detail lengkap. Aktifkan jenis telur / stok lewat tombol di atas.</p>

            {{-- ═════ MODAL DETAIL ═════ --}}
            <template x-teleport="body">
                <div class="at-ov" x-show="modal !== null" x-cloak x-transition.opacity @click.self="modal = null">
                    <template x-if="mp">
                        <div class="at-modal">
                            <div class="at-mh">
                                <h3 x-text="mp.label"></h3>
                                <button type="button" class="at-x" @click="modal = null">&times;</button>
                            </div>

                            <div class="at-mbig" x-text="rp(mp.omset)"></div>
                            <div class="at-msub">
                                <span class="at-chip" :class="cls(mp.delta_persen)" x-text="pcs(mp.delta_persen)"></span>
                                <span x-text="'dibanding ' + d.banding"></span>
                                <span x-show="mp.delta !== null" x-text="'(' + (mp.delta > 0 ? '+' : '') + rp(mp.delta) + ')'"></span>
                            </div>

                            <div class="at-sec">Komposisi penjualan</div>
                            <template x-for="r in rows(mp)" :key="r.k">
                                <div class="at-row">
                                    <div class="t">
                                        <i :style="'background:' + r.color"></i>
                                        <span x-text="r.label"></span>
                                        <b x-text="pc(r.persen)"></b>
                                    </div>
                                    <div class="at-track"><div :style="'width:' + r.persen + '%;background:' + r.color"></div></div>
                                    <div class="s">
                                        <span x-text="rp(r.omset) + '  ·  qty ' + num(r.qty)"></span>
                                        <span class="at-chip" :class="cls(r.dp)" x-text="pcs(r.dp)"></span>
                                    </div>
                                </div>
                            </template>
                            <div class="at-mu" x-show="rows(mp).length === 0">Tidak ada penjualan pada periode ini.</div>

                            <div class="at-sec">Laba kotor penjualan</div>
                            <div class="at-kv"><span>Laba kotor (omset − HPP)</span><b :class="mp.laba < 0 ? 'at-neg' : 'at-pos'" x-text="rps(mp.laba)"></b></div>
                            <div class="at-kv"><span>Margin</span><b x-text="mp.margin === null ? '—' : pc(mp.margin)"></b></div>
                            <div class="at-kv"><span>HPP barang terjual</span><b x-text="rp(mp.hpp)"></b></div>
                            <div class="at-kv" x-show="mp.laba_delta !== null">
                                <span x-text="'vs ' + d.banding"></span>
                                <b :class="mp.laba_delta < 0 ? 'at-neg' : 'at-pos'" x-text="(mp.laba_delta > 0 ? '+' : '') + rps(mp.laba_delta)"></b>
                            </div>
                            <template x-for="r in labaRows(mp)" :key="'l' + r.k">
                                <div class="at-kv">
                                    <span class="at-mu" x-text="r.label"></span>
                                    <span :class="r.laba < 0 ? 'at-neg' : ''" x-text="rps(r.laba) + (r.margin !== null ? ' (' + pc(r.margin) + ')' : '')"></span>
                                </div>
                            </template>

                            <div class="at-note" x-show="mp.naik">
                                <b>Paling naik:</b> <span x-text="mp.naik ? mp.naik.label + ' (+' + rp(mp.naik.delta) + ')' : ''"></span>
                            </div>
                            <div class="at-note" x-show="mp.turun">
                                <b>Paling turun:</b> <span x-text="mp.turun ? mp.turun.label + ' (' + rp(mp.turun.delta) + ')' : ''"></span>
                            </div>

                            <template x-if="d.hasStok &amp;&amp; stokRows(mp).length">
                                <div>
                                    <div class="at-sec">Stok telur di akhir periode</div>
                                    <template x-for="r in stokRows(mp)" :key="r.k">
                                        <div class="at-kv"><span x-text="r.label"></span><b x-text="num(r.v)"></b></div>
                                    </template>
                                    <div class="at-kv" style="border-top:1px dashed var(--at-bd);margin-top:4px;padding-top:8px">
                                        <span>Total</span><b x-text="num(mp.stok)"></b>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- ════════════ LABA KOTOR PER JENIS ════════════ --}}
        <div class="at-card">
            <h2 class="at-title">Laba Kotor per Jenis</h2>
            <div class="at-lb">
                @foreach ($d['kategori'] as $k)
                    @if ($k['omset'] > 0)
                        <div class="at-lbk">
                            <div class="nm"><i style="background: {{ $k['color'] }}"></i>{{ $k['label'] }}</div>
                            <div class="big {{ $k['laba'] < 0 ? 'at-neg' : 'at-pos' }}">{{ $rps($k['laba']) }}</div>
                            <div class="kv"><span>Margin</span><b>{{ $k['margin'] === null ? '—' : number_format($k['margin'], 1, ',', '.') . '%' }}</b></div>
                            <div class="kv"><span>Omset</span><b>{{ $rp($k['omset']) }}</b></div>
                            <div class="kv"><span>HPP</span><b>{{ $rp($k['hpp']) }}</b></div>
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="at-mu" style="margin: 12px 0 0">
                Laba kotor = omset − (qty × HPP di Master Barang saat ini). Margin merah (negatif) berarti dijual di bawah HPP.
                Beban operasional (gaji, listrik, dll.) belum dikurangkan, lihat kartu laba/rugi bersih di atas.
            </p>
        </div>

        {{-- ════════════ INSIGHT OTOMATIS ════════════ --}}
        <div class="at-card">
            <h2 class="at-title">Kesimpulan Otomatis</h2>
            <ul class="at-ins">
                @foreach ($d['insight'] as $teks)
                    <li>{{ $teks }}</li>
                @endforeach
            </ul>
        </div>

        {{-- ════════════ POLA ════════════ --}}
        <div class="at-cols">
            <div class="at-card">
                <h2 class="at-title">Pola per Hari (rata-rata omset)</h2>
                @foreach ($d['hari'] as $h)
                    <div class="at-bar">
                        <span>{{ $h['nama'] }}</span>
                        <div class="tr"><i class="{{ $h['terbaik'] ? 'top' : '' }}" style="width: {{ $h['lebar'] }}%"></i></div>
                        <b>{{ $rpk($h['rata']) }}</b>
                    </div>
                @endforeach
                <div class="at-mu">Hari dengan batang paling panjang = hari terkuat di rentang ini.</div>
            </div>

            <div class="at-card">
                <h2 class="at-title">Pola per Bulan (rata-rata omset)</h2>
                @forelse ($d['bulan'] as $b)
                    <div class="at-bar">
                        <span>{{ $b['nama'] }}</span>
                        <div class="tr"><i class="{{ $b['terbaik'] ? 'top' : '' }}" style="width: {{ $b['lebar'] }}%"></i></div>
                        <b>{{ $rpk($b['rata']) }}</b>
                    </div>
                @empty
                    <div class="at-mu">Pilih rentang minimal 2 bulan penuh (mis. tombol “12 Bulan” atau “Semua”) untuk melihat bulan tertinggi, misalnya saat Natal.</div>
                @endforelse
            </div>

            <div class="at-card">
                <h2 class="at-title">Kontribusi per Jenis</h2>
                @foreach ($d['kategori'] as $k)
                    <div class="at-bar k">
                        <div class="at-row" style="margin:0">
                            <div class="t">
                                <i style="background: {{ $k['color'] }}"></i>
                                <span>{{ $k['label'] }}</span>
                                <b>{{ number_format($k['persen'], 1, ',', '.') }}%</b>
                            </div>
                            <div class="at-track"><div style="width: {{ $k['persen'] }}%; background: {{ $k['color'] }}"></div></div>
                            <div class="s">
                                <span>{{ $rp($k['omset']) }} · qty {{ $num($k['qty']) }}</span>
                                {!! $chip($k['growth']) !!}
                            </div>
                            @if ($k['key'] === 'lainnya' && count($k['barang']))
                                <div class="at-lain">
                                    <div style="display:block">Barang yang masuk kategori ini (nama tidak memuat kiloan / petian / bentes):</div>
                                    @foreach ($k['barang'] as $bl)
                                        <div><span>{{ $bl['nama'] }}</span><b>{{ $rp($bl['omset']) }} · {{ $num($bl['qty']) }} {{ $bl['satuan'] }}</b></div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ════════════ STOK TELUR & PETI ════════════ --}}
        @php
            $petiKosong = $d['stok']['peti']['kosong'];
            $petiIsi    = $d['stok']['peti']['isi'];
            $kartuTelur = array_values(array_filter($d['stok']['cards'], fn ($c) => $c['tipe'] === 'telur'));
        @endphp
        <div class="at-card">
            <h2 class="at-title">Stok Barang Telur</h2>
            @if (count($kartuTelur))
                <div class="at-stoks">
                    @foreach ($kartuTelur as $c)
                        <div class="at-stok {{ $c['menipis'] ? 'low' : '' }}">
                            <i style="background: {{ $c['color'] }}"></i>
                            <div>
                                <div class="nm">{{ $c['nama'] }}</div>
                                <div class="qty">{{ $num($c['stok']) }} <small>{{ $c['satuan'] }}</small></div>
                                <div class="tg">
                                    @if ($c['menipis'])
                                        <span class="at-chip at-down">Menipis</span>
                                    @endif
                                    @unless ($c['ruko'])
                                        <span class="at-chip at-flat">Di pabrik</span>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="at-mu">Belum ada barang bernama “telur” yang terhubung ke akun (sub anak akun) di Master Barang.</div>
            @endif

            <div class="at-sub">Stok Peti</div>
            <div class="at-stoks">
                {{-- Peti kosong --}}
                @forelse ($petiKosong as $c)
                    <div class="at-stok {{ $c['menipis'] ? 'low' : '' }}">
                        <i style="background: #f59e0b"></i>
                        <div>
                            <div class="nm">Peti Kosong · {{ $c['nama'] }}</div>
                            <div class="qty">{{ $num($c['stok']) }} <small>{{ $c['satuan'] }}</small></div>
                            @if ($c['menipis'])
                                <div class="tg"><span class="at-chip at-down">Menipis</span></div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="at-stok">
                        <i style="background: #f59e0b"></i>
                        <div>
                            <div class="nm">Peti Kosong</div>
                            <div class="qty">—</div>
                            <div class="at-mu">Barang belum ditemukan. Pastikan di Master Barang ada barang bernama memuat “peti kosong” dan sudah terhubung ke akun.</div>
                        </div>
                    </div>
                @endforelse

                {{-- Peti isi telur --}}
                @forelse ($petiIsi as $c)
                    <div class="at-stok {{ $c['menipis'] ? 'low' : '' }}">
                        <i style="background: #6366f1"></i>
                        <div>
                            <div class="nm">Peti Isi Telur · {{ $c['nama'] }}</div>
                            <div class="qty">{{ $num($c['stok']) }} <small>{{ $c['satuan'] }}</small></div>
                            @if ($d['stok']['peti']['isi_fallback'])
                                <div class="at-mu">Diambil dari stok Telur Petian</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="at-stok">
                        <i style="background: #6366f1"></i>
                        <div>
                            <div class="nm">Peti Isi Telur</div>
                            <div class="qty">—</div>
                            <div class="at-mu">Barang belum ditemukan. Pastikan ada barang bernama memuat “peti isi” atau Telur Petian yang terhubung ke akun.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ════════════ HARGA PATOK vs HARGA ASLI (admin) ════════════ --}}
        @if (!empty($hargaData))
            {{-- di HP (<=700px) dipindah ke paling atas lewat CSS order --}}
            <div class="at-harga-wrap">
                @include('filament.pages.partials.harga-patok-vs-asli')
            </div>
        @endif

    </div>
</x-filament-panels::page>