<?php

namespace App\Filament\Pages;

use App\Models\Ayam;
use App\Models\PencatatanKematianAyam as ModelsPencatatanKematianAyam;
use App\Services\MutasiAyamService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use UnitEnum;

class PencatatanKematianAyam extends Page
{
    /* STREAMING_CHUNK:Configuring Filament page attributes for Mortality-only recording */
    protected static ?string $navigationLabel = 'Ayam Mati';
    protected static ?string $title = 'Pencatatan Ayam Mati';
    protected static UnitEnum|string|null $navigationGroup = 'Produksi & Kandang';
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.pencatatan-kematian-ayam';

    // ─── State Utama ───────────────────────────────────────────
    public string $tanggal = '';
    protected string $tanggalSebelumnya = '';

    // Status Data
    public bool $hasSavedData = false;
    public bool $is_validated  = false;

    // State Hak Akses Form & Tombol
    public bool $isEditable   = true;
    public bool $canSaveDraft = true;
    public bool $canValidate  = false;

    /**
     * Grid data input kematian per ayam:
     * $gridData[id_ayam] = ['mati' => x]
     */
    public array $gridData = [];
    public array $listAyam = [];

    // Summary Totals
    public int $totalAwal  = 0;
    public int $totalMati  = 0;
    public int $totalSisa  = 0;

    /* STREAMING_CHUNK:Mount lifecycle hook initialization */
    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
        $this->tanggalSebelumnya = $this->tanggal;
        $this->loadDataByTanggal();
    }

    private function sessionKey(): string
    {
        return 'kematian_draft_' . Auth::id() . '_' . ($this->tanggal ?? 'nodate');
    }

    private function saveToSession(): void
    {
        Session::put($this->sessionKey(), [
            'gridData' => $this->gridData,
        ]);
    }

    private function restoreFromSession(): void
    {
        $draft = Session::get($this->sessionKey());
        if (!$draft || !isset($draft['gridData'])) return;

        foreach ($this->listAyam as $item) {
            $id = $item['id'];
            if (isset($draft['gridData'][$id])) {
                $this->gridData[$id] = [
                    'mati' => (int) ($draft['gridData'][$id]['mati'] ?? 0),
                ];
            }
        }
    }

    /* STREAMING_CHUNK:Role permissions helper methods */
    public function isSuperAdmin(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) return false;

        if (method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'Super Admin', 'admin', 'Admin'])) {
            return true;
        }

        return (bool) ($user->is_super_admin ?? $user->is_admin ?? ($user->role === 'super_admin' || $user->role === 'admin'));
    }

    public function isValidator(): bool
    {
        if ($this->isSuperAdmin()) return true;

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) return false;

        if (method_exists($user, 'hasRole') && $user->hasRole(['validator', 'Validator', 'supervisor', 'Supervisor'])) {
            return true;
        }

        return (bool) ($user->is_validator ?? ($user->role === 'validator' || $user->role === 'supervisor'));
    }

    /* STREAMING_CHUNK:Loading active population without Afkir column */
    public function loadDataByTanggal(): void
    {
        $ayams = Ayam::with(['kandang'])
            ->get()
            ->sortBy(function ($ayam) {
                $nama = strtoupper($ayam->kandang?->nama_kandang ?? $ayam->nama_batch);
                return str_contains($nama, 'DOC') ? 'ZZZ_' . $nama : $nama;
            });

        // Ambil record kematian pada tanggal aktif
        $existingOnDate = ModelsPencatatanKematianAyam::whereDate('tanggal', $this->tanggal)->get();

        $this->hasSavedData = $existingOnDate->isNotEmpty();
        $this->is_validated = $existingOnDate->where('is_validated', true)->count() > 0;

        // Evaluasi Hak Akses Berdasarkan Role
        if ($this->isSuperAdmin()) {
            $this->isEditable   = true;
            $this->canSaveDraft = true;
            $this->canValidate  = true;
        } elseif ($this->isValidator()) {
            $this->isEditable   = !$this->is_validated;
            $this->canSaveDraft = false;
            $this->canValidate  = !$this->is_validated;
        } else {
            $this->isEditable   = !$this->hasSavedData;
            $this->canSaveDraft = !$this->hasSavedData;
            $this->canValidate  = false;
        }

        $this->listAyam = [];
        $this->gridData = [];

        $tglAktif = Carbon::parse($this->tanggal)->startOfDay();

        foreach ($ayams as $a) {
            $idAyam = $a->id;

            // Hitung akumulasi kematian SEBELUM tanggal aktif
            $kematianSebelumnya = (int) ModelsPencatatanKematianAyam::where('id_ayam', $idAyam)
                ->whereDate('tanggal', '<', $this->tanggal)
                ->sum(DB::raw('jumlah_mati + jumlah_afkir'));

            $populasiAwalHariIni = max(0, $a->jumlah_awal - $kematianSebelumnya);

            $rowToday = $existingOnDate->where('id_ayam', $idAyam)->first();
            $matiVal  = $rowToday ? (int) $rowToday->jumlah_mati : 0;

            $namaKandang = $a->kandang?->nama_kandang ?? 'Tanpa Kandang';
            if (str_contains(strtoupper($a->nama_batch), 'DOC') && !str_contains(strtoupper($namaKandang), 'DOC')) {
                $namaKandang = 'DOC';
            }

            // Bersihkan teks usia statis "(77 Minggu)" jika ada di string nama_batch
            $cleanBatchName = trim(preg_replace('/\s*\(\d+[^)]*\)/i', '', $a->nama_batch));

            // Hitung usia relatif terhadap Tanggal Transaksi Aktif
            $usiaHariAwal = $a->usia ?? 0;
            $hariBerjalan = $a->tanggal_masuk
                ? (int) $a->tanggal_masuk->startOfDay()->diffInDays($tglAktif)
                : 0;

            $totalHariRelatif = $usiaHariAwal + $hariBerjalan;
            $mingguRelatif = intdiv($totalHariRelatif, 7);
            $umurFormat = "{$mingguRelatif} minggu";

            $this->listAyam[] = [
                'id'            => $idAyam,
                'nama_batch'    => $cleanBatchName,
                'nama_kandang'  => $namaKandang,
                'populasi_awal' => $populasiAwalHariIni,
                'umur_format'   => $umurFormat,
            ];

            $this->gridData[$idAyam] = [
                'mati' => $matiVal > 0 ? $matiVal : null,
            ];
        }

        if ($existingOnDate->isEmpty()) {
            $this->restoreFromSession();
        }

        $this->recalculate();
    }

    /* STREAMING_CHUNK:Handling real-time input updates */
    public function updatedTanggal(): void
    {
        Session::forget('kematian_draft_' . Auth::id() . '_' . $this->tanggalSebelumnya);
        $this->tanggalSebelumnya = $this->tanggal;

        $this->loadDataByTanggal();
    }

    public function updated($propertyName): void
    {
        if (str_starts_with($propertyName, 'gridData')) {
            $this->recalculate();
            if ($this->isEditable) {
                $this->saveToSession();
            }
        }
    }

    public function recalculate(): void
    {
        $this->totalAwal = 0;
        $this->totalMati = 0;

        foreach ($this->listAyam as $item) {
            $id = $item['id'];
            $awal = (int) $item['populasi_awal'];
            $mati = (int) ($this->gridData[$id]['mati'] ?? 0);

            $this->totalAwal += $awal;
            $this->totalMati += $mati;
        }

        $this->totalSisa = max(0, $this->totalAwal - $this->totalMati);
    }

    /* STREAMING_CHUNK:Saving mortality draft without Afkir field */
    public function save(): void
    {
        $this->validate(['tanggal' => 'required|date']);

        if (!$this->isEditable && !$this->canSaveDraft) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Data pada tanggal ini sudah disimpan dan terkunci.')
                ->danger()
                ->send();
            return;
        }

        $userId = Auth::id();

        DB::transaction(function () use ($userId) {
            foreach ($this->gridData as $idAyam => $row) {
                $mati = (int) ($row['mati'] ?? 0);

                if ($mati <= 0) {
                    ModelsPencatatanKematianAyam::whereDate('tanggal', $this->tanggal)
                        ->where('id_ayam', $idAyam)
                        ->where('jumlah_afkir', 0)
                        ->delete();
                    continue;
                }

                ModelsPencatatanKematianAyam::updateOrCreate(
                    [
                        'id_ayam' => $idAyam,
                        'tanggal' => $this->tanggal,
                    ],
                    [
                        'jumlah_mati' => $mati,
                        'created_by'  => $userId,
                    ]
                );
            }
        });

        Session::forget($this->sessionKey());

        Notification::make()
            ->title('Data Berhasil Disimpan')
            ->body('Draft catatan kematian ayam telah disimpan.')
            ->success()
            ->send();

        $this->loadDataByTanggal();
    }

    /* STREAMING_CHUNK:Validating data and generating mortality journal entry */
    public function validateData(): void
    {
        if (!$this->canValidate && !$this->isSuperAdmin()) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Anda tidak memiliki wewenang untuk memvalidasi data ini.')
                ->danger()
                ->send();
            return;
        }

        $this->save();

        try {
            DB::transaction(function () {
                ModelsPencatatanKematianAyam::whereDate('tanggal', $this->tanggal)->update([
                    'is_validated' => true,
                    'validated_by' => Auth::id(),
                    'validated_at' => now(),
                ]);

                // Terbitkan Jurnal Kematian Ayam Otomatis via MutasiAyamService
                app(MutasiAyamService::class)->prosesJurnalKematian($this->tanggal, Auth::id());
            });

            $this->is_validated = true;
            $this->loadDataByTanggal();

            Notification::make()
                ->title('Catatan Divalidasi')
                ->body('Data kematian berhasil divalidasi & Jurnal Beban Kematian diterbitkan!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Memvalidasi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
