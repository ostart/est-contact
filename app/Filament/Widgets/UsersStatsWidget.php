<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): string
    {
        return 'Статистика пользователей';
    }

    protected function getDescription(): ?string
    {
        return 'Информация о пользователях системы';
    }

    protected function getStats(): array
    {
        $total = User::count();
        $active = User::where('is_approved', true)->count();
        $pending = User::where('is_approved', false)->count();
        $newThisWeek = User::where('created_at', '>=', now()->subWeek())->count();
        
        $activeRate = $total > 0 ? round(($active / $total) * 100, 1) : 0;

        // График динамики регистрации пользователей за последние 5 дней
        $chartData = [];
        for ($i = 4; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $chartData[] = User::where('created_at', '<=', $date)->count();
        }

        return [
            Stat::make('Всего пользователей', number_format($total, 0, ',', ' '))
                ->description('Зарегистрировано')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->icon('heroicon-o-users')
                ->chart($chartData),

            Stat::make('Активных', number_format($active, 0, ',', ' '))
                ->description($activeRate . '%')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->icon('heroicon-o-shield-check'),

            Stat::make('Ожидают подтверждения', number_format($pending, 0, ',', ' '))
                ->description($total > 0 ? round(($pending / $total) * 100, 1) . '%' : '0%')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Новых за неделю', number_format($newThisWeek, 0, ',', ' '))
                ->description('За 7 дней')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
