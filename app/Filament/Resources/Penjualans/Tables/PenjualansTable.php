<?php

namespace App\Filament\Resources\Penjualans\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenjualansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_nota')->searchable(),

                TextColumn::make('status_transaksi')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'LUNAS' => 'success',
                        'COD' => 'warning',
                        'PENDING' => 'gray',
                        'BELUM DIBAYAR' => 'danger',
                        'DIBATALKAN' => 'danger',
                        default => 'secondary',
                    })
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('validator.name')
                    ->label('Validator')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Belum Divalidasi') // Menampilkan teks jika belum ada yang validasi
                    ->badge() // Opsional: menjadikannya badge agar lebih menonjol
                    ->color(fn($state) => $state ? 'success' : 'gray') // Warna hijau jika ada validator, abu-abu jika belum
                    ->toggleable(),

                TextColumn::make('tanggal')->dateTime()->sortable(),

                TextColumn::make('keterangan')
                    ->placeholder('kosong')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('keterangan_pembayaran')
                    ->placeholder('kosong')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nama_customer')
                    ->searchable()
                    ->placeholder('Tidak Dicatat'),

                TextColumn::make('metode_pembayaran')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label('Total Pembelian')
                    ->money('IDR', locale: 'id_ID')
                    ->sortable(),
            ])

            ->defaultSort('created_at', 'desc')

            ->filters([
                // Validasi: Semua / Sudah divalidasi / Belum divalidasi
                TernaryFilter::make('validated_by')
                    ->label('Status Validasi')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah divalidasi')
                    ->falseLabel('Belum divalidasi')
                    ->nullable(),

                // Rentang tanggal nota
                Filter::make('tanggal')
                    ->label('Tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '>=', $d))
                            ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if (! empty($data['dari'])) {
                            $out[] = 'Dari ' . \Carbon\Carbon::parse($data['dari'])->translatedFormat('d M Y');
                        }
                        if (! empty($data['sampai'])) {
                            $out[] = 'Sampai ' . \Carbon\Carbon::parse($data['sampai'])->translatedFormat('d M Y');
                        }
                        return $out;
                    }),
            ])

            ->recordActions([
                ViewAction::make(),

                // 🖨 CETAK
                Action::make('cetak')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(fn($record) => route('nota.cetak', $record))
                    ->openUrlInNewTab()
                    ->visible(
                        fn($record) =>
                        !empty($record->validated_by)
                            && !in_array($record->status_transaksi, [
                                'DIBATALKAN',
                                'BELUM DIBAYAR',
                                'PENDING',
                            ])
                    ),

                Action::make('edit_keterangan')
                    ->label('Edit Keterangan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Edit Keterangan')
                    ->modalSubmitActionLabel('Simpan')
                    ->form([
                        TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('Masukkan keterangan...')
                            ->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Keterangan berhasil diperbarui')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = filament()->auth()->user();
                        // Super Admin selalu bisa lihat tombol
                        if ($user->hasRole('super_admin')) {
                            return true;
                        }
                        // Staff hanya bisa lihat jika belum divalidasi
                        return empty($record->validated_by);
                    })
                    ->requiresConfirmation()
                    ->action(function ($record, DeleteAction $action) {

                        // 1. Cek relasi yang ada di model Penjualan
                        $adaDetailBarang = $record->details()->exists();
                        $adaReturn       = $record->returns()->exists();

                        // 2. Logika validasi
                        if ($adaDetailBarang || $adaReturn) {
                            $alasan = $adaReturn
                                ? 'Sudah terdapat data retur yang terikat pada nota ini.'
                                : 'Masih terdapat rincian detail barang (item) dalam nota.';

                            Notification::make()
                                ->danger()
                                ->title('Data Gagal Dihapus!')
                                ->body("Nota {$record->no_nota} tidak dapat dihapus karena: {$alasan} Silakan hapus data relasi terlebih dahulu.")
                                ->persistent()
                                ->send();

                            // Menghentikan proses penghapusan
                            $action->halt();
                        }

                        // 3. Jika lolos pemeriksaan, hapus record
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Berhasil Dihapus')
                            ->body("Data penjualan Nota {$record->no_nota} telah berhasil dihapus dari sistem.")
                            ->send();
                    }),
            ])
            ->headerActions([
                //
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}