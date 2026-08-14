<?php

namespace App\Filament\Widgets;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContactsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

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
        $assigned = Contact::where('status', ContactStatus::ASSIGNED)->count();
        $inProgress = Contact::where('status', ContactStatus::IN_PROGRESS)->count();
        $frozen = Contact::where('status', ContactStatus::FROZEN)->count();
        $overdue = Contact::where('status', ContactStatus::OVERDUE)->count();
        $success = Contact::where('status', ContactStatus::SUCCESS)->count();
        $failed = Contact::where('status', ContactStatus::FAILED)->count();
        $newThisWeek = Contact::where('created_at', '>=', now()->subWeek())->count();

        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
        $assignedRate = $total > 0 ? round(($assigned / $total) * 100, 1) : 0;
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

            $this->statusStat(
                Stat::make('Ожидают обработки', number_format($awaiting, 0, ',', ' '))
                    ->description($total > 0 ? round(($awaiting / $total) * 100, 1).'%' : '0%')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color(ContactStatus::NOT_PROCESSED->getColor())
                    ->icon('heroicon-o-pause-circle'),
                ContactStatus::NOT_PROCESSED,
            ),

            $this->statusStat(
                Stat::make(ContactStatus::ASSIGNED->getLabel(), number_format($assigned, 0, ',', ' '))
                    ->description($assignedRate.'% в очереди')
                    ->descriptionIcon('heroicon-m-user-plus')
                    ->color(ContactStatus::ASSIGNED->getColor())
                    ->icon('heroicon-o-inbox'),
                ContactStatus::ASSIGNED,
            ),

            $this->statusStat(
                Stat::make(ContactStatus::IN_PROGRESS->getLabel(), number_format($inProgress, 0, ',', ' '))
                    ->description($inProgressRate.'% активных')
                    ->descriptionIcon('heroicon-m-arrow-path')
                    ->color(ContactStatus::IN_PROGRESS->getColor())
                    ->icon('heroicon-o-cog-6-tooth'),
                ContactStatus::IN_PROGRESS,
            ),

            $this->statusStat(
                Stat::make(ContactStatus::FROZEN->getLabel(), number_format($frozen, 0, ',', ' '))
                    ->description($total > 0 ? round(($frozen / $total) * 100, 1).'%' : '0%')
                    ->descriptionIcon('heroicon-m-pause')
                    ->color(ContactStatus::FROZEN->getColor())
                    ->icon('heroicon-o-pause-circle'),
                ContactStatus::FROZEN,
            ),

            $this->statusStat(
                Stat::make('Просрочено', number_format($overdue, 0, ',', ' '))
                    ->description($overdueRate.'%')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color(ContactStatus::OVERDUE->getColor())
                    ->icon('heroicon-o-clock'),
                ContactStatus::OVERDUE,
            ),

            $this->statusStat(
                Stat::make(ContactStatus::SUCCESS->getLabel(), number_format($success, 0, ',', ' '))
                    ->description($successRate.'% успешных')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color(ContactStatus::SUCCESS->getColor())
                    ->icon('heroicon-o-check-badge'),
                ContactStatus::SUCCESS,
            ),

            $this->statusStat(
                Stat::make(ContactStatus::FAILED->getLabel(), number_format($failed, 0, ',', ' '))
                    ->description($total > 0 ? round(($failed / $total) * 100, 1).'%' : '0%')
                    ->descriptionIcon('heroicon-m-x-circle')
                    ->color(ContactStatus::FAILED->getColor())
                    ->icon('heroicon-o-x-circle'),
                ContactStatus::FAILED,
            ),

            Stat::make('Новых за неделю', number_format($newThisWeek, 0, ',', ' '))
                ->description('За 7 дней')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('brown')
                ->icon('heroicon-o-star'),
        ];
    }

    protected function statusStat(Stat $stat, ContactStatus $status): Stat
    {
        $url = $this->filteredContactsUrl($status);

        if ($url === null) {
            return $stat;
        }

        return $stat->url($url);
    }

    protected function filteredContactsUrl(ContactStatus $status): ?string
    {
        if (! $this->userCanUseContactFilters()) {
            return null;
        }

        return ContactResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'value' => $status->value,
                ],
                'my_contacts' => [
                    'isActive' => false,
                ],
            ],
        ]);
    }

    protected function userCanUseContactFilters(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole('leader') && ! $user->can_use_contact_filters) {
            return false;
        }

        return true;
    }
}
