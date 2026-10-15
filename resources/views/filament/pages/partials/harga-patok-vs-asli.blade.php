{{-- ════════════ HARGA PATOK vs HARGA ASLI (khusus admin) ════════════ --}}
@if (!empty($hargaData))
    @php
        $hp  = $hargaData[$hargaTab] ?? $hargaData['pakan'];
        $isTelur = $hargaTab === 'telur';

        $hf = function ($v) {
            $v = (float) $v;
            return number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2, ',', '.');
        };
        $hrp  = fn ($v) => 'Rp ' . $hf($v);
        $hsg  = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . $hf(abs($v));
        $tcls = ['good' => 'at-up', 'bad' => 'at-down', 'flat' => 'at-flat'];
        $tclr = ['good' => '#059669', 'bad' => '#dc2626', 'flat' => 'var(--at-mu)'];
        $lbl  = function ($pos) { return max(12, min(88, $pos)); };

        // Kalimat penjelas hasil (bahasa sehari-hari)
        if ($hp['tone'] === 'flat') {
            $verdict = $isTelur ? 'Penjualan telur sama dengan harga jual patok.' : 'Pembelian sama dengan harga patok.';
        } elseif (!$isTelur) {
            $verdict = $hp['selisih'] < 0
                ? 'Secara total, pembelian lebih HEMAT ' . $hrp(abs($hp['selisih'])) . ' dibanding harga patok (kelebihan).'
                : 'Secara total, pembelian lebih MAHAL ' . $hrp(abs($hp['selisih'])) . ' dibanding harga patok (kerugian).';
        } else {
            $verdict = $hp['selisih'] < 0
                ? 'Secara total, telur terjual ' . $hrp(abs($hp['selisih'])) . ' DI BAWAH harga jual patok (karena potongan / harga disesuaikan).'
                : 'Secara total, telur terjual ' . $hrp(abs($hp['selisih'])) . ' DI ATAS harga jual patok.';
        }
    @endphp

    <style>
        .hp-head { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; }
        .hp-head .at-title { margin-bottom: 4px; }
        .hp-help { font-size: 12px; color: var(--at-mu); line-height: 1.55; max-width: 640px; }
        .hp-help b { color: var(--at-tx); }

        .hp-sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px; }
        .hp-sum > div { background: var(--at-soft); border-radius: 12px; padding: 12px 14px; }
        .hp-sum .lb { font-size: 11px; color: var(--at-mu); margin-bottom: 4px; }
        .hp-sum .vl { font-size: 19px; font-weight: 800; line-height: 1.2; }

        .hp-verdict { display: flex; gap: 10px; align-items: center; border-radius: 12px; padding: 11px 14px; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
        .hp-verdict.good { background: rgba(16,185,129,.12); color: #059669; }
        .hp-verdict.bad  { background: rgba(239,68,68,.12);  color: #dc2626; }
        .hp-verdict.flat { background: var(--at-soft); color: var(--at-mu); }
        .dark .hp-verdict.good { color: #34d399; } .dark .hp-verdict.bad { color: #f87171; }

        .hp-list { display: flex; flex-direction: column; gap: 12px; }
        .hp-item { border: 1px solid var(--at-bd); border-radius: 14px; padding: 14px 16px; }
        .hp-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .hp-nm { font-size: 15px; font-weight: 800; }
        .hp-nm small { font-size: 12px; font-weight: 600; color: var(--at-mu); }
        .hp-total { text-align: right; }
        .hp-total .vl { font-size: 17px; font-weight: 800; }
        .hp-total .lb { font-size: 11px; color: var(--at-mu); }

        .hp-cmp { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin: 12px 0 2px; }
        .hp-cmp .box .lb { font-size: 11px; color: var(--at-mu); }
        .hp-cmp .box .vl { font-size: 22px; font-weight: 800; line-height: 1.15; }
        .hp-cmp .arrow { color: var(--at-mu); font-size: 18px; }

        /* penggaris harga */
        .hp-ruler { position: relative; height: 80px; margin: 6px 14px 8px; }
        .hp-line { position: absolute; left: 0; right: 0; top: 38px; height: 4px; border-radius: 99px; background: var(--at-soft); }
        .hp-band { position: absolute; top: 35px; height: 10px; border-radius: 99px; background: rgba(245,158,11,.28); }
        .hp-tick { position: absolute; top: 28px; width: 2px; height: 20px; background: var(--at-mu); transform: translateX(-1px); }
        .hp-dot  { position: absolute; top: 30px; width: 16px; height: 16px; border-radius: 99px; background: var(--at-amber); border: 3px solid var(--at-card); box-shadow: 0 0 0 1px var(--at-amber); transform: translateX(-8px); }
        .hp-lab  { position: absolute; transform: translateX(-50%); font-size: 10px; font-weight: 700; white-space: nowrap; text-align: center; line-height: 1.2; }
        .hp-lab.up { top: 0; color: var(--at-mu); }
        .hp-lab.dn { top: 54px; color: #b45309; }
        .dark .hp-lab.dn { color: #fbbf24; }

        .hp-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 12px; color: var(--at-mu); margin-top: 4px; }
        .hp-meta b { color: var(--at-tx); font-weight: 600; }

        .hp-det { margin-top: 10px; border-top: 1px dashed var(--at-bd); padding-top: 8px; }
        .hp-det summary { cursor: pointer; font-size: 12px; font-weight: 700; color: var(--at-mu); list-style: none; }
        .hp-det summary::-webkit-details-marker { display: none; }
        .hp-det summary::before { content: '▸ '; }
        .hp-det[open] summary::before { content: '▾ '; }
        .hp-tbl-wrap { overflow-x: auto; margin-top: 8px; }
        .hp-tbl { width: 100%; border-collapse: collapse; font-size: 12px; }
        .hp-tbl th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: var(--at-mu); padding: 4px 8px; border-bottom: 1px solid var(--at-bd); }
        .hp-tbl td { padding: 5px 8px; border-bottom: 1px solid var(--at-grid); }
        .hp-tbl .r { text-align: right; }

        .hp-mob { display: none; }
        .hp-link { color: #b45309; font-weight: 700; text-decoration: underline; }
        .dark .hp-link { color: #fbbf24; }
        .hp-how { margin-top: 4px; }
        .hp-how summary { cursor: pointer; font-size: 12px; font-weight: 700; color: var(--at-mu); list-style: none; }
        .hp-how summary::-webkit-details-marker { display: none; }
        .hp-how summary::before { content: 'ⓘ '; }

        /* ═══════ TAMPILAN HP ═══════ */
        @media (max-width: 700px) {
            .hp-desk { display: none; }
            .hp-mob { display: block; }
            .hp-card { padding: 14px 12px; }
            .hp-head { gap: 10px; margin-bottom: 12px; }
            .hp-head > div:first-child { width: 100%; }
            .hp-head .at-seg { width: 100%; display: flex; }
            .hp-head .at-seg button { flex: 1; padding: 8px 6px; font-size: 13px; }

            /* ringkasan: selisih jadi sorotan utama, 2 lainnya berdampingan */
            .hp-sum { grid-template-columns: 1fr 1fr; gap: 8px; }
            .hp-sum > div { padding: 10px 12px; }
            .hp-sum > div:nth-child(3) { grid-column: 1 / -1; order: -1; text-align: center; padding: 14px 12px; }
            .hp-sum > div:nth-child(3) .vl { font-size: 26px; }
            .hp-sum .vl { font-size: 15px; }
            .hp-verdict { font-size: 12px; padding: 10px 12px; align-items: flex-start; line-height: 1.45; }

            .hp-list { gap: 10px; }
            .hp-item { padding: 12px; border-radius: 12px; }
            .hp-nm { font-size: 15px; }
            .hp-total { text-align: left; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; width: 100%; padding: 6px 10px; border-radius: 8px; background: var(--at-soft); }
            .hp-total .vl { font-size: 16px; }

            /* Patok → Asli satu baris, chip di bawahnya */
            .hp-cmp { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px 8px; align-items: end; margin-top: 10px; }
            .hp-cmp .box .vl { font-size: 17px; }
            .hp-cmp .arrow { font-size: 16px; padding-bottom: 2px; }
            .hp-cmp .at-chip { grid-column: 1 / -1; justify-self: start; }

            .hp-ruler { margin: 6px 22px 8px; }
            .hp-meta { flex-direction: column; gap: 3px; }
        }
    </style>

    <div class="at-card hp-card">
        <div class="hp-head">
            <div>
                <h2 class="at-title">Harga Patok vs Harga Asli</h2>
                @php
                    $periode = \Carbon\Carbon::parse($hargaData['dari'])->format('d/m/Y') . ' – ' . \Carbon\Carbon::parse($hargaData['sampai'])->format('d/m/Y');
                    $helpHtml = $isTelur
                        ? '<b>Patok</b> = harga jual di master barang (yang tampil di POS). <b>Asli</b> = rata-rata harga jual yang benar-benar diterima setelah potongan. Telur tidak dibeli dari supplier, jadi yang dibandingkan adalah harga <b>jual</b>.'
                        : '<b>Patok</b> = harga beli di master barang (yang dipakai website). <b>Asli</b> = rata-rata harga di nota pembelian (total nilai nota ÷ total qty, tanpa ongkir).';
                @endphp
                {{-- Desktop --}}
                <div class="hp-help hp-desk">
                    {!! $helpHtml !!}
                    Periode mengikuti filter tanggal di atas: <b>{{ $periode }}</b>.
                </div>
                {{-- HP: ringkas --}}
                <div class="hp-mob">
                    <div class="hp-help">Periode: <b>{{ $periode }}</b> · <a href="#at-filter" class="hp-link">Ubah</a></div>
                    <details class="hp-how">
                        <summary>Cara membaca</summary>
                        <div class="hp-help">{!! $helpHtml !!}</div>
                    </details>
                </div>
            </div>
            <div class="at-seg">
                <button type="button" wire:click="setHargaTab('pakan')" class="{{ !$isTelur ? 'on' : '' }}">Pakan &amp; Bahan</button>
                <button type="button" wire:click="setHargaTab('telur')" class="{{ $isTelur ? 'on' : '' }}">Telur</button>
            </div>
        </div>

        @if (count($hp['items']) === 0)
            <div class="at-mu" style="padding: 18px 0; text-align: center;">
                {{ $isTelur ? 'Belum ada penjualan telur pada periode ini.' : 'Belum ada nota pembelian pada periode ini.' }}
            </div>
        @else
            {{-- Ringkasan --}}
            <div class="hp-sum">
                <div>
                    <div class="lb">{{ $isTelur ? 'Hasil penjualan sebenarnya' : 'Yang dibayar ke supplier (harga asli)' }}</div>
                    <div class="vl">{{ $hrp($hp['aktual']) }}</div>
                </div>
                <div>
                    <div class="lb">{{ $isTelur ? 'Jika sesuai harga jual patok' : 'Jika dihitung harga patok' }}</div>
                    <div class="vl">{{ $hrp($hp['patok']) }}</div>
                </div>
                <div>
                    <div class="lb">Selisih</div>
                    <div class="vl" style="color: {{ $tclr[$hp['tone']] }}">{{ $hsg($hp['selisih']) }}</div>
                </div>
            </div>

            <div class="hp-verdict {{ $hp['tone'] }}">
                <span>{{ $hp['tone'] === 'good' ? '✔' : ($hp['tone'] === 'bad' ? '⚠' : '•') }}</span>
                <span>{{ $verdict }}</span>
            </div>

            {{-- Daftar barang --}}
            <div class="hp-list">
                @foreach ($hp['items'] as $it)
                    @php $rl = $it['ruler']; @endphp
                    <div class="hp-item">
                        <div class="hp-top">
                            <div>
                                <div class="hp-nm">{{ $it['nama'] }} <small>/ {{ $it['satuan'] }}</small></div>
                                <div class="hp-meta">
                                    <span>{{ $isTelur ? 'Terjual' : 'Dibeli' }} <b>{{ $hf($it['qty']) }} {{ $it['satuan'] }}</b></span>
                                    <span><b>{{ $it['nota'] }}</b> nota</span>
                                </div>
                            </div>
                            <div class="hp-total">
                                <div class="vl" style="color: {{ $tclr[$it['tone']] }}">{{ $hsg($it['selisih_nilai']) }}</div>
                                <div class="lb">
                                    selisih total
                                    @if ($it['tone'] === 'good') · {{ $isTelur ? 'di atas patok' : 'hemat' }}
                                    @elseif ($it['tone'] === 'bad') · {{ $isTelur ? 'di bawah patok' : 'lebih mahal' }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="hp-cmp">
                            <div class="box">
                                <div class="lb">Patok</div>
                                <div class="vl">{{ $hrp($it['patok']) }}</div>
                            </div>
                            <div class="arrow">→</div>
                            <div class="box">
                                <div class="lb">Asli (rata-rata)</div>
                                <div class="vl" style="color: {{ $tclr[$it['tone']] }}">{{ $hrp($it['asli']) }}</div>
                            </div>
                            <span class="at-chip {{ $tcls[$it['tone']] }}">
                                {{ $hsg($it['selisih_unit']) }} / {{ $it['satuan'] }}
                                @if ($it['selisih_pct'] !== null) ({{ $hsg(round($it['selisih_pct'], 1)) }}%) @endif
                            </span>
                        </div>

                        {{-- Penggaris harga: tick abu = patok, titik oranye = asli, pita = rentang harga --}}
                        <div class="hp-ruler">
                            <div class="hp-line"></div>
                            <div class="hp-band" style="left: {{ $rl['min'] }}%; width: {{ max($rl['max'] - $rl['min'], 0.8) }}%"></div>
                            <div class="hp-tick" style="left: {{ $rl['patok'] }}%"></div>
                            <div class="hp-dot" style="left: {{ $rl['asli'] }}%"></div>
                            <div class="hp-lab up" style="left: {{ $lbl($rl['patok']) }}%">Patok<br>{{ $hf($it['patok']) }}</div>
                            <div class="hp-lab dn" style="left: {{ $lbl($rl['asli']) }}%">Asli<br>{{ $hf($it['asli']) }}</div>
                        </div>

                        <div class="hp-meta">
                            <span>Rentang harga: <b>{{ $hf($it['min']) }}{{ $it['min'] != $it['max'] ? ' – ' . $hf($it['max']) : '' }}</b></span>
                            @if ($isTelur)
                                <span>Total potongan: <b>{{ $hrp($it['potongan']) }}</b></span>
                                <span>Modal patok: <b>{{ $hrp($it['modal']) }}</b> / {{ $it['satuan'] }}</span>
                            @else
                                <span>Beli terakhir: <b>{{ $hrp($it['terakhir']) }}</b> ({{ $it['tgl_terakhir'] }})</span>
                            @endif
                        </div>

                        @unless ($isTelur)
                            <details class="hp-det">
                                <summary>Lihat {{ count($it['riwayat']) }} nota pembelian terakhir</summary>
                                <div class="hp-tbl-wrap">
                                    <table class="hp-tbl">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th><th>Nota</th><th>Supplier</th>
                                                <th class="r">Qty</th><th class="r">Harga Nota</th>
                                                <th class="r">vs Patok</th><th class="r">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($it['riwayat'] as $rw)
                                                <tr>
                                                    <td data-label="Tanggal">{{ $rw['tgl'] }}</td>
                                                    <td data-label="Nota" class="full">{{ $rw['nota'] }}</td>
                                                    <td data-label="Supplier">{{ $rw['supplier'] }}</td>
                                                    <td data-label="Qty" class="r">{{ $hf($rw['qty']) }}</td>
                                                    <td data-label="Harga nota" class="r"><b>{{ $hrp($rw['harga']) }}</b></td>
                                                    <td data-label="vs Patok" class="r" style="color: {{ $rw['vs_patok'] < 0 ? '#059669' : ($rw['vs_patok'] > 0 ? '#dc2626' : 'var(--at-mu)') }}">{{ $hsg($rw['vs_patok']) }}</td>
                                                    <td data-label="Subtotal" class="r">{{ $hrp($rw['subtotal']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endunless

                        @if ($isTelur && count($it['riwayat']) > 0)
                            <details class="hp-det">
                                <summary>Lihat {{ count($it['riwayat']) }} nota penjualan terakhir</summary>
                                <div class="hp-tbl-wrap">
                                    <table class="hp-tbl">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th><th>Nota</th><th>Customer</th>
                                                <th class="r">Qty</th><th class="r">Harga Nota</th>
                                                <th class="r">vs Patok</th><th class="r">Potongan</th>
                                                <th class="r">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($it['riwayat'] as $rw)
                                                <tr>
                                                    <td data-label="Tanggal">{{ $rw['tgl'] }}</td>
                                                    <td data-label="Nota" class="full">{{ $rw['nota'] }}</td>
                                                    <td data-label="Customer">{{ $rw['customer'] }}</td>
                                                    <td data-label="Qty" class="r">{{ $hf($rw['qty']) }}</td>
                                                    <td data-label="Harga nota" class="r"><b>{{ $hrp($rw['harga']) }}</b></td>
                                                    {{-- jual: di atas patok = hijau, di bawah = merah --}}
                                                    <td data-label="vs Patok" class="r" style="color: {{ $rw['vs_patok'] > 0 ? '#059669' : ($rw['vs_patok'] < 0 ? '#dc2626' : 'var(--at-mu)') }}">{{ $hsg($rw['vs_patok']) }}</td>
                                                    <td data-label="Potongan" class="r">{{ $rw['potongan'] > 0 ? $hrp($rw['potongan']) : '–' }}</td>
                                                    <td data-label="Subtotal" class="r">{{ $hrp($rw['subtotal']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="at-note" style="margin-top: 14px;">
                <span class="at-up at-chip">Hijau</span> = menguntungkan &nbsp;
                <span class="at-down at-chip">Merah</span> = merugikan.
                @if ($isTelur)
                    Harga pokok (modal) telur dicatat tetap di harga patok dan tidak ada nota pembelian, jadi belum ada harga asli modal telur untuk dibandingkan.
                @else
                    Hanya nota berstatus hutang, cicilan, dan lunas yang dihitung (draft &amp; batal diabaikan).
                @endif
            </div>
        @endif
    </div>
@endif