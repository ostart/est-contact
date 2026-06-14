<?php

namespace App\Console\Commands;

use App\Support\Dashboard\ContactStatusHistoryBackfillService;
use App\Support\Dashboard\DashboardMetricsSnapshotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillDashboardMetrics extends Command
{
    protected $signature = 'dashboard:backfill-metrics
                            {--status-history : Только восстановить contact_status_histories из activity_log}
                            {--snapshots : Только пересчитать снимки метрик}
                            {--from= : Начало диапазона снимков (Y-m-d), по умолчанию — дата первого контакта или пользователя}
                            {--to= : Конец диапазона снимков (Y-m-d), по умолчанию — вчера}';

    protected $description = 'Восстановить историю статусов контактов и снимки метрик дашборда';

    public function handle(
        ContactStatusHistoryBackfillService $historyBackfill,
        DashboardMetricsSnapshotService $snapshotService,
    ): int {
        $statusHistoryOnly = (bool) $this->option('status-history');
        $snapshotsOnly = (bool) $this->option('snapshots');
        $runAll = ! $statusHistoryOnly && ! $snapshotsOnly;

        if ($runAll || $statusHistoryOnly) {
            $this->info('Восстановление contact_status_histories из activity_log...');
            $added = $historyBackfill->backfill(function (int $count): void {
                if ($count % 100 === 0) {
                    $this->output->write('.');
                }
            });
            $this->newLine();
            $this->info("Добавлено записей истории статусов: {$added}");
            $this->comment('Записи activity_log для удалённых контактов пропущены.');
        }

        if ($runAll || $snapshotsOnly) {
            $from = $this->resolveFromDate();
            $to = $this->resolveToDate();

            if ($from->gt($to)) {
                $this->error('Дата --from не может быть позже --to.');

                return self::FAILURE;
            }

            $this->info("Пересчёт снимков метрик с {$from->toDateString()} по {$to->toDateString()}...");

            $days = $snapshotService->backfillSnapshots($from, $to, function (Carbon $date, int $count): void {
                if ($count % 30 === 0) {
                    $this->line("  … {$date->toDateString()}");
                }
            });

            $this->info("Сохранено снимков за {$days} дн.");
        }

        return self::SUCCESS;
    }

    private function resolveFromDate(): Carbon
    {
        $from = $this->option('from');

        if (is_string($from) && $from !== '') {
            return Carbon::parse($from, 'UTC')->startOfDay();
        }

        $firstContact = \App\Models\Contact::query()->min('created_at');
        $firstUser = \App\Models\User::query()->min('created_at');

        $candidates = array_filter([$firstContact, $firstUser]);

        if ($candidates === []) {
            return now('UTC')->subDays(30)->startOfDay();
        }

        return Carbon::parse(min($candidates), 'UTC')->startOfDay();
    }

    private function resolveToDate(): Carbon
    {
        $to = $this->option('to');

        if (is_string($to) && $to !== '') {
            return Carbon::parse($to, 'UTC')->startOfDay();
        }

        return now('UTC')->subDay()->startOfDay();
    }
}
