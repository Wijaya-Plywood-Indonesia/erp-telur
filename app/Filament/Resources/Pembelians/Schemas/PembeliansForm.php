<?php

namespace App\Filament\Resources\Pembelians\Schemas;

use App\Models\Pembelian;
use App\Models\PembelianMetodePembayaran;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PembeliansForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pembelian')
                    ->schema([
                        TextInput::make('nomor_nota')
                            ->label('Nomor Nota')
                            ->required()
                            ->maxLength(255),

                        Select::make('created_by')
                            ->label('Dibuat Oleh')
                            ->relationship('createdBy', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->default(auth()->id()),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->required(),

                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'nama')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $supplier = Supplier::find($state);

                                if (!$supplier) {
                                    $set('supplier_name', null);
                                    $set('supplier_phone', null);
                                    $set('supplier_address', null);
                                    $set('supplier_npwp', null);
                                    return;
                                }

                                $set('supplier_name', $supplier->nama);
                                $set('supplier_phone', $supplier->telepon);
                                $set('supplier_address', $supplier->alamat);
                                $set('supplier_npwp', $supplier->npwp);
                            }),

                        TextInput::make('supplier_name')
                            ->label('Nama Supplier')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('supplier_phone')
                            ->label('Telepon')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('supplier_npwp')
                            ->label('NPWP')
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('supplier_address')
                            ->label('Alamat')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),

                        Select::make('status')
                            ->label('Status')
                            ->options(Pembelian::labelStatus())
                            ->required()
                            ->default(Pembelian::STATUS_DRAFT),

                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('foto')
                            ->label('Foto Nota')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('pembelian')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Nominal')
                    ->schema([
                        TextInput::make('sub_total')
                            ->label('Sub Total')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(callable $set, Get $get) => static::recalculateGrandTotal($set, $get)),

                        TextInput::make('total_diskon')
                            ->label('Diskon')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(callable $set, Get $get) => static::recalculateGrandTotal($set, $get)),

                        TextInput::make('total_ppn')
                            ->label('PPN')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(callable $set, Get $get) => static::recalculateGrandTotal($set, $get)),

                        TextInput::make('ongkir')
                            ->label('Ongkir')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(callable $set, Get $get) => static::recalculateGrandTotal($set, $get)),

                        TextInput::make('biaya_lain')
                            ->label('Biaya Lain')
                            ->numeric()
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(callable $set, Get $get) => static::recalculateGrandTotal($set, $get)),

                        TextInput::make('grand_total')
                            ->label('Grand Total')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->default(0),
                    ])
                    ->columns(3),

                Section::make('Riwayat / Metode Pembayaran')
                    ->description('Detail pembayaran untuk transaksi ini.')
                    ->schema([
                        Repeater::make('metodePembayarans')
                            ->label('')
                            ->relationship('metodePembayarans')
                            ->schema([
                                DatePicker::make('tanggal_bayar')
                                    ->label('Tanggal Bayar')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d M Y'),

                                Select::make('payment_method')
                                    ->label('Metode')
                                    ->options(PembelianMetodePembayaran::labelMetode())
                                    ->required(),

                                TextInput::make('reference_number')
                                    ->label('No. Referensi / Rekening')
                                    ->placeholder('Kosongkan jika tunai'),

                                TextInput::make('amount')
                                    ->label('Nominal Terbayar')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp'),

                                Textarea::make('catatan')
                                    ->label('Catatan')
                                    ->rows(2)
                                    ->columnSpanFull()
                                    ->placeholder('Tidak ada catatan.'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Pembayaran')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->collapsible()
                            ->collapseAllAction(
                                fn($action) => $action->label('Ciutkan Semua')
                            )
                            ->expandAllAction(
                                fn($action) => $action->label('Buka Semua')
                            )
                            ->itemLabel(function (array $state): ?string {
                                $tanggal = isset($state['tanggal_bayar'])
                                    ? \Carbon\Carbon::parse($state['tanggal_bayar'])->translatedFormat('d M Y')
                                    : '-';

                                $metode = PembelianMetodePembayaran::labelMetode()[$state['payment_method'] ?? ''] ?? '-';

                                $nominal = isset($state['amount'])
                                    ? 'Rp ' . number_format((float) $state['amount'], 0, ',', '.')
                                    : '-';

                                return "{$tanggal} — {$metode} — {$nominal}";
                            }),
                    ]),
            ]);
    }

    protected static function recalculateGrandTotal(callable $set, Get $get): void
    {
        $grandTotal =
            ((float) $get('sub_total'))
            - ((float) $get('total_diskon'))
            + ((float) $get('total_ppn'))
            + ((float) $get('ongkir'))
            + ((float) $get('biaya_lain'));

        $set('grand_total', $grandTotal);
    }
}
