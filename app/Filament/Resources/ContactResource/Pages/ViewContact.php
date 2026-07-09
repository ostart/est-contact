<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Enums\CommentsContext;
use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Filament\Support\ContactCommentsSection;
use App\Filament\Support\ContactFreezeFields;
use App\Filament\Support\ContactPhotoFields;
use App\Filament\Support\PhoneDisplay;
use App\Livewire\ContactReopenButton;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Support\HtmlString;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Изменить статус')
            ->size(Size::Small)
            ->modalHeading('Изменить статус')
            ->modalSubmitActionLabel('Сохранить')
            ->modalCancelActionLabel('Отмена')
            ->visible(fn (): bool => $this->canLeaderEditContactStatus()
                && $this->getContactStatus() !== ContactStatus::FAILED)
            ->fillForm(fn (): array => [
                'status' => $this->resolveDefaultStatusFormValue(),
            ])
            ->form(fn (): array => [
                Forms\Components\Select::make('status')
                    ->label('Новый статус')
                    ->options($this->getAvailableStatusOptions())
                    ->required()
                    ->live(),
                ...ContactFreezeFields::schema(),
            ])
            ->action(function (array $data): void {
                $this->handleChangeStatus($data);
            });
    }

    public function handleChangeStatus(array $data): void
    {
        $status = $this->getContactStatus();

        if (
            $data['status'] === ContactStatus::FROZEN->value
            && $status !== ContactStatus::FROZEN
        ) {
            ContactFreezeFields::applyFreeze($this->record, $data);

            Notification::make()
                ->title('Статус обновлен')
                ->success()
                ->send();

            $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));

            return;
        }

        $updateData = ['status' => $data['status']];

        if ($data['status'] === ContactStatus::NOT_PROCESSED->value) {
            $updateData['assigned_leader_id'] = null;
        } elseif (
            ($status === ContactStatus::NOT_PROCESSED || $status === ContactStatus::ASSIGNED)
            && $data['status'] === ContactStatus::IN_PROGRESS->value
        ) {
            $updateData['assigned_leader_id'] = auth()->id();
        }

        $this->record->update($updateData);

        Notification::make()
            ->title('Статус обновлен')
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
    }

    public function infolist(Schema $schema): Schema
    {
        $status = $this->getContactStatus();
        $isFailed = $status === ContactStatus::FAILED;

        return $schema
            ->components([
                SchemaComponents\Section::make()
                    ->schema([
                        SchemaComponents\Section::make('Информация о контакте')
                            ->schema([
                                Components\TextEntry::make('full_name')
                                    ->label('ФИО'),
                                PhoneDisplay::textEntry(
                                    Components\TextEntry::make('phone')
                                        ->label('Телефон')
                                        ->copyable(),
                                ),
                                Components\TextEntry::make('email')
                                    ->label('Email')
                                    ->copyable(),
                                Components\TextEntry::make('district')
                                    ->label('Район'),
                                Components\TextEntry::make('source')
                                    ->label('Источник')
                                    ->formatStateUsing(fn ($state) => $state->getLabel()),
                                Components\TextEntry::make('status')
                                    ->label('Статус')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state->getLabel())
                                    ->color(fn ($state) => $state->getColor()),
                                Components\TextEntry::make('frozen_until')
                                    ->label('Разморозить')
                                    ->formatStateUsing(fn ($state) => ContactFreezeFields::formatFrozenUntilDisplay($state))
                                    ->visible(fn () => $status === ContactStatus::FROZEN),
                                Components\TextEntry::make('assignedLeader.name')
                                    ->label('Ответственный лидер'),
                                Components\TextEntry::make('creator.name')
                                    ->label('Создал'),
                                Components\TextEntry::make('created_at')
                                    ->label('Дата создания')
                                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state)),
                            ])
                            ->columns(2)
                            ->columnSpan(['default' => 'full', 'lg' => 1]),

                        ContactPhotoFields::infolistSection()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                ContactCommentsSection::infolistSection(CommentsContext::LeaderView),

                SchemaComponents\Section::make()
                    ->schema([
                        SchemaComponents\Flex::make([
                            SchemaComponents\Html::make(new HtmlString('<span class="fi-contact-status-label">Статус</span>')),
                            SchemaComponents\Text::make(fn (): string => $status->getLabel())
                                ->badge()
                                ->color($status->getColor()),
                            SchemaComponents\Livewire::make(ContactReopenButton::class)
                                ->data(fn (): array => [
                                    'contactId' => $this->getRecord()->getKey(),
                                ])
                                ->key(fn (): string => 'contact-reopen-'.$this->getRecord()->getKey())
                                ->visible($isFailed),
                            SchemaComponents\Actions::make([
                                $this->changeStatusAction(),
                            ])
                                ->key('contact-status-actions')
                                ->visible(! $isFailed),
                        ])
                            ->extraAttributes([
                                'class' => 'fi-contact-status-row',
                                'style' => 'gap: 1rem; flex-direction: row; justify-content: flex-start; align-items: center;',
                            ])
                            ->alignStart()
                            ->verticallyAlignCenter(),
                    ])
                    ->extraAttributes(['class' => 'fi-contact-status-section'])
                    ->visible(fn (): bool => $this->canLeaderEditContactStatus())
                    ->columnSpanFull(),

                SchemaComponents\Section::make('История статусов')
                    ->schema([
                        Components\RepeatableEntry::make('statusHistories')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('contact.full_name')
                                    ->label('Контакт'),
                                Components\TextEntry::make('old_status')
                                    ->label('Старый статус')
                                    ->default('—'),
                                Components\TextEntry::make('new_status')
                                    ->label('Новый статус'),
                                Components\TextEntry::make('user.name')
                                    ->label('Пользователь'),
                                Components\TextEntry::make('created_at')
                                    ->label('Дата')
                                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state)),
                            ])
                            ->columns(['default' => 1, 'lg' => 5]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->extraAttributes(['class' => 'fi-contact-status-history-section'])
                    ->columnSpanFull(),
            ]);
    }

    protected function getContactStatus(): ContactStatus
    {
        return $this->record->status instanceof ContactStatus
            ? $this->record->status
            : ContactStatus::from($this->record->status);
    }

    protected function canLeaderEditContactStatus(): bool
    {
        if (! auth()->user()->hasRole('leader')) {
            return false;
        }

        $status = $this->getContactStatus();

        return $status === ContactStatus::NOT_PROCESSED
            || $status === ContactStatus::FAILED
            || $this->record->assigned_leader_id === auth()->id();
    }

    /**
     * @return array<string, string>
     */
    protected function getAvailableStatusOptions(): array
    {
        $status = $this->getContactStatus();
        $isAssignedToCurrentUser = $this->record->assigned_leader_id === auth()->id();
        $takeToWorkOption = [ContactStatus::IN_PROGRESS->value => 'Взять в работу'];

        return match (true) {
            $status === ContactStatus::NOT_PROCESSED => $takeToWorkOption,
            $status === ContactStatus::ASSIGNED && $isAssignedToCurrentUser => array_merge(
                $takeToWorkOption,
                collect($status->transitionOptions(includeCurrent: false))
                    ->except([ContactStatus::IN_PROGRESS->value])
                    ->all(),
            ),
            default => collect($status->transitionOptions(includeCurrent: false))
                ->when(
                    ! $status->isFinal(),
                    fn ($options) => $options->except([ContactStatus::OVERDUE->value]),
                )
                ->all(),
        };
    }

    protected function resolveDefaultStatusFormValue(): string
    {
        $status = $this->getContactStatus();
        $isLeader = auth()->user()->hasRole('leader');

        return match (true) {
            $status === ContactStatus::FROZEN && $isLeader => ContactStatus::IN_PROGRESS->value,
            $status === ContactStatus::FROZEN => $this->record->statusBeforeFrozen()->value,
            default => $status->value,
        };
    }
}
