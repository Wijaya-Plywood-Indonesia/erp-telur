<?php

namespace App\Filament\Resources\Satpams\Pages;

use App\Filament\Resources\Satpams\SatpamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSatpam extends EditRecord
{
    protected static string $resource = SatpamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}