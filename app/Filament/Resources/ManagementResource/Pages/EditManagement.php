<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource;
use App\Filament\Support\ContactFreezeFields;
use App\Filament\Support\ContactPhotoFields;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManagement extends EditRecord
{
    protected static string $resource = ManagementResource::class;

    protected ?string $pendingFreezeReason = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->successRedirectUrl(ManagementResource::getUrl('index')),
        ];
    }

    public function persistContactPhoto(): void
    {
        ContactPhotoFields::persistPhotoOnEdit($this);
    }

    public function updated($propertyName): void
    {
        if (! is_string($propertyName) || ! str_starts_with($propertyName, 'data.photo')) {
            return;
        }

        $this->js('setTimeout(() => $wire.call("persistContactPhoto"), 500)');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ContactFreezeFields::splitFrozenUntilForForm($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingFreezeReason = trim((string) ($data['freeze_reason'] ?? ''));

        // Если статус "Не обработан" - сбрасываем ответственного
        if (isset($data['status']) && $data['status'] === ContactStatus::NOT_PROCESSED->value) {
            $data['assigned_leader_id'] = null;
        }
        // Если ответственный лидер снят - переключаем статус на "Не обработан" (кроме финальных статусов)
        $recordStatus = $this->record->status instanceof ContactStatus
            ? $this->record->status
            : ContactStatus::tryFrom($this->record->status ?? '');
        if (empty($data['assigned_leader_id']) && $recordStatus && ! $recordStatus->isFinal()) {
            $data['status'] = ContactStatus::NOT_PROCESSED->value;
        }

        return ContactFreezeFields::mergeIntoFormData($data, $recordStatus);
    }

    protected function afterSave(): void
    {
        if (
            ($this->pendingFreezeReason ?? '') !== ''
            && $this->record->wasChanged('status')
            && $this->record->status === ContactStatus::FROZEN
        ) {
            $this->record->comments()->create([
                'comment' => $this->pendingFreezeReason,
                'user_id' => auth()->id(),
                'created_at' => Carbon::now('UTC'),
            ]);
        }

        $this->pendingFreezeReason = null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
