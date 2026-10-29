<?php

namespace App\Filament\Resources\Satpams\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PerangkatsRelationManager extends RelationManager
{
    protected static string $relationship = 'perangkats';

    protected static ?string $title = 'Perangkat Terhubung';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('HP #'),
                TextColumn::make('aktivasi_pada')->label('Diaktifkan')->dateTime('d M Y H:i')->placeholder('-'),
                TextColumn::make('terakhir_dipakai')->label('Terakhir dipakai')->since()->placeholder('-'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Keluarkan')
                    ->modalHeading('Keluarkan perangkat ini?')
                    ->modalDescription('HP ini langsung keluar dan harus memasukkan kode akses lagi.'),
            ])
            ->defaultSort('id');
    }
}