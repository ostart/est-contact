<?php

namespace App\Filament\Resources;

use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource\Pages;
use App\Models\Contact;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManagementResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'контакт';

    protected static ?string $pluralModelLabel = 'Управление контактами';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole('manager');
    }

    public static function getNavigationLabel(): string
    {
        return 'Управление';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'management';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaComponents\Section::make('Основная информация')
                    ->schema([
                        Components\TextInput::make('full_name')
                            ->label('ФИО')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->required()
                            ->maxLength(255)
                            ->tel()
                            ->telRegex('/^[0-9\s\+\-\(\)]+$/')
                            ->rule('phone:AUTO,RU,US,UA,BY,KZ')
                            ->helperText('Введите номер телефона в международном формате (например: +7 915 123-45-55)'),

                        Components\TextInput::make('email')
                            ->label('Email')
                            ->email('Некорректный формат email')
                            ->nullable()
                            ->maxLength(255),

                        Components\TextInput::make('district')
                            ->label('Округ')
                            ->maxLength(255),
                    ])->columns(2),

                SchemaComponents\Section::make('Комментарии')
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
                            ->deletable(true)
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                SchemaComponents\Section::make('Статус и назначение')
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
                            ->disabled(fn ($record) => $record && (($record->status instanceof ContactStatus ? $record->status : ContactStatus::from($record->status))->isFinal()))
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state === ContactStatus::NOT_PROCESSED->value) {
                                    $set('assigned_leader_id', null);
                                }
                            }),

                        Components\Select::make('assigned_leader_id')
                            ->label('Ответственный лидер')
                            ->relationship('assignedLeader', 'name', fn (Builder $query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'leader')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if (filled($state)) {
                                    $set('status', ContactStatus::ASSIGNED->value);
                                }
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
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
                    ->formatStateUsing(fn ($state): string => ($state instanceof ContactStatus ? $state : ContactStatus::from($state))->getLabel())
                    ->color(fn ($state): string => ($state instanceof ContactStatus ? $state : ContactStatus::from($state))->getColor())
                    ->sortable(),

                Columns\TextColumn::make('assignedLeader.name')
                    ->label('Ответственный')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
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

                Tables\Filters\SelectFilter::make('district')
                    ->label('Округ')
                    ->options(fn () => Contact::distinct()->pluck('district', 'district')->filter()),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Изменить'),
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Удалить'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagement::route('/'),
            'create' => Pages\CreateManagement::route('/create'),
            'edit' => Pages\EditManagement::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('manager');
    }

    public static function canView($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Contact::count();
    }
}
