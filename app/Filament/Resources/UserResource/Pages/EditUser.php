<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Notifications\UserApprovedNotification;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected bool $wasApprovedBefore = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isSuperAdmin())
                ->successRedirectUrl(UserResource::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
        $this->wasApprovedBefore = (bool) $this->record->getOriginal('is_approved');
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
        if ($this->record->isSuperAdmin() && $this->record->is_banned) {
            $this->record->forceFill([
                'is_banned' => false,
                'ban_reason' => null,
                'banned_at' => null,
            ])->saveQuietly();
        }

        if (!$this->wasApprovedBefore && $this->record->is_approved) {
            $this->record->notify(new UserApprovedNotification());
        }
    }
}
