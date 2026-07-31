<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        /* Styling khusus Microsoft Excel Rendering */
        .title {
            font-size: 16px;
            font-weight: bold;
            color: #107C41;
        }

        .header-info {
            font-size: 10px;
            color: #333333;
        }

        .th-main {
            background-color: #27272A;
            color: #FFFFFF;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000000;
        }

        .th-kandang {
            background-color: #F4F4F5;
            color: #000000;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #D4D4D8;
        }

        .th-pakan {
            background-color: #E4E4E7;
            color: #52525B;
            font-size: 9px;
            text-align: center;
            border: 1px solid #D4D4D8;
        }

        .th-sub {
            background-color: #FAFAFA;
            color: #52525B;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
            border: 1px solid #D4D4D8;
        }

        .td-cell {
            text-align: center;
            border: 1px solid #E4E4E7;
            font-family: monospace;
        }

        .td-no {
            background-color: #F4F4F5;
            font-weight: bold;
            text-align: center;
            border: 1px solid #D4D4D8;
        }

        .tf-total {
            background-color: #E4E4E7;
            font-weight: bold;
            text-align: center;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            border-left: 1px solid #D4D4D8;
            border-right: 1px solid #D4D4D8;
        }

        .bg-korektor {
            background-color: #ECFDF5;
            color: #047857;
            font-weight: bold;
        }

        .bg-kandang {
            background-color: #F0F9FF;
            color: #0369A1;
            font-weight: bold;
        }

        .bg-selisih-success {
            background-color: #D1FAE5;
            color: #065F46;
            font-weight: bold;
        }

        .bg-selisih-warning {
            background-color: #FEF3C7;
            color: #92400E;
            font-weight: bold;
        }

        .bg-selisih-danger {
            background-color: #FEE2E2;
            color: #991B1B;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Title & Metadata -->
    <table>
        <tr>
            <td colspan="4" class="title">LAPORAN PRODUKSI TELUR HARIAN</td>
        </tr>
        <tr>
            <td class="header-info"><b>Tanggal Laporan:</b></td>
            <td>{{ $tanggal }}</td>
        </tr>
        <tr>
            <td></td>
        </tr>
    </table>

    <!-- Matriks produksi harian -->
    <table>
        <thead>
            <!-- Baris 1: Nama Kandang -->
            <tr>
                <th class="th-main" rowspan="2">NO</th>
                @foreach($kandangs as $kandang)
                <th class="th-kandang" colspan="3">{{ strtoupper($kandang['nama_kandang']) }}</th>
                @endforeach
            </tr>
            <!-- Baris 2: Nama Pakan -->
            <tr>
                @foreach($kandangs as $kandang)
                <th class="th-pakan" colspan="3">Pakan: {{ $kandangPakanNama[$kandang['id']] ?? '-' }}</th>
                @endforeach
            </tr>
            <!-- Baris 3: Sub Kolom (Butir, Kilo, Tray) -->
            <tr>
                <th></th>
                @foreach($kandangs as $kandang)
                <th class="th-sub">BUTIR</th>
                <th class="th-sub">KILO (KG)</th>
                <th class="th-sub">TRAY</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++)
                <tr>
                <td class="td-no">{{ $rowIdx + 1 }}</td>
                @foreach($kandangs as $kandang)
                @php $idKandang = $kandang['id']; @endphp
                <td class="td-cell">{{ number_format($gridData[$idKandang][$rowIdx]['butir'] ?? 0) }}</td>
                <td class="td-cell">{{ number_format($gridData[$idKandang][$rowIdx]['kilo'] ?? 0, 1) }}</td>
                <td class="td-cell">{{ number_format($gridData[$idKandang][$rowIdx]['tray'] ?? 0, 1) }}</td>
                @endforeach
                </tr>
                @endfor
        </tbody>
        <tfoot>
            <tr>
                <td class="tf-total">TOTAL</td>
                @foreach($kandangs as $kandang)
                @php $idKandang = $kandang['id']; @endphp
                <td class="tf-total">{{ number_format($kandangTotals[$idKandang]['butir'] ?? 0) }}</td>
                <td class="tf-total">{{ number_format($kandangTotals[$idKandang]['kilo'] ?? 0, 1) }}</td>
                <td class="tf-total">{{ number_format($kandangTotals[$idKandang]['tray'] ?? 0, 1) }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>

    <table>
        <tr>
            <td></td>
        </tr>
    </table>

    <!-- Section Hasil Analisa Korektor -->
    <table>
        <thead>
            <tr>
                <th colspan="4" style="background-color: #18181B; color: #FFFFFF; font-weight: bold; text-align: left;">HASIL ANALISA KOREKTOR GUDANG</th>
            </tr>
            <tr style="background-color: #F4F4F5; font-weight: bold;">
                <th style="border: 1px solid #D4D4D8;">KOMPONEN / ITEM</th>
                <th style="border: 1px solid #D4D4D8; text-align: center;">NILAI INPUT</th>
                <th style="border: 1px solid #D4D4D8; text-align: center;">SATUAN</th>
                <th style="border: 1px solid #D4D4D8; text-align: right;">HASIL KONVERSI (KG)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border: 1px solid #E4E4E7;">1. Peti (@10 Kg/Pt)</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">{{ $korektorPeti ?? 0 }}</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">Pt</td>
                <td style="border: 1px solid #E4E4E7; text-align: right;">{{ number_format(($korektorPeti ?? 0) * 10, 1) }} Kg</td>
            </tr>
            <tr>
                <td style="border: 1px solid #E4E4E7;">2. Kiloan</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">{{ number_format($korektorKiloan ?? 0, 1) }}</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">Kg</td>
                <td style="border: 1px solid #E4E4E7; text-align: right;">{{ number_format($korektorKiloan ?? 0, 1) }} Kg</td>
            </tr>
            <tr>
                <td style="border: 1px solid #E4E4E7;">3. Sisa</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">{{ number_format($korektorSisa ?? 0, 1) }}</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">Kg</td>
                <td style="border: 1px solid #E4E4E7; text-align: right;">{{ number_format($korektorSisa ?? 0, 1) }} Kg</td>
            </tr>
            <tr>
                <td style="border: 1px solid #E4E4E7;">4. Bentes (Retak)</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">{{ number_format($korektorBentes ?? 0, 1) }}</td>
                <td style="border: 1px solid #E4E4E7; text-align: center;">Kg</td>
                <td style="border: 1px solid #E4E4E7; text-align: right;">{{ number_format($korektorBentes ?? 0, 1) }} Kg</td>
            </tr>
            <!-- Total Korektor -->
            <tr class="bg-korektor">
                <td style="border: 1px solid #D4D4D8;">Total Korektor</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">{{ number_format($korektorTotalKg, 1) }}</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">Kg</td>
                <td style="border: 1px solid #D4D4D8; text-align: right;">{{ number_format($korektorTotalKg, 1) }} Kg</td>
            </tr>
            <!-- Dari Kandang -->
            <tr class="bg-kandang">
                <td style="border: 1px solid #D4D4D8;">Dari Kandang</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">{{ number_format($grandTotal['kilo'], 1) }}</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">Kg</td>
                <td style="border: 1px solid #D4D4D8; text-align: right;">{{ number_format($grandTotal['kilo'], 1) }} Kg</td>
            </tr>
            <!-- Selisih -->
            @php
            $color = $statusKorektor['color'] ?? 'info';
            $selisihClass = match($color) {
            'success' => 'bg-selisih-success',
            'warning' => 'bg-selisih-warning',
            'danger' => 'bg-selisih-danger',
            default => 'bg-kandang',
            };
            @endphp
            <tr class="{{ $selisihClass }}">
                <td style="border: 1px solid #D4D4D8;">Selisih Margin</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }}</td>
                <td style="border: 1px solid #D4D4D8; text-align: center;">Kg</td>
                <td style="border: 1px solid #D4D4D8; text-align: right;">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }} Kg</td>
            </tr>
            <tr>
                <td style="border: 1px solid #E4E4E7; font-weight: bold;">Status Rekonsiliasi</td>
                <td colspan="3" style="border: 1px solid #E4E4E7;"><b>{{ $statusKorektor['label'] ?? '-' }}</b> ({{ $statusKorektor['desc'] ?? '' }})</td>
            </tr>
            @if($korektorCatatan)
            <tr>
                <td style="border: 1px solid #E4E4E7; font-weight: bold;">Catatan Korektor</td>
                <td colspan="3" style="border: 1px solid #E4E4E7;">{{ $korektorCatatan }}</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>

</html>