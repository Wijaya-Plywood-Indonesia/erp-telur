<?php

namespace App\Filament\Resources\Kandangs\RelationManagers;

use App\Filament\Resources\Kandangs\KandangResource;
use App\Models\Kandang;
use App\Models\SubAnakAkun;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AyamRelationManager extends RelationManager
{
    protected static string $relationship = 'ayam';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_sub_anak_akun')
                    ->label('Akun Ayam (CoA)')
                    ->relationship('subAnakAkun', 'nama_sub_anak_akun')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state && empty($get('nama_batch'))) {
                            $subAkun = SubAnakAkun::find($state);
                            if ($subAkun) {
                                $set('nama_batch', $subAkun->nama_sub_anak_akun);
                            }
                        }
                    })
                    ->required(),

                DatePicker::make('tanggal_masuk')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->default(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary')
                    ->required(),

                TextInput::make('jumlah_awal')
                    ->label('Jumlah Ayam')
                    ->required()
                    ->numeric(),

                TextInput::make('usia_minggu')
                    ->label('Usia Ayam')
                    ->numeric()
                    ->default(1)
                    ->suffix('minggu')
                    ->required()
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state(intdiv($record->umur_hari, 7));
                        }
                    }),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_batch')
            ->columns([
                TextColumn::make('nama_batch')
                    ->label('Nama Batch / Akun')
                    ->searchable()
                    ->state(function ($record) {
                        return trim(preg_replace('/\s*\(\d+[^)]*\)/i', '', $record->nama_batch));
                    })
                    ->description(fn($record) => $record->subAnakAkun?->kode_sub_anak_akun ?? '-'),

                TextColumn::make('tanggal_masuk')
                    ->label('Tanggal Masuk')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('jumlah_awal')
                    ->label('Jumlah Ayam Awal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jumlah_saat_ini')
                    ->label('Jumlah Ayam Saat Ini')
                    ->numeric()
                    ->badge()
                    ->color(fn($state): string => match (true) {
                        $state < 0 => 'danger',
                        (int) $state === 0 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('usia')
                    ->label('Usia Masuk')
                    ->state(fn($record) => round(($record->usia ?? 0) / 7, 2) . ' minggu')
                    ->suffix(' minggu')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('umur_format')
                    ->label('Usia Ayam')
                    ->state(fn($record) => $record->umur_format)
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tambahkan filter di sini jika diperlukan nanti
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $inputHariCurrent = isset($data['usia_minggu'])
                            ? (int) round((float) $data['usia_minggu'] * 7)
                            : 7;
                        $tglMasuk = isset($data['tanggal_masuk'])
                            ? Carbon::parse($data['tanggal_masuk'])->startOfDay()
                            : now()->startOfDay();
                        $selisihHari = (int) $tglMasuk->diffInDays(now()->startOfDay());
                        $data['usia'] = max(0, $inputHariCurrent - $selisihHari);
                        if (empty($data['nama_batch']) && !empty($data['id_sub_anak_akun'])) {
                            $subAkun = SubAnakAkun::find($data['id_sub_anak_akun']);
                            if ($subAkun) {
                                $data['nama_batch'] = $subAkun->nama_sub_anak_akun;
                            }
                        }
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Ubah Data Ayam')
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        if (isset($data['usia_minggu'])) {
                            $inputHariCurrent = (int) round((float) $data['usia_minggu'] * 7);
                            $tglMasuk = isset($data['tanggal_masuk'])
                                ? Carbon::parse($data['tanggal_masuk'])->startOfDay()
                                : $record->tanggal_masuk->startOfDay();

                            $selisihHari = (int) $tglMasuk->diffInDays(now()->startOfDay());
                            $data['usia'] = max(0, $inputHariCurrent - $selisihHari);
                        }
                        if (empty($data['nama_batch']) && !empty($data['id_sub_anak_akun'])) {
                            $subAkun = SubAnakAkun::find($data['id_sub_anak_akun']);
                            if ($subAkun) {
                                $data['nama_batch'] = $subAkun->nama_sub_anak_akun;
                            }
                        }
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
