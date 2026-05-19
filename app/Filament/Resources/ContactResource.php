<?php

namespace App\Filament\Resources;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource\Pages;
use App\Filament\Support\PhoneDisplay;
use App\Models\Contact;
use App\Support\PhoneNumberHelper;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Контакты';

    protected static ?string $modelLabel = 'контакт';

    protected static ?string $pluralModelLabel = 'контакты';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        // Показывать для Лидеров
        return auth()->user()->hasRole('leader');
    }

    public static function getNavigationLabel(): string
    {
        return 'Контакты';
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

                        PhoneDisplay::textInput(
                            Components\TextInput::make('phone')
                                ->label('Телефон')
                                ->tel()
                                ->required()
                                ->maxLength(32)
                                ->rules([
                                    Rule::phone()->country(PhoneNumberHelper::CONTACT_REGIONS),
                                ]),
                        ),

                        Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Components\TextInput::make('district')
                            ->label('Район')
                            ->maxLength(255),

                        Components\Select::make('source')
                            ->label('Источник')
                            ->options(ContactSource::options())
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

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
                            })
                            ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),

                        Components\Select::make('assigned_leader_id')
                            ->label('Ответственный лидер')
                            ->relationship(
                                'assignedLeader',
                                'name',
                                fn (Builder $query) => $query->whereNotNull('email_verified_at')->where('is_approved', true)
                            )
                            ->searchable()
                            ->preload()
                            ->requiredUnless('status', ContactStatus::NOT_PROCESSED->value)
                            ->prohibitedIf('status', ContactStatus::NOT_PROCESSED->value)
                            ->validationMessages([
                                'required_unless' => 'При статусе отличном от «Не обработан» необходимо указать ответственного лидера.',
                                'prohibited_if' => 'При статусе «Не обработан» ответственный лидер должен быть не выбран.',
                            ])
                            ->visible(fn () => auth()->user()->hasAnyRole(['manager', 'administrator', 'superadmin'])),
                    ])->columns(2),

                SchemaComponents\Section::make('Комментарии')
                    ->schema([
                        Components\Repeater::make('comments')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Components\Placeholder::make('user_name')
                                    ->label('Автор')
                                    ->content(fn ($record) => $record?->user?->name ?? '—')
                                    ->visibleOn('edit'),

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
        $user = auth()->user();
        $isLeader = $user->hasRole('leader');
        $leaderFiltersUiLocked = $isLeader && ! $user->can_use_contact_filters;

        $applyLeaderContactsInWorkScope = fn (Builder $query): Builder => $query
            ->where('assigned_leader_id', auth()->id())
            ->whereNotIn('status', [ContactStatus::SUCCESS->value, ContactStatus::FAILED->value]);

        $table = $table
            ->columns([
                Columns\TextColumn::make('full_name')
                    ->label('ФИО')
                    ->searchable()
                    ->sortable(),

                PhoneDisplay::tableColumn(
                    Columns\TextColumn::make('phone')
                        ->label('Телефон')
                        ->searchable(query: function (Builder $query, string $search): void {
                            PhoneNumberHelper::applyColumnSearch($query, 'phone', $search, PhoneNumberHelper::CONTACT_REGIONS);
                        })
                        ->copyable(),
                ),

                Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),

                Columns\TextColumn::make('district')
                    ->label('Район')
                    ->searchable()
                    ->toggleable(),

                Columns\TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn ($state): string => ($state instanceof ContactSource ? $state : ContactSource::from($state))->getLabel())
                    ->sortable()
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
                    ->toggleable(),

                Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);

        if ($leaderFiltersUiLocked) {
            // Без UI-фильтров кнопка «Фильтр» не рендерится; отбор «Мои контакты в работе» — в запросе.
            $table = $table
                ->filters([])
                ->modifyQueryUsing($applyLeaderContactsInWorkScope);
        } else {
            $table = $table
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

                    Tables\Filters\SelectFilter::make('source')
                        ->label('Источник')
                        ->options(ContactSource::options())
                        ->native(false),

                    Tables\Filters\SelectFilter::make('assigned_leader_id')
                        ->label('Ответственный')
                        ->relationship('assignedLeader', 'name')
                        ->searchable()
                        ->preload(),

                    Tables\Filters\Filter::make('my_contacts')
                        ->label('Мои контакты в работе')
                        ->query(fn (Builder $query): Builder => $query
                            ->where('assigned_leader_id', auth()->id())
                            ->whereNotIn('status', [ContactStatus::SUCCESS->value, ContactStatus::FAILED->value])
                        )
                        ->default($isLeader),

                    Tables\Filters\SelectFilter::make('district')
                        ->label('Район')
                        ->options(fn () => Contact::distinct()->pluck('district', 'district')->filter()),
                ]);
        }

        return $table
            ->recordActions([
                Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('Просмотр'),
            ])
            ->toolbarActions([
                // Нет массовых действий для лидеров
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'view' => Pages\ViewContact::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
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
