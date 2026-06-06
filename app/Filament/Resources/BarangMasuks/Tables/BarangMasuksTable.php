<?php

namespace App\Filament\Resources\BarangMasuks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class BarangMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label("Tanggal Barang Masuk")
                    ->formatStateUsing(function ($state) {
                        if (!$state)
                            return '-';

                        return Carbon::parse($state)
                            ->locale('id')
                            ->translatedFormat('l , d F Y');
                    })
                    ->sortable(),
                TextColumn::make('nomor_nota')
                    ->label("Nomor nota")
                    ->searchable(),
                TextColumn::make('penerima_barang')
                    ->label('Penerima barang')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Dibuat Oleh')
                    ->formatStateUsing(fn($record) => "{$record->created_by} (" . $record->created_at->format('d/m/Y H:i') . ")")
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('validated_by')
                    ->label('Divalidasi Oleh')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state || $state === 'Belum Validasi') {
                            return 'Belum Validasi';
                        }
                        // Menggunakan updated_at sebagai asumsi waktu validasi
                        return "{$state} (" . $record->updated_at->format('d/m/Y H:i') . ")";
                    })
                    ->badge()
                    // Karena state sekarang berisi tanggal, kita cek apakah mengandung kata 'Belum'
                    ->color(fn($state) => str_contains($state, 'Belum') ? 'danger' : 'success')
                    ->searchable(),
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
                //
            ])
            ->recordActions([
                Action::make('validate')
                    ->label('Validasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    // 1. Sembunyikan tombol jika data SUDAH divalidasi
                    ->hidden(fn($record) => $record->validated_by !== null)

                    // 2. Logika Hak Akses (Siapa yang bisa melihat tombol ini)
                    ->visible(function ($record) {
                        $user = Auth::user();

                        // JIKA SUPER ADMIN: Bisa memvalidasi siapapun (termasuk dirinya sendiri)
                        if ($user->hasRole('super_admin')) {
                            return true;
                        }

                        // JIKA USER BIASA: Tombol HANYA muncul jika dia BUKAN orang yang membuat record tersebut
                        // Ini memastikan harus ada 2 user berbeda (Pembuat & Validator)
                        return $record->created_by !== $user->name;
                    })

                    // 3. Eksekusi Validasi
                    ->action(function ($record) {
                        $record->update([
                            'validated_by' => 'Divalidasi oleh ' . Auth::user()->name . ' pada ' . now()->translatedFormat('d M Y, H:i'),
                        ]);
                    })
                    ->successNotificationTitle('Data Berhasil Divalidasi'),
                ViewAction::make()->visible(fn($record) => Auth::user()->hasRole('super_admin') || $record->validated_by === null),
                EditAction::make()->visible(fn($record) => Auth::user()->hasRole('super_admin') || $record->validated_by === null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn() => Auth::user()->hasRole('super_admin')),
                ]),
            ]);
    }
}
