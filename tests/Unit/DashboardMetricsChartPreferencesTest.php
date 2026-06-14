<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserDashboardChartPreference;
use App\Support\Dashboard\DashboardChartColors;
use App\Support\Dashboard\DashboardMetricKey;
use App\Support\Dashboard\DashboardMetricsChartPreferenceService;
use App\Support\Dashboard\DashboardMetricsChartPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsChartPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_use_last_30_days_date_range(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-13', 'Europe/Moscow'));

        $defaults = DashboardMetricsChartPreferences::defaults();

        $this->assertSame('2026-05-14', $defaults->startDate);
        $this->assertSame('2026-06-13', $defaults->endDate);

        \Carbon\Carbon::setTestNow();
    }

    public function test_defaults_include_only_contact_metrics(): void
    {
        $defaults = DashboardMetricsChartPreferences::defaults();

        $this->assertSame(DashboardMetricKey::contactValues(), $defaults->metrics);
        $this->assertNotContains(DashboardMetricKey::UsersTotal->value, $defaults->metrics);
    }

    public function test_filter_array_splits_contact_and_user_metrics(): void
    {
        $filters = DashboardMetricsChartPreferences::fromFilterArray([
            'contactMetrics' => [DashboardMetricKey::ContactsNew->value],
            'userMetrics' => [DashboardMetricKey::UsersActive->value],
            'startDate' => '2026-06-01',
            'endDate' => '2026-06-10',
        ])->toFilterArray();

        $this->assertSame([DashboardMetricKey::ContactsNew->value], $filters['contactMetrics']);
        $this->assertSame([DashboardMetricKey::UsersActive->value], $filters['userMetrics']);
    }

    public function test_new_contacts_chart_color_is_distinct_from_overdue(): void
    {
        $newContacts = DashboardChartColors::forMetric(DashboardMetricKey::ContactsNew);
        $overdue = DashboardChartColors::forMetric(DashboardMetricKey::ContactsOverdue);

        $newUsers = DashboardChartColors::forMetric(DashboardMetricKey::UsersNew);

        $this->assertNotSame($newContacts['borderColor'], $overdue['borderColor']);
        $this->assertSame('#92400e', $newContacts['borderColor']);
        $this->assertSame($newContacts['borderColor'], $newUsers['borderColor']);
    }

    public function test_filter_state_round_trip_preserves_checkbox_selection(): void
    {
        $user = User::factory()->create();

        $preferences = DashboardMetricsChartPreferences::fromFilterArray([
            'startDate' => '2026-06-01',
            'endDate' => '2026-06-10',
            'contactMetrics' => [DashboardMetricKey::ContactsTotal->value],
            'userMetrics' => [DashboardMetricKey::UsersActive->value],
        ]);

        app(DashboardMetricsChartPreferenceService::class)->saveForUser($user, $preferences);

        $record = UserDashboardChartPreference::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(
            [DashboardMetricKey::ContactsTotal->value],
            $record->filter_state['contactMetrics'] ?? null,
        );
        $this->assertSame(
            [DashboardMetricKey::UsersActive->value],
            $record->filter_state['userMetrics'] ?? null,
        );
        $this->assertArrayNotHasKey('startDate', $record->filter_state ?? []);
        $this->assertArrayNotHasKey('endDate', $record->filter_state ?? []);
        $this->assertNull($record->start_date);
        $this->assertNull($record->end_date);

        $loaded = app(DashboardMetricsChartPreferenceService::class)->resolveForUser($user)->toFilterArray();

        $this->assertSame([DashboardMetricKey::ContactsTotal->value], $loaded['contactMetrics']);
        $this->assertSame([DashboardMetricKey::UsersActive->value], $loaded['userMetrics']);
        $this->assertSame(DashboardMetricsChartPreferences::defaultStartDate(), $loaded['startDate']);
        $this->assertSame(DashboardMetricsChartPreferences::defaultEndDate(), $loaded['endDate']);
    }

    public function test_apply_filter_array_persists_selected_contact_metrics(): void
    {
        $user = User::factory()->create();

        $validated = DashboardMetricsChartPreferences::validatedFilterArray([
            'startDate' => '2026-06-01',
            'endDate' => '2026-06-10',
            'contactMetrics' => [
                DashboardMetricKey::ContactsTotal->value,
                DashboardMetricKey::ContactsNew->value,
            ],
            'userMetrics' => [],
        ]);

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $user,
            DashboardMetricsChartPreferences::fromFilterArray($validated),
        );

        $loaded = app(DashboardMetricsChartPreferenceService::class)->resolveForUser($user);

        $this->assertSame(
            [DashboardMetricKey::ContactsTotal->value, DashboardMetricKey::ContactsNew->value],
            $loaded->metrics,
        );
        $this->assertSame(DashboardMetricsChartPreferences::defaultStartDate(), $loaded->startDate);
        $this->assertSame(DashboardMetricsChartPreferences::defaultEndDate(), $loaded->endDate);

        $filterForm = $loaded->toFilterArray();

        $this->assertSame(
            [DashboardMetricKey::ContactsTotal->value, DashboardMetricKey::ContactsNew->value],
            $filterForm['contactMetrics'],
        );
        $this->assertSame([], $filterForm['userMetrics']);
    }

    public function test_preferences_are_stored_per_user(): void
    {
        $user = User::factory()->create();

        $preferences = new DashboardMetricsChartPreferences(
            metrics: [DashboardMetricKey::ContactsTotal->value, DashboardMetricKey::UsersTotal->value],
            startDate: '2026-05-01',
            endDate: '2026-06-01',
        );

        app(DashboardMetricsChartPreferenceService::class)->saveForUser($user, $preferences);

        $loaded = app(DashboardMetricsChartPreferenceService::class)->resolveForUser($user);

        $this->assertSame($preferences->metrics, $loaded->metrics);
        $this->assertSame(DashboardMetricsChartPreferences::defaultStartDate(), $loaded->startDate);
        $this->assertSame(DashboardMetricsChartPreferences::defaultEndDate(), $loaded->endDate);
    }

    public function test_reset_removes_saved_preferences(): void
    {
        $user = User::factory()->create();

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $user,
            DashboardMetricsChartPreferences::defaults(),
        );

        app(DashboardMetricsChartPreferenceService::class)->resetForUser($user);

        $this->assertNull(
            UserDashboardChartPreference::query()->where('user_id', $user->id)->first(),
        );
    }

    public function test_users_have_separate_chart_preferences(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $firstUser,
            DashboardMetricsChartPreferences::fromFilterArray([
                'metrics' => [DashboardMetricKey::ContactsNew->value],
                'startDate' => '2026-06-01',
                'endDate' => '2026-06-10',
            ]),
        );

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $secondUser,
            DashboardMetricsChartPreferences::fromFilterArray([
                'metrics' => [DashboardMetricKey::UsersBanned->value],
                'startDate' => '2026-01-01',
                'endDate' => '2026-01-31',
            ]),
        );

        $first = app(DashboardMetricsChartPreferenceService::class)->resolveForUser($firstUser);
        $second = app(DashboardMetricsChartPreferenceService::class)->resolveForUser($secondUser);

        $this->assertSame([DashboardMetricKey::ContactsNew->value], $first->metrics);
        $this->assertSame([DashboardMetricKey::UsersBanned->value], $second->metrics);
    }

    public function test_chart_data_service_filters_selected_metrics(): void
    {
        $date = now('UTC')->toDateString();

        $chart = app(\App\Support\Dashboard\DashboardMetricsChartDataService::class)->build(
            \Carbon\Carbon::parse($date, 'UTC'),
            \Carbon\Carbon::parse($date, 'UTC'),
            [DashboardMetricKey::ContactsTotal->value],
        );

        $this->assertCount(1, $chart['datasets']);
        $this->assertSame('Всего контактов', $chart['datasets'][0]['label']);
    }
}
