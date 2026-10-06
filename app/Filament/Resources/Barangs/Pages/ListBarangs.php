<?php

namespace App\Filament\Resources\Barangs\Pages;

use App\Filament\Resources\Barangs\BarangResource;
use App\Models\Barang;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class ListBarangs extends ListRecords
{
    protected static string $resource = BarangResource::class;

    /**
     * Jenis telur yang harganya disamakan di semua lokasi (Ruko, Wijaya, Wahana).
     * Barang dicari dari awalan nama: "Telur Petian Ruko", "Telur Petian Wijaya", dst.
     */
    private const JENIS_TELUR = [
        'petian' => 'Telur Petian',
        'kiloan' => 'Telur Kiloan',
        'bentes' => 'Telur Bentes',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ubah_harga_telur')
                ->label('Ubah Harga Telur')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->modalHeading('Ubah Harga Telur (Semua Lokasi)')
                ->modalDescription('Harga baru berlaku untuk Ruko, Wijaya, dan Wahana sekaligus. Kosongkan jenis yang tidak ingin diubah.')
                ->modalSubmitActionLabel('Simpan Harga')
                ->form([
                    TextInput::make('harga_petian')
                        ->label('Harga Telur Petian (per peti)')
                        ->numeric()
                        ->minValue(1)
                        ->prefix('Rp')
                        ->helperText(fn () => $this->infoHargaSaatIni('Telur Petian')),

                    TextInput::make('harga_kiloan')
                        ->label('Harga Telur Kiloan (per kg)')
                        ->numeric()
                        ->minValue(1)
                        ->prefix('Rp')
                        ->helperText(fn () => $this->infoHargaSaatIni('Telur Kiloan')),

                    TextInput::make('harga_bentes')
                        ->label('Harga Telur Bentes (per kg)')
                        ->numeric()
                        ->minValue(1)
                        ->prefix('Rp')
                        ->helperText(fn () => $this->infoHargaSaatIni('Telur Bentes')),
                ])
                ->action(fn (array $data) => $this->ubahHargaTelur($data)),

            CreateAction::make(),
        ];
    }

    /** Teks bantuan: harga saat ini per lokasi, supaya perbedaan antar lokasi terlihat. */
    private function infoHargaSaatIni(string $label): string
    {
        $barangs = Barang::where('nama_barang', 'like', $label . ' %')
            ->orderBy('nama_barang')
            ->get(['nama_barang', 'harga_jual']);

        if ($barangs->isEmpty()) {
            return 'Barang "' . $label . ' ..." tidak ditemukan';
        }

        return 'Saat ini: ' . $barangs
            ->map(fn ($b) => trim(str_replace($label, '', $b->nama_barang))
                . ' Rp ' . number_format((float) $b->harga_jual, 0, ',', '.'))
            ->implode(' · ');
    }

    private function ubahHargaTelur(array $data): void
    {
        $adaIsi = false;
        foreach (array_keys(self::JENIS_TELUR) as $kode) {
            if (($data["harga_$kode"] ?? '') !== '' && $data["harga_$kode"] !== null) {
                $adaIsi = true;
            }
        }

        if (! $adaIsi) {
            Notification::make()
                ->title('Isi minimal satu harga')
                ->warning()
                ->send();

            return;
        }

        $user    = filament()->auth()->user();
        $perubahan = [];

        DB::transaction(function () use ($data, $user, &$perubahan) {
            foreach (self::JENIS_TELUR as $kode => $label) {
                $input = $data["harga_$kode"] ?? null;
                if ($input === null || $input === '') {
                    continue;
                }
                $hargaBaru = round((float) $input, 2);

                $barangs = Barang::where('nama_barang', 'like', $label . ' %')
                    ->lockForUpdate()
                    ->get();

                foreach ($barangs as $barang) {
                    $hargaLama = (float) $barang->harga_jual;
                    if (abs($hargaLama - $hargaBaru) < 0.005) {
                        continue;   // sudah sama, tidak perlu diubah
                    }

                    $barang->update(['harga_jual' => $hargaBaru]);

                    $perubahan[] = sprintf(
                        '%s: %s → %s',
                        $barang->nama_barang,
                        number_format($hargaLama, 0, ',', '.'),
                        number_format($hargaBaru, 0, ',', '.'),
                    );

                    Log::info('Ubah harga telur', [
                        'barang_id'  => $barang->id,
                        'nama'       => $barang->nama_barang,
                        'harga_lama' => $hargaLama,
                        'harga_baru' => $hargaBaru,
                        'user_id'    => $user?->id,
                        'user_name'  => $user?->name,
                    ]);
                }
            }
        });

        if (empty($perubahan)) {
            Notification::make()
                ->title('Tidak ada perubahan')
                ->body('Semua harga sudah sama dengan yang diisi.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Harga telur berhasil diubah')
            ->body(new HtmlString(implode('<br>', array_map('e', $perubahan))))
            ->success()
            ->send();
    }
}