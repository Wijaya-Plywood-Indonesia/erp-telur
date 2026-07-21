<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use App\Models\Barang;
use App\Models\BukuBesar as BukuBesarModel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Carbon\Carbon;
use UnitEnum;
use Illuminate\Support\Facades\DB;

class BukuBesar extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Akuntansi';
    protected string $view = 'filament.pages.buku-besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';
    protected static ?int $navigationSort = 6;

    public $indukAkuns = [];
    public $filterBulan;
    public bool $isLoading = true;
    public $saldoMap = [];
    public $saldoAwalMap = [];
    public $saldoAwalQtyMap = [];
    /** Kode akun yang merupakan akun persediaan SATU barang (id_sub_anak_akun
     *  di tabel barangs). Hanya akun-akun ini yang qty-nya bermakna sebagai
     *  kuantitas fisik — akun lain (Penjualan, HPP, dll) menerima posting
     *  dari banyak barang dengan satuan berbeda-beda, jadi menjumlah qty-nya
     *  tidak punya arti fisik apapun. */
    public $persediaanKodes = [];

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('Y-m');
        // isLoading = true by default, initLoad akan dipanggil via wire:init
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\BukuBesarExport($this->filterBulan),
                        'Buku_Besar_' . $this->filterBulan . '.xlsx'
                    );
                }),
        ];
    }

    public function initLoad(): void
    {
        $this->preloadPersediaanKodes();
        $this->preloadSaldoAwal();
        $this->preloadSaldo();
        $this->loadData();
        $this->isLoading = false;
    }

    public function updatedFilterBulan(): void
    {
        $this->isLoading = true;
        $this->saldoAwalMap = [];
        $this->saldoAwalQtyMap = [];
        $this->saldoMap = [];

        $this->preloadSaldoAwal();
        $this->preloadSaldo();
        $this->loadData();
        $this->isLoading = false;
    }

    // ── Kumpulkan kode akun yang merupakan akun persediaan SATU barang ───────
    // (yaitu akun yang dipakai sebagai id_sub_anak_akun di tabel barangs,
    // sama seperti yang dipakai StokMatrix::mount() / Barang::getStokBukuBesarAttribute).
    // Kode akun di luar daftar ini (Penjualan, HPP, dll) tidak punya makna
    // qty tunggal karena menerima posting dari berbagai barang & satuan.
    private function preloadPersediaanKodes(): void
    {
        $this->persediaanKodes = Barang::with('subAnakAkun')
            ->get()
            ->map(fn($b) => $b->subAnakAkun?->kode_sub_anak_akun)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    // ── Cek apakah suatu kode akun adalah akun persediaan satu barang ────────
    public function isPersediaanAkun(?string $kode): bool
    {
        return $kode && in_array($kode, $this->persediaanKodes, true);
    }
    // PENTING: Kita TIDAK punya proses tutup buku bulanan, jadi tabel snapshot
    // 'buku_besar' tidak pernah terisi/update — membacanya selalu balik 0,
    // meski transaksi historis (2-3 bulan ke belakang) sebenarnya ada.
    // Solusinya: hitung saldo awal langsung dari JurnalUmum, dengan menjumlah
    // SEMUA transaksi SEBELUM awal bulan yang dipilih (tgl < awal bulan ini).
    // Ini re-hitung "live" tiap kali halaman dibuka — tidak butuh tutup buku
    // sama sekali, dan otomatis akan selalu sinkron dengan Stok Matrix
    // (yang juga menjumlah seluruh riwayat JurnalUmum tanpa filter tanggal).
    private function preloadSaldoAwal(): void
    {
        $end = Carbon::parse($this->filterBulan)->startOfMonth();

        $rows = JurnalUmum::where('tgl', '<', $end)
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak * harga, harga, 0) ELSE 0 END) as total_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak * harga, harga, 0) ELSE 0 END) as total_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_kredit
            ")
            ->groupBy('no_akun')
            ->get();

        $this->saldoAwalMap = [];
        $this->saldoAwalQtyMap = [];

        foreach ($rows as $row) {
            // Disimpan dalam konvensi "debit positif" (raw, belum disesuaikan
            // dengan saldo_normal akun). Penyesuaian tanda untuk akun
            // kredit-normal tetap dilakukan di getTotalRecursive(), sama
            // seperti perlakuan terhadap mutasi bulan berjalan — supaya
            // saldo awal dan mutasi bulan ini konsisten satu konvensi.
            $this->saldoAwalMap[$row->no_akun] = (float) $row->total_debit - (float) $row->total_kredit;
            $this->saldoAwalQtyMap[$row->no_akun] = (float) $row->total_qty_debit - (float) $row->total_qty_kredit;
        }
    }

    // ── Mutasi bulan terpilih dari jurnal_umums ──────────────────────────────
    // Simpan debit dan kredit GROSS terpisah — bukan net
    private function preloadSaldo(): void
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        $rows = JurnalUmum::whereBetween('tgl', [$start, $end])
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak * harga, harga, 0) ELSE 0 END) as total_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak * harga, harga, 0) ELSE 0 END) as total_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_kredit
            ")
            ->groupBy('no_akun')
            ->get();

        $this->saldoMap = [];
        foreach ($rows as $row) {
            $this->saldoMap[$row->no_akun] = [
                'd' => (float) $row->total_debit,
                'k' => (float) $row->total_kredit,
                'd_qty' => (float) $row->total_qty_debit,
                'k_qty' => (float) $row->total_qty_kredit,
            ];
        }
    }

    public function loadData(): void
    {
        $this->indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'subAnakAkuns',
                        'children' => function ($q) {
                            $q->with([
                                'subAnakAkuns',
                                'children' => function ($q2) {
                                    $q2->with(['subAnakAkuns']);
                                },
                            ]);
                        },
                    ]);
            },
        ])->get();
    }

    // ── Hitung nominal satu transaksi: cukup banyak × harga ─────────────────
    public function hitungNominal($trx): float
    {
        return (float) ($trx->banyak ?? 1) * (float) ($trx->harga ?? 0);
    }

    // ── Saldo awal Rupiah (raw debit-net, dihitung dari JurnalUmum sebelum
    //    awal bulan terpilih — lihat preloadSaldoAwal()). Untuk akun
    //    debit-normal ini sudah langsung siap pakai; untuk akun kredit-normal
    //    gunakan getTotalRecursive() yang sudah menyesuaikan tandanya. ───────
    public function getSaldoAwal(string $kode): float
    {
        return (float) ($this->saldoAwalMap[$kode] ?? 0);
    }

    // ── Saldo awal QTY (raw debit-net) untuk akun/barang tertentu.
    //    Hanya bermakna untuk akun persediaan satu barang — lihat catatan
    //    di preloadPersediaanKodes(). Untuk akun lain, kembalikan null
    //    supaya blade tahu harus menampilkan "—" bukan angka gado-gado. ─────
    public function getSaldoAwalQty(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }

        return (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
    }

    // ── Total QTY kumulatif (saldo awal qty + mutasi qty bulan ini) ─────────
    // Ini yang dipakai untuk menyamakan angka dengan Stok Matrix / Stock Opname.
    public function getSaldoQtyKumulatif(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }

        $awal = (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
        $d = (float) ($this->saldoMap[$kode]['d_qty'] ?? 0);
        $k = (float) ($this->saldoMap[$kode]['k_qty'] ?? 0);

        return $awal + $d - $k;
    }

    // ── Mutasi bulan ini untuk satu akun (debit gross) ───────────────────────
    public function getSaldoBulan(string $kode): float
    {
        return (float) ($this->saldoMap[$kode]['d'] ?? 0)
            - (float) ($this->saldoMap[$kode]['k'] ?? 0);
    }

    // ── Transaksi bulan terpilih untuk satu kode akun ───────────────────────
    public function getTransaksiByKode(string $kode)
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->orderBy('tgl', 'asc')
            ->orderBy('jurnal', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    // ── Saldo rekursif berdasarkan saldo_normal akun ─────────────────────────
    public function getTotalRecursive($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
        $isKredit = in_array($saldoNormal, ['kredit', 'credit', 'k']);

        // saldoAwalMap disimpan dalam konvensi RAW debit-net (debit positif),
        // sama seperti debit/kredit mutasi bulan ini. Karena itu, penyesuaian
        // tanda untuk akun kredit-normal harus diterapkan ke saldoAwal juga —
        // bukan cuma ke mutasi bulan ini seperti versi sebelumnya (yang
        // mengasumsikan saldoAwal sudah datang ter-adjust dari snapshot).
        $saldoAwalRaw = (float) ($this->saldoAwalMap[$kode] ?? 0);
        $saldoAwal = $isKredit ? -$saldoAwalRaw : $saldoAwalRaw;

        if ($kode && isset($this->saldoMap[$kode])) {
            $debit = (float) ($this->saldoMap[$kode]['d'] ?? 0);
            $kredit = (float) ($this->saldoMap[$kode]['k'] ?? 0);

            if ($isKredit) {
                // Akun kredit: saldo naik jika kredit, turun jika debit
                $total += $saldoAwal + $kredit - $debit;
            } else {
                // Akun debit: saldo naik jika debit, turun jika kredit
                $total += $saldoAwal + $debit - $kredit;
            }
        } elseif ($kode) {
            // Tidak ada mutasi bulan ini, hanya saldo awal
            $total += $saldoAwal;
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursive($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursive($sub);
            }
        }

        return $total;
    }

    // ── Versi QTY dari getTotalRecursive() — total qty kumulatif akun ini
    //    beserta seluruh child/sub-akun-nya. HANYA menjumlah kode akun yang
    //    memang akun persediaan satu barang (lihat isPersediaanAkun()) —
    //    akun lain (Penjualan/HPP dsb) dilewati (kontribusi 0), supaya tidak
    //    ikut menjumlah qty lintas satuan yang tidak sepadan. ─────────────────
    public function getTotalRecursiveQty($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        if ($kode && $this->isPersediaanAkun($kode)) {
            $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
            $isKredit = in_array($saldoNormal, ['kredit', 'credit', 'k']);

            $awalRaw = (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
            $dQty = (float) ($this->saldoMap[$kode]['d_qty'] ?? 0);
            $kQty = (float) ($this->saldoMap[$kode]['k_qty'] ?? 0);

            $total += $isKredit
                ? (-$awalRaw + $kQty - $dQty)
                : ($awalRaw + $dQty - $kQty);
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursiveQty($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursiveQty($sub);
            }
        }

        return $total;
    }
}