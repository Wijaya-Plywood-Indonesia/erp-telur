<?php

namespace App\Filament\Resources\Satpams\Pages;

use App\Filament\Resources\Satpams\SatpamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListSatpams extends ListRecords
{
    protected static string $resource = SatpamResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}