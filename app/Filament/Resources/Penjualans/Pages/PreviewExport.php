<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Exports\LaporanKeranjangPenjualanExport;
use App\Exports\LaporanPenjualanDetailExport;
use App\Exports\LaporanPenjualanExport;
use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Renderless;

class PreviewExport extends Page
{
    protected static string $resource = PenjualanResource::class;

    protected string $view = 'filament.resources.penjualans.pages.preview-export';

    protected static string $layout = 'components.layouts.blank';

    public Collection $allPenjualan;

    public string $viewType = 'main';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public $laporanGabungan = [];

    /** Ringkasan total: tunai, transfer, keseluruhan, jumlah nota, dan rincian per bank */
    public array $ringkasan = [
        'tunai' => 0,
        'transfer' => 0,
        'total' => 0,
        'jumlah_nota' => 0,
        'per_bank' => [],
    ];

    public function mount(Request $request)
    {
        // 1. Setup Filter Tanggal (Ambil dari URL atau default bulan ini)
        $this->viewType = $request->query('view_type', 'main');
        $this->startDate = $request->query('dari_tanggal', now()->startOfMonth()->format('Y-m-d'));
        $this->endDate = $request->query('sampai_tanggal', now()->format('Y-m-d'));

        // Aktifkan detail jika view_type adalah 'detail'

        // 2. Tentukan apakah butuh detail atau tidak (berdasarkan klik tombol)
        $exportType = $request->query('export');

        // 3. Jalankan Load Data dengan Filter
        $this->loadLaporan();

        // 4. Jika ada request export, handle download
    }

    public function loadLaporan()
    {
        // Gunakan filter tanggal agar data di tabel preview sinkron
        $penjualans = Penjualan::query()
            ->whereNotNull('validated_by')
            ->whereBetween('created_at', [
                (string) $this->startDate.' 00:00:00',
                (string) $this->endDate.' 23:59:59',
            ])
            ->with(['user', 'validator'])
            ->latest()
            ->get();

        $this->hitungRingkasan($penjualans);

        $this->laporanGabungan = $penjualans
            ->map(function ($p) {
                return [
                    'no_nota' => $p->no_nota,
                    'tanggal' => $p->tanggal,
                    'nama_customer' => $p->nama_customer,
                    'member' => $p->is_member ? 'MEMBER' : 'REGULAR',
                    'alamat' => $p->alamat,
                    'metode_pembayaran' => $p->metode_pembayaran,
                    'total' => $p->total,
                    'bayar' => $p->bayar,
                    'kembalian' => $p->kembalian,
                    'kasir' => $p->user?->name,
                    'validator' => $p->validator?->name,
                    'bank' => $p->bank ?? '-',
                    'no_rekening' => $p->no_rekening ?? '-',
                    'kendaraan' => $p->kendaraan ?? 'ANTAR SENDIRI',
                    'plat_kendaraan' => $p->plat_kendaraan ?? '-',
                    'nama_sopir' => $p->nama_sopir,
                    'status_transaksi' => $p->status_transaksi,
                    'keterangan' => $p->keterangan ?? '-',
                    // Load detail hanya jika dibutuhkan
                    'data_penjualan_detail' => $this->viewType === 'full' || $this->viewType === 'detail'  ? $this->data_detail($p->id) : [],
                ];
            })
            ->toArray();
    }

    /**
     * Hitung total uang masuk per nota (satu nota dihitung sekali, bukan per baris item).
     * Tunai = bayar_tunai dikurangi kembalian (uang riil yang masuk kas).
     * Transfer = bayar_transfer.
     */
    protected function hitungRingkasan(Collection $penjualans): void
    {
        $tunai = 0.0;
        $transfer = 0.0;
        $perBank = [];

        foreach ($penjualans as $p) {
            $nTunai = (float) ($p->bayar_tunai ?? 0);
            $nTransfer = (float) ($p->bayar_transfer ?? 0);

            if ($nTunai + $nTransfer <= 0) {
                // Data lama yang belum punya rincian split: pakai metode pembayaran
                $total = (float) $p->total;
                if (strtoupper((string) $p->metode_pembayaran) === 'TRANSFER') {
                    $nTransfer = $total;
                } else {
                    $nTunai = $total;
                }
            } else {
                // Potong kembalian dari sisi tunai
                $nTunai = max(0, $nTunai - (float) ($p->kembalian ?? 0));
            }

            $tunai += $nTunai;
            $transfer += $nTransfer;

            if ($nTransfer > 0) {
                $bank = trim(($p->bank ?: 'Tanpa Bank').' '.($p->no_rekening ?: ''));
                $perBank[$bank] = ($perBank[$bank] ?? 0) + $nTransfer;
            }
        }

        arsort($perBank);

        $this->ringkasan = [
            'tunai' => $tunai,
            'transfer' => $transfer,
            'total' => $tunai + $transfer,
            'jumlah_nota' => $penjualans->count(),
            'per_bank' => $perBank,
        ];
    }

    public function data_detail($penjualan_id)
    {
        return DetailPenjualan::where('penjualan_id', $penjualan_id)
            ->get()
            ->map(fn ($detail) => [
                'nama_barang' => $detail->nama_barang,
                'harga_awal' => $detail->harga_awal,
                'harga_jual' => $detail->harga_jual,
                'diskon' => $detail->potongan ?? 0,
                'jumlah' => (string) $detail->qty.' '.$detail->satuan,
                'total_diskon' => ($detail->potongan * $detail->qty),
                'subtotal' => $detail->subtotal,
            ])
            ->toArray();
    }

    public function loadLaporanDetail()
    {
        $this->laporanGabungan = Penjualan::query()
            ->whereNotNull('validated_by')
            ->whereBetween('created_at', [
                (string) $this->startDate.' 00:00:00',
                (string) $this->endDate.' 23:59:59',
            ])
            ->with(['user', 'validator'])
            ->get()
            ->map(function ($p) {
                return [
                    'no_nota' => $p->no_nota,
                    'tanggal' => $p->tanggal,
                    'nama_customer' => $p->nama_customer,
                    'kasir' => $p->user?->name,
                    'status_transaksi' => $p->status_transaksi,
                    'keterangan' => $p->keterangan ?? '-',
                    'data_penjualan_detail' => $this->data_detail($p->id),
                ];
            })
            ->toArray();
    }

    public function render(): View
    {
        /** @var \Illuminate\View\View $view */
        $view = view($this->view);

        return $view->layout('components.layouts.blank');
    }

    #[Renderless]
    public function exportExcel($type)
    {
        dd($type, $this->startDate, $this->endDate, "Export triggered");
        // 1. Pastikan data ter-load sesuai filter saat ini
        if ($type === 'detail') {
            $this->loadLaporanDetail();
        } else {
            $this->loadLaporan();
        }

        $fileName = "Laporan-{$type}-{$this->startDate}-to-{$this->endDate}.xlsx";

        // 2. Gunakan Livewire Stream Download
        return response()->streamDownload(function () use ($type) {
            $export = match($type) {
                'main'   => new LaporanPenjualanExport($this->laporanGabungan),
                'detail' => new LaporanKeranjangPenjualanExport($this->laporanGabungan),
                'full'   => new LaporanPenjualanDetailExport($this->laporanGabungan),
                default  => new LaporanPenjualanExport($this->laporanGabungan),
            };

            // Langsung output ke stream
            return Excel::store($export, 'php://output', \Maatwebsite\Excel\Excel::XLSX);
        }, $fileName);
    }
}