<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\PhoneDisplay;
use App\Filament\Support\UserActionsSection;
use App\Models\User;
use App\Support\PhoneNumberHelper;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователя';

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

                SchemaComponents\Section::make('Контактные данные')
                    ->schema([
                        PhoneDisplay::textInput(
                            Components\TextInput::make('phone')
                                ->label('Телефон')
                                ->tel()
                                ->maxLength(32)
                                ->rules([
                                    'nullable',
                                    Rule::phone()->country([PhoneNumberHelper::DEFAULT_REGION]),
                                ])
                                ->rule(static function (Field $component): Closure {
                                    return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                        if (! filled($value)) {
                                            return;
                                        }
                                        $e164 = PhoneNumberHelper::normalize($value, [PhoneNumberHelper::DEFAULT_REGION]);
                                        if ($e164 === null) {
                                            return;
                                        }
                                        $query = User::query()->where('phone', $e164);
                                        $record = $component->getRecord();
                                        if ($record && $record->getKey()) {
                                            $query->whereKeyNot($record->getKey());
                                        }
                                        if ($query->exists()) {
                                            $fail('Пользователь с таким номером телефона уже зарегистрирован.');
                                        }
                                    };
                                }),
                        ),

                        Components\Textarea::make('address')
                            ->label('Адрес')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Components\Textarea::make('bio')
                            ->label('Дополнительная информация')
                            ->rows(3)
                            ->maxLength(1000)
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
                            ->columnSpanFull()
                            ->live(),

                        Components\Toggle::make('can_use_contact_filters')
                            ->label('Разрешить фильтрацию списка контактов')
                            ->helperText('Включено — лидер может открыть фильтры и менять отбор. Выключено — отбор по умолчанию «Мои контакты» остаётся, но изменить фильтры лидер не может.')
                            ->default(false)
                            ->visible(function ($get): bool {
                                $leaderRoleId = Role::query()->where('name', 'leader')->value('id');
                                if ($leaderRoleId === null) {
                                    return false;
                                }

                                $roles = $get('roles');

                                return is_array($roles) && in_array((int) $leaderRoleId, array_map('intval', $roles), true);
                            }),

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
                            ->disabled(fn ($record) => blank($record?->email))
                            ->helperText(fn ($record) => blank($record?->email) ? 'Укажите email для подтверждения' : null)
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record && filled($record->email)) {
                                    $record->email_verified_at = $state ? now() : null;
                                    $record->save();
                                }
                            }),

                    ])->columns(2),

                SchemaComponents\Section::make('Действия')
                    ->schema([
                        SchemaComponents\Actions::make(UserActionsSection::editFormActions())
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),
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

                Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->size(32)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=random')
                    ->getStateUsing(fn ($record) => $record->avatar ? Storage::disk('public')->url($record->avatar) : null)
                    ->action(
                        Actions\Action::make('viewContact')
                            ->modalHeading(fn (User $record) => "Контакт: {$record->name}")
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Закрыть')
                            ->modalContent(function (User $record) {
                                $avatarUrl = $record->avatar 
                                    ? Storage::disk('public')->url($record->avatar) 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&size=120&background=random';
                                
                                $html = '<div style="text-align: center; margin-bottom: 16px;">';
                                $html .= '<img src="' . e($avatarUrl) . '" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #e5e7eb; margin: 0 auto;">';
                                $html .= '</div>';
                                
                                $html .= '<div style="space-y: 8px;">';
                                
                                $html .= '<p><strong>Email:</strong> ' . e($record->email) . '</p>';
                                
                                if ($record->phone) {
                                    $html .= '<p><strong>Телефон:</strong> '.PhoneDisplay::html($record->phone).'</p>';
                                } else {
                                    $html .= '<p><strong>Телефон:</strong> <span style="color: #9ca3af;">не указан</span></p>';
                                }
                                
                                if ($record->address) {
                                    $html .= '<p><strong>Адрес:</strong> ' . e($record->address) . '</p>';
                                } else {
                                    $html .= '<p><strong>Адрес:</strong> <span style="color: #9ca3af;">не указан</span></p>';
                                }
                                
                                if ($record->bio) {
                                    $html .= '<p><strong>Дополнительно:</strong> ' . e($record->bio) . '</p>';
                                } else {
                                    $html .= '<p><strong>Дополнительно:</strong> <span style="color: #9ca3af;">не указано</span></p>';
                                }
                                
                                $roles = $record->roles->pluck('name')->implode(', ');
                                $html .= '<p><strong>Роли:</strong> ' . e($roles ?: 'нет ролей') . '</p>';
                                
                                $html .= '</div>';
                                
                                return new HtmlString($html);
                            })
                    ),

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

                Columns\IconColumn::make('can_use_contact_filters')
                    ->label(new HtmlString('<span class="fi-users-contact-filters-col-label">Фильтры<br>контактов</span>'))
                    ->wrapHeader()
                    ->extraHeaderAttributes([
                        'class' => 'fi-users-contact-filters-col-header',
                    ])
                    ->getStateUsing(fn (User $record): ?bool => $record->hasRole('leader')
                        ? (bool) $record->can_use_contact_filters
                        : null)
                    ->boolean()
                    ->sortable(),

                Columns\IconColumn::make('email_verified_at')
                    ->label('Email')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null)
                    ->boolean()
                    ->sortable(),

                Columns\IconColumn::make('is_banned')
                    ->label('Бан')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создан')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable()
                    ->toggleable(),
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

                Tables\Filters\TernaryFilter::make('is_banned')
                    ->label('Заблокирован'),
            ])
            ->recordActions([
                Actions\Action::make('warn')
                    ->label('Предупреждение')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Отправить предупреждение')
                    ->requiresConfirmation()
                    ->modalHeading('Отправить предупреждение')
                    ->modalDescription(fn (User $record) => "Пользователь {$record->name} получит уведомление в колокольчик и на email (если включено в настройках).")
                    ->form([
                        Components\Textarea::make('message')
                            ->label('Текст предупреждения')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Введите текст предупреждения для пользователя...'),
                    ])
                    ->action(fn (User $record, array $data) => UserActionsSection::sendWarning($record, $data['message'])),

                Actions\Action::make('ban')
                    ->label('Заблокировать')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Заблокировать пользователя')
                    ->visible(fn (User $record) => ! $record->is_banned && ! $record->isSuperAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Заблокировать пользователя')
                    ->modalDescription(fn (User $record) => "Вы уверены, что хотите заблокировать пользователя {$record->name}?")
                    ->form([
                        Components\Textarea::make('ban_reason')
                            ->label('Причина блокировки')
                            ->maxLength(1000)
                            ->rows(2)
                            ->placeholder('Укажите причину блокировки (необязательно)...'),
                    ])
                    ->action(fn (User $record, array $data) => UserActionsSection::banUser($record, $data['ban_reason'] ?? null)),

                Actions\Action::make('unban')
                    ->label('Разблокировать')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Разблокировать пользователя')
                    ->visible(fn (User $record) => $record->is_banned)
                    ->requiresConfirmation()
                    ->modalHeading('Разблокировать пользователя')
                    ->modalDescription(fn (User $record) => "Вы уверены, что хотите разблокировать пользователя {$record->name}?")
                    ->action(fn (User $record) => UserActionsSection::unbanUser($record)),

                Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Изменить'),
                Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Удалить')
                    ->visible(fn (User $record) => ! $record->isSuperAdmin())
                    ->successRedirectUrl(UserResource::getUrl('index')),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => ! $record->isSuperAdmin())
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
