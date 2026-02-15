<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManagement extends CreateRecord
{
    protected static string $resource = ManagementResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Если статус "Не обработан" - сбрасываем ответственного
        if (isset($data['status']) && $data['status'] === ContactStatus::NOT_PROCESSED->value) {
            $data['assigned_leader_id'] = null;
        }
        // Если назначен ответственный - статус автоматически "Назначен исполнитель"
        elseif (! empty($data['assigned_leader_id'])) {
            $data['status'] = ContactStatus::ASSIGNED->value;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
