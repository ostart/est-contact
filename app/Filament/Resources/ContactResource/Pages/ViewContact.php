<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;

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
        // 2. Он назначен ответственным и контакт не в финальном статусе
        $canEditStatus = $isLeader && ($isNotProcessed || ($isAssignedToCurrentUser && !$status->isFinal()));

        // Список доступных статусов зависит от текущего статуса
        $availableStatuses = $isNotProcessed 
            ? [ContactStatus::ASSIGNED->value => 'Взять в работу'] // Для NOT_PROCESSED - дружелюбный текст
            : [ // Для своих контактов - все кроме OVERDUE
                ContactStatus::NOT_PROCESSED->value => ContactStatus::NOT_PROCESSED->getLabel(),
                ContactStatus::ASSIGNED->value => ContactStatus::ASSIGNED->getLabel(),
                ContactStatus::SUCCESS->value => ContactStatus::SUCCESS->getLabel(),
                ContactStatus::FAILED->value => ContactStatus::FAILED->getLabel(),
            ];

        return $schema
            ->components([
                SchemaComponents\Section::make('Информация о контакте')
                    ->schema([
                        Components\TextEntry::make('full_name')
                            ->label('ФИО'),
                        Components\TextEntry::make('phone')
                            ->label('Телефон')
                            ->copyable(),
                        Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        Components\TextEntry::make('district')
                            ->label('Округ'),
                        Components\TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->getLabel())
                            ->color(fn ($state) => $state->getColor()),
                        Components\TextEntry::make('assignedLeader.name')
                            ->label('Ответственный лидер'),
                        Components\TextEntry::make('creator.name')
                            ->label('Создал'),
                        Components\TextEntry::make('created_at')
                            ->label('Дата создания')
                            ->formatStateUsing(fn ($state) => format_datetime_moscow($state)),
                    ])->columns(2)->columnSpanFull(),

                // Десктоп: слева Комментарии, справа Добавить комментарий. Мобильная: в один столбец.
                SchemaComponents\Section::make()
                    ->schema([
                        SchemaComponents\Section::make('Комментарии')
                            ->schema([
                                Components\RepeatableEntry::make('comments')
                                    ->label('')
                                    ->schema([
                                        Components\TextEntry::make('user.name')
                                            ->label('Пользователь'),
                                        Components\TextEntry::make('comment')
                                            ->label('Комментарий')
                                            ->columnSpan(2),
                                        Components\TextEntry::make('created_at')
                                            ->label('Дата')
                                            ->formatStateUsing(fn ($state) => format_datetime_moscow($state)),
                                    ])
                                    ->columns(4),
                            ])
                            ->collapsible()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                        SchemaComponents\Section::make('Добавить комментарий')
                            ->schema([
                                SchemaComponents\Actions::make([
                                    \Filament\Actions\Action::make('add_comment')
                                        ->label('Добавить комментарий')
                                        ->form([
                                            Forms\Components\Textarea::make('comment')
                                                ->label('Комментарий')
                                                ->required()
                                                ->rows(3),
                                        ])
                                        ->action(function (array $data) {
                                            $this->record->comments()->create([
                                                'comment' => $data['comment'],
                                                'user_id' => auth()->id(),
                                                'created_at' => \Carbon\Carbon::now('UTC'),
                                            ]);
                                            
                                            Notification::make()
                                                ->title('Комментарий добавлен')
                                                ->success()
                                                ->send();
                                            
                                            redirect()->to(static::getResource()::getUrl('view', ['record' => $this->record]));
                                        })
                                        ->modalSubmitActionLabel('Добавить')
                                        ->modalCancelActionLabel('Отмена'),
                                ]),
                            ])
                            ->visible($isLeader)
                            ->collapsible()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                // Десктоп: слева История статусов, справа Изменить статус. Мобильная: в один столбец.
                SchemaComponents\Section::make()
                    ->schema([
                        SchemaComponents\Section::make('История статусов')
                            ->schema([
                                Components\RepeatableEntry::make('statusHistories')
                                    ->label('')
                                    ->schema([
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
                                    ->columns(4),
                            ])
                            ->collapsible()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                        SchemaComponents\Section::make('Изменить статус')
                            ->schema([
                                SchemaComponents\Actions::make([
                                    \Filament\Actions\Action::make('change_status')
                                        ->label('Изменить статус')
                                        ->form([
                                            Forms\Components\Select::make('status')
                                                ->label('Новый статус')
                                                ->options($availableStatuses)
                                                ->default(fn () => $this->record->status instanceof ContactStatus ? $this->record->status->value : $this->record->status)
                                                ->required(),
                                        ])
                                        ->action(function (array $data) use ($isNotProcessed) {
                                            $updateData = ['status' => $data['status']];
                                            
                                            if ($data['status'] === ContactStatus::NOT_PROCESSED->value) {
                                                $updateData['assigned_leader_id'] = null;
                                            } elseif ($isNotProcessed && $data['status'] === ContactStatus::ASSIGNED->value) {
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
                                        ->modalCancelActionLabel('Отмена')
                                        ->visible($canEditStatus),
                                ]),
                            ])
                            ->visible($canEditStatus)
                            ->collapsible()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),
            ]);
    }
}
