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
        if (!$this->wasApprovedBefore && $this->record->is_approved) {
            $this->record->notify(new UserApprovedNotification());
        }
    }
}
