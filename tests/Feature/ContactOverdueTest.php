<?php

namespace Tests\Feature;

use App\Console\Commands\CheckOverdueContacts;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactOverdueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('contact_processing_timeout_days', '30');
    }

    public function test_processing_started_at_uses_first_assignment_when_never_taken_to_work(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
        ]);

        $this->assertTrue($assignedAt->equalTo($contact->processingStartedAt()));
    }

    public function test_processing_started_at_resets_on_take_to_work(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');
        $inProgressAt = Carbon::parse('2026-06-10 12:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $inProgressAt],
        ]);

        $this->assertTrue($inProgressAt->equalTo($contact->processingStartedAt()));
    }

    public function test_processing_started_at_resets_after_unfreeze(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');
        $unfrozenAt = Carbon::parse('2026-06-11 00:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $assignedAt->copy()->addDays(2)],
            ['old_status' => ContactStatus::IN_PROGRESS->value, 'new_status' => ContactStatus::FROZEN->value, 'created_at' => $assignedAt->copy()->addDays(3)],
            ['old_status' => ContactStatus::FROZEN->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $unfrozenAt],
        ]);

        $this->assertTrue($unfrozenAt->equalTo($contact->processingStartedAt()));
    }

    public function test_processing_started_at_resets_when_returned_from_overdue(): void
    {
        $returnedAt = Carbon::parse('2026-06-10 09:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::OVERDUE->value, 'created_at' => Carbon::parse('2026-02-15 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::OVERDUE->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $returnedAt],
        ]);

        $this->assertTrue($returnedAt->equalTo($contact->processingStartedAt()));
    }

    public function test_take_to_work_prevents_overdue_when_assignment_is_old(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => Carbon::parse('2026-06-10 12:00:00', 'UTC')],
        ]);

        $this->assertFalse($contact->isOverdue());
    }

    public function test_unfrozen_contact_is_not_marked_overdue_in_same_command_run(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 00:00:00', 'UTC'));

        $user = User::factory()->create();

        $contact = Contact::withoutEvents(function () use ($user): Contact {
            return Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => ContactStatus::FROZEN,
                'frozen_until' => Carbon::parse('2026-06-11 00:00:00', 'UTC'),
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
        });

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'old_status' => null,
            'new_status' => ContactStatus::NOT_PROCESSED->value,
            'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC'),
        ]);

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'old_status' => ContactStatus::NOT_PROCESSED->value,
            'new_status' => ContactStatus::ASSIGNED->value,
            'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC'),
        ]);

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'old_status' => ContactStatus::ASSIGNED->value,
            'new_status' => ContactStatus::FROZEN->value,
            'created_at' => Carbon::parse('2026-06-01 10:00:00', 'UTC'),
        ]);

        $this->artisan(CheckOverdueContacts::class)->assertSuccessful();

        $contact->refresh();

        $this->assertSame(ContactStatus::ASSIGNED, $contact->status);
        $this->assertFalse($contact->isOverdue());
    }

    public function test_old_assignment_without_take_to_work_is_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
        ]);

        $this->assertTrue($contact->isOverdue());
    }

    /**
     * @param  list<array{old_status: ?string, new_status: string, created_at: Carbon}>  $history
     */
    private function createContact(ContactStatus $status, array $history): Contact
    {
        $user = User::factory()->create();

        $contact = Contact::withoutEvents(function () use ($user, $status): Contact {
            return Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => $status,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);
        });

        foreach ($history as $entry) {
            ContactStatusHistory::create([
                'contact_id' => $contact->id,
                'user_id' => $user->id,
                'old_status' => $entry['old_status'],
                'new_status' => $entry['new_status'],
                'created_at' => $entry['created_at'],
            ]);
        }

        return $contact->fresh();
    }
}
