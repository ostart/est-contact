<?php

namespace Tests\Unit;

use App\Filament\Widgets\DashboardMetricsChartWidget;
use App\Models\User;
use App\Models\UserDashboardChartPreference;
use App\Support\Dashboard\DashboardMetricKey;
use App\Support\Dashboard\DashboardMetricsChartPreferenceService;
use App\Support\Dashboard\DashboardMetricsChartPreferences;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMetricsChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-13', 'Europe/Moscow'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mount_uses_default_date_range_on_first_open(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DashboardMetricsChartWidget::class)
            ->assertSet('filters.startDate', '2026-05-14')
            ->assertSet('filters.endDate', '2026-06-13')
            ->assertSet('deferredFilters.startDate', '2026-05-14')
            ->assertSet('deferredFilters.endDate', '2026-06-13');
    }

    public function test_mount_loads_saved_metrics_but_not_saved_dates(): void
    {
        $user = User::factory()->create();

        app(DashboardMetricsChartPreferenceService::class)->saveForUser(
            $user,
            DashboardMetricsChartPreferences::fromFilterArray([
                'startDate' => '2026-01-01',
                'endDate' => '2026-01-31',
                'contactMetrics' => [DashboardMetricKey::ContactsTotal->value],
                'userMetrics' => [DashboardMetricKey::UsersActive->value],
            ]),
        );

        $this->actingAs($user);

        Livewire::test(DashboardMetricsChartWidget::class)
            ->assertSet('filters.contactMetrics', [DashboardMetricKey::ContactsTotal->value])
            ->assertSet('deferredFilters.contactMetrics', [DashboardMetricKey::ContactsTotal->value])
            ->assertSet('filters.userMetrics', [DashboardMetricKey::UsersActive->value])
            ->assertSet('filters.startDate', '2026-05-14')
            ->assertSet('filters.endDate', '2026-06-13');
    }

    public function test_apply_persists_selected_metrics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DashboardMetricsChartWidget::class)
            ->set('deferredFilters.contactMetrics', [
                DashboardMetricKey::ContactsTotal->value,
                DashboardMetricKey::ContactsNew->value,
            ])
            ->set('deferredFilters.userMetrics', [DashboardMetricKey::UsersBanned->value])
            ->set('deferredFilters.startDate', '2026-01-01')
            ->set('deferredFilters.endDate', '2026-01-31')
            ->call('applyFilters');

        $record = UserDashboardChartPreference::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(
            [DashboardMetricKey::ContactsTotal->value, DashboardMetricKey::ContactsNew->value],
            $record->filter_state['contactMetrics'] ?? null,
        );
        $this->assertSame(
            [DashboardMetricKey::UsersBanned->value],
            $record->filter_state['userMetrics'] ?? null,
        );
        $this->assertArrayNotHasKey('startDate', $record->filter_state ?? []);
        $this->assertArrayNotHasKey('endDate', $record->filter_state ?? []);
    }

    public function test_second_mount_restores_metrics_and_resets_dates_to_default_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DashboardMetricsChartWidget::class)
            ->set('deferredFilters.contactMetrics', [DashboardMetricKey::ContactsOverdue->value])
            ->set('deferredFilters.userMetrics', [])
            ->set('deferredFilters.startDate', '2026-01-01')
            ->set('deferredFilters.endDate', '2026-01-31')
            ->call('applyFilters');

        auth()->logout();

        $this->actingAs($user);

        Livewire::test(DashboardMetricsChartWidget::class)
            ->assertSet('filters.contactMetrics', [DashboardMetricKey::ContactsOverdue->value])
            ->assertSet('deferredFilters.contactMetrics', [DashboardMetricKey::ContactsOverdue->value])
            ->assertSet('filters.userMetrics', [])
            ->assertSet('filters.startDate', '2026-05-14')
            ->assertSet('filters.endDate', '2026-06-13');
    }
}
