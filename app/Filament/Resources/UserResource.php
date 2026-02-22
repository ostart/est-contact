<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователь';

    protected static ?string $pluralModelLabel = 'пользователи';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        // Показывать только для Администраторов
        return auth()->user()->hasRole('administrator');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaComponents\Section::make('Основная информация')
                    ->schema([
                        Components\TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Components\TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->required(fn ($record) => $record === null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                SchemaComponents\Section::make('Роли и доступ')
                    ->schema([
                        Components\CheckboxList::make('roles')
                            ->label('Роли')
                            ->relationship('roles', 'name')
                            ->options(Role::all()->pluck('name', 'id'))
                            ->default(fn ($record) => $record ? [] : [Role::where('name', 'leader')->first()?->id])
                            ->required()
                            ->columns(4)
                            ->columnSpanFull(),

                        Components\Toggle::make('is_approved')
                            ->label('Доступ в систему разрешен')
                            ->default(false),

                        Components\Toggle::make('has_dashboard_access')
                            ->label('Доступ к Dashboard')
                            ->default(false),

                        Components\Toggle::make('email_verified')
                            ->label('Email подтвержден')
                            ->afterStateHydrated(fn ($component, $record) => $component->state($record?->email_verified_at !== null))
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record) {
                                    $record->email_verified_at = $state ? now() : null;
                                    $record->save();
                                }
                            }),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                Columns\TextColumn::make('roles.name')
                    ->label('Роли')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state)
                    ->listWithLineBreaks()
                    ->bulleted(false)
                    ->limitList(5)
                    ->expandableLimitedList(),

                Columns\IconColumn::make('is_approved')
                    ->label('Доступ')
                    ->boolean()
                    ->sortable(),

                Columns\IconColumn::make('has_dashboard_access')
                    ->label('Dashboard')
                    ->boolean()
                    ->sortable(),

                Columns\IconColumn::make('email_verified_at')
                    ->label('Email')
                    ->boolean()
                    ->sortable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Роль')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Доступ разрешен'),

                Tables\Filters\TernaryFilter::make('has_dashboard_access')
                    ->label('Доступ к Dashboard'),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email подтвержден')
                    ->nullable(),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Изменить'),
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Удалить')
                    ->successRedirectUrl(UserResource::getUrl('index')),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->successRedirectUrl(UserResource::getUrl('index')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasAnyRole(['administrator', 'superadmin']);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) User::count();
    }
}
