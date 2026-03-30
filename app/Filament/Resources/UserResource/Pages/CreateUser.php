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

        $this->record->refresh();
        if ($this->record->isSuperAdmin() && $this->record->is_banned) {
            $this->record->forceFill([
                'is_banned' => false,
                'ban_reason' => null,
                'banned_at' => null,
            ])->saveQuietly();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
