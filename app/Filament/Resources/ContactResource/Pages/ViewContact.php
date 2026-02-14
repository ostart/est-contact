<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components;
use Filament\Schemas\Schema;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Информация о контакте')
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
                            ->dateTime('d.m.Y H:i'),
                    ])->columns(2),

                Components\Section::make('История статусов')
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
                                    ->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(4),
                    ])->collapsible(),

                Components\Section::make('Комментарии')
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
                                    ->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(3),
                    ])->collapsible(),
            ]);
    }
}
