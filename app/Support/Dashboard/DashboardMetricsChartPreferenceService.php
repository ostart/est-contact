<?php

namespace App\Support\Dashboard;

use App\Models\User;
use App\Models\UserDashboardChartPreference;

class DashboardMetricsChartPreferenceService
{
    public function resolveForUser(?User $user): DashboardMetricsChartPreferences
    {
        if ($user === null) {
            return DashboardMetricsChartPreferences::defaults();
        }

        $record = UserDashboardChartPreference::query()
            ->where('user_id', $user->id)
            ->first();

        if ($record === null) {
            return DashboardMetricsChartPreferences::defaults();
        }

        if (is_array($record->filter_state) && $record->filter_state !== []) {
            return DashboardMetricsChartPreferences::fromPersistedFilterState($record->filter_state);
        }

        return DashboardMetricsChartPreferences::fromPersistedFilterState([
            'metrics' => $record->metrics,
        ]);
    }

    public function saveForUser(User $user, DashboardMetricsChartPreferences $preferences): void
    {
        UserDashboardChartPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'metrics' => $preferences->metrics,
                'filter_state' => $preferences->toPersistedFilterState(),
                'start_date' => null,
                'end_date' => null,
            ],
        );
    }

    public function resetForUser(?User $user): void
    {
        if ($user === null) {
            return;
        }

        UserDashboardChartPreference::query()
            ->where('user_id', $user->id)
            ->delete();
    }
}
