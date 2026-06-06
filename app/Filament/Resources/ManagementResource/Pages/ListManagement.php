<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Filament\Concerns\HasPersistedContactTablePreferences;
use App\Filament\Contracts\PersistsContactTablePreferences;
use App\Filament\Resources\ManagementResource;
use App\Filament\Support\ContactTablePreferencesAction;
use App\Filament\Support\YandexMapAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;

class ListManagement extends ListRecords implements PersistsContactTablePreferences
{
    use HasPersistedContactTablePreferences;

    protected static string $resource = ManagementResource::class;

    protected function getContactTablePreferencesKey(): string
    {
        return 'management';
    }

    protected function getHeaderActions(): array
    {
        return [
            ContactTablePreferencesAction::sortAction(),
            YandexMapAction::make(),
            Actions\CreateAction::make()
                ->label('Создать')
                ->icon('heroicon-o-user-plus')
                ->iconSize(IconSize::Large)
                ->size(Size::Large)
                ->color('primary'),
        ];
    }
}
