<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManagement extends EditRecord
{
    protected static string $resource = ManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Если статус "Не обработан" - сбрасываем ответственного
        if (isset($data['status']) && $data['status'] === ContactStatus::NOT_PROCESSED->value) {
            $data['assigned_leader_id'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
