<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Table;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Настройки';

    protected static ?string $modelLabel = 'настройка';

    protected static ?string $pluralModelLabel = 'настройки';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        // Показывать только для Суперадминов
        return auth()->user()->hasRole('superadmin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaComponents\Section::make('Настройка системы')
                    ->schema([
                        Components\TextInput::make('key')
                            ->label('Ключ')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->maxLength(255),

                        Components\Textarea::make('value')
                            ->label('Значение')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Components\Placeholder::make('description')
                            ->label('Описание')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Создайте новую настройку';
                                }

                                return match ($record->key) {
                                    'contact_processing_timeout_days' => 'Таймаут обработки контакта в днях. По умолчанию: 30 дней.',
                                    default => 'Нет описания для этой настройки',
                                };
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('value')
                    ->label('Значение')
                    ->limit(50)
                    ->searchable(),

                Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->formatStateUsing(fn ($state) => format_datetime_moscow($state))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Изменить'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Нет настроек')
            ->emptyStateDescription('Настройки создаются через сидер или миграции.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSystemSettings::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('superadmin');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
