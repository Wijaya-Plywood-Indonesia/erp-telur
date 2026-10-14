@php
    $fmt = fn ($v, $d = 1) => number_format((float) $v, $d, '.', ',');
    $pakanName = function ($id) use ($allPakan) {
        foreach ($allPakan as $p) {
            if ((int) $p['id'] === (int) $id) return $p['nama_barang'];
        }
        return '-';
    };
    $val = fn ($key) => ($info[$key] ?? '') !== '' ? $info[$key] : '-';

    $peti   = (float) ($korektor['peti'] ?? 0);
    $kiloan = (float) ($korektor['kiloan'] ?? 0);
    $sisa   = (float) ($korektor['sisa'] ?? 0);
    $bentes = (float) ($korektor['bentes'] ?? 0);
    $selisih = (float) ($korektor['selisih'] ?? 0);

    $warnaStatus = match ($korektor['statusColor'] ?? 'success') {
        'success' => '#1F7A3D',
        'warning' => '#B45309',
        'danger'  => '#C0392B',
        default   => '#374151',
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Produksi Telur {{ $tanggal }}</title>
    <style>
        @page { margin: 7mm 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; line-height: 1.1; color: #111; }
        h1 { font-size: 13px; color: #1F7A3D; margin: 0 0 1px 0; }
        .sub { font-size: 8.5px; margin-bottom: 4px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 4px; }
        th, td { border: 1px solid #999; padding: 1px 2px; }
        th { background: #1B1B1B; color: #fff; text-align: center; }
        .pakan { background: #E4E4E7; color: #2F5496; font-style: italic; text-align: center; }
        .sub-h { background: #F4F4F5; font-weight: bold; text-align: center; font-size: 6.5px; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .total td { background: #EEF2F7; font-weight: bold; text-align: center; }
        .box { page-break-inside: avoid; width: 48%; display: inline-block; vertical-align: top; }
        .banner { background: #1B1B1B; color: #fff; font-weight: bold; padding: 3px 4px; font-size: 8px; }
        .korektor td, .korektor th { font-size: 7.5px; padding: 2px 3px; }
        .hijau td { background: #E6F4EA; font-weight: bold; }
        .biru td { background: #E8EEF8; font-weight: bold; }
        .selisih td { background: {{ $warnaStatus }}; color: #fff; font-weight: bold; }
        .ttd td { border: none; text-align: center; font-size: 8px; padding-top: 2px; }
        .ttd .space { height: 26px; }
        .ttd .garis { border-top: 1px solid #000; margin: 0 14px; padding-top: 2px; }
    </style>
</head>
<body>
    <h1>LAPORAN PRODUKSI TELUR HARIAN</h1>
    <div class="sub"><b>Tanggal Laporan:</b> {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</div>

    {{-- ── Tabel produksi per kandang (maks. 6 kandang per tabel agar muat di kertas) ── --}}
    @foreach (array_chunk($kandangs, 6) as $chunk)
        <table>
            <thead>
                <tr>
                    <th rowspan="3" style="width: 22px">NO</th>
                    @foreach ($chunk as $k)
                        <th colspan="3">{{ strtoupper($k['nama_kandang']) }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $k)
                        <td colspan="3" class="pakan">Pakan: {{ $pakanName($kandangPakan[$k['id']] ?? null) }}</td>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($chunk as $k)
                        <td class="sub-h">BUTIR</td><td class="sub-h">KILO (KG)</td><td class="sub-h">TRAY</td>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @for ($i = 0; $i < 10; $i++)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        @foreach ($chunk as $k)
                            @php($d = $gridData[$k['id']][$i] ?? [])
                            <td class="r">{{ (int) ($d['butir'] ?? 0) }}</td>
                            <td class="r">{{ $fmt($d['kilo'] ?? 0) }}</td>
                            <td class="r">{{ $fmt($d['tray'] ?? 0) }}</td>
                        @endforeach
                    </tr>
                @endfor
                <tr class="total">
                    <td>TOTAL</td>
                    @foreach ($chunk as $k)
                        @php($t = $kandangTotals[$k['id']] ?? [])
                        <td class="r">{{ (int) ($t['butir'] ?? 0) }}</td>
                        <td class="r">{{ $fmt($t['kilo'] ?? 0) }}</td>
                        <td class="r">{{ $fmt($t['tray'] ?? 0) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="sub">
        <b>TOTAL SEMUA KANDANG:</b>
        {{ number_format((int) ($grandTotal['butir'] ?? 0)) }} butir &nbsp;|&nbsp;
        {{ $fmt($grandTotal['kilo'] ?? 0) }} Kg &nbsp;|&nbsp;
        {{ $fmt($grandTotal['tray'] ?? 0) }} Tray
    </div>

    {{-- ── Analisa korektor + informasi penginput ── --}}
    <div class="box">
        <div class="banner">HASIL ANALISA KOREKTOR GUDANG</div>
        <table class="korektor">
            <thead>
                <tr><th style="text-align:left">KOMPONEN / ITEM</th><th>NILAI INPUT</th><th>SATUAN</th><th>HASIL (KG)</th></tr>
            </thead>
            <tbody>
                <tr><td>1. Peti (@10 Kg/Pt)</td><td class="r">{{ $fmt($peti) }}</td><td class="c">Pt</td><td class="r">{{ $fmt($peti * 10) }}</td></tr>
                <tr><td>2. Kiloan</td><td class="r">{{ $fmt($kiloan) }}</td><td class="c">Kg</td><td class="r">{{ $fmt($kiloan) }}</td></tr>
                <tr><td>3. Sisa</td><td class="r">{{ $fmt($sisa) }}</td><td class="c">Kg</td><td class="r">{{ $fmt($sisa) }}</td></tr>
                <tr><td>4. Bentes (Retak)</td><td class="r">{{ $fmt($bentes) }}</td><td class="c">Kg</td><td class="r">{{ $fmt($bentes) }}</td></tr>
                <tr class="hijau"><td>Total Korektor</td><td class="r">{{ $fmt($korektor['total'] ?? 0) }}</td><td class="c">Kg</td><td class="r">{{ $fmt($korektor['total'] ?? 0) }}</td></tr>
                <tr class="biru"><td>Dari Kandang</td><td class="r">{{ $fmt($korektor['dariKandang'] ?? 0) }}</td><td class="c">Kg</td><td class="r">{{ $fmt($korektor['dariKandang'] ?? 0) }}</td></tr>
                <tr class="selisih"><td>Selisih</td><td class="r">{{ ($selisih > 0 ? '+' : '') . $fmt($selisih) }}</td><td class="c">Kg</td><td class="r">{{ ($selisih > 0 ? '+' : '') . $fmt($selisih) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="box" style="margin-left: 2%">
        <div class="banner">INFORMASI PENGINPUT &amp; KOREKTOR</div>
        <table class="korektor">
            <tr>
                <td class="b" style="width: 38%">Diinput oleh (Produksi)</td>
                <td>{{ $val('penginput') }}</td>
            </tr>
            <tr>
                <td class="b">Korektor (Analisa)</td>
                <td>{{ $val('korektor') }}@if ($val('waktuKorektor') !== '-') <br><span style="font-size:7px;color:#555">{{ $val('waktuKorektor') }}</span>@endif</td>
            </tr>
            <tr>
                <td class="b">Divalidasi oleh</td>
                <td>{{ $val('validator') }}@if ($val('waktuValidasi') !== '-') <br><span style="font-size:7px;color:#555">{{ $val('waktuValidasi') }}</span>@endif</td>
            </tr>
        </table>

        <table class="ttd">
            <tr><td>Penginput</td><td>Korektor</td><td>Validator</td></tr>
            <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
            <tr>
                <td><div class="garis">{{ $val('penginput') }}</div></td>
                <td><div class="garis">{{ $val('korektor') }}</div></td>
                <td><div class="garis">{{ $val('validator') }}</div></td>
            </tr>
        </table>
    </div>

    <div style="font-size:6.5px;color:#777;margin-top:2px">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>