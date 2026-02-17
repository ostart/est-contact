<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContactsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getHeading(): string
    {
        return 'Статистика контактов';
    }

    protected function getDescription(): ?string
    {
        return 'Общая информация о контактах в системе';
    }

    protected function getStats(): array
    {
        $total = Contact::count();
        $awaiting = Contact::where('status', ContactStatus::NOT_PROCESSED)->count();
        $inProgress = Contact::where('status', ContactStatus::ASSIGNED)->count();
        $overdue = Contact::where('status', ContactStatus::OVERDUE)->count();
        $success = Contact::where('status', ContactStatus::SUCCESS)->count();
        $failed = Contact::where('status', ContactStatus::FAILED)->count();
        $newThisWeek = Contact::where('created_at', '>=', now()->subWeek())->count();
        
        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
        $inProgressRate = $total > 0 ? round(($inProgress / $total) * 100, 1) : 0;
        $overdueRate = $total > 0 ? round(($overdue / $total) * 100, 1) : 0;

        // График динамики создания контактов за последние 5 дней
        $chartData = [];
        for ($i = 4; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $chartData[] = Contact::where('created_at', '<=', $date)->count();
        }

        return [
            Stat::make('Всего контактов', number_format($total, 0, ',', ' '))
                ->description('Всего записей')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->icon('heroicon-o-document-text')
                ->chart($chartData),

            Stat::make('Ожидают обработки', number_format($awaiting, 0, ',', ' '))
                ->description($total > 0 ? round(($awaiting / $total) * 100, 1) . '%' : '0%')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray')
                ->icon('heroicon-o-pause-circle'),

            Stat::make('В работе', number_format($inProgress, 0, ',', ' '))
                ->description($inProgressRate . '% активных')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->icon('heroicon-o-cog-6-tooth'),

            Stat::make('Просрочено', number_format($overdue, 0, ',', ' '))
                ->description($overdueRate . '%')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Успешно', number_format($success, 0, ',', ' '))
                ->description($successRate . '% успешных')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-badge'),

            Stat::make('Неуспешно', number_format($failed, 0, ',', ' '))
                ->description($total > 0 ? round(($failed / $total) * 100, 1) . '%' : '0%')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Новых за неделю', number_format($newThisWeek, 0, ',', ' '))
                ->description('За 7 дней')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning')
                ->icon('heroicon-o-star'),
        ];
    }
}
