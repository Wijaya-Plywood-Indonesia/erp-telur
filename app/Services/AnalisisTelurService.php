<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data untuk menu "Analisis Telur":
 *  - omset per periode (harian / mingguan / bulanan) + komposisi per jenis telur
 *  - stok telur (dari jurnal umum, sama seperti Matrix Barang)
 *  - pola per hari, pola per bulan, dan kalimat insight otomatis
 *
 * Jenis telur dikenali dari NAMA barang (sama seperti MutasiTelurService):
 *   mengandung "telur" + "kiloan" / "peti(an)" / "bentes". Selain itu = "Lainnya".
 */
class AnalisisTelurService
{
    public const JENIS = [
        'kiloan' => ['label' => 'Telur Kiloan', 'color' => '#10b981'],
        'petian' => ['label' => 'Telur Petian', 'color' => '#6366f1'],
        'bentes' => ['label' => 'Telur Bentes', 'color' => '#ec4899'],
        'lainnya' => ['label' => 'Lainnya (barang lain)', 'color' => '#94a3b8'],
    ];

    /** Nota dengan status ini tidak dihitung sebagai omset. */
    private const STATUS_BATAL = ['DIBATALKAN'];

    /** Barang telur yang namanya memuat kata ini tidak dihitung sebagai stok Ruko. */
    private const LOKASI_LUAR_RUKO = ['wijaya', 'wahana'];

    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const BULAN_PENDEK = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    // ══════════════════════════════════════════════════════════════════════════
    // ENTRY POINT
    // ══════════════════════════════════════════════════════════════════════════

    public function build(string $gran, string $dariStr, string $sampaiStr): array
    {
        if (!in_array($gran, ['harian', 'mingguan', 'bulanan'], true)) {
            $gran = 'harian';
        }

        $today  = Carbon::today();
        $dari   = Carbon::parse($dariStr)->startOfDay();
        $sampai = Carbon::parse($sampaiStr)->startOfDay();

        if ($sampai->gt($today)) {
            $sampai = $today->copy();
        }
        if ($dari->gt($sampai)) {
            $dari = $sampai->copy();
        }
        if ($dari->lt($sampai->copy()->subYears(5))) {
            $dari = $sampai->copy()->subYears(5);
        }

        $jumlahHari = (int) round($dari->diffInDays($sampai)) + 1;

        // ── Omset per hari per jenis ────────────────────────────────────────
        $harian = [];
        foreach ($this->queryPenjualan($dari, $sampai) as $r) {
            $harian[$r->tgl][$r->jenis] = ['omset' => (float) $r->omset, 'qty' => (float) $r->qty];
        }

        $stok = $this->stok($dari, $sampai);

        $acc         = [];
        $totalJenis  = $this->kosongJenis();
        $sumHari     = array_fill(1, 7, 0.0);
        $cntHari     = array_fill(1, 7, 0);
        $bulanSum    = [];
        $bulanCnt    = [];
        $hariTerbaik = null;

        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            $tgl       = $d->toDateString();
            $jenisHari = $this->kosongJenis();
            foreach ($harian[$tgl] ?? [] as $k => $v) {
                $jenisHari[$k] = $v;
            }
            $omsetHari = 0.0;
            foreach ($jenisHari as $v) {
                $omsetHari += $v['omset'];
            }

            [$key, $start, $end] = $this->bucket($gran, $d);
            if (!isset($acc[$key])) {
                $acc[$key] = [
                    'start' => $start,
                    'end'   => $end,
                    'akhir' => $d->copy(),
                    'jenis' => $this->kosongJenis(),
                ];
            }
            $acc[$key]['akhir'] = $d->copy();

            foreach ($jenisHari as $k => $v) {
                $acc[$key]['jenis'][$k]['omset'] += $v['omset'];
                $acc[$key]['jenis'][$k]['qty']   += $v['qty'];
                $totalJenis[$k]['omset']         += $v['omset'];
                $totalJenis[$k]['qty']           += $v['qty'];
            }

            $iso = $d->dayOfWeekIso;
            $sumHari[$iso] += $omsetHari;
            $cntHari[$iso]++;

            $ym = $d->format('Y-m');
            $bulanSum[$ym] = ($bulanSum[$ym] ?? 0.0) + $omsetHari;
            $bulanCnt[$ym] = ($bulanCnt[$ym] ?? 0) + 1;

            if ($omsetHari > 0 && ($hariTerbaik === null || $omsetHari > $hariTerbaik['omset'])) {
                $hariTerbaik = [
                    'omset' => $omsetHari,
                    'label' => self::HARI[$d->dayOfWeek] . ', ' . $d->day . ' ' . self::BULAN[$d->month] . ' ' . $d->year,
                ];
            }
        }

        $total = 0.0;
        foreach ($totalJenis as $v) {
            $total += $v['omset'];
        }

        // ── Periode pembanding (panjang sama, tepat sebelum periode ini) ───
        $prevSampai = $dari->copy()->subDay();
        $prevDari   = $prevSampai->copy()->subDays($jumlahHari - 1);
        $prevJenis  = $this->kosongJenis();
        foreach ($this->queryPenjualan($prevDari, $prevSampai) as $r) {
            $prevJenis[$r->jenis]['omset'] += (float) $r->omset;
        }
        $prevTotal = 0.0;
        foreach ($prevJenis as $v) {
            $prevTotal += $v['omset'];
        }
        $bp        = self::BULAN_PENDEK;
        $prevLabel = $prevDari->day . ' ' . $bp[$prevDari->month] . ' ' . $prevDari->year
            . ' – ' . $prevSampai->day . ' ' . $bp[$prevSampai->month] . ' ' . $prevSampai->year;
        $barangLain = $this->barangLainnya($dari, $sampai);

        // ── Titik grafik ────────────────────────────────────────────────────
        $points     = [];
        $prev       = null;
        $prevOmset  = 0.0;

        foreach ($acc as $a) {
            $omset = 0.0;
            foreach ($a['jenis'] as $v) {
                $omset += $v['omset'];
            }

            $jenis = [];
            $naik  = null;
            $turun = null;

            foreach (self::JENIS as $k => $_) {
                $o = $a['jenis'][$k]['omset'];
                $q = $a['jenis'][$k]['qty'];

                $item = [
                    'omset'        => round($o, 2),
                    'qty'          => round($q, 2),
                    'persen'       => $omset > 0 ? round($o / $omset * 100, 1) : 0,
                    'delta'        => null,
                    'delta_persen' => null,
                ];

                if ($prev !== null) {
                    $po = $prev[$k]['omset'];
                    $item['delta']        = round($o - $po, 2);
                    $item['delta_persen'] = $po > 0 ? round(($o - $po) / $po * 100, 1) : null;

                    if ($item['delta'] > 0 && ($naik === null || $item['delta'] > $naik['delta'])) {
                        $naik = ['label' => self::JENIS[$k]['label'], 'delta' => $item['delta']];
                    }
                    if ($item['delta'] < 0 && ($turun === null || $item['delta'] < $turun['delta'])) {
                        $turun = ['label' => self::JENIS[$k]['label'], 'delta' => $item['delta']];
                    }
                }

                $jenis[$k] = $item;
            }

            [$label, $short] = $this->labelPeriode($gran, $a['start'], $a['end']);
            $stokTitik       = $stok['harian'][$a['akhir']->toDateString()] ?? null;

            $points[] = [
                'label'        => $label,
                'short'        => $short,
                'omset'        => round($omset, 2),
                'delta'        => $prev !== null ? round($omset - $prevOmset, 2) : null,
                'delta_persen' => ($prev !== null && $prevOmset > 0) ? round(($omset - $prevOmset) / $prevOmset * 100, 1) : null,
                'jenis'        => $jenis,
                'naik'         => $naik,
                'turun'        => $turun,
                'stok'         => $stokTitik['total'] ?? null,
                'stok_jenis'   => $stokTitik['jenis'] ?? null,
            ];

            $prev      = $a['jenis'];
            $prevOmset = $omset;
        }

        // ── Pola per hari ───────────────────────────────────────────────────
        $rataHarian = $jumlahHari > 0 ? $total / $jumlahHari : 0.0;
        $hari       = [];
        $maxHari    = 0.0;
        foreach (range(1, 7) as $iso) {
            $rata = $cntHari[$iso] > 0 ? $sumHari[$iso] / $cntHari[$iso] : 0.0;
            $rata    = round($rata, 2);
            $maxHari = max($maxHari, $rata);
            $hari[] = [
                'nama'    => self::HARI[$iso % 7],
                'rata'    => round($rata, 2),
                'indeks'  => $rataHarian > 0 ? round(($rata - $rataHarian) / $rataHarian * 100, 1) : null,
                'lebar'   => 0,
                'terbaik' => false,
            ];
        }
        foreach ($hari as $i => $h) {
            $hari[$i]['lebar']   = $maxHari > 0 ? round($h['rata'] / $maxHari * 100, 1) : 0;
            $hari[$i]['terbaik'] = $maxHari > 0 && $h['rata'] == $maxHari;
        }

        // ── Pola per bulan (hanya bulan yang terisi penuh) ──────────────────
        $perNomor = [];
        foreach ($bulanSum as $ym => $sum) {
            $hariDalamBulan = Carbon::createFromFormat('Y-m-d', $ym . '-01')->daysInMonth;
            if ($bulanCnt[$ym] === $hariDalamBulan) {
                $perNomor[(int) substr($ym, 5, 2)][] = $sum;
            }
        }
        ksort($perNomor);

        $bulan    = [];
        $maxBulan = 0.0;
        foreach ($perNomor as $no => $list) {
            $rata     = round(array_sum($list) / count($list), 2);
            $maxBulan = max($maxBulan, $rata);
            $bulan[]  = ['no' => $no, 'nama' => self::BULAN[$no], 'rata' => round($rata, 2), 'lebar' => 0, 'terbaik' => false];
        }
        foreach ($bulan as $i => $b) {
            $bulan[$i]['lebar']   = $maxBulan > 0 ? round($b['rata'] / $maxBulan * 100, 1) : 0;
            $bulan[$i]['terbaik'] = $maxBulan > 0 && $b['rata'] == $maxBulan;
        }
        if (count($bulan) < 2) {
            $bulan = [];
        }

        // ── Ringkasan per kategori ──────────────────────────────────────────
        $kategori = [];
        foreach (self::JENIS as $k => $meta) {
            $o  = $totalJenis[$k]['omset'];
            $po = $prevJenis[$k]['omset'];
            $kategori[] = [
                'key'    => $k,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'omset'  => round($o, 2),
                'qty'    => round($totalJenis[$k]['qty'], 2),
                'persen' => $total > 0 ? round($o / $total * 100, 1) : 0,
                'growth' => $po > 0 ? round(($o - $po) / $po * 100, 1) : null,
                'delta'  => round($o - $po, 2),
                'barang' => $k === 'lainnya' ? $barangLain : [],
            ];
        }

        $growthTotal = $prevTotal > 0 ? round(($total - $prevTotal) / $prevTotal * 100, 1) : null;

        return [
            'dari'        => $dari->toDateString(),
            'sampai'      => $sampai->toDateString(),
            'gran'        => $gran,
            'jumlah_hari' => $jumlahHari,
            'chart'       => [
                'points'  => $points,
                'jenis'   => self::JENIS,
                'hasStok' => $stok['ada'],
                'banding' => match ($gran) {
                    'mingguan' => 'minggu sebelumnya',
                    'bulanan'  => 'bulan sebelumnya',
                    default    => 'hari sebelumnya',
                },
            ],
            'kpi' => [
                'total'        => round($total, 2),
                'growth'       => $growthTotal,
                'rata_harian'  => round($rataHarian, 2),
                'hari_terbaik' => $hariTerbaik,
                'prev_label'   => $prevLabel,
                'prev_ada'     => $prevTotal > 0,
            ],
            'kategori' => $kategori,
            'hari'     => $hari,
            'bulan'    => $bulan,
            'stok'     => ['cards' => $stok['cards'], 'total' => $stok['total'], 'ada' => $stok['ada'], 'peti' => $stok['peti']],
            'insight'  => $this->insight($total, $growthTotal, $hariTerbaik, $hari, $bulan, $kategori),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PENJUALAN
    // ══════════════════════════════════════════════════════════════════════════

    private function caseJenis(): string
    {
        $n = 'LOWER(d.nama_barang)';

        return "CASE
            WHEN {$n} LIKE '%telur%' AND {$n} LIKE '%kiloan%' THEN 'kiloan'
            WHEN {$n} LIKE '%telur%' AND {$n} LIKE '%peti%'   THEN 'petian'
            WHEN {$n} LIKE '%telur%' AND {$n} LIKE '%bentes%' THEN 'bentes'
            ELSE 'lainnya'
        END";
    }

    private function basePenjualan(Carbon $dari, Carbon $sampai)
    {
        return DB::table('penjualan_details as d')
            ->join('penjualans as p', 'p.id', '=', 'd.penjualan_id')
            ->whereBetween('p.tanggal', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()])
            ->where(function ($q) {
                $q->whereNull('p.status_transaksi')
                    ->orWhereNotIn('p.status_transaksi', self::STATUS_BATAL);
            });
    }

    private function queryPenjualan(Carbon $dari, Carbon $sampai): Collection
    {
        $case = $this->caseJenis();

        return $this->basePenjualan($dari, $sampai)
            ->selectRaw("DATE(p.tanggal) as tgl, {$case} as jenis, SUM(d.subtotal) as omset, SUM(d.qty) as qty")
            ->groupBy('tgl', 'jenis')
            ->get();
    }

    /** Barang apa saja yang masuk kategori "Lainnya" (5 terbesar). */
    private function barangLainnya(Carbon $dari, Carbon $sampai): array
    {
        return $this->basePenjualan($dari, $sampai)
            ->whereRaw($this->caseJenis() . " = 'lainnya'")
            ->selectRaw('d.nama_barang as nama, MAX(d.satuan) as satuan, SUM(d.subtotal) as omset, SUM(d.qty) as qty')
            ->groupBy('d.nama_barang')
            ->orderByDesc('omset')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'nama'   => (string) $r->nama,
                'satuan' => (string) $r->satuan,
                'omset'  => round((float) $r->omset, 2),
                'qty'    => round((float) $r->qty, 2),
            ])
            ->all();
    }

    private function kosongJenis(): array
    {
        $out = [];
        foreach (self::JENIS as $k => $_) {
            $out[$k] = ['omset' => 0.0, 'qty' => 0.0];
        }

        return $out;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOK TELUR & PETI (dari Jurnal Umum, sama seperti Matrix Barang)
    // ══════════════════════════════════════════════════════════════════════════

    private function jenisDariNama(string $nama): string
    {
        $n = strtolower($nama);

        if (str_contains($n, 'kiloan')) {
            return 'kiloan';
        }
        if (str_contains($n, 'peti')) {
            return 'petian';
        }
        if (str_contains($n, 'bentes')) {
            return 'bentes';
        }

        return 'lainnya';
    }

    /** telur | peti_kosong | peti_isi | lain (barang "peti" lain yang diabaikan) */
    private function tipeBarang(string $nama): string
    {
        $n = strtolower($nama);

        if (str_contains($n, 'peti') && str_contains($n, 'kosong')) {
            return 'peti_kosong';
        }
        if (str_contains($n, 'peti') && str_contains($n, 'isi')) {
            return 'peti_isi';
        }
        if (str_contains($n, 'telur')) {
            return 'telur';
        }

        return 'lain';
    }

    private function luarRuko(string $nama): bool
    {
        $n = strtolower($nama);
        foreach (self::LOKASI_LUAR_RUKO as $lokasi) {
            if (str_contains($n, $lokasi)) {
                return true;
            }
        }

        return false;
    }

    private function stok(Carbon $dari, Carbon $sampai): array
    {
        $kosong = [
            'ada' => false, 'harian' => [], 'cards' => [], 'total' => 0.0,
            'peti' => ['kosong' => [], 'isi' => [], 'isi_fallback' => false],
        ];

        $barangs = Barang::with(['subAnakAkun', 'satuan'])
            ->where(function ($q) {
                $q->whereRaw('LOWER(nama_barang) LIKE ?', ['%telur%'])
                    ->orWhereRaw('LOWER(nama_barang) LIKE ?', ['%peti%']);
            })
            ->whereHas('subAnakAkun', function ($q) {
                $q->whereNotNull('kode_sub_anak_akun')->where('kode_sub_anak_akun', '!=', '');
            })
            ->orderBy('nama_barang')
            ->get()
            ->filter(fn ($b) => $this->tipeBarang($b->nama_barang) !== 'lain')
            ->values();

        if ($barangs->isEmpty()) {
            return $kosong;
        }

        // kode akun => info
        $meta     = [];
        $adaTelur = false;
        foreach ($barangs as $b) {
            $kode = (string) $b->subAnakAkun->kode_sub_anak_akun;
            $tipe = $this->tipeBarang($b->nama_barang);
            $meta[$kode] = [
                'tipe'  => $tipe,
                'jenis' => $this->jenisDariNama($b->nama_barang),
                'ruko'  => !$this->luarRuko($b->nama_barang),
            ];
            if ($tipe === 'telur') {
                $adaTelur = true;
            }
        }

        $rows = JurnalUmum::query()
            ->whereIn('no_akun', array_keys($meta))
            ->whereDate('tgl', '<=', $sampai->toDateString())
            ->selectRaw('tgl, no_akun, map, SUM(COALESCE(banyak, 0)) as q')
            ->groupBy('tgl', 'no_akun', 'map')
            ->get();

        $awal      = $dari->toDateString();
        $saldo     = array_fill_keys(array_keys($meta), 0.0);
        $perubahan = [];

        foreach ($rows as $r) {
            $kode = (string) $r->no_akun;
            $tgl  = Carbon::parse($r->tgl)->toDateString();
            $isD  = in_array(strtolower((string) $r->map), ['d', 'debit'], true);
            $q    = ($isD ? 1 : -1) * (float) $r->q;

            if ($tgl < $awal) {
                $saldo[$kode] += $q;
            } else {
                $perubahan[$tgl][$kode] = ($perubahan[$tgl][$kode] ?? 0.0) + $q;
            }
        }

        // Stok harian untuk grafik: hanya barang telur di Ruko
        $harian = [];
        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            $tgl = $d->toDateString();
            foreach ($perubahan[$tgl] ?? [] as $kode => $q) {
                $saldo[$kode] += $q;
            }

            $perJenis = [];
            foreach (self::JENIS as $k => $_) {
                $perJenis[$k] = 0.0;
            }
            $totalHari = 0.0;
            foreach ($meta as $kode => $info) {
                if ($info['tipe'] === 'telur' && $info['ruko']) {
                    $perJenis[$info['jenis']] += $saldo[$kode];
                    $totalHari += $saldo[$kode];
                }
            }
            foreach ($perJenis as $k => $v) {
                $perJenis[$k] = round($v, 2);
            }

            $harian[$tgl] = ['total' => round($totalHari, 2), 'jenis' => $perJenis];
        }

        $cards = [];
        $total = 0.0;
        foreach ($barangs as $b) {
            $kode = (string) $b->subAnakAkun->kode_sub_anak_akun;
            $qty  = round($saldo[$kode] ?? 0.0, 2);
            $min  = (float) $b->stok_minimum;
            $info = $meta[$kode];

            if ($info['tipe'] === 'telur' && $info['ruko']) {
                $total += $qty;
            }

            $cards[] = [
                'nama'    => $b->nama_barang,
                'satuan'  => $b->satuan?->nama_satuan ?? '',
                'stok'    => $qty,
                'minimum' => $min,
                'menipis' => $min > 0 && $qty <= $min,
                'ruko'    => $info['ruko'],
                'tipe'    => $info['tipe'],
                'jenis'   => $info['jenis'],
                'color'   => self::JENIS[$info['jenis']]['color'],
            ];
        }

        // Stok peti: kosong & isi telur
        $peti = ['kosong' => [], 'isi' => [], 'isi_fallback' => false];
        foreach ($cards as $c) {
            if ($c['tipe'] === 'peti_kosong') {
                $peti['kosong'][] = $c;
            } elseif ($c['tipe'] === 'peti_isi') {
                $peti['isi'][] = $c;
            }
        }
        if (!$peti['isi']) {
            // Tidak ada barang "peti isi": pakai stok Telur Petian (Ruko) sebagai peti isi telur
            foreach ($cards as $c) {
                if ($c['tipe'] === 'telur' && $c['jenis'] === 'petian' && $c['ruko']) {
                    $peti['isi'][] = $c;
                }
            }
            $peti['isi_fallback'] = count($peti['isi']) > 0;
        }

        return [
            'ada'    => $adaTelur,
            'harian' => $harian,
            'cards'  => $cards,
            'total'  => round($total, 2),
            'peti'   => $peti,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PERIODE & LABEL
    // ══════════════════════════════════════════════════════════════════════════

    /** @return array{0:string,1:Carbon,2:Carbon} key, awal bucket, akhir bucket */
    private function bucket(string $gran, Carbon $d): array
    {
        if ($gran === 'mingguan') {
            $start = $d->copy()->startOfWeek(Carbon::MONDAY);

            return [$start->toDateString(), $start, $start->copy()->addDays(6)];
        }

        if ($gran === 'bulanan') {
            $start = $d->copy()->startOfMonth();

            return [$start->format('Y-m'), $start, $d->copy()->endOfMonth()->startOfDay()];
        }

        $start = $d->copy();

        return [$start->toDateString(), $start, $start->copy()];
    }

    /** @return array{0:string,1:string} label panjang, label pendek (sumbu X) */
    private function labelPeriode(string $gran, Carbon $start, Carbon $end): array
    {
        $bp = self::BULAN_PENDEK;

        if ($gran === 'mingguan') {
            return [
                'Minggu ' . $start->day . ' ' . $bp[$start->month] . ' – ' . $end->day . ' ' . $bp[$end->month] . ' ' . $end->year,
                $start->day . ' ' . $bp[$start->month],
            ];
        }

        if ($gran === 'bulanan') {
            return [
                self::BULAN[$start->month] . ' ' . $start->year,
                $bp[$start->month] . ' ' . substr((string) $start->year, 2),
            ];
        }

        return [
            self::HARI[$start->dayOfWeek] . ', ' . $start->day . ' ' . self::BULAN[$start->month] . ' ' . $start->year,
            $start->day . ' ' . $bp[$start->month],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INSIGHT OTOMATIS
    // ══════════════════════════════════════════════════════════════════════════

    private function insight(float $total, ?float $growth, ?array $hariTerbaik, array $hari, array $bulan, array $kategori): array
    {
        if ($total <= 0) {
            return ['Belum ada penjualan pada rentang tanggal ini.'];
        }

        $out = [];

        if ($growth !== null) {
            $arah  = $growth >= 0 ? 'naik' : 'turun';
            $out[] = 'Omset periode ini ' . self::rp($total) . ', ' . $arah . ' ' . abs($growth) . '% dibanding periode sebelumnya dengan panjang yang sama.';
        } else {
            $out[] = 'Omset periode ini ' . self::rp($total) . ' (belum ada data pembanding di periode sebelumnya).';
        }

        if ($hariTerbaik) {
            $out[] = 'Omset harian tertinggi terjadi pada ' . $hariTerbaik['label'] . ' sebesar ' . self::rp($hariTerbaik['omset']) . '.';
        }

        $top = null;
        foreach ($hari as $h) {
            if ($h['terbaik']) {
                $top = $h;
                break;
            }
        }
        if ($top && $top['indeks'] !== null && $top['indeks'] > 0) {
            $out[] = 'Hari terkuat adalah ' . $top['nama'] . ': rata-rata ' . self::rpRingkas($top['rata']) . ', ' . $top['indeks'] . '% di atas rata-rata harian.';
        }

        $terbesar = null;
        $terNaik  = null;
        foreach ($kategori as $k) {
            if ($k['key'] !== 'lainnya' && ($terbesar === null || $k['omset'] > $terbesar['omset'])) {
                $terbesar = $k;
            }
            if ($k['growth'] !== null && $k['delta'] > 0 && ($terNaik === null || $k['delta'] > $terNaik['delta'])) {
                $terNaik = $k;
            }
        }
        if ($terbesar && $terbesar['omset'] > 0) {
            $out[] = 'Kontribusi terbesar datang dari ' . $terbesar['label'] . ' (' . $terbesar['persen'] . '% dari omset).';
        }
        if ($terNaik) {
            $out[] = 'Yang paling naik dibanding periode sebelumnya: ' . $terNaik['label'] . ' (+' . $terNaik['growth'] . '%).';
        }

        foreach ($bulan as $b) {
            if ($b['terbaik']) {
                $musim = $b['no'] === 12 ? ' (musim Natal & Tahun Baru)' : '';
                $out[] = 'Bulan terkuat: ' . $b['nama'] . $musim . ' dengan rata-rata omset ' . self::rpRingkas($b['rata']) . ' per bulan.';
                break;
            }
        }

        return $out;
    }

    private static function rp(float $v): string
    {
        return 'Rp ' . number_format($v, 0, ',', '.');
    }

    private static function rpRingkas(float $v): string
    {
        $a = abs($v);
        if ($a >= 1_000_000_000) {
            return 'Rp ' . rtrim(rtrim(number_format($v / 1_000_000_000, 1, ',', '.'), '0'), ',') . ' M';
        }
        if ($a >= 1_000_000) {
            return 'Rp ' . rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',') . ' jt';
        }
        if ($a >= 1_000) {
            return 'Rp ' . number_format($v / 1_000, 0, ',', '.') . ' rb';
        }

        return self::rp($v);
    }
}