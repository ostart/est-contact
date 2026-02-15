<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Filament\Resources\ManagementResource;
use App\Enums\ContactStatus;
use Filament\Actions;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;

class ViewManagement extends ViewRecord
{
    protected static string $resource = ManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
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
                    ])->columns(2),

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
                    ])->collapsible(),

                SchemaComponents\Section::make('Комментарии')
                    ->schema([
                        Components\RepeatableEntry::make('comments')
                            ->label('')
                            ->schema([
                                Components\TextEntry::make('user.name')
                                    ->label('Пользователь'),
                                Components\TextEntry::make('comment')
                                    ->label('Комментарий'),
                                Components\TextEntry::make('created_at')
                                    ->label('Дата')
                                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state)),
                            ])
                            ->columns(3),
                    ])->collapsible(),
            ]);
    }
}
