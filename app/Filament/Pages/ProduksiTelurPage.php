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
use Illuminate\Support\Facades\Session;
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
                        ),
                        'Laporan-Produksi-Telur-' . $this->tanggal . '.xlsx'
                    );
                }),

            // ✅ BARU: Super Admin bisa membuka kembali bagian yang sudah terkunci.
            // Ini otomatis membatalkan validasi (karena validasi adalah kunci tertinggi),
            // lalu membuka kunci sesuai scope yang dipilih.
            Action::make('bukaKunci')
                ->label('Buka Data')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn() => $this->isSuperAdmin)
                ->modalHeading('Buka Data Produksi Telur')
                ->modalDescription('Validasi akan dibatalkan dan bagian yang dipilih bisa diedit ulang.')
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
        ];
    }

    // ─── State Utama (Matriks Excel) ─────────────────────────

    public string $tanggal = '';
    protected string $tanggalSebelumnya = '';
    public bool $is_validated = false;
    public bool $isEditable = true;
    public ?int $produksiTelurId = null;

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

    public bool $isSuperAdmin = false;
    public bool $isCreator = false;
    public bool $isDraftSaved = false;
    public bool $isLocked = false;          // true jika sudah TERVALIDASI (kunci total)
    public bool $isProduksiLocked = false;  // true jika grid produksi sudah pernah disimpan (kunci per-bagian)
    public bool $isKorektorLocked = false;  // true jika korektor sudah pernah disimpan (kunci per-bagian)
    public bool $canEdit = true;
    public bool $canEditKorektor = true;
    public bool $showValidateButton = false;

    // ─── Supporting Data ─────────────────────────────────────

    public array $kandangs = [];
    public array $allPakan = [];
    public int $maxRows = 10; // 10 baris input harian kaku sesuai template Excel Anda

    public bool $isPegawaiKandang = false;
    public bool $isPegawaiRuko = false;
    public bool $canViewProduksiTab = true;
    public bool $canViewKorektorTab = true;

    // ─── Lifecycle Hooks ─────────────────────────────────────

    public function mount(): void
    {
        $this->isSuperAdmin = Auth::user()->hasRole('super_admin');
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

        if (! $this->isEditable) {
            $this->denyAccess('Data sudah dikunci/divalidasi. Hubungi Super Admin untuk membuka kembali.');
            return;
        }

        if (! $this->canViewProduksiTab) {
            $this->denyAccess('Akun Anda tidak memiliki izin mengisi Produksi Telur.');
            return;
        }

        $adaData = false;

        foreach ($this->gridData as $idKandang => $rows) {
            foreach ($rows as $row) {
                $butir = (int) ($row['butir'] ?? 0);
                $kilo  = (float) ($row['kilo'] ?? 0);
                $tray  = (float) ($row['tray'] ?? 0);

                if ($butir > 0 || $kilo > 0 || $tray > 0) {
                    $adaData = true;
                    break 2;
                }
            }
        }

        if (! $adaData) {
            Notification::make()
                ->title('Data Kosong')
                ->body('Harap isi minimal satu baris data produksi telur sebelum menyimpan.')
                ->warning()
                ->send();
            return;
        }

        $userId = Auth::id();

        DB::transaction(function () use ($userId) {
            $existing = ProduksiTelur::whereDate('tanggal', $this->tanggal)
                ->where('created_by', '!=', $userId)
                ->exists();

            if ($existing && ! $this->isSuperAdmin) {
                Notification::make()
                    ->title('Data Sudah Ada')
                    ->body('Data untuk tanggal ini sudah diinput oleh pengguna lain.')
                    ->danger()
                    ->send();
                return;
            }

            $produksi = ProduksiTelur::updateOrCreate(
                ['tanggal' => $this->tanggal],
                [
                    'created_by' => $userId,
                    'is_locked'  => true, // ✅ terkunci otomatis setelah disimpan
                ]
            );

            $this->produksiTelurId = $produksi->id;

            DetailProduksiTelur::where('id_produksi_telur', $produksi->id)->delete();

            foreach ($this->gridData as $idKandang => $rows) {
                $idPakanSelected = $this->kandangPakan[$idKandang] ?: null;

                foreach ($rows as $row) {
                    $butir = (int) ($row['butir'] ?? 0);
                    $kilo  = (float) ($row['kilo'] ?? 0);
                    $tray  = (float) ($row['tray'] ?? 0);

                    if ($butir === 0 && $kilo == 0 && $tray === 0) continue;

                    DetailProduksiTelur::create([
                        'id_produksi_telur'          => $produksi->id,
                        'id_kandang'                 => $idKandang,
                        'id_produksi_pakan_campuran' => $idPakanSelected,
                        'jumlah_telur_butir'         => $butir,
                        'jumlah_telur_kilo'          => $kilo,
                        'jumlah_telur_tray'          => $tray,
                    ]);
                }
            }

            if (method_exists($produksi, 'recalculateTotals')) {
                $produksi->recalculateTotals();
            }
        });

        $namaUser = Auth::user()->name;
        Notification::make()
            ->title('Data Berhasil Disimpan')
            ->body("Disimpan oleh: {$namaUser}. Data kini terkunci — hanya Super Admin yang bisa mengedit lagi.")
            ->success()
            ->send();

        Session::forget($this->sessionKey());

        $this->dispatch('data-saved');
        $this->loadExistingDataByTanggal();
    }

    /**
     * Simpan analisa korektor ke tabel TERPISAH (produksi_telur_korektors).
     * Begitu berhasil disimpan, langsung dikunci (is_locked = true) —
     * tidak ada satupun user biasa yang bisa mengedit lagi setelah ini,
     * termasuk yang baru saja menyimpan.
     */
    public function saveKorektorOnly(): void
    {
        if (! $this->canEditKorektor || ! $this->produksiTelurId) {
            Notification::make()
                ->title('Tidak diizinkan mengubah korektor.')
                ->body('Korektor sudah dikunci. Hubungi Super Admin untuk membuka kembali.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->canViewKorektorTab) {
            $this->denyAccess('Akun Anda tidak memiliki izin mengisi Korektor.');
            return;
        }

        $korektor = ProduksiTelurKorektor::firstOrNew([
            'id_produksi_telur' => $this->produksiTelurId,
        ]);

        $isNew = ! $korektor->exists;

        $korektor->korektor_peti    = $this->korektorPeti;
        $korektor->korektor_kiloan  = $this->korektorKiloan;
        $korektor->korektor_sisa    = $this->korektorSisa;
        $korektor->korektor_bentes  = $this->korektorBentes;
        $korektor->korektor_catatan = $this->korektorCatatan;

        if ($isNew) {
            $korektor->created_by = Auth::id();
        }
        $korektor->updated_by = Auth::id();
        $korektor->is_locked  = true; // ✅ terkunci otomatis setelah disimpan

        $korektor->save();

        Notification::make()
            ->title('Analisa korektor berhasil disimpan')
            ->body('Data korektor kini terkunci dan tidak bisa diedit lagi tanpa izin Super Admin.')
            ->success()
            ->send();

        $this->hitungKorektor();
        $this->loadKorektorAuditInfo($korektor);
        $this->isKorektorLocked = true;
        $this->canEditKorektor = false;
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

        $this->korektorPeti     = $korektor->korektor_peti    ?: null;
        $this->korektorKiloan   = $korektor->korektor_kiloan  ?: null;
        $this->korektorSisa     = $korektor->korektor_sisa    ?: null;
        $this->korektorBentes   = $korektor->korektor_bentes  ?: null;
        $this->korektorCatatan  = $korektor->korektor_catatan ?: null;

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

        $this->recalculate();
    }

    protected function loadKorektorAuditInfo(?ProduksiTelurKorektor $korektor): void
    {
        if (! $korektor || ! $korektor->exists) {
            $this->namaKorektorPenyimpan = '';
            $this->waktuKorektorUpdate = '';
            return;
        }

        $this->namaKorektorPenyimpan = \App\Models\User::find($korektor->updated_by)?->name ?? '-';
        $this->waktuKorektorUpdate = $korektor->updated_at
            ? $korektor->updated_at->format('d M Y H:i')
            : '';
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
            $this->canEditKorektor = true;
            $this->showValidateButton = false;
            $this->isEditable = true;

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

        $this->namaPenyimpan = \App\Models\User::find($produksi->created_by)?->name
            ?? $produksi->created_by
            ?? 'Tidak diketahui';

        $this->namaValidator = $produksi->validated_by ?? '';
        $this->waktuValidasi = $produksi->validated_at
            ? \Carbon\Carbon::parse($produksi->validated_at)->format('d M Y H:i')
            : '';

        // ── 1. Super Admin selalu bisa edit ──
        if ($this->isSuperAdmin) {
            $this->canEdit = true;
            $this->canEditKorektor = true;
            $this->isEditable = true;
            $this->showValidateButton = ! $produksi->is_validated;
            return;
        }

        // ── 2. Sudah tervalidasi → terkunci total untuk user biasa ──
        if ($produksi->is_validated) {
            $this->canEdit = false;
            $this->canEditKorektor = false;
            $this->isEditable = false;
            $this->showValidateButton = false;
            return;
        }

        // ── 3. Belum tervalidasi: kunci per-bagian yang menentukan ──
        $this->canEdit = ! $this->isProduksiLocked;
        $this->isEditable = $this->canEdit;
        $this->canEditKorektor = ! $this->isKorektorLocked;

        // Validasi hanya untuk yang BUKAN pencatat, dan grid produksi harus sudah disimpan,
        // dan bukan pegawai_ruko (dia memang gak boleh lihat/isi tab produksi sama sekali).
        $this->showValidateButton = $this->isProduksiLocked
            && ! $this->isCreator
            && $this->canViewProduksiTab;
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
                if (method_exists($produksi, 'validate')) {
                    $produksi->validate();
                } else {
                    $this->validate(['tanggal' => 'required|date']);
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

        $this->is_validated = true;
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
        if (! $this->isSuperAdmin) {
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

        DB::transaction(function () use ($produksi, $scope) {
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
}
