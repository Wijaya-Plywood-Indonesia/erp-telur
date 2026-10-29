<?php

namespace App\Filament\Resources\Satpams\RelationManagers;

use App\Models\SatpamSetoran;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class SetoransRelationManager extends RelationManager
{
    protected static string $relationship = 'setorans';

    protected static ?string $title = 'Riwayat Setoran';
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('dibuka_pada')->label('Dibuka')->dateTime('d M Y H:i'),
                TextColumn::make('pembuka.name')->label('Dibuka oleh')->placeholder('-'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'SELESAI' => 'success',
                        'DIBUKA'  => 'warning',
                        default   => 'gray',
                    }),
                TextColumn::make('total')->label('Total')->money('IDR', locale: 'id'),
                TextColumn::make('jumlah_nota')->label('Nota'),
                TextColumn::make('rincian')
                    ->label('Rincian per satpam')
                    ->state(fn (SatpamSetoran $r) => $r->rincianTeks())
                    ->wrap(),
                TextColumn::make('penutup.nama')->label('Ditutup di HP')->placeholder('-'),
                TextColumn::make('ditutup_pada')->label('Waktu tutup')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading('Hapus riwayat setoran?')
                    ->modalDescription(fn (SatpamSetoran $record) => $record->status === 'SELESAI'
                        ? 'Setoran ini sudah ditutup. Kalau dihapus, ' . $record->jumlah_nota
                            . ' nota di dalamnya kembali masuk ke "Setoran berjalan" dan ikut dihitung lagi pada '
                            . 'penutupan berikutnya. Hapus hanya kalau penutupan ini salah.'
                        : 'Riwayat ini akan dihapus. Tidak ada nota yang terpengaruh.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Setoran berstatus SELESAI yang ikut terhapus akan mengembalikan notanya ke "Setoran berjalan".'),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}