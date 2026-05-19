<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserWarning;
use App\Support\PhoneNumberHelper;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Журнал аудита';

    protected static ?string $title = 'Журнал аудита';

    protected static string|\UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.activity-log';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings/activity-log';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('superadmin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest('created_at'))
            ->columns([
                TextColumn::make('subject_name')
                    ->label('Объект операции')
                    ->getStateUsing(function (Activity $record) {
                        $subject = $record->subject;
                        if (!$subject) {
                            return $record->subject_id ? "#{$record->subject_id}" : '—';
                        }
                        return match (get_class($subject)) {
                            \App\Models\Contact::class => $subject->full_name ?? "#{$record->subject_id}",
                            \App\Models\User::class => $subject->name ?? $subject->email ?? "#{$record->subject_id}",
                            \App\Models\SystemSetting::class => $subject->key ?? "#{$record->subject_id}",
                            \App\Models\UserWarning::class => $subject->user?->name ?? "Пользователь #{$subject->user_id}",
                            default => $subject->name ?? $subject->full_name ?? $subject->key ?? "#{$record->subject_id}",
                        };
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->whereHasMorph('subject', [Contact::class], function (Builder $q) use ($search) {
                                $q->where(function (Builder $inner) use ($search) {
                                    $inner->where('full_name', 'like', '%'.addcslashes($search, '%_\\').'%');
                                    $inner->orWhere(function (Builder $phoneQ) use ($search) {
                                        PhoneNumberHelper::applyColumnSearch(
                                            $phoneQ,
                                            'phone',
                                            $search,
                                            PhoneNumberHelper::CONTACT_REGIONS
                                        );
                                    });
                                });
                            })
                            ->orWhereHasMorph('subject', [User::class], function (Builder $q) use ($search) {
                                $q->where(function (Builder $inner) use ($search) {
                                    $inner->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                                        ->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%');
                                    $inner->orWhere(function (Builder $phoneQ) use ($search) {
                                        PhoneNumberHelper::applyColumnSearch(
                                            $phoneQ,
                                            'phone',
                                            $search,
                                            [PhoneNumberHelper::DEFAULT_REGION]
                                        );
                                    });
                                });
                            })
                            ->orWhereHasMorph('subject', [SystemSetting::class], function (Builder $q) use ($search) {
                                $q->where('key', 'like', '%'.addcslashes($search, '%_\\').'%');
                            })
                            ->orWhereHasMorph('subject', [UserWarning::class], function (Builder $q) use ($search) {
                                $like = '%'.addcslashes($search, '%_\\').'%';
                                $q->where('message', 'like', $like)
                                    ->orWhereHas('user', function (Builder $userQuery) use ($search, $like) {
                                        $userQuery->where(function (Builder $inner) use ($like, $search) {
                                            $inner->where('name', 'like', $like)
                                                ->orWhere('email', 'like', $like);
                                            $inner->orWhere(function (Builder $phoneQ) use ($search) {
                                                PhoneNumberHelper::applyColumnSearch(
                                                    $phoneQ,
                                                    'phone',
                                                    $search,
                                                    [PhoneNumberHelper::DEFAULT_REGION]
                                                );
                                            });
                                        });
                                    });
                            });
                        });
                    }),

                TextColumn::make('causer.name')
                    ->label('Пользователь')
                    ->searchable()
                    ->default('Система')
                    ->icon('heroicon-o-user'),

                TextColumn::make('subject_type')
                    ->label('Модель')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return '—';
                        }
                        return match ($state) {
                            'App\Models\Contact' => 'Контакт',
                            'App\Models\User' => 'Пользователь',
                            'App\Models\SystemSetting' => 'Настройка',
                            'App\Models\UserWarning' => 'Предупреждение',
                            default => class_basename($state),
                        };
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'App\Models\Contact' => 'primary',
                        'App\Models\User' => 'success',
                        'App\Models\SystemSetting' => 'warning',
                        'App\Models\UserWarning' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('event')
                    ->label('Событие')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return '—';
                        }
                        return match ($state) {
                            'created' => 'Создано',
                            'updated' => 'Обновлено',
                            'deleted' => 'Удалено',
                            default => $state,
                        };
                    })
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('properties')
                    ->label('Детали')
                    ->formatStateUsing(function ($state) {
                        if (!$state || empty($state)) {
                            return '—';
                        }

                        if (is_array($state) || is_object($state)) {
                            $data = is_object($state) ? (array) $state : $state;

                            $result = [];
                            if (isset($data['attributes']) && is_array($data['attributes'])) {
                                $changes = [];
                                foreach ($data['attributes'] as $key => $value) {
                                    $displayValue = is_array($value) || is_object($value)
                                        ? json_encode($value, JSON_UNESCAPED_UNICODE)
                                        : (string) $value;
                                    $changes[] = "{$key}: {$displayValue}";
                                }
                                if (!empty($changes)) {
                                    $result[] = 'Новые: ' . implode(', ', $changes);
                                }
                            }

                            if (isset($data['old']) && is_array($data['old'])) {
                                $oldChanges = [];
                                foreach ($data['old'] as $key => $value) {
                                    $displayValue = is_array($value) || is_object($value)
                                        ? json_encode($value, JSON_UNESCAPED_UNICODE)
                                        : (string) $value;
                                    $oldChanges[] = "{$key}: {$displayValue}";
                                }
                                if (!empty($oldChanges)) {
                                    $result[] = 'Старые: ' . implode(', ', $oldChanges);
                                }
                            }

                            if (empty($result)) {
                                $formatted = [];
                                foreach ($data as $key => $value) {
                                    $displayValue = is_array($value) || is_object($value)
                                        ? json_encode($value, JSON_UNESCAPED_UNICODE)
                                        : (string) $value;
                                    $formatted[] = "{$key}: {$displayValue}";
                                }
                                return implode(', ', $formatted);
                            }

                            return implode(' | ', $result);
                        }

                        return is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE);
                    })
                    ->wrap()
                    ->limit(200)
                    ->tooltip(function ($record) {
                        if (!$record->properties || empty($record->properties)) {
                            return null;
                        }
                        return json_encode($record->properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }),

                TextColumn::make('created_at')
                    ->label('Дата и время')
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => format_datetime_moscow($record->created_at)),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Модель')
                    ->options([
                        Contact::class => 'Контакт',
                        User::class => 'Пользователь',
                        SystemSetting::class => 'Настройка',
                        UserWarning::class => 'Предупреждение',
                    ])
                    ->native(false),

                SelectFilter::make('event')
                    ->label('Событие')
                    ->options([
                        'created' => 'Создано',
                        'updated' => 'Обновлено',
                        'deleted' => 'Удалено',
                    ])
                    ->native(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('30s')
            ->emptyStateHeading('Нет записей')
            ->emptyStateDescription('Журнал аудита пуст');
    }
}
