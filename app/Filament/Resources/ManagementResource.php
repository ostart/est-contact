<?php

namespace App\Filament\Resources;

use App\Enums\CommentsContext;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Filament\Resources\ManagementResource\Pages;
use App\Filament\Support\ContactTableColumns;
use App\Filament\Support\ContactTableSearch;
use App\Filament\Support\ContactCommentsSection;
use App\Filament\Support\ContactFreezeFields;
use App\Filament\Support\ContactPhotoFields;
use App\Filament\Support\PhoneDisplay;
use App\Models\Contact;
use App\Support\PhoneNumberHelper;
use BackedEnum;
use Closure;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Forms\Components\Field;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

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
                SchemaComponents\Section::make()
                    ->schema([
                        SchemaComponents\Section::make('Основная информация')
                            ->schema([
                                Components\TextInput::make('full_name')
                                    ->label('ФИО')
                                    ->required()
                                    ->maxLength(255),

                                PhoneDisplay::textInput(
                                    Components\TextInput::make('phone')
                                        ->label('Телефон')
                                        ->required()
                                        ->maxLength(32)
                                        ->tel()
                                        ->telRegex('/^[0-9\s\+\-\(\)]+$/')
                                        ->rules([
                                            Rule::phone()->country(PhoneNumberHelper::CONTACT_REGIONS),
                                        ])
                                        ->rule(static function (Field $component): Closure {
                                            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                                if (! filled($value)) {
                                                    return;
                                                }
                                                $e164 = PhoneNumberHelper::normalize($value, PhoneNumberHelper::CONTACT_REGIONS);
                                                if ($e164 === null) {
                                                    return;
                                                }
                                                $query = Contact::query()->where('phone', $e164);
                                                $record = $component->getRecord();
                                                if ($record && $record->getKey()) {
                                                    $query->whereKeyNot($record->getKey());
                                                }
                                                if ($query->exists()) {
                                                    $fail('Контакт с таким номером телефона уже существует.');
                                                }
                                            };
                                        })
                                        ->helperText('Номер в международном формате: РФ и страны СНГ (+7 …), США (+1 …). После сохранения хранится в формате E.164.'),
                                ),

                                Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email('Некорректный формат email')
                                    ->nullable()
                                    ->maxLength(255),

                                Components\TextInput::make('district')
                                    ->label('Район')
                                    ->maxLength(255),

                                Components\Select::make('source')
                                    ->label('Источник')
                                    ->options(ContactSource::options())
                                    ->default(ContactSource::TEMPLE->value)
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(2)
                            ->columnSpan(['default' => 'full', 'lg' => 1]),

                        ContactPhotoFields::formSection()
                            ->columnSpan(['default' => 'full', 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull(),

                ContactCommentsSection::formSection(CommentsContext::ManagerEdit),

                SchemaComponents\Section::make('Статус и назначение')
                    ->schema([
                        Components\Select::make('status')
                            ->label('Статус')
                            ->options(function (?Contact $record): array {
                                $current = $record?->status instanceof ContactStatus
                                    ? $record->status
                                    : ($record ? ContactStatus::tryFrom((string) $record->status) : null);

                                return ContactStatus::formOptions($current, forManager: true);
                            })
                            ->required()
                            ->default(ContactStatus::NOT_PROCESSED->value)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state === ContactStatus::NOT_PROCESSED->value) {
                                    $set('assigned_leader_id', null);
                                }
                                if ($state !== ContactStatus::FROZEN->value) {
                                    $set('freeze_date', null);
                                    $set('freeze_reason', null);
                                }
                            }),

                        ...ContactFreezeFields::schema(),

                        Components\Select::make('assigned_leader_id')
                            ->label('Ответственный лидер')
                            ->relationship(
                                'assignedLeader',
                                'name',
                                fn (Builder $query) => $query
                                    ->whereNotNull('email_verified_at')
                                    ->where('is_approved', true)
                                    ->whereHas('roles', fn ($q) => $q->where('name', 'leader'))
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->requiredUnless('status', ContactStatus::NOT_PROCESSED->value)
                            ->prohibitedIf('status', ContactStatus::NOT_PROCESSED->value)
                            ->validationMessages([
                                'required_unless' => 'При статусе отличном от «Не обработан» необходимо указать ответственного лидера.',
                                'prohibited_if' => 'При статусе «Не обработан» ответственный лидер должен быть не выбран.',
                            ])
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if (filled($state)) {
                                    $set('status', ContactStatus::ASSIGNED->value);
                                } else {
                                    $set('status', ContactStatus::NOT_PROCESSED->value);
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
                    ->searchable(query: function (Builder $query, string $search): void {
                        ContactTableSearch::applyLike($query, 'full_name', $search);
                    })
                    ->sortable(),

                ContactPhotoFields::tableColumn(),

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
                    ->searchable(query: function (Builder $query, string $search): void {
                        ContactTableSearch::applyLike($query, 'email', $search);
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                Columns\TextColumn::make('district')
                    ->label('Район')
                    ->searchable(query: function (Builder $query, string $search): void {
                        ContactTableSearch::applyLike($query, 'district', $search);
                    })
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

                ContactTableColumns::overdueAt(),

                Columns\TextColumn::make('assignedLeader.name')
                    ->label('Ответственный')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state, withSeconds: true))
                    ->sortable()
                    ->toggleable(),

                Columns\TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state, withSeconds: true))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        ContactStatus::NOT_PROCESSED->value => ContactStatus::NOT_PROCESSED->getLabel(),
                        ContactStatus::ASSIGNED->value => ContactStatus::ASSIGNED->getLabel(),
                        ContactStatus::IN_PROGRESS->value => ContactStatus::IN_PROGRESS->getLabel(),
                        ContactStatus::FROZEN->value => ContactStatus::FROZEN->getLabel(),
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

                Tables\Filters\SelectFilter::make('district')
                    ->label('Район')
                    ->options(fn () => Contact::distinct()->pluck('district', 'district')->filter()),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Изменить'),
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Удалить')
                    ->successRedirectUrl(ManagementResource::getUrl('index')),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->successRedirectUrl(ManagementResource::getUrl('index')),
                ]),
            ])
            ->reorderableColumns()
            ->defaultKeySort(false);
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
