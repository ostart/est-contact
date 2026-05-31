<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateManagement extends CreateRecord
{
    protected static string $resource = ManagementResource::class;

    protected static bool $canCreateAnother = false;

    protected ?string $pendingInitialComment = null;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingInitialComment = trim((string) ($data['initial_comment'] ?? ''));
        unset($data['initial_comment']);

        $data['created_by'] = auth()->id();

        // Если статус "Не обработан" - сбрасываем ответственного
        if (isset($data['status']) && $data['status'] === ContactStatus::NOT_PROCESSED->value) {
            $data['assigned_leader_id'] = null;
        }
        // Если назначен ответственный — статус «Назначено»
        elseif (! empty($data['assigned_leader_id'])) {
            $data['status'] = ContactStatus::ASSIGNED->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (($this->pendingInitialComment ?? '') !== '') {
            $this->record->comments()->create([
                'comment' => $this->pendingInitialComment,
                'user_id' => auth()->id(),
                'created_at' => Carbon::now('UTC'),
            ]);
        }

        $this->pendingInitialComment = null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
