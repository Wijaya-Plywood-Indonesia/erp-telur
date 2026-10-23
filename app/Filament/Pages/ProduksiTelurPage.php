<?php

namespace App\Filament\Pages;

use App\Exports\ProduksiTelurExport;
use App\Models\DetailProduksiTelur;
use App\Models\Kandang;
use App\Models\ProduksiPakanCampuran;
use App\Models\ProduksiTelur;
use App\Models\ProduksiTelurKorektor;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Locked;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class ProduksiTelurPage extends Page
{
    protected static ?string $navigationLabel = 'Produksi Telur';
    protected static ?string $title = 'Produksi Telur';
    protected static UnitEnum|string|null $navigationGroup = 'Produksi & Kandang';
    protected static ?int $navigationSort = 4;

    public function getView(): string
    {
        return 'filament.pages.produksi-telur-page';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn() => Auth::user()->hasAnyRole(['admin', 'super_admin']))
                ->authorize(fn() => Auth::user()->hasAnyRole(['admin', 'super_admin']))
                ->action(function () {
                    return Excel::download(
                        new ProduksiTelurExport(
                            tanggal: $this->tanggal,
                            kandangs: $this->kandangs,
                            gridData: $this->gridData,
                            kandangPakan: $this->kandangPakan,
                            allPakan: $this->allPakan,
                            kandangTotals: $this->kandangTotals,
                            grandTotal: $this->grandTotal,
                            korektor: [
                                'peti'   => $this->korektorPeti,
                                'kiloan' => $this->korektorKiloan,
                                'sisa'   => $this->korektorSisa,
                                'bentes' => $this->korektorBentes,
                                'total'  => $this->korektorTotalKg,
                                'dariKandang'  => $this->grandTotal['kilo'],
                                'selisih'      => $this->selisihKg,
                                'statusLabel'  => $this->statusKorektor['label'] ?? '-',
                                'statusColor'  => $this->statusKorektor['color'] ?? 'success',
                            ],
                            info: [
                                'penginput'      => $this->namaPenyimpan,
                                'validator'      => $this->namaValidator,
                                'waktuValidasi'  => $this->waktuValidasi,
                                'korektor'       => $this->namaKorektorPenyimpan,
                                'waktuKorektor'  => $this->waktuKorektorUpdate,
                            ],
                        ),
                        'Laporan-Produksi-Telur-' . $this->tanggal . '.xlsx'
                    );
                }),

            Action::make('exportPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => Auth::user()->hasAnyRole(['admin', 'super_admin']))
                ->authorize(fn() => Auth::user()->hasAnyRole(['admin', 'super_admin']))
                ->action(function () {
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('filament.pages.laporan-produksi-telur-pdf', [
                        'tanggal'       => $this->tanggal,
                        'kandangs'      => $this->kandangs,
                        'gridData'      => $this->gridData,
                        'kandangPakan'  => $this->kandangPakan,
                        'allPakan'      => $this->allPakan,
                        'kandangTotals' => $this->kandangTotals,
                        'grandTotal'    => $this->grandTotal,
                        'korektor'      => [
                            'peti'        => $this->korektorPeti,
                            'kiloan'      => $this->korektorKiloan,
                            'sisa'        => $this->korektorSisa,
                            'bentes'      => $this->korektorBentes,
                            'total'       => $this->korektorTotalKg,
                            'dariKandang' => $this->grandTotal['kilo'],
                            'selisih'     => $this->selisihKg,
                            'statusLabel' => $this->statusKorektor['label'] ?? '-',
                            'statusColor' => $this->statusKorektor['color'] ?? 'success',
                        ],
                        'info'          => [
                            'penginput'     => $this->namaPenyimpan,
                            'validator'     => $this->namaValidator,
                            'waktuValidasi' => $this->waktuValidasi,
                            'korektor'      => $this->namaKorektorPenyimpan,
                            'waktuKorektor' => $this->waktuKorektorUpdate,
                        ],
                    ])->setPaper('a4', 'landscape');

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        'Laporan-Produksi-Telur-' . $this->tanggal . '.pdf'
                    );
                }),

            // Super Admin / Admin bisa membuka data hanya jika belum divalidasi.
            Action::make('bukaKunci')
                ->label('Buka Data')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(
                    fn() => ($this->isSuperAdmin || $this->isAdmin)
                        && ($this->isProduksiLocked || $this->isKorektorLocked)
                        && ! $this->is_validated
                )
                ->modalHeading('Buka Data Produksi Telur')
                ->modalDescription(
                    'Bagian yang dipilih akan dibuka agar bisa diedit kembali.'
                )
                ->form([
                    DatePicker::make('tanggal')
                        ->label('Tanggal Data')
                        ->default($this->tanggal)
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required(),

                    Radio::make('scope')
                        ->label('Bagian yang ingin dibuka')
                        ->options([
                            'produksi' => 'Produksi Telur saja (grid kandang)',
                            'korektor' => 'Analisa Korektor saja',
                            'semua'    => 'Semua (Produksi & Korektor)',
                        ])
                        ->default('semua')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->bukaKunci($data['tanggal'], $data['scope']);
                }),

            // Muncul hanya setelah data sudah divalidasi.
            Action::make('batalValidasi')
                ->label('Batal Validasi')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(
                    fn() => ($this->isSuperAdmin || $this->isAdmin)
                        && $this->is_validated
                )
                ->requiresConfirmation()
                ->modalHeading('Batalkan Validasi Produksi Telur')
                ->modalDescription(
                    'Status validasi akan direset menjadi belum tervalidasi. ' .
                        'Data produksi dan korektor tetap terkunci. ' .
                        'Hanya status validasi yang dibatalkan.'
                )
                ->modalSubmitActionLabel('Ya, Batalkan Validasi')
                ->form([
                    DatePicker::make('tanggal')
                        ->label('Tanggal Data')
                        ->default($this->tanggal)
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->batalkanValidasi($data['tanggal']);
                }),

            // Muncul hanya jika data memenuhi syarat validasi dan belum divalidasi.
            Action::make('validateProduksiGlobal')
                ->label('Validasi Data')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(
                    fn() =>
                    $this->showValidateButton
                        && ! $this->is_validated
                )
                ->requiresConfirmation()
                ->modalHeading('Validasi Data Produksi Telur')
                ->modalDescription(
                    'Data yang sudah divalidasi tidak dapat diubah kecuali oleh ' .
                        'Super Admin. Pastikan Produksi Telur dan Analisa Korektor sudah benar.'
                )
                ->modalSubmitActionLabel('Ya, Validasi')
                ->action(fn() => $this->validateProduksi()),
        ];
    }

    // ─── State Utama (Matriks Excel) ─────────────────────────

    public string $tanggal = '';
    protected string $tanggalSebelumnya = '';
    public bool $isEditable = true;

    public string $namaUserLogin    = '';  // nama user yang sedang login
    public string $namaPenyimpan    = '';  // nama user yang menyimpan data produksi (grid)
    public string $namaValidator    = '';  // nama user yang memvalidasi
    public string $waktuValidasi    = '';  // waktu validasi dalam format human-readable

    // ─── Audit khusus Korektor (terpisah dari audit produksi) ────
    public string $namaKorektorPenyimpan = ''; // siapa terakhir isi/ubah korektor
    public string $waktuKorektorUpdate   = ''; // kapan korektor terakhir diubah

    /**
     * Grid data 10 baris input per kandang:
     * $gridData[id_kandang][rowIndex] = ['id' => x, 'butir' => x, 'kilo' => y, 'tray' => z]
     */
    public array $gridData = [];

    // ─── State Analisa Korektor ──────────────────────────────
    public ?int   $korektorPeti     = null;
    public ?float $korektorKiloan   = null;
    public ?float $korektorSisa     = null;
    public ?float $korektorBentes   = null;
    public ?string $korektorCatatan = null;

    public bool $produksiDiperbaiki = false;
    public bool $korektorDiperbaiki = false;

    public string $waktuPenyimpanan = '';

    public float $korektorTotalKg = 0.0;
    public float $selisihKg       = 0.0;
    public array $statusKorektor  = [];

    private const KG_PER_PETI = 10.0;

    /**
     * Dropdown pilihan pakan campuran aktif per kandang:
     * $kandangPakan[id_kandang] = id_produksi_pakan_campuran
     */
    public array $kandangPakan = [];

    /**
     * Akumulasi total per kandang untuk footer:
     * $kandangTotals[id_kandang] = ['butir' => x, 'kilo' => y, 'tray' => z]
     */
    public array $kandangTotals = [];

    /**
     * Akumulasi total keseluruhan untuk header summary:
     */
    public array $grandTotal = ['butir' => 0, 'kilo' => 0.00, 'tray' => 0];

    // ─── Status & Izin Pengeditan ─────────────────────────────

    #[Locked] public bool $isSuperAdmin = false;
    #[Locked] public bool $isAdmin = false;
    #[Locked] public bool $canEdit = true;
    #[Locked] public bool $canEditKorektor = true;
    #[Locked] public bool $showValidateButton = false;
    #[Locked] public bool $canViewProduksiTab = true;
    #[Locked] public bool $canViewKorektorTab = true;
    #[Locked] public bool $isProduksiLocked = false;
    #[Locked] public bool $isKorektorLocked = false;
    #[Locked] public bool $is_validated = false;
    #[Locked] public ?int $produksiTelurId = null;

    public bool $isCreator = false;
    public bool $isDraftSaved = false;
    public bool $isLocked = false;          // true jika sudah TERVALIDASI (kunci total)
    public bool $isPegawaiKandang = false;
    public bool $isPegawaiRuko = false;

    // ─── Supporting Data ─────────────────────────────────────

    public array $kandangs = [];
    public array $allPakan = [];
    public int $maxRows = 10; // 10 baris input harian kaku sesuai template Excel Anda



    // ─── Lifecycle Hooks ─────────────────────────────────────

    public function mount(): void
    {
        $this->isSuperAdmin = Auth::user()->hasRole('super_admin');
        $this->isAdmin = Auth::user()->hasRole('admin');
        $this->tanggal = now()->toDateString();
        $this->tanggalSebelumnya = $this->tanggal;
        $this->loadKandangs();
        $this->loadPakanByTanggal();
        $this->loadExistingDataByTanggal();
    }

    private function sessionKey(): string
    {
        return 'pt_draft_' . Auth::id() . '_' . ($this->tanggal ?? 'nodate');
    }

    private function saveToSession(): void
    {
        Session::put($this->sessionKey(), [
            'gridData'    => $this->gridData,
            'kandangPakan' => $this->kandangPakan,
        ]);
    }

    public function save(): void
    {
        $this->validate(['tanggal' => 'required|date']);

        // ── Satpam 1: akun harus boleh mengisi Produksi Telur ──
        if (! $this->canViewProduksiTab) {
            $this->denyAccess('Akun Anda tidak memiliki izin mengisi Produksi Telur.');
            return;
        }

        // ── Satpam 2: cek kunci dari DATABASE ──
        // Tanggal baru (null) boleh lanjut. Data terkunci/tervalidasi ditolak.
        $produksi = ProduksiTelur::whereDate('tanggal', $this->tanggal)->first();

        if ($produksi && ($produksi->is_locked || $produksi->is_validated)) {
            $this->denyAccess('Data sudah dikunci/divalidasi. Hubungi Admin untuk membuka kembali.');
            return;
        }

        // ── Bersihkan data grid ──
        // Loop dari $this->kandangs (dari database), bukan dari $this->gridData mentah,
        // supaya id kandang palsu dari browser tidak ikut tersimpan.
        $dataBersih = [];

        foreach ($this->kandangs as $kandang) {
            $idKandang = $kandang['id'];

            // Batasi sebanyak baris yang disediakan template
            $rows = array_slice(array_values($this->gridData[$idKandang] ?? []), 0, $this->maxRows);

            foreach ($rows as $row) {
                $butir = (int) ($row['butir'] ?? 0);
                $kilo  = (float) ($row['kilo'] ?? 0);
                $tray  = (float) ($row['tray'] ?? 0);

                if ($butir < 0 || $kilo < 0 || $tray < 0) {
                    Notification::make()
                        ->title('Nilai tidak valid')
                        ->body('Butir, kilo, dan tray tidak boleh bernilai negatif.')
                        ->warning()
                        ->send();
                    return;
                }

                // Lewati baris kosong. Pakai == (bukan ===) karena $kilo dan $tray bertipe float.
                if ($butir == 0 && $kilo == 0 && $tray == 0) {
                    continue;
                }

                $dataBersih[$idKandang][] = [
                    'butir' => $butir,
                    'kilo'  => $kilo,
                    'tray'  => $tray,
                ];
            }
        }

        // ── Minimal ada satu baris data ──
        if (empty($dataBersih)) {
            Notification::make()
                ->title('Data Kosong')
                ->body('Harap isi minimal satu baris data produksi telur sebelum menyimpan.')
                ->warning()
                ->send();
            return;
        }

        // ── Pakan wajib dipilih untuk kandang yang ada produksinya ──
        foreach (array_keys($dataBersih) as $idKandang) {
            if (empty($this->kandangPakan[$idKandang])) {
                Notification::make()
                    ->title('Pakan belum dipilih')
                    ->body('Pilih pakan untuk semua kandang yang ada produksinya.')
                    ->warning()
                    ->send();
                return;
            }
        }

        // ── Pakan yang dipilih harus benar-benar ada di database ──
        $idPakanDipilih = collect(array_keys($dataBersih))
            ->map(fn($id) => $this->kandangPakan[$id])
            ->unique()
            ->values();

        if (ProduksiPakanCampuran::whereIn('id', $idPakanDipilih)->count() !== $idPakanDipilih->count()) {
            $this->denyAccess('Pakan yang dipilih tidak valid. Muat ulang halaman lalu pilih kembali.');
            return;
        }

        $userId      = Auth::id();
        $isPerbaikan = false;   // diisi di dalam transaksi, dipakai untuk notifikasi

        // ── Simpan di dalam transaksi ──
        try {
            DB::transaction(function () use ($userId, $dataBersih, &$isPerbaikan) {
                // Ambil ulang dengan kunci baris, supaya dua klik bersamaan tidak lolos keduanya
                $produksi = ProduksiTelur::whereDate('tanggal', $this->tanggal)
                    ->lockForUpdate()
                    ->first();

                if ($produksi && ($produksi->is_locked || $produksi->is_validated)) {
                    throw new \RuntimeException(
                        'Data sudah dikunci/divalidasi. Hubungi Admin untuk membuka kembali.'
                    );
                }

                // Data baru -> buat; data yang sudah dibuka kuncinya -> pakai yang lama
                $produksi ??= new ProduksiTelur();

                // Record sudah ada = ini penyimpanan ulang setelah "Buka Data" = perbaikan.
                // WAJIB dihitung SEBELUM save(), karena sesudahnya exists selalu true.
                $isPerbaikan = $produksi->exists;

                if (! $produksi->exists) {
                    $produksi->tanggal    = $this->tanggal;
                    $produksi->created_by = $userId;   // hanya diisi sekali, saat pertama dibuat
                }

                $produksi->is_locked     = true;
                $produksi->saved_by      = $userId;        // siapa yang terakhir menyimpan
                $produksi->saved_at      = now();          // kapan (tidak ikut berubah saat validasi)
                $produksi->is_diperbaiki = $isPerbaikan;   // label (Diperbaiki)
                $produksi->save();

                // Hapus detail lama, lalu isi ulang dari data bersih
                DetailProduksiTelur::where('id_produksi_telur', $produksi->id)->delete();

                foreach ($dataBersih as $idKandang => $rows) {
                    $idPakanSelected = $this->kandangPakan[$idKandang] ?: null;

                    foreach ($rows as $row) {
                        DetailProduksiTelur::create([
                            'id_produksi_telur'          => $produksi->id,
                            'id_kandang'                 => $idKandang,
                            'id_produksi_pakan_campuran' => $idPakanSelected,
                            'jumlah_telur_butir'         => $row['butir'],
                            'jumlah_telur_kilo'          => $row['kilo'],
                            'jumlah_telur_tray'          => $row['tray'],
                        ]);
                    }
                }

                if (method_exists($produksi, 'recalculateTotals')) {
                    $produksi->recalculateTotals();
                }
            });
        } catch (\RuntimeException $e) {
            // Hanya menangkap penolakan kunci dari dalam transaksi.
            // Error database lain sengaja dibiarkan muncul agar tidak tersembunyi.
            $this->denyAccess($e->getMessage());
            return;
        }

        $korektor = ProduksiTelurKorektor::where('id_produksi_telur', $produksi->id)->first();
        $this->computePermissions($produksi, $korektor);

        Notification::make()
            ->title($isPerbaikan ? 'Perbaikan Berhasil Disimpan' : 'Data Berhasil Disimpan')
            ->body(
                ($isPerbaikan ? 'Diperbaiki oleh: ' : 'Disimpan oleh: ') . Auth::user()->name .
                    '. Data kini terkunci, hubungi Admin bila perlu koreksi.'
            )
            ->success()
            ->send();

        Session::forget($this->sessionKey());

        $this->dispatch('data-saved');
        $this->loadExistingDataByTanggal();   // mengisi ulang produksiTelurId, nama penyimpan, dan label
    }

    /**
     * Simpan analisa korektor ke tabel TERPISAH (produksi_telur_korektors).
     * Begitu berhasil disimpan, langsung dikunci (is_locked = true) —
     * tidak ada satupun user biasa yang bisa mengedit lagi setelah ini,
     * termasuk yang baru saja menyimpan.
     */
    public function saveKorektorOnly(): void
    {
        // ── Satpam 1: akun harus boleh melihat/mengisi tab Korektor ──
        if (! $this->canViewKorektorTab) {
            $this->denyAccess('Akun Anda tidak memiliki izin mengisi Korektor.');
            return;
        }

        // ── Satpam 2: Produksi Telur harus sudah disimpan (dicek ke DATABASE) ──
        $produksi = ProduksiTelur::whereDate('tanggal', $this->tanggal)->first();

        if (! $produksi || ! $produksi->is_locked) {
            $this->denyAccess('Simpan Produksi Telur terlebih dahulu sebelum menyimpan Analisa Korektor.');
            return;
        }

        // ── Satpam 3: data yang sudah divalidasi hanya boleh diubah Super Admin ──
        if ($produksi->is_validated && ! $this->isSuperAdmin) {
            $this->denyAccess('Data sudah divalidasi.');
            return;
        }

        // ── Satpam 4: korektor yang sudah dikunci hanya boleh diubah Super Admin ──
        $korektor = ProduksiTelurKorektor::firstOrNew([
            'id_produksi_telur' => $produksi->id,
        ]);

        if ($korektor->exists && $korektor->is_locked && ! $this->isSuperAdmin) {
            $this->denyAccess('Korektor sudah dikunci. Hubungi Super Admin untuk membuka kembali.');
            return;
        }

        // ── Nilai tidak boleh negatif ──
        foreach ([$this->korektorPeti, $this->korektorKiloan, $this->korektorSisa, $this->korektorBentes] as $nilai) {
            if (($nilai ?? 0) < 0) {
                Notification::make()
                    ->title('Nilai tidak valid')
                    ->body('Nilai korektor tidak boleh negatif.')
                    ->warning()
                    ->send();
                return;
            }
        }

        // ── Semua satpam lolos: simpan data ──
        $isNew = ! $korektor->exists;   // dihitung SEBELUM save()

        $korektor->korektor_peti    = $this->korektorPeti;
        $korektor->korektor_kiloan  = $this->korektorKiloan;
        $korektor->korektor_sisa    = $this->korektorSisa;
        $korektor->korektor_bentes  = $this->korektorBentes;
        $korektor->korektor_catatan = $this->korektorCatatan;

        if ($isNew) {
            $korektor->created_by = Auth::id();   // hanya diisi sekali
        }

        $korektor->is_locked     = true;
        $korektor->saved_by      = Auth::id();
        $korektor->saved_at      = now();
        $korektor->is_diperbaiki = ! $isNew;      // sudah ada sebelumnya = perbaikan
        $korektor->save();

        Notification::make()
            ->title($isNew ? 'Analisa korektor berhasil disimpan' : 'Perbaikan korektor berhasil disimpan')
            ->body('Data korektor kini terkunci dan tidak bisa diedit lagi tanpa izin Super Admin.')
            ->success()
            ->send();

        // ✅ Ganti baris manual sebelumnya dengan pemanggilan ini agar state sinkron sempurna
        $this->loadExistingDataByTanggal();
    }

    private function restoreFromSession(): void
    {
        $draft = Session::get($this->sessionKey());
        if (!$draft) return;

        foreach ($this->kandangs as $kandang) {
            $id = $kandang['id'];

            if (isset($draft['gridData'][$id])) {
                foreach ($draft['gridData'][$id] as $rowIdx => $row) {
                    $this->gridData[$id][$rowIdx] = [
                        'id'    => null,
                        'butir' => (int)   ($row['butir'] ?? 0),
                        'kilo'  => (float) ($row['kilo']  ?? 0),
                        'tray'  => (float) ($row['tray']  ?? 0),
                    ];
                }
            }

            if (isset($draft['kandangPakan'][$id])) {
                $this->kandangPakan[$id] = $draft['kandangPakan'][$id];
            }
        }
    }

    protected function loadKandangs(): void
    {
        $this->kandangs = Kandang::orderBy('nama_kandang')->get(['id', 'nama_kandang'])->toArray();
        $this->resetMatrix();
    }

    protected function loadPakanByTanggal(): void
    {
        $this->allPakan = ProduksiPakanCampuran::query()
            ->join('produksi_pakans', 'produksi_pakan_campurans.id_produksi_pakan', '=', 'produksi_pakans.id')
            ->join('barangs', 'produksi_pakan_campurans.id_barang', '=', 'barangs.id')
            ->whereDate('produksi_pakans.tanggal_produksi', $this->tanggal)
            ->where(function ($query) {
                $query->where('produksi_pakan_campurans.keluar_pullet', '>', 0)
                    ->orWhere('produksi_pakan_campurans.keluar_l1', '>', 0)
                    ->orWhere('produksi_pakan_campurans.keluar_l2', '>', 0);
            })
            ->orderBy('produksi_pakan_campurans.id')
            ->get([
                'produksi_pakan_campurans.id',
                'barangs.nama_barang',
            ])
            ->toArray();
    }

    public function loadExistingDataByTanggal(): void
    {
        $produksi = ProduksiTelur::whereDate('tanggal', $this->tanggal)->first();

        if (! $produksi) {
            $this->korektorPeti = $this->korektorKiloan = $this->korektorSisa = $this->korektorBentes = null;
            $this->korektorCatatan = null;
            $this->resetMatrix();
            $this->restoreFromSession();
            $this->recalculate();
            return;
        }

        $this->produksiTelurId = $produksi->id;
        $this->is_validated    = (bool) $produksi->is_validated;

        // ✅ Ambil korektor dari tabel TERPISAH
        $korektor = ProduksiTelurKorektor::where('id_produksi_telur', $produksi->id)->first();

        $this->korektorPeti     = $korektor?->korektor_peti    ?: null;
        $this->korektorKiloan   = $korektor?->korektor_kiloan  ?: null;
        $this->korektorSisa     = $korektor?->korektor_sisa    ?: null;
        $this->korektorBentes   = $korektor?->korektor_bentes  ?: null;
        $this->korektorCatatan  = $korektor?->korektor_catatan ?: null;

        $this->loadKorektorAuditInfo($korektor);

        // Evaluasi perizinan berdasarkan status kunci produksi & korektor
        $this->computePermissions($produksi, $korektor);

        $details = DetailProduksiTelur::where('id_produksi_telur', $produksi->id)->get();

        foreach ($this->kandangs as $kandang) {
            $idKandang = $kandang['id'];
            $this->gridData[$idKandang] = [];
            $this->kandangPakan[$idKandang] = null;

            $kandangDetails = $details->where('id_kandang', $idKandang)->values();

            if ($kandangDetails->count() > 0) {
                $this->kandangPakan[$idKandang] = $kandangDetails->first()->id_produksi_pakan_campuran;
            }

            for ($i = 0; $i < $this->maxRows; $i++) {
                if (isset($kandangDetails[$i])) {
                    $butirRaw = (int) $kandangDetails[$i]->jumlah_telur_butir;
                    $kiloRaw  = (float) $kandangDetails[$i]->jumlah_telur_kilo;
                    $trayRaw  = (float) $kandangDetails[$i]->jumlah_telur_tray;

                    $this->gridData[$idKandang][$i] = [
                        'id' => $kandangDetails[$i]->id,
                        'butir' => $butirRaw ?: null,
                        'kilo'  => $kiloRaw  ?: null,
                        'tray'  => $trayRaw  ?: null,
                    ];
                } else {
                    $this->gridData[$idKandang][$i] = [
                        'id' => null,
                        'butir' => null,
                        'kilo' => null,
                        'tray' => null,
                    ];
                }
            }
        }

        $this->muatPakanTersimpan();

        $this->recalculate();
    }

    protected function loadKorektorAuditInfo(?ProduksiTelurKorektor $korektor): void
    {
        if (! $korektor || ! $korektor->exists) {
            $this->namaKorektorPenyimpan = '';
            $this->waktuKorektorUpdate   = '';
            $this->korektorDiperbaiki    = false;
            return;
        }

        // saved_by untuk data baru. created_by menjadi cadangan untuk data lama.
        $this->namaKorektorPenyimpan = \App\Models\User::find(
            $korektor->saved_by ?? $korektor->created_by
        )?->name ?? 'Tidak diketahui';

        $waktu = $korektor->saved_at ?? $korektor->updated_at ?? $korektor->created_at;

        $this->waktuKorektorUpdate = $waktu
            ? \Carbon\Carbon::parse($waktu)->format('d M Y H:i')
            : '';

        $this->korektorDiperbaiki = (bool) ($korektor->is_diperbaiki ?? false);
    }

    protected function resetMatrix(): void
    {
        foreach ($this->kandangs as $kandang) {
            $id = $kandang['id'];
            $this->gridData[$id] = [];
            for ($i = 0; $i < $this->maxRows; $i++) {
                $this->gridData[$id][$i] = [
                    'id' => null,
                    'butir' => null,
                    'kilo' => null,
                    'tray' => null,
                ];
            }
            $this->kandangPakan[$id] = null;
        }

        $this->produksiTelurId = null;
        $this->is_validated    = false;
        $this->isEditable      = true;
        $this->kandangTotals   = [];
        $this->grandTotal      = ['butir' => 0, 'kilo' => 0.00, 'tray' => 0];
        $this->namaKorektorPenyimpan = '';
        $this->waktuKorektorUpdate = '';

        $this->computePermissions(null);
    }

    // ─── Real-Time Listeners & Recalculate ───────────────────

    public function updatedTanggal(): void
    {
        $keyLama = 'pt_draft_' . Auth::id() . '_' . $this->tanggalSebelumnya;
        Session::forget($keyLama);
        $this->tanggalSebelumnya = $this->tanggal;

        $this->loadKandangs();
        $this->loadPakanByTanggal();
        $this->loadExistingDataByTanggal();
    }

    public function updated($propertyName): void
    {
        if (str_starts_with($propertyName, 'gridData') || str_starts_with($propertyName, 'kandangPakan')) {
            $this->recalculate();

            if (! $this->produksiTelurId) {
                $this->saveToSession();
            }
        }

        if (str_starts_with($propertyName, 'korektor')) {
            $this->hitungKorektor();
        }
    }

    public function recalculate(): void
    {
        $this->kandangTotals = [];
        $this->grandTotal = ['butir' => 0, 'kilo' => 0.00, 'tray' => 0];

        foreach ($this->kandangs as $kandang) {
            $idKandang = $kandang['id'];
            $this->kandangTotals[$idKandang] = ['butir' => 0, 'kilo' => 0.00, 'tray' => 0];

            if (isset($this->gridData[$idKandang])) {
                foreach ($this->gridData[$idKandang] as $row) {
                    $butir = (int) ($row['butir'] ?? 0);
                    $kilo  = (float) ($row['kilo'] ?? 0);
                    $tray  = (float) ($row['tray'] ?? 0);

                    $this->kandangTotals[$idKandang]['butir'] += $butir;
                    $this->kandangTotals[$idKandang]['kilo']  += $kilo;
                    $this->kandangTotals[$idKandang]['tray']  += $tray;

                    $this->grandTotal['butir'] += $butir;
                    $this->grandTotal['kilo']  += $kilo;
                    $this->grandTotal['tray']  += $tray;
                }
            }
        }

        $this->hitungKorektor();
    }

    private function denyAccess(string $body, string $title = 'Akses Ditolak'): void
    {
        Notification::make()->title($title)->body($body)->danger()->send();
    }

    public function hitungKorektor(): void
    {
        $petiKg = ($this->korektorPeti ?? 0) * self::KG_PER_PETI;

        $this->korektorTotalKg = round(
            $petiKg + ($this->korektorKiloan ?? 0) + ($this->korektorSisa ?? 0) + ($this->korektorBentes ?? 0),
            2
        );

        $this->selisihKg = round($this->korektorTotalKg - $this->grandTotal['kilo'], 2);

        $abs = abs($this->selisihKg);
        $this->statusKorektor = match (true) {
            $abs == 0.0 => ['label' => 'Sesuai (Presisi)', 'color' => 'success'],
            $abs <= 2.0 => ['label' => 'Selisih Wajar',     'color' => 'warning'],
            default     => ['label' => 'Selisih Tinggi',    'color' => 'danger'],
        };
    }

    // ─── Evaluasi Izin Pengeditan & Validasi ─────────────────

    /**
     * Aturan barunya:
     * 1. Super Admin selalu bisa edit apapun, kapanpun.
     * 2. Kalau sudah TERVALIDASI → semuanya terkunci total untuk user biasa.
     * 3. Kalau BELUM tervalidasi:
     *    - Grid produksi bisa diedit HANYA jika belum pernah disimpan (isProduksiLocked = false).
     *    - Korektor bisa diedit HANYA jika belum pernah disimpan (isKorektorLocked = false).
     *    - Tombol Validasi hanya muncul untuk yang BUKAN pencatat (creator),
     *      dan hanya setelah grid produksi pernah disimpan.
     */
    protected function computePermissions($produksi = null, $korektor = null): void
    {
        $this->isSuperAdmin = Auth::user()->hasRole('super_admin');
        $this->isAdmin      = Auth::user()->hasRole('admin');
        $this->namaUserLogin = Auth::user()->name;

        $this->isPegawaiKandang = Auth::user()->hasRole('Pegawai Kandang');
        $this->isPegawaiRuko    = Auth::user()->hasRole('Pegawai Ruko');

        if ($this->isPegawaiKandang) {
            $this->canViewProduksiTab = true;
            $this->canViewKorektorTab = false;
        } elseif ($this->isPegawaiRuko) {
            $this->canViewProduksiTab = false;
            $this->canViewKorektorTab = true;
        } else {
            $this->canViewProduksiTab = true;
            $this->canViewKorektorTab = true;
        }

        if (!$produksi) {
            $this->isLocked = false;
            $this->isProduksiLocked = false;
            $this->isKorektorLocked = false;
            $this->isDraftSaved = false;
            $this->isCreator = false;
            $this->canEdit = true;
            $this->canEditKorektor = false;
            $this->showValidateButton = false;
            $this->isEditable = true;
            $this->waktuPenyimpanan = '';

            $this->namaPenyimpan = '';
            $this->namaValidator = '';
            $this->waktuValidasi = '';
            return;
        }

        $this->isDraftSaved = true;
        $this->isLocked = (bool) $produksi->is_validated;
        $this->isCreator = ($produksi->created_by == (string) Auth::id());
        $this->isProduksiLocked = (bool) $produksi->is_locked;
        $this->isKorektorLocked = $korektor ? (bool) $korektor->is_locked : false;

        $this->namaPenyimpan = \App\Models\User::find(
            $produksi->saved_by ?? $produksi->created_by
        )?->name ?? 'Tidak diketahui';

        $waktuSimpan = $produksi->saved_at ?? $produksi->updated_at ?? $produksi->created_at;

        $this->waktuPenyimpanan = $waktuSimpan
            ? \Carbon\Carbon::parse($waktuSimpan)->format('d M Y H:i')
            : '';

        $this->produksiDiperbaiki = (bool) ($produksi->is_diperbaiki ?? false);

        $this->namaValidator = $produksi->validated_by ?? '';
        $this->waktuValidasi = $produksi->validated_at
            ? \Carbon\Carbon::parse($produksi->validated_at)->format('d M Y H:i')
            : '';

        // ── 1. Jika sudah tervalidasi → terkunci total untuk semua user (termasuk Super Admin) ──
        if ($produksi->is_validated) {
            $this->canEdit = false;
            $this->canEditKorektor = false;
            $this->isEditable = false;
            $this->showValidateButton = false;
            return;
        }

        // ── 2. Aturan Standar (Berlaku untuk Super Admin, Admin, dan Pegawai):
        // Jika sudah disimpan/dikunci, maka tidak bisa diedit langsung (harus Buka Data dulu).
        $this->canEdit = ! $this->isProduksiLocked;
        $this->isEditable = $this->canEdit;
        $this->canEditKorektor = $this->isProduksiLocked && ! $this->isKorektorLocked;

        $isAdminOrSuper = $this->isSuperAdmin || $this->isAdmin;

        // Tombol validasi muncul jika produksi & korektor sudah dikunci dan belum tervalidasi
        $this->showValidateButton = $this->isProduksiLocked
            && $this->isKorektorLocked
            && $isAdminOrSuper;
    }

    public function validateProduksi(): void
    {
        if (! $this->produksiTelurId) {
            Notification::make()
                ->title('Simpan data terlebih dahulu sebelum memvalidasi.')
                ->warning()
                ->send();
            return;
        }

        if (! $this->showValidateButton) {
            Notification::make()
                ->title('Tidak diizinkan memvalidasi data ini.')
                ->danger()
                ->send();
            return;
        }


        $produksi = ProduksiTelur::findOrFail($this->produksiTelurId);

        try {
            DB::transaction(function () use ($produksi) {
                // 1. Tandai tervalidasi
                if (method_exists($produksi, 'validate')) {
                    $produksi->validate();
                } else {
                    $produksi->update([
                        'is_validated' => true,
                        'validated_by' => Auth::user()->name,
                        'validated_at' => now(),
                    ]);
                }
                app(\App\Services\ProduksiTelurService::class)
                    ->buatJurnalDariProduksi($produksi, Auth::id());
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Memvalidasi Data')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $this->loadExistingDataByTanggal();

        Notification::make()
            ->title('Data produksi telur berhasil divalidasi.')
            ->success()
            ->send();
    }

    /**
     * ✅ BARU — khusus Super Admin.
     * Membatalkan validasi (kunci tertinggi) lalu membuka kunci per-bagian
     * sesuai scope yang dipilih: 'produksi', 'korektor', atau 'semua'.
     *
     * Catatan: jurnal yang sudah terlanjur dibuat oleh ProduksiTelurService
     * TIDAK otomatis dibatalkan di sini — itu di luar cakupan model yang
     * saya lihat. Kalau perlu, tambahkan pemanggilan method pembatalan
     * jurnal (mis. $produksi->service->batalkanJurnal()) di dalam transaction ini.
     */
    public function bukaKunci(string $tanggal, string $scope): void
    {
        if (! $this->isSuperAdmin && ! $this->isAdmin) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $produksi = ProduksiTelur::whereDate('tanggal', $tanggal)->first();

        if (! $produksi) {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->body("Tidak ada data produksi telur untuk tanggal {$tanggal}.")
                ->danger()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($produksi, $scope) {
                // Tolak kalau ada jurnal diposting yang belum dibalik,
                // dan hapus jurnal draft kalau ada.
                app(\App\Services\ProduksiTelurService::class)->bersihkanJurnalSebelumnya($produksi);

                $produksi->is_validated = false;
                $produksi->validated_by = null;
                $produksi->validated_at = null;

                if (in_array($scope, ['produksi', 'semua'], true)) {
                    $produksi->is_locked = false;
                }
                $produksi->save();

                if (in_array($scope, ['korektor', 'semua'], true)) {
                    ProduksiTelurKorektor::where('id_produksi_telur', $produksi->id)
                        ->update(['is_locked' => false]);
                }
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Tidak bisa membuka data')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $label = match ($scope) {
            'produksi' => 'Produksi Telur',
            'korektor' => 'Analisa Korektor',
            default    => 'Produksi Telur & Analisa Korektor',
        };

        Notification::make()
            ->title('Kunci berhasil dibuka')
            ->body("{$label} untuk tanggal {$tanggal} kini bisa diedit kembali.")
            ->warning()
            ->send();

        if ($tanggal === $this->tanggal) {
            $this->is_validated = false;
            $this->loadExistingDataByTanggal();
        }
    }

    /**
     * ✅ BARU — Membatalkan status validasi SAJA (tanpa membuka kunci edit produksi/korektor),
     * supaya data bisa divalidasi ulang (misalnya untuk regenerasi jurnal pembantu
     * setelah perbaikan bug) tanpa membuka celah untuk mengubah isi data lagi.
     */
    public function batalkanValidasi(string $tanggal): void
    {
        if (! $this->isSuperAdmin && ! $this->isAdmin) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $produksi = ProduksiTelur::whereDate('tanggal', $tanggal)->first();

        if (! $produksi) {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->body("Tidak ada data produksi telur untuk tanggal {$tanggal}.")
                ->danger()
                ->send();
            return;
        }

        if (! $produksi->is_validated) {
            Notification::make()
                ->title('Belum Tervalidasi')
                ->body("Data tanggal {$tanggal} memang belum berstatus tervalidasi.")
                ->warning()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($produksi) {
                app(\App\Services\ProduksiTelurService::class)->bersihkanJurnalSebelumnya($produksi);

                $produksi->is_validated = false;
                $produksi->validated_by = null;
                $produksi->validated_at = null;
                $produksi->save();
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Tidak bisa membatalkan validasi')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        Notification::make()
            ->title('Validasi Dibatalkan')
            ->body("Status validasi untuk tanggal {$tanggal} telah direset. Silakan klik 'Validasi Data' lagi untuk membuat jurnal pembantu.")
            ->warning()
            ->send();

        if ($tanggal === $this->tanggal) {
            $this->is_validated = false;
            $this->loadExistingDataByTanggal();
        }
    }

    /**
     * Pastikan pakan yang sudah tersimpan tetap muncul di dropdown,
     * walaupun tidak lolos filter loadPakanByTanggal().
     */
    protected function muatPakanTersimpan(): void
    {
        // Ambil semua id pakan yang tersimpan (buang null/kosong)
        $idTersimpan = array_values(array_filter($this->kandangPakan));

        if (empty($idTersimpan)) {
            return;
        }

        // Cari id yang belum ada di daftar opsi
        $idSudahAda = array_column($this->allPakan, 'id');
        $idHilang   = array_diff($idTersimpan, $idSudahAda);

        if (empty($idHilang)) {
            return;
        }

        // Ambil data pakan yang hilang itu TANPA filter tanggal/keluar
        $tambahan = ProduksiPakanCampuran::query()
            ->join('barangs', 'produksi_pakan_campurans.id_barang', '=', 'barangs.id')
            ->whereIn('produksi_pakan_campurans.id', $idHilang)
            ->get([
                'produksi_pakan_campurans.id',
                'barangs.nama_barang',
            ])
            ->toArray();

        $this->allPakan = array_merge($this->allPakan, $tambahan);
    }
}
