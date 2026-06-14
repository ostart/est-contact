<?php

namespace App\Support\Dashboard;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Models\DashboardMetricSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsSnapshotService
{
    /**
     * @return array<string, int>
     */
    public function computeForDate(Carbon $date): array
    {
        $startOfDay = $date->copy()->timezone('UTC')->startOfDay();
        $endOfDay = $date->copy()->timezone('UTC')->endOfDay();

        $statusCounts = $this->contactStatusCountsAt($endOfDay);
        $userCounts = $this->userCountsAt($endOfDay);

        $values = [
            DashboardMetricKey::ContactsTotal->value => Contact::query()
                ->where('created_at', '<=', $endOfDay)
                ->count(),
            DashboardMetricKey::ContactsNew->value => Contact::query()
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count(),
            DashboardMetricKey::UsersTotal->value => $userCounts['total'],
            DashboardMetricKey::UsersNew->value => User::query()
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count(),
            DashboardMetricKey::UsersActive->value => $userCounts['active'],
            DashboardMetricKey::UsersPending->value => $userCounts['pending'],
            DashboardMetricKey::UsersBanned->value => $userCounts['banned'],
        ];

        foreach (DashboardMetricKey::chartSeries() as $metric) {
            $status = $metric->contactStatus();

            if ($status === null) {
                continue;
            }

            $values[$metric->value] = $statusCounts[$status->value] ?? 0;
        }

        return $values;
    }

    public function captureForDate(Carbon $date): void
    {
        $this->persistValues($date, $this->computeForDate($date));
    }

    /**
     * @return array<string, int>
     */
    public function contactStatusCountsAt(Carbon $endOfDay): array
    {
        $counts = [];

        foreach (ContactStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $contactIds = Contact::query()
            ->where('created_at', '<=', $endOfDay)
            ->pluck('id');

        if ($contactIds->isEmpty()) {
            return $counts;
        }

        $historiesByContact = ContactStatusHistory::query()
            ->whereIn('contact_id', $contactIds)
            ->where('created_at', '<=', $endOfDay)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('contact_id');

        foreach ($contactIds as $contactId) {
            /** @var Collection<int, ContactStatusHistory> $history */
            $history = $historiesByContact->get($contactId, collect());
            $status = $this->resolveContactStatusFromHistory($history);
            $counts[$status->value]++;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, ContactStatusHistory>  $history
     */
    public function resolveContactStatusFromHistory(Collection $history): ContactStatus
    {
        $latest = $history->last();

        if ($latest === null) {
            return ContactStatus::NOT_PROCESSED;
        }

        return ContactStatus::from($latest->new_status);
    }

    /**
     * @return array{total: int, active: int, pending: int, banned: int}
     */
    public function userCountsAt(Carbon $endOfDay): array
    {
        $total = 0;
        $active = 0;
        $pending = 0;
        $banned = 0;

        User::query()
            ->where('created_at', '<=', $endOfDay)
            ->select(['id', 'created_at', 'is_approved', 'is_banned'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($endOfDay, &$total, &$active, &$pending, &$banned): void {
                $states = $this->userStatesAt($users->pluck('id')->all(), $endOfDay);

                foreach ($users as $user) {
                    $total++;
                    $state = $states[$user->id] ?? [
                        'is_approved' => (bool) $user->is_approved,
                        'is_banned' => (bool) $user->is_banned,
                    ];

                    if ($state['is_banned']) {
                        $banned++;
                    } elseif ($state['is_approved']) {
                        $active++;
                    } else {
                        $pending++;
                    }
                }
            });

        return compact('total', 'active', 'pending', 'banned');
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{is_approved: bool, is_banned: bool}>
     */
    public function userStatesAt(array $userIds, Carbon $endOfDay): array
    {
        if ($userIds === []) {
            return [];
        }

        $table = (string) config('activitylog.table_name');

        $activities = DB::table($table)
            ->where('subject_type', User::class)
            ->whereIn('subject_id', $userIds)
            ->where('created_at', '<=', $endOfDay)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['subject_id', 'event', 'properties']);

        $states = [];

        foreach ($userIds as $userId) {
            $states[$userId] = [
                'is_approved' => false,
                'is_banned' => false,
            ];
        }

        foreach ($activities as $activity) {
            $properties = json_decode((string) $activity->properties, true) ?? [];
            $attributes = $properties['attributes'] ?? [];
            $userId = (int) $activity->subject_id;

            if (! array_key_exists($userId, $states)) {
                continue;
            }

            if (array_key_exists('is_approved', $attributes)) {
                $states[$userId]['is_approved'] = (bool) $attributes['is_approved'];
            }

            if (array_key_exists('is_banned', $attributes)) {
                $states[$userId]['is_banned'] = (bool) $attributes['is_banned'];
            }
        }

        return $states;
    }

    public function backfillSnapshots(Carbon $from, Carbon $to, ?callable $onProgress = null): int
    {
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
        $written = 0;

        foreach ($period as $date) {
            $this->captureForDate($date);
            $written++;

            if ($onProgress !== null) {
                $onProgress($date, $written);
            }
        }

        return $written;
    }

    /**
     * @param  array<string, int>  $values
     */
    private function persistValues(Carbon $date, array $values): void
    {
        $snapshotDate = $date->copy()->timezone('UTC')->toDateString();
        $now = now('UTC');

        foreach ($values as $metricKey => $value) {
            DashboardMetricSnapshot::query()->updateOrCreate(
                [
                    'snapshot_date' => $snapshotDate,
                    'metric_key' => $metricKey,
                ],
                [
                    'value' => $value,
                    'created_at' => $now,
                ],
            );
        }
    }
}
