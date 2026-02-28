<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class UsersTableWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Статистика по пользователям';
    }

    public function getDescription(): ?string
    {
        return 'Детальная информация о работе пользователей с контактами';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->with('roles')
                    ->withCount([
                        'assignedContacts as total_contacts',
                        'assignedContacts as success_contacts' => function (Builder $query) {
                            $query->where('status', ContactStatus::SUCCESS->value);
                        },
                        'assignedContacts as failed_contacts' => function (Builder $query) {
                            $query->where('status', ContactStatus::FAILED->value);
                        },
                        'assignedContacts as in_progress_contacts' => function (Builder $query) {
                            $query->where('status', ContactStatus::ASSIGNED->value);
                        },
                        'assignedContacts as overdue_contacts' => function (Builder $query) {
                            $query->where('status', ContactStatus::OVERDUE->value);
                        },
                        'warnings as warnings_count',
                    ])
                    ->addSelect([
                        'bans_count' => DB::table('activity_log')
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('activity_log.subject_id', 'users.id')
                            ->where('activity_log.subject_type', User::class)
                            ->where('activity_log.event', 'updated')
                            ->whereRaw("JSON_EXTRACT(activity_log.properties, '$.attributes.is_banned') = true"),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Пользователь')
                    ->searchable()
                    ->sortable(),

                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->size(32)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&background=random')
                    ->getStateUsing(fn ($record) => $record->avatar ? Storage::disk('public')->url($record->avatar) : null)
                    ->action(
                        Action::make('viewContact')
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
                                    $html .= '<p><strong>Телефон:</strong> ' . e($record->phone) . '</p>';
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

                TextColumn::make('roles.name')
                    ->label('Роли')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $abbreviations = [
                            'leader' => 'L',
                            'manager' => 'M',
                            'administrator' => 'A',
                            'superadmin' => 'S',
                        ];
                        return $abbreviations[strtolower($state)] ?? strtoupper(substr($state, 0, 1));
                    })
                    ->tooltip(function ($record) {
                        $roleNames = $record->roles->pluck('name')->map(function ($role) {
                            $fullNames = [
                                'leader' => 'Leader',
                                'manager' => 'Manager',
                                'administrator' => 'Administrator',
                                'superadmin' => 'Superadmin',
                            ];
                            return $fullNames[strtolower($role)] ?? $role;
                        });
                        return $roleNames->implode(', ');
                    })
                    ->listWithLineBreaks()
                    ->limitList(4),

                TextColumn::make('total_contacts')
                    ->label('Всего')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('success_contacts')
                    ->label('Успешно')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                TextColumn::make('failed_contacts')
                    ->label('Отказ')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('danger'),

                TextColumn::make('in_progress_contacts')
                    ->label('В работе')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('overdue_contacts')
                    ->label('Просрочено')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('warnings_count')
                    ->label('Предупр.')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('bans_count')
                    ->label('Баны')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('total_contacts', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Нет пользователей')
            ->emptyStateDescription('Пользователи пока не зарегистрированы');
    }
}
