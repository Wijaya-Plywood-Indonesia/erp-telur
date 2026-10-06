<x-filament-panels::page>
    <style>
        /* Warna mengikuti tema Filament (primary = Amber, mode terang/gelap otomatis) */
        .rj {
            --rj-bg: #ffffff;
            --rj-bg2: var(--gray-50, #f9fafb);
            --rj-head: var(--gray-50, #f9fafb);
            --rj-band: var(--primary-50, #fffbeb);
            --rj-line: var(--gray-200, #e5e7eb);
            --rj-text: var(--gray-950, #030712);
            --rj-muted: var(--gray-500, #6b7280);
            --rj-accent: var(--primary-600, #d97706);
            --rj-ok: var(--success-700, #15803d);
            --rj-ok-bg: var(--success-50, #f0fdf4);
            --rj-bad: var(--danger-700, #b91c1c);
            --rj-bad-bg: var(--danger-50, #fef2f2);
            --rj-sep: var(--gray-300, #d1d5db);
            --rj-hbg: var(--primary-600, #d97706);
            --rj-htext: #ffffff;
            --rj-hline: rgba(255, 255, 255, .28);
        }
        .dark .rj {
            --rj-bg: var(--gray-900, #111827);
            --rj-bg2: rgba(255, 255, 255, .05);
            --rj-head: var(--gray-800, #1f2937);
            --rj-band: color-mix(in srgb, var(--primary-500, #f59e0b) 10%, transparent);
            --rj-line: rgba(255, 255, 255, .10);
            --rj-text: #ffffff;
            --rj-muted: var(--gray-400, #9ca3af);
            --rj-accent: var(--primary-400, #fbbf24);
            --rj-ok: var(--success-400, #4ade80);
            --rj-ok-bg: color-mix(in srgb, var(--success-500, #22c55e) 15%, transparent);
            --rj-bad: var(--danger-400, #f87171);
            --rj-bad-bg: color-mix(in srgb, var(--danger-500, #ef4444) 15%, transparent);
            --rj-sep: rgba(255, 255, 255, .22);
            --rj-hbg: var(--primary-500, #f59e0b);
            --rj-htext: var(--gray-950, #030712);
            --rj-hline: rgba(0, 0, 0, .18);
        }
        .rj { color:var(--rj-text); font-size:13px; }
        .rj-bar, .rj-card, .rj-wrap { background:var(--rj-bg); border:1px solid var(--rj-line); border-radius:12px; box-shadow:0 1px 2px rgba(0,0,0,.05); }
        .rj-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; padding:14px 16px; margin-bottom:14px; }
        .rj-field { display:flex; flex-direction:column; gap:4px; }
        .rj-field label { font-size:12px; font-weight:600; color:var(--rj-muted); }
        .rj-input { padding:7px 10px; border:1px solid var(--rj-line); border-radius:8px; background:var(--rj-bg); color:var(--rj-text); font-size:13px; outline:none; }
        .rj-input:focus { border-color:var(--rj-accent); box-shadow:0 0 0 1px var(--rj-accent); }
        .rj-btn { padding:7px 12px; border:1px solid var(--rj-line); border-radius:8px; background:var(--rj-bg); color:var(--rj-text); cursor:pointer; font-size:13px; font-weight:600; }
        .rj-btn:hover { background:var(--rj-bg2); color:var(--rj-accent); border-color:var(--rj-accent); }
        .rj-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:14px; }
        .rj-card { padding:12px 16px; }
        .rj-card .l { font-size:12px; font-weight:600; color:var(--rj-muted); }
        .rj-card .v { font-size:20px; font-weight:700; margin-top:3px; }
        .rj-sec { font-size:12px; font-weight:700; color:var(--rj-muted); margin:2px 0 8px; text-transform:uppercase; letter-spacing:.04em; }
        .rj-card .s { font-size:12px; color:var(--rj-muted); margin-top:5px; line-height:1.55; }
        .rj-card.hl { border-color:var(--rj-accent); }
        .rj .neg { color:var(--rj-bad); }
        .rj-badge { padding:2px 8px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap; }
        .rj-badge.ok { background:var(--rj-ok-bg); color:var(--rj-ok); }
        .rj-badge.bad { background:var(--rj-bad-bg); color:var(--rj-bad); }

        /* ── Tabel gaya sheet "isi jurnal" ── */
        .rj-wrap { overflow:auto; max-height:calc(100vh - 300px); min-height:200px; }
        .rj table { width:100%; border-collapse:collapse; min-width:1000px; }
        .rj thead th { position:sticky; top:0; z-index:2; background:var(--rj-hbg); color:var(--rj-htext); padding:9px 10px; font-size:13px; font-weight:700; text-align:left; white-space:nowrap; border-bottom:1px solid var(--rj-hline); border-right:1px solid var(--rj-hline); }
        .rj td { padding:5px 10px; border-bottom:1px solid var(--rj-line); border-right:1px solid var(--rj-line); vertical-align:top; }
        .rj td:last-child, .rj thead th:last-child { border-right:0; }
        .rj tbody.band td { background:var(--rj-band); }
        /* Pemisah antar jurnal: cukup 1px tipis, pembeda utama adalah warna selang-seling */
        .rj tbody.jr tr:last-child td { border-bottom:1px solid var(--rj-sep); }
        .rj .r { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .rj .c { text-align:center; white-space:nowrap; }
        .rj .mono { font-family:monospace; white-space:nowrap; }
        .rj .nm { white-space:nowrap; }
        .rj .kt { min-width:240px; }
        .rj .dim { color:var(--rj-muted); }
        .rj .kre-akun { padding-left:22px; }
        .rj tr.selisih td { background:var(--rj-bad-bg); color:var(--rj-bad); font-weight:600; }
        .rj-empty { text-align:center; padding:50px 10px; color:var(--rj-muted); }
    </style>

    @php
        // Angka 0 tampil "-" seperti di Excel
        $n = fn ($v) => (float) $v == 0 ? '-' : number_format($v, 0, ',', '.');
        $q = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    @endphp

    <div class="rj">
        <div class="rj-bar">
            <button type="button" class="rj-btn" wire:click="hariSebelumnya" title="Hari sebelumnya">&larr;</button>
            <div class="rj-field">
                <label>Tanggal</label>
                <input type="date" class="rj-input" wire:model.live="tanggal">
            </div>
            <button type="button" class="rj-btn" wire:click="hariBerikutnya" title="Hari berikutnya">&rarr;</button>
            <button type="button" class="rj-btn" wire:click="hariIni">Hari ini</button>

            <div class="rj-field">
                <label>Sumber Transaksi</label>
                <select class="rj-input" wire:model.live="sumber">
                    <option value="">Semua sumber</option>
                    @foreach($daftarSumber as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div class="rj-field" style="flex:1; min-width:180px;">
                <label>Cari (akun / keterangan / nama / no jurnal)</label>
                <input type="text" class="rj-input" placeholder="mis. kas tunai, S381, 195" wire:model.live.debounce.400ms="cari">
            </div>
        </div>

        <div style="margin-bottom:10px; font-weight:700; font-size:15px;">{{ $tanggalLabel }}</div>

        @php
            $rpn = fn ($v) => ($v < 0 ? '-' : '') . 'Rp ' . number_format(abs($v), 0, ',', '.');
            $blok = [
                'kas'  => ['judul' => 'Kas Tunai', 'data' => $kasBank['kas']],
                'bank' => ['judul' => 'Bank / Transfer', 'data' => $kasBank['bank']],
            ];
        @endphp

        <div class="rj-sec">Posisi bersih kas &amp; bank (masuk dikurangi keluar)</div>
        <div class="rj-cards">
            @foreach($blok as $b)
                <div class="rj-card">
                    <div class="l">{{ $b['judul'] }}</div>
                    <div class="v {{ $b['data']['net'] < 0 ? 'neg' : '' }}">{{ $rpn($b['data']['net']) }}</div>
                    <div class="s">
                        Masuk Rp {{ number_format($b['data']['masuk'], 0, ',', '.') }}
                        · Keluar Rp {{ number_format($b['data']['keluar'], 0, ',', '.') }}
                        @if($b['data']['akun']->count() > 1)
                            @foreach($b['data']['akun'] as $a)
                                <br>{{ $a['nama'] }}: <strong class="{{ $a['net'] < 0 ? 'neg' : '' }}">{{ $rpn($a['net']) }}</strong>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="rj-card hl">
                <div class="l">Total Kas + Bank</div>
                <div class="v {{ $kasBank['total']['net'] < 0 ? 'neg' : '' }}">{{ $rpn($kasBank['total']['net']) }}</div>
                <div class="s">
                    Masuk Rp {{ number_format($kasBank['total']['masuk'], 0, ',', '.') }}
                    · Keluar Rp {{ number_format($kasBank['total']['keluar'], 0, ',', '.') }}
                </div>
            </div>
        </div>

        <div class="rj-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama Akun</th>
                        <th>Tgl</th>
                        <th>Jurnal</th>
                        <th>No Akun</th>
                        <th>Keterangan</th>
                        <th class="c">D/K</th>
                        <th class="r">Banyak</th>
                        <th class="r">Harga</th>
                        <th class="r">Total</th>
                    </tr>
                </thead>

                @forelse($groups as $g)
                    <tbody class="jr {{ $loop->odd ? '' : 'band' }}" wire:key="rj-{{ $g['jurnal'] }}">
                        @foreach($g['lines'] as $l)
                            <tr>
                                <td class="nm {{ $l['map'] === 'k' ? 'kre-akun' : '' }}">{{ $l['nama_akun'] }}</td>
                                <td class="nm">{{ $l['tgl'] }}</td>
                                <td class="mono">{{ $l['jurnal'] }}</td>
                                <td class="mono">{{ $l['no_akun'] }}</td>
                                <td class="kt">
                                    @php
                                        // Buang segmen "| No.Nota: XXX" hanya bila XXX sama dengan nomor di kolom nama
                                        $ket = $l['keterangan'];
                                        if ($l['nama']) {
                                            $ket = preg_replace(
                                                '/\s*\|\s*No\.Nota:\s*' . preg_quote($l['nama'], '/') . '(?=\s*(?:\||$))/i',
                                                '',
                                                $ket
                                            );
                                        }
                                    @endphp
                                    @if($l['nama'])<span class="dim">{{ $l['nama'] }}</span>@endif
                                    {{ $ket }}
                                </td>
                                <td class="c">{{ $l['map'] }}</td>
                                <td class="r">{{ $q($l['banyak']) }}</td>
                                <td class="r">{{ number_format($l['harga'], 0, ',', '.') }}</td>
                                <td class="r">{{ $n($l['total']) }}</td>
                            </tr>
                        @endforeach

                        @unless($g['balance'])
                            <tr class="selisih">
                                <td colspan="8" class="r">Jurnal #{{ $g['jurnal'] }} tidak balance — selisih</td>
                                <td class="r">{{ number_format(abs($g['debet'] - $g['kredit']), 0, ',', '.') }}</td>
                            </tr>
                        @endunless
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="9" class="rj-empty">Tidak ada jurnal pada tanggal ini.</td></tr>
                    </tbody>
                @endforelse

            </table>
        </div>

        <div class="rj-cards" style="margin-top:14px; margin-bottom:0;">
            <div class="rj-card"><div class="l">Jumlah Transaksi/Jurnal</div><div class="v">{{ number_format($jumlahJurnal, 0, ',', '.') }}</div></div>
            <div class="rj-card"><div class="l">Total Debet</div><div class="v">Rp {{ number_format($totalDebet, 0, ',', '.') }}</div></div>
            <div class="rj-card"><div class="l">Total Kredit</div><div class="v">Rp {{ number_format($totalKredit, 0, ',', '.') }}</div></div>
            <div class="rj-card">
                <div class="l">Status</div>
                <div class="v">
                    @if($jumlahTidakBalance === 0)
                        <span class="rj-badge ok">Semua balance</span>
                    @else
                        <span class="rj-badge bad">{{ $jumlahTidakBalance }} jurnal tidak balance</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>