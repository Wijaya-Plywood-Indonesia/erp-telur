<?php

namespace App\Filament\Resources\Satpams;

use App\Filament\Resources\Satpams\Pages\CreateSatpam;
use App\Filament\Resources\Satpams\Pages\EditSatpam;
use App\Filament\Resources\Satpams\Pages\ListSatpams;
use App\Filament\Resources\Satpams\RelationManagers\PerangkatsRelationManager;
use App\Filament\Resources\Satpams\RelationManagers\SetoransRelationManager;
use App\Models\Satpam;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SatpamResource extends Resource
{
    protected static ?string $model = Satpam::class;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Satpam';
    protected static ?string $pluralModelLabel = 'Satpam';
    protected static ?string $modelLabel = 'Satpam';
    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('pabrik')
                ->label('Pabrik')
                ->options(['wijaya' => 'Wijaya', 'wahana' => 'Wahana'])
                ->required()
                ->live(),
            TextInput::make('nama')
                ->label('Nama satpam')
                ->required()
                ->maxLength(100),
            TextInput::make('kode_akses')
                ->label('Kode akses (passcode)')
                ->helperText('Diketik satpam sekali saja saat pertama kali membuka aplikasi.')
                ->default(fn () => (string) random_int(100000, 999999))
                ->required()
                ->maxLength(20)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule, $get) => $rule->where('pabrik', $get('pabrik')),
                ),
            TextInput::make('maks_perangkat')
                ->label('Maks. HP bersamaan')
                ->helperText('Berapa HP yang boleh memakai kode ini sekaligus. Isi 0 untuk tanpa batas (mis. akun admin).')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required(),
            Toggle::make('aktif')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Satpam')->searchable(),
                TextColumn::make('pabrik')->badge()->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('kode_akses')->label('Kode akses')->copyable(),
                TextColumn::make('perangkat')
                    ->label('Perangkat')
                    ->state(function (Satpam $r) {
                        $n = $r->perangkats()->count();

                        return $n === 0
                            ? 'Belum aktif'
                            : $n . ' / ' . ((int) $r->maks_perangkat === 0 ? 'tanpa batas' : $r->maks_perangkat) . ' HP';
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'Belum aktif' ? 'gray' : 'success'),
                TextColumn::make('total_pabrik')
                    ->label('Setoran berjalan (pabrik)')
                    ->state(fn (Satpam $r) => 'Rp ' . number_format($r->totalPabrik(), 0, ',', '.')
                        . ' (' . $r->jumlahNotaPabrik() . ' nota)'),
                TextColumn::make('total_satpam')
                    ->label('Penjualan satpam ini')
                    ->state(fn (Satpam $r) => 'Rp ' . number_format($r->totalBerjalan(), 0, ',', '.')
                        . ' (' . $r->jumlahNotaBerjalan() . ' nota)'),
                TextColumn::make('setoran_terakhir')
                    ->label('Disetor terakhir (satpam ini)')
                    ->state(fn (Satpam $r) => $r->setoranTerakhirTeks())
                    ->wrap(),
                TextColumn::make('status_tutup_buku')
                    ->label('Tutup buku')
                    ->state(fn (Satpam $r) => $r->setoranDibuka() ? 'TERBUKA' : 'Terkunci')
                    ->badge()
                    ->color(fn (string $state) => $state === 'TERBUKA' ? 'warning' : 'gray'),
            ])
            ->recordActions([
                Action::make('buka_tutup_buku')
                    ->label('Buka Tutup Buku')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->visible(fn (Satpam $r) => ! $r->setoranDibuka())
                    ->requiresConfirmation()
                    ->modalHeading('Buka kunci tutup buku')
                    ->modalDescription(fn (Satpam $r) => 'Setoran ' . ucfirst($r->pabrik) . ': Rp '
                        . number_format($r->totalPabrik(), 0, ',', '.') . ' dari '
                        . $r->jumlahNotaPabrik() . ' nota. Rincian: ' . $r->rincianBerjalanTeks()
                        . '. Pastikan sama dengan uang yang diserahkan.')
                    ->action(function (Satpam $r) {
                        $setoran = $r->bukaTutupBuku(Auth::id());

                        if (! $setoran) {
                            Notification::make()->title('Belum ada penjualan yang bisa disetor')->warning()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Kunci dibuka untuk pabrik ' . ucfirst($r->pabrik) . '. Tombol aktif di HP satpam mana pun di pabrik ini.')
                            ->success()
                            ->send();
                    }),
                Action::make('batal_buka')
                    ->label('Kunci Lagi')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->visible(fn (Satpam $r) => (bool) $r->setoranDibuka())
                    ->requiresConfirmation()
                    ->action(function (Satpam $r) {
                        $r->setorans()->where('status', 'DIBUKA')->update(['status' => 'DIBATALKAN']);
                        Notification::make()->title('Kunci dipasang kembali')->success()->send();
                    }),
                Action::make('reset_perangkat')
                    ->label('Reset Perangkat')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('danger')
                    ->visible(fn (Satpam $r) => $r->perangkats()->exists())
                    ->requiresConfirmation()
                    ->modalDescription('Semua HP yang memakai kode ini langsung keluar dan harus memasukkan kode akses lagi.')
                    ->action(function (Satpam $r) {
                        $r->perangkats()->delete();
                        Notification::make()->title('Semua perangkat direset')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SetoransRelationManager::class,
            PerangkatsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSatpams::route('/'),
            'create' => CreateSatpam::route('/create'),
            'edit'   => EditSatpam::route('/{record}/edit'),
        ];
    }
}