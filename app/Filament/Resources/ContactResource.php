<?php

namespace App\Filament\Resources;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use BackedEnum;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Контакты';

    protected static ?string $modelLabel = 'контакт';

    protected static ?string $pluralModelLabel = 'контакты';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Основная информация')
                    ->schema([
                        Components\TextInput::make('full_name')
                            ->label('ФИО')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Components\TextInput::make('district')
                            ->label('Округ')
                            ->maxLength(255),
                    ])->columns(2),

                Components\Section::make('Статус и назначение')
                    ->schema([
                        Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                ContactStatus::NOT_PROCESSED->value => ContactStatus::NOT_PROCESSED->getLabel(),
                                ContactStatus::ASSIGNED->value => ContactStatus::ASSIGNED->getLabel(),
                                ContactStatus::OVERDUE->value => ContactStatus::OVERDUE->getLabel(),
                                ContactStatus::SUCCESS->value => ContactStatus::SUCCESS->getLabel(),
                                ContactStatus::FAILED->value => ContactStatus::FAILED->getLabel(),
                            ])
                            ->required()
                            ->default(ContactStatus::NOT_PROCESSED->value)
                            ->disabled(fn ($record) => $record && ContactStatus::from($record->status)->isFinal())
                            ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),

                        Components\Select::make('assigned_leader_id')
                            ->label('Ответственный лидер')
                            ->relationship('assignedLeader', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
                    ])->columns(2),

                Components\Section::make('Комментарии')
                    ->schema([
                        Components\Repeater::make('comments')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Components\Textarea::make('comment')
                                    ->label('Комментарий')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Components\Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                            ])
                            ->addActionLabel('Добавить комментарий')
                            ->deletable(false)
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isLeader = auth()->user()->hasRole('leader');

        return $table
            ->columns([
                Columns\TextColumn::make('full_name')
                    ->label('ФИО')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->copyable(),

                Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),

                Columns\TextColumn::make('district')
                    ->label('Округ')
                    ->searchable()
                    ->toggleable(),

                Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ContactStatus::from($state)->getLabel())
                    ->color(fn ($state): string => ContactStatus::from($state)->getColor())
                    ->sortable(),

                Columns\TextColumn::make('assignedLeader.name')
                    ->label('Ответственный')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        ContactStatus::NOT_PROCESSED->value => ContactStatus::NOT_PROCESSED->getLabel(),
                        ContactStatus::ASSIGNED->value => ContactStatus::ASSIGNED->getLabel(),
                        ContactStatus::OVERDUE->value => ContactStatus::OVERDUE->getLabel(),
                        ContactStatus::SUCCESS->value => ContactStatus::SUCCESS->getLabel(),
                        ContactStatus::FAILED->value => ContactStatus::FAILED->getLabel(),
                    ]),

                Tables\Filters\SelectFilter::make('assigned_leader_id')
                    ->label('Ответственный')
                    ->relationship('assignedLeader', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('my_contacts')
                    ->label('Мои контакты')
                    ->query(fn (Builder $query): Builder => $query->where('assigned_leader_id', auth()->id()))
                    ->default($isLeader),

                Tables\Filters\SelectFilter::make('district')
                    ->label('Округ')
                    ->options(fn () => Contact::distinct()->pluck('district', 'district')->filter()),
            ])
            ->recordActions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
            ])
            ->toolbarActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) use ($isLeader) {
                // Для лидеров по умолчанию показываем только их контакты без финальных статусов
                // Это поведение можно отключить фильтром "Мои контакты"
                return $query;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'view' => Pages\ViewContact::route('/{record}'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['leader', 'manager', 'administrator', 'superadmin']);
    }

    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()->hasRole('leader')) {
            return (string) Contact::where('assigned_leader_id', auth()->id())
                ->whereNotIn('status', [ContactStatus::SUCCESS->value, ContactStatus::FAILED->value])
                ->count();
        }

        return null;
    }
}
