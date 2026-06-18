<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Enums\CommentsContext;
use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Filament\Support\ContactCommentsSection;
use App\Filament\Support\ContactFreezeFields;
use App\Filament\Support\ContactPhotoFields;
use App\Filament\Support\PhoneDisplay;
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
        return [
            // Нет действий редактирования для лидеров
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        $isLeader = auth()->user()->hasRole('leader');
        $status = $this->record->status instanceof ContactStatus ? $this->record->status : ContactStatus::from($this->record->status);
        $isAssignedToCurrentUser = $this->record->assigned_leader_id === auth()->id();
        $isNotProcessed = $status === ContactStatus::NOT_PROCESSED;
        
        // Лидер может изменять статус если:
        // 1. Контакт NOT_PROCESSED (может взять в работу)
        // 2. Он назначен ответственным (из финальных — только «В работе» или другой финальный статус)
        $canEditStatus = $isLeader && ($isNotProcessed || $isAssignedToCurrentUser);

        $takeToWorkOption = [ContactStatus::IN_PROGRESS->value => 'Взять в работу'];

        $availableStatuses = match (true) {
            $isNotProcessed => $takeToWorkOption,
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
                            SchemaComponents\Actions::make([
                                \Filament\Actions\Action::make('change_status')
                                    ->label('Изменить статус')
                                    ->size(Size::Small)
                                    ->form([
                                        Forms\Components\Select::make('status')
                                            ->label('Новый статус')
                                            ->options($availableStatuses)
                                            ->default(fn () => match (true) {
                                                $status === ContactStatus::FROZEN && $isLeader => ContactStatus::IN_PROGRESS->value,
                                                $status === ContactStatus::FROZEN => $this->record->statusBeforeFrozen()->value,
                                                default => $this->record->status instanceof ContactStatus
                                                    ? $this->record->status->value
                                                    : $this->record->status,
                                            })
                                            ->required()
                                            ->live(),
                                        ...ContactFreezeFields::schema(),
                                    ])
                                    ->action(function (array $data) use ($isNotProcessed, $status) {
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
                                            ($isNotProcessed || $status === ContactStatus::ASSIGNED)
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
                                    })
                                    ->modalSubmitActionLabel('Сохранить')
                                    ->modalCancelActionLabel('Отмена'),
                            ]),
                        ])
                        ->extraAttributes([
                            'class' => 'fi-contact-status-row',
                            'style' => 'gap: 1rem; flex-direction: row; justify-content: flex-start; align-items: center;',
                        ])
                        ->alignStart()
                        ->verticallyAlignCenter(),
                    ])
                    ->extraAttributes(['class' => 'fi-contact-status-section'])
                    ->visible($canEditStatus)
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
}
