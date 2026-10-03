<?php

namespace App\Filament\Pages;

use App\Models\JurnalPembantuHeader;
use App\Models\PengajuanMutasiTelur;
use App\Services\MutasiTelurService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class MutasiTelur extends Page
{
    protected static ?string $navigationLabel = 'Mutasi Telur';
    protected static ?string $title = 'Mutasi Telur Ruko ke Pabrik';
    protected static UnitEnum|string|null $navigationGroup = 'Stock Barang';
    protected static ?int $navigationSort = 5;

    public function getView(): string
    {
        return 'filament.pages.mutasi-telur';
    }

    // ── Form (dipakai untuk Mutasi Baru dan Edit) ─────────────────────────────

    protected function formMutasi(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(now())
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),

                Select::make('tujuan')
                    ->label('Tujuan Mutasi')
                    ->options(MutasiTelurService::TUJUAN)
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        // Segarkan stok tujuan di semua baris
                        $items = $get('items') ?? [];
                        foreach ($items as $k => $row) {
                            $info = app(MutasiTelurService::class)->infoJenis($row['jenis'] ?? null, $state);
                            $items[$k] = array_merge($row, $info);
                        }
                        $set('items', $items);
                    }),
            ]),

            Repeater::make('items')
                ->label('Telur yang dimutasi')
                ->table([
                    TableColumn::make('Jenis'),
                    TableColumn::make('Stok Ruko')->width('130px'),
                    TableColumn::make('Stok Tujuan')->width('130px'),
                    TableColumn::make('Jumlah')->width('130px'),
                ])
                ->schema([
                    Select::make('jenis')
                        ->options(MutasiTelurService::JENIS)
                        ->required()
                        ->live()
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $info = app(MutasiTelurService::class)->infoJenis($state, $get('../../tujuan'));
                            foreach ($info as $key => $val) {
                                $set($key, $val);
                            }
                        }),
                    TextInput::make('stok_ruko')->disabled()->dehydrated(false),
                    TextInput::make('stok_tujuan')->disabled()->dehydrated(false),
                    TextInput::make('qty')
                        ->numeric()
                        ->minValue(0.01)
                        ->required()
                        ->placeholder('Peti / Kg'),
                ])
                ->defaultItems(1)
                ->minItems(1)
                ->maxItems(count(MutasiTelurService::JENIS))
                ->addActionLabel('Tambah jenis telur'),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(2),
        ];
    }

    // ── Header: ajukan mutasi baru ────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mutasiBaru')
                ->label('Mutasi Baru')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->modalHeading('Ajukan Mutasi Telur Ruko ke Pabrik')
                ->modalDescription('Pengajuan harus divalidasi akun lain sebelum masuk ke Jurnal Pembantu.')
                ->modalSubmitActionLabel('Ajukan')
                ->modalWidth('3xl')
                ->form($this->formMutasi())
                ->action(function (array $data) {
                    try {
                        $nota = app(MutasiTelurService::class)->ajukan(
                            tanggal: \Carbon\Carbon::parse($data['tanggal'])->toDateString(),
                            tujuan: $data['tujuan'],
                            items: $data['items'] ?? [],
                            keterangan: $data['keterangan'] ?? null,
                            userId: (int) Auth::id(),
                        );

                        Notification::make()->success()
                            ->title('Mutasi diajukan')
                            ->body("No. dokumen: {$nota}. Menunggu validasi dari akun lain.")
                            ->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()
                            ->title('Pengajuan gagal')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    // ── Aksi per baris riwayat (dipanggil dari blade: mountAction('nama', {id: X})) ──

    public function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->modalHeading('Edit Pengajuan Mutasi Telur')
            ->modalDescription('Hanya bisa diedit selama belum divalidasi.')
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->modalWidth('3xl')
            ->form($this->formMutasi())
            ->fillForm(function (array $arguments): array {
                $p   = PengajuanMutasiTelur::findOrFail($arguments['id']);
                $svc = app(MutasiTelurService::class);

                return [
                    'tanggal'    => $p->tanggal?->toDateString(),
                    'tujuan'     => $p->tujuan,
                    'keterangan' => $p->keterangan,
                    'items'      => collect($p->items)->map(fn($i) => array_merge(
                        ['jenis' => $i['jenis'], 'qty' => $i['qty']],
                        $svc->infoJenis($i['jenis'], $p->tujuan)
                    ))->values()->all(),
                ];
            })
            ->action(function (array $data, array $arguments) {
                try {
                    $nota = app(MutasiTelurService::class)->ubah(
                        id: (int) $arguments['id'],
                        tanggal: \Carbon\Carbon::parse($data['tanggal'])->toDateString(),
                        tujuan: $data['tujuan'],
                        items: $data['items'] ?? [],
                        keterangan: $data['keterangan'] ?? null,
                        user: Auth::user(),
                    );

                    Notification::make()->success()
                        ->title('Pengajuan diperbarui')
                        ->body($nota)
                        ->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Gagal menyimpan')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public function validasiAction(): Action
    {
        return Action::make('validasi')
            ->label('Validasi')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Validasi mutasi telur')
            ->modalDescription('Setelah divalidasi, data terkunci dan jurnal masuk ke Jurnal Pembantu sebagai draft. Posting tetap manual.')
            ->modalSubmitActionLabel('Ya, Validasi')
            ->action(function (array $arguments) {
                try {
                    $nota = app(MutasiTelurService::class)->validasi((int) $arguments['id'], Auth::user());

                    Notification::make()->success()
                        ->title('Mutasi divalidasi')
                        ->body("{$nota} masuk ke Jurnal Pembantu sebagai draft.")
                        ->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Gagal memvalidasi')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public function tolakAction(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->color('danger')
            ->modalHeading('Tolak pengajuan mutasi')
            ->modalSubmitActionLabel('Tolak')
            ->form([
                Textarea::make('alasan')
                    ->label('Alasan penolakan')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                try {
                    $nota = app(MutasiTelurService::class)->tolak((int) $arguments['id'], Auth::user(), $data['alasan']);

                    Notification::make()->success()->title('Pengajuan ditolak')->body($nota)->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Gagal menolak')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public function batalAction(): Action
    {
        return Action::make('batal')
            ->label('Batalkan')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Batalkan mutasi telur')
            ->modalDescription('Pengajuan dibatalkan. Bila jurnalnya sudah ada: draft dihapus, atau dibuat jurnal pembalik bila sudah diposting.')
            ->modalSubmitActionLabel('Ya, Batalkan')
            ->action(function (array $arguments) {
                try {
                    $hasil = app(MutasiTelurService::class)->batalkan((int) $arguments['id'], Auth::user());

                    Notification::make()->success()->title('Berhasil')->body($hasil)->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Gagal membatalkan')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    // ── Data riwayat ──────────────────────────────────────────────────────────

    /** 50 pengajuan terbaru beserta hak aksi untuk user yang sedang login. */
    public function getRiwayat(): array
    {
        $user    = Auth::user();
        $isSuper = $user->hasRole('super_admin');
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin']);

        $list = PengajuanMutasiTelur::with(['creator', 'validator'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Status jurnal pembantu per nomor dokumen (draft / diposting)
        $statusJurnal = JurnalPembantuHeader::where('modul_asal', MutasiTelurService::MODUL)
            ->whereIn('no_dokumen', $list->pluck('no_dokumen'))
            ->pluck('status', 'no_dokumen');

        return $list->map(function (PengajuanMutasiTelur $p) use ($user, $isSuper, $isAdmin, $statusJurnal) {
            $jurnal       = $statusJurnal[$p->no_dokumen] ?? null;
            $milikSendiri = (int) $p->created_by === (int) $user->id;
            $menunggu     = $p->status === PengajuanMutasiTelur::MENUNGGU;

            $label = match ($p->status) {
                PengajuanMutasiTelur::MENUNGGU    => 'Menunggu Validasi',
                PengajuanMutasiTelur::TERVALIDASI => 'Tervalidasi' . ($jurnal === 'diposting' ? ' (Diposting)' : ' (Draft Jurnal)'),
                PengajuanMutasiTelur::DITOLAK     => 'Ditolak',
                PengajuanMutasiTelur::DIBATALKAN  => 'Dibatalkan',
                default                           => $p->status,
            };

            return [
                'id'           => $p->id,
                'nota'         => $p->no_dokumen,
                'tanggal'      => $p->tanggal?->format('d/m/Y'),
                'tujuan'       => MutasiTelurService::TUJUAN[$p->tujuan] ?? $p->tujuan,
                'rincian'      => collect($p->items)->map(fn($i) => [
                    'label' => MutasiTelurService::JENIS[$i['jenis']] ?? $i['jenis'],
                    'qty'   => (float) $i['qty'],
                ])->all(),
                'status'       => $p->status,
                'status_label' => $label,
                'pembuat'      => $p->creator?->name,
                'validator'    => $p->validator?->name,
                'keterangan'   => $p->keterangan,
                'alasan_tolak' => $p->alasan_tolak,
                'can_validasi' => $menunggu && (!$milikSendiri || $isSuper),
                'can_edit'     => $menunggu && ($milikSendiri || $isSuper),
                'can_batal'    => ($menunggu && ($milikSendiri || $isSuper))
                    || ($p->status === PengajuanMutasiTelur::TERVALIDASI && $isAdmin),
            ];
        })->all();
    }
}