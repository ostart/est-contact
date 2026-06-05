<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Concerns\HasPersistedContactTablePreferences;
use App\Filament\Contracts\PersistsContactTablePreferences;
use App\Filament\Resources\ContactResource;
use App\Filament\Support\ContactTablePreferencesAction;
use App\Filament\Support\YandexMapAction;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords implements PersistsContactTablePreferences
{
    use HasPersistedContactTablePreferences;

    protected static string $resource = ContactResource::class;

    protected function getContactTablePreferencesKey(): string
    {
        return 'contacts';
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactTablePreferencesAction::sortAction(),
            ContactTablePreferencesAction::resetAction(),
            YandexMapAction::make(),
        ];
    }
}
