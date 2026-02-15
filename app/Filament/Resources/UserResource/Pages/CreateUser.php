<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;

    protected function afterCreate(): void
    {
        // Если роли не назначены, добавляем роль Leader по умолчанию
        if ($this->record->roles->isEmpty()) {
            $this->record->assignRole('leader');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
