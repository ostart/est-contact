<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Concerns\AppliesContactTableDefaultOrder;
use App\Filament\Concerns\UsesContactTableColumnDefaults;
use App\Filament\Resources\ContactResource;
use App\Filament\Support\YandexMapAction;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords
{
    use AppliesContactTableDefaultOrder;
    use UsesContactTableColumnDefaults;
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            YandexMapAction::make(),
        ];
    }
}
