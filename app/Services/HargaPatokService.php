<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan HARGA PATOK (master barang) dengan HARGA ASLI.
 *
 *  • Pakan & bahan : harga asli = rata-rata tertimbang nota pembelian
 *                    (Σ subtotal ÷ Σ qty, tanpa ongkir).
 *  • Telur         : telur tidak dibeli dari supplier, jadi yang dibandingkan adalah
 *                    harga JUAL patok vs rata-rata harga jual aktual (setelah potongan).
 */
class HargaPatokService
{
    /** Status nota pembelian yang dianggap sah (bukan draft / batal). */
    private const STATUS_BELI = ['hutang', 'cicilan', 'lunas'];

    public function build(string $dari, string $sampai): array
    {
        return [
            'dari'   => $dari,
            'sampai' => $sampai,
            'pakan'  => $this->pakan($dari, $sampai),
            'telur'  => $this->telur($dari, $sampai),
        ];
    }

    // ═════════════════════════════════════════════════════════
    //  PAKAN & BAHAN
    // ═════════════════════════════════════════════════════════
    private function pakan(string $dari, string $sampai): array
    {
        $rows = DB::table('detail_pembelians as dp')
            ->join('pembelians as p', 'p.id', '=', 'dp.pembelian_id')
            ->join('barangs as b', 'b.id', '=', 'dp.barang_id')
            ->whereIn('p.status', self::STATUS_BELI)
            ->whereDate('p.tanggal', '>=', $dari)
            ->whereDate('p.tanggal', '<=', $sampai)
            ->orderByDesc('p.tanggal')
            ->orderByDesc('p.id')
            ->get([
                'dp.barang_id', 'b.nama_barang', 'b.harga_beli as patok', 'dp.satuan',
                'p.tanggal', 'p.nomor_nota', 'p.supplier_name',
                'dp.qty', 'dp.harga_beli as harga_nota', 'dp.subtotal',
            ]);

        $items = [];
        $aktual = 0.0;
        $patokTotal = 0.0;

        foreach ($rows->groupBy('barang_id') as $id => $g) {
            $terbaru = $g->first();
            $patok   = (float) $terbaru->patok;
            $qty     = (float) $g->sum('qty');
            $nilai   = (float) $g->sum('subtotal');
            $avg     = $qty > 0 ? $nilai / $qty : 0.0;
            $min     = (float) $g->min('harga_nota');
            $max     = (float) $g->max('harga_nota');
            $selisih = $avg - $patok;

            $aktual     += $nilai;
            $patokTotal += $qty * $patok;

            $items[] = [
                'id'            => (int) $id,
                'nama'          => $terbaru->nama_barang,
                'satuan'        => $terbaru->satuan,
                'patok'         => $patok,
                'asli'          => $avg,
                'selisih_unit'  => $selisih,
                'selisih_pct'   => $patok > 0 ? $selisih / $patok * 100 : null,
                // beli lebih murah dari patok = baik
                'tone'          => abs($selisih) < 0.005 ? 'flat' : ($selisih < 0 ? 'good' : 'bad'),
                'qty'           => $qty,
                'nota'          => $g->pluck('nomor_nota')->unique()->count(),
                'nilai'         => $nilai,
                'selisih_nilai' => $nilai - $qty * $patok,
                'min'           => $min,
                'max'           => $max,
                'terakhir'      => (float) $terbaru->harga_nota,
                'tgl_terakhir'  => Carbon::parse($terbaru->tanggal)->format('d/m/Y'),
                'ruler'         => $this->ruler($patok, $avg, $min, $max),
                'riwayat'       => $g->take(5)->map(fn ($r) => [
                    'tgl'      => Carbon::parse($r->tanggal)->format('d/m/Y'),
                    'nota'     => $r->nomor_nota,
                    'supplier' => $r->supplier_name,
                    'qty'      => (float) $r->qty,
                    'harga'    => (float) $r->harga_nota,
                    'vs_patok' => (float) $r->harga_nota - $patok,
                    'subtotal' => (float) $r->subtotal,
                ])->values()->all(),
            ];
        }

        usort($items, fn ($a, $b) => abs($b['selisih_nilai']) <=> abs($a['selisih_nilai']));

        $selisih = $aktual - $patokTotal;

        return [
            'items'   => $items,
            'aktual'  => $aktual,
            'patok'   => $patokTotal,
            'selisih' => $selisih,
            'tone'    => abs($selisih) < 1 ? 'flat' : ($selisih < 0 ? 'good' : 'bad'),
        ];
    }

    // ═════════════════════════════════════════════════════════
    //  TELUR  (harga jual patok vs harga jual aktual)
    // ═════════════════════════════════════════════════════════
    private function telur(string $dari, string $sampai): array
    {
        $rows = DB::table('penjualan_details as d')
            ->join('penjualans as p', 'p.id', '=', 'd.penjualan_id')
            ->join('barangs as b', 'b.id', '=', 'd.barang_id')
            ->where(function ($q) {
                $q->whereNull('p.status_transaksi')->orWhere('p.status_transaksi', '!=', 'DIBATALKAN');
            })
            ->where(function ($q) {
                $q->where('b.nama_barang', 'like', '%telur%')
                    ->orWhere('b.nama_barang', 'like', '%petian%')
                    ->orWhere('b.nama_barang', 'like', '%bentes%');
            })
            ->whereDate('p.tanggal', '>=', $dari)
            ->whereDate('p.tanggal', '<=', $sampai)
            ->selectRaw('
                b.id as barang_id, b.nama_barang, b.harga_beli as modal, b.harga_jual as patok,
                MAX(d.satuan) as satuan,
                COUNT(DISTINCT d.penjualan_id) as nota,
                SUM(d.qty) as qty,
                SUM(d.subtotal) as nilai,
                SUM(COALESCE(d.potongan, 0) * d.qty) as potongan,
                MIN(d.subtotal / NULLIF(d.qty, 0)) as min_h,
                MAX(d.subtotal / NULLIF(d.qty, 0)) as max_h
            ')
            ->groupBy('b.id', 'b.nama_barang', 'b.harga_beli', 'b.harga_jual')
            ->get();

        // Riwayat nota terakhir per barang (untuk tabel rincian)
        $riwayat = DB::table('penjualan_details as d')
            ->join('penjualans as p', 'p.id', '=', 'd.penjualan_id')
            ->join('barangs as b', 'b.id', '=', 'd.barang_id')
            ->where(function ($q) {
                $q->whereNull('p.status_transaksi')->orWhere('p.status_transaksi', '!=', 'DIBATALKAN');
            })
            ->whereIn('d.barang_id', $rows->pluck('barang_id'))
            ->whereDate('p.tanggal', '>=', $dari)
            ->whereDate('p.tanggal', '<=', $sampai)
            ->orderByDesc('p.tanggal')
            ->orderByDesc('p.id')
            ->get([
                'd.barang_id', 'p.tanggal', 'p.no_nota', 'p.nama_customer',
                'd.qty', 'd.subtotal', DB::raw('COALESCE(d.potongan, 0) as potongan'),
            ])
            ->groupBy('barang_id');

        $items = [];
        $aktual = 0.0;
        $patokTotal = 0.0;

        foreach ($rows as $r) {
            $qty   = (float) $r->qty;
            $nilai = (float) $r->nilai;
            $patok = (float) $r->patok;
            $avg   = $qty > 0 ? $nilai / $qty : 0.0;
            $selisih = $avg - $patok;

            $aktual     += $nilai;
            $patokTotal += $qty * $patok;

            $items[] = [
                'id'            => (int) $r->barang_id,
                'nama'          => $r->nama_barang,
                'satuan'        => $r->satuan,
                'patok'         => $patok,
                'asli'          => $avg,
                'modal'         => (float) $r->modal,
                'selisih_unit'  => $selisih,
                'selisih_pct'   => $patok > 0 ? $selisih / $patok * 100 : null,
                // terjual di atas patok = baik
                'tone'          => abs($selisih) < 0.005 ? 'flat' : ($selisih > 0 ? 'good' : 'bad'),
                'qty'           => $qty,
                'nota'          => (int) $r->nota,
                'nilai'         => $nilai,
                'selisih_nilai' => $nilai - $qty * $patok,
                'potongan'      => (float) $r->potongan,
                'min'           => (float) $r->min_h,
                'max'           => (float) $r->max_h,
                'ruler'         => $this->ruler($patok, $avg, (float) $r->min_h, (float) $r->max_h),
                'riwayat'       => ($riwayat[$r->barang_id] ?? collect())->take(10)->map(function ($n) use ($patok) {
                    $q     = (float) $n->qty;
                    $harga = $q > 0 ? (float) $n->subtotal / $q : 0.0;

                    return [
                        'tgl'      => Carbon::parse($n->tanggal)->format('d/m/Y'),
                        'nota'     => $n->no_nota,
                        'customer' => $n->nama_customer,
                        'qty'      => $q,
                        'harga'    => $harga,
                        'vs_patok' => $harga - $patok,
                        'potongan' => (float) $n->potongan,
                        'subtotal' => (float) $n->subtotal,
                    ];
                })->values()->all(),
            ];
        }

        usort($items, fn ($a, $b) => abs($b['selisih_nilai']) <=> abs($a['selisih_nilai']));

        $selisih = $aktual - $patokTotal;

        return [
            'items'   => $items,
            'aktual'  => $aktual,
            'patok'   => $patokTotal,
            'selisih' => $selisih,
            'tone'    => abs($selisih) < 1 ? 'flat' : ($selisih > 0 ? 'good' : 'bad'),
        ];
    }

    // ═════════════════════════════════════════════════════════
    //  Posisi penanda pada "penggaris harga" (0–100 %)
    // ═════════════════════════════════════════════════════════
    private function ruler(float $patok, float $asli, float $min, float $max): array
    {
        $lo = min($patok, $asli, $min);
        $hi = max($patok, $asli, $max);

        $span = $hi - $lo;
        $pad  = $span > 0 ? $span * 0.18 : max($hi * 0.02, 1);
        $dlo  = $lo - $pad;
        $dhi  = $hi + $pad;
        $range = $dhi - $dlo;

        $pos = fn (float $v) => round(($v - $dlo) / $range * 100, 2);

        return [
            'patok' => $pos($patok),
            'asli'  => $pos($asli),
            'min'   => $pos($min),
            'max'   => $pos($max),
        ];
    }
}