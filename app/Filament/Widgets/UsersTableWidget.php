<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

                TextColumn::make('roles.name')
                    ->label('Роли')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state)
                    ->listWithLineBreaks()
                    ->limitList(2),

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
