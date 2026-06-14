<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Models\DashboardMetricSnapshot;
use App\Models\User;
use App\Support\Dashboard\ContactStatusHistoryBackfillService;
use App\Support\Dashboard\DashboardMetricKey;
use App\Support\Dashboard\DashboardMetricsChartDataService;
use App\Support\Dashboard\DashboardMetricsSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_service_counts_contact_statuses_at_date(): void
    {
        $dayOne = Carbon::parse('2026-06-01 10:00:00', 'UTC');
        $dayTwo = Carbon::parse('2026-06-02 10:00:00', 'UTC');
        $user = User::factory()->create();

        $contact = Contact::withoutEvents(function () use ($user, $dayOne): Contact {
            $contact = Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => ContactStatus::ASSIGNED,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
            $contact->forceFill([
                'created_at' => $dayOne,
                'updated_at' => $dayOne,
            ])->saveQuietly();

            return $contact;
        });

        ContactStatusHistory::query()->create([
            'contact_id' => $contact->id,
            'user_id' => null,
            'old_status' => null,
            'new_status' => ContactStatus::NOT_PROCESSED->value,
            'created_at' => $dayOne,
        ]);

        ContactStatusHistory::query()->create([
            'contact_id' => $contact->id,
            'user_id' => null,
            'old_status' => ContactStatus::NOT_PROCESSED->value,
            'new_status' => ContactStatus::ASSIGNED->value,
            'created_at' => $dayOne->copy()->addHour(),
        ]);

        ContactStatusHistory::query()->create([
            'contact_id' => $contact->id,
            'user_id' => null,
            'old_status' => ContactStatus::ASSIGNED->value,
            'new_status' => ContactStatus::SUCCESS->value,
            'created_at' => $dayTwo,
        ]);

        $service = app(DashboardMetricsSnapshotService::class);

        $dayOneValues = $service->computeForDate($dayOne->copy()->endOfDay());
        $dayTwoValues = $service->computeForDate($dayTwo->copy()->endOfDay());

        $this->assertSame(1, $dayOneValues[DashboardMetricKey::ContactsAssigned->value]);
        $this->assertSame(0, $dayOneValues[DashboardMetricKey::ContactsSuccess->value]);
        $this->assertSame(1, $dayTwoValues[DashboardMetricKey::ContactsSuccess->value]);
        $this->assertSame(0, $dayTwoValues[DashboardMetricKey::ContactsAssigned->value]);
    }

    public function test_capture_for_date_persists_all_metric_keys(): void
    {
        $user = User::factory()->create();

        Contact::withoutEvents(function () use ($user): void {
            $contact = Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => ContactStatus::NOT_PROCESSED,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
            $contact->forceFill([
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ])->saveQuietly();
        });

        User::factory()->create([
            'is_approved' => true,
            'created_at' => now('UTC'),
        ]);

        $service = app(DashboardMetricsSnapshotService::class);
        $service->captureForDate(now('UTC'));

        $this->assertSame(
            count(DashboardMetricKey::chartSeries()),
            DashboardMetricSnapshot::query()->whereDate('snapshot_date', now('UTC')->toDateString())->count(),
        );
    }

    public function test_backfill_service_skips_activity_for_deleted_contact(): void
    {
        DB::table((string) config('activitylog.table_name'))->insert([
            'log_name' => 'default',
            'description' => 'created',
            'subject_type' => Contact::class,
            'subject_id' => 999_999,
            'event' => 'created',
            'properties' => json_encode([
                'attributes' => ['status' => ContactStatus::NOT_PROCESSED->value],
            ]),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $added = app(ContactStatusHistoryBackfillService::class)->backfill();

        $this->assertSame(0, ContactStatusHistory::query()->where('contact_id', 999_999)->count());
        $this->assertGreaterThanOrEqual(0, $added);
    }

    public function test_backfill_service_inserts_missing_status_history_from_activity_log(): void
    {
        $user = User::factory()->create();

        $contact = Contact::withoutEvents(function () use ($user): Contact {
            $contact = Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => ContactStatus::IN_PROGRESS,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
            $contact->forceFill([
                'created_at' => Carbon::parse('2026-05-01 12:00:00', 'UTC'),
                'updated_at' => Carbon::parse('2026-05-01 12:00:00', 'UTC'),
            ])->saveQuietly();

            return $contact;
        });

        ContactStatusHistory::query()->delete();

        activity()
            ->performedOn($contact)
            ->event('created')
            ->withProperties([
                'attributes' => ['status' => ContactStatus::NOT_PROCESSED->value],
            ])
            ->createdAt(Carbon::parse('2026-05-01 12:00:00', 'UTC'))
            ->log('created');

        activity()
            ->performedOn($contact)
            ->event('updated')
            ->withProperties([
                'attributes' => ['status' => ContactStatus::ASSIGNED->value],
                'old' => ['status' => ContactStatus::NOT_PROCESSED->value],
            ])
            ->createdAt(Carbon::parse('2026-05-02 12:00:00', 'UTC'))
            ->log('updated');

        $added = app(ContactStatusHistoryBackfillService::class)->backfill();

        $this->assertGreaterThanOrEqual(2, $added);
        $this->assertSame(2, ContactStatusHistory::query()->where('contact_id', $contact->id)->count());
    }

    public function test_chart_data_service_builds_datasets_for_date_range(): void
    {
        $date = Carbon::parse('2026-06-10', 'UTC');
        $user = User::factory()->create();

        Contact::withoutEvents(function () use ($user, $date): void {
            $contact = Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => ContactStatus::ASSIGNED,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
            $contact->forceFill([
                'created_at' => $date,
                'updated_at' => $date,
            ])->saveQuietly();
        });

        app(DashboardMetricsSnapshotService::class)->captureForDate($date);

        $chart = app(DashboardMetricsChartDataService::class)->build($date, $date);

        $this->assertNotEmpty($chart['labels']);
        $this->assertCount(count(DashboardMetricKey::defaultValues()), $chart['datasets']);
        $this->assertSame('Новых контактов', $chart['datasets'][1]['label']);
        $this->assertSame('y1', $chart['datasets'][1]['yAxisID']);
    }

    public function test_check_overdue_command_creates_metric_snapshot(): void
    {
        $this->artisan('contacts:check-overdue')->assertSuccessful();

        $this->assertGreaterThan(
            0,
            DashboardMetricSnapshot::query()->whereDate('snapshot_date', now('UTC')->toDateString())->count(),
        );
    }
}
