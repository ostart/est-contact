<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components;
use Filament\Resources\Resource;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Основная информация')
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

                Components\Section::make('Роли и доступ')
                    ->schema([
                        Components\CheckboxList::make('roles')
                            ->label('Роли')
                            ->relationship('roles', 'name')
                            ->options(Role::all()->pluck('name', 'name'))
                            ->default(['leader'])
                            ->required()
                            ->columns(4)
                            ->columnSpanFull(),

                        Components\Toggle::make('is_approved')
                            ->label('Доступ в систему разрешен')
                            ->default(false)
                            ->helperText('Разрешить пользователю доступ к системе'),

                        Components\Toggle::make('has_dashboard_access')
                            ->label('Доступ к Dashboard')
                            ->default(false)
                            ->helperText('Предоставить доступ к главной панели'),

                        Components\Toggle::make('email_verified')
                            ->label('Email подтвержден')
                            ->helperText('Отметить email как подтвержденный')
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
                    ->separator(','),

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
                    ->dateTime('d.m.Y H:i')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
}
