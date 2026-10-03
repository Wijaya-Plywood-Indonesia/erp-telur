<?php

namespace App\Filament\Pages;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mutasiBaru')
                ->label('Mutasi Baru')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->modalHeading('Mutasi Telur Ruko ke Pabrik')
                ->modalDescription('Disimpan sebagai draft di Jurnal Pembantu. Stok berubah setelah jurnal diposting.')
                ->modalSubmitActionLabel('Simpan Draft')
                ->modalWidth('3xl')
                ->form([
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
                ])
                ->action(function (array $data) {
                    try {
                        $nota = app(MutasiTelurService::class)->mutasi(
                            tanggal: \Carbon\Carbon::parse($data['tanggal'])->toDateString(),
                            tujuan: $data['tujuan'],
                            items: $data['items'] ?? [],
                            keterangan: $data['keterangan'] ?? null,
                            userId: (int) Auth::id(),
                        );

                        Notification::make()->success()
                            ->title('Mutasi tersimpan sebagai draft')
                            ->body("No. dokumen: {$nota}. Posting manual lewat menu Jurnal Pembantu.")
                            ->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()
                            ->title('Mutasi gagal')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    public function batalMutasi(string $nota): void
    {
        try {
            $hasil = app(MutasiTelurService::class)->batal($nota, (int) Auth::id());

            Notification::make()->success()
                ->title('Berhasil')
                ->body($hasil)
                ->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
        }
    }

    /** Riwayat mutasi (satu baris per nomor dokumen), 50 terbaru. */
    public function getRiwayat(): array
    {
        $headers = JurnalPembantuHeader::where('modul_asal', MutasiTelurService::MODUL)
            ->where('no_dokumen', 'NOT LIKE', 'BATAL-%')
            ->with('items')
            ->orderByDesc('tgl_transaksi')
            ->orderByDesc('id')
            ->get()
            ->groupBy('no_dokumen')
            ->take(50);

        $dibatalkan = JurnalUmum::where('nama', 'LIKE', 'BATAL-MUTASITELUR-%')
            ->pluck('nama')
            ->unique()
            ->all();

        return $headers->map(function ($rows, $nota) use ($dibatalkan) {
            $first = $rows->first();

            return [
                'nota'       => $nota,
                'tanggal'    => $first->tgl_transaksi?->format('d/m/Y'),
                'keterangan' => $first->keterangan,
                'batal'      => in_array("BATAL-{$nota}", $dibatalkan, true),
                'status'     => $first->status,
                // hanya sisi debet = akun tujuan
                'rincian'    => $rows->where('map', 'd')->map(fn($h) => [
                    'akun' => $h->nama_akun,
                    'qty'  => (float) $h->items->sum('banyak'),
                ])->values()->all(),
            ];
        })->values()->all();
    }
}