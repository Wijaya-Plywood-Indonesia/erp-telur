<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>Bukti Mutasi {{ $pengajuan->no_dokumen }}</title>

        <style>
            /* THERMAL 57mm – sama dengan nota penjualan */
            @page {
                size: 57mm auto;
                margin: 0;
            }

            body {
                margin: 0;
                padding: 0;
                font-family: "Courier New", Consolas, monospace;
                font-size: 11px;
                font-weight: 800;
                line-height: 1.35;
                letter-spacing: -0.2px;
                color: #000;
            }

            @media screen {
                body { background: #eee; }
                .thermal {
                    width: 57mm;
                    background: #fff;
                    margin: 12px auto;
                    padding: 4px;
                    box-shadow: 0 0 6px rgba(0, 0, 0, 0.3);
                }
            }

            @media print {
                .thermal {
                    width: 57mm;
                    margin: 0;
                    padding: 0 2mm;
                    box-sizing: border-box;
                    box-shadow: none;
                }
            }

            * {
                text-shadow: 0.35px 0 0 #000, -0.35px 0 0 #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .text-center { text-align: center; }
            .text-right  { text-align: right; }
            .bold  { font-weight: 900; font-size: 12px; }
            .qty   { font-weight: 900; font-size: 14px; }
            .small { font-size: 10px; font-weight: 700; }

            hr {
                border: none;
                border-top: 1px dashed #000;
                margin: 4px 0;
            }

            table { width: 100%; border-collapse: collapse; }
            td { padding: 2px 0; vertical-align: top; }

            .ttd { margin-top: 6px; }
            .ttd td { width: 50%; text-align: center; font-size: 10px; }
            .ttd .space { height: 48px; }
            .ttd .garis { border-top: 1px solid #000; margin: 0 4px; padding-top: 2px; }
        </style>
    </head>

    <body onload="window.print()">
        <div class="thermal">
            <div style="letter-spacing: 2px" class="text-center bold">BUKTI MUTASI TELUR</div>
            <div style="letter-spacing: 2px" class="text-center bold">RUKO INA</div>
            <div class="text-center small">------------------------------</div>

            No  : {{ $pengajuan->no_dokumen }}<br />
            Tgl : {{ $pengajuan->tanggal->format('d-m-Y') }}<br />
            Dari: Ruko<br />
            Ke  : {{ $tujuan }}

            <hr />

            <table>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td class="text-right qty">
                            {{ rtrim(rtrim(number_format($item['qty'], 2, ',', '.'), '0'), ',') }}
                            {{ $item['satuan'] }}
                        </td>
                    </tr>
                @endforeach
            </table>

            @if ($pengajuan->keterangan)
                <hr />
                <div class="small">Ket: {{ $pengajuan->keterangan }}</div>
            @endif

            <hr />

            <table class="ttd">
                <tr>
                    <td>Pengirim</td>
                    <td>Penerima</td>
                </tr>
                <tr>
                    <td class="space"></td>
                    <td class="space"></td>
                </tr>
                <tr>
                    <td><div class="garis">Ruko</div></td>
                    <td><div class="garis">Satpam</div></td>
                </tr>
            </table>

            <div class="text-center small" style="margin-top: 6px">
                Dicetak {{ now()->format('d-m-Y H:i') }}
            </div>
            <div style="height: 10mm"></div>
        </div>
    </body>
</html>