<?php

namespace Tests\Feature;

use App\Console\Commands\CheckOverdueContacts;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactComment;
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

    public function test_processing_activity_starts_on_assignment(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
        ], $assignedAt);

        $this->assertTrue($assignedAt->equalTo($contact->processing_activity_at));
        $this->assertTrue($assignedAt->copy()->addDays(30)->equalTo($contact->overdue_at));
    }

    public function test_processing_activity_resets_on_take_to_work(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');
        $inProgressAt = Carbon::parse('2026-06-10 12:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $inProgressAt],
        ], $inProgressAt);

        $this->assertTrue($inProgressAt->equalTo($contact->processing_activity_at));
    }

    public function test_processing_activity_resets_after_unfreeze(): void
    {
        $assignedAt = Carbon::parse('2026-01-01 10:00:00', 'UTC');
        $unfrozenAt = Carbon::parse('2026-06-11 00:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => $assignedAt->copy()->subDay()],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => $assignedAt],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $assignedAt->copy()->addDays(2)],
            ['old_status' => ContactStatus::IN_PROGRESS->value, 'new_status' => ContactStatus::FROZEN->value, 'created_at' => $assignedAt->copy()->addDays(3)],
            ['old_status' => ContactStatus::FROZEN->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $unfrozenAt],
        ], $unfrozenAt);

        $this->assertTrue($unfrozenAt->equalTo($contact->processing_activity_at));
    }

    public function test_processing_activity_resets_when_returned_from_overdue(): void
    {
        $returnedAt = Carbon::parse('2026-06-10 09:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::OVERDUE->value, 'created_at' => Carbon::parse('2026-02-15 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::OVERDUE->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => $returnedAt],
        ], $returnedAt);

        $this->assertTrue($returnedAt->equalTo($contact->processing_activity_at));
    }

    public function test_take_to_work_prevents_overdue_when_assignment_is_old(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $contact = $this->createContact(ContactStatus::IN_PROGRESS, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => Carbon::parse('2026-06-10 12:00:00', 'UTC')],
        ], Carbon::parse('2026-06-10 12:00:00', 'UTC'));

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
        ], Carbon::parse('2026-01-01 10:00:00', 'UTC'));

        $this->assertTrue($contact->isOverdue());
    }

    public function test_leader_comment_resets_overdue_timer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $commentAt = Carbon::parse('2026-06-10 12:00:00', 'UTC');

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
        ], Carbon::parse('2026-01-01 10:00:00', 'UTC'), $user);

        $this->assertTrue($contact->isOverdue());

        Carbon::setTestNow($commentAt);

        ContactComment::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'comment' => 'Связался с контактом',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $contact->refresh();

        $this->assertFalse($contact->isOverdue());
        $this->assertNotNull($contact->processing_activity_at);
        $this->assertSame(
            $commentAt->toIso8601String(),
            $contact->processing_activity_at->utc()->toIso8601String(),
        );
    }

    public function test_manager_comment_does_not_reset_overdue_timer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $leader = User::factory()->create();
        $manager = User::factory()->create();

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
        ], Carbon::parse('2026-01-01 10:00:00', 'UTC'), $leader);

        ContactComment::create([
            'contact_id' => $contact->id,
            'user_id' => $manager->id,
            'comment' => 'Комментарий менеджера',
            'created_at' => Carbon::parse('2026-06-10 12:00:00', 'UTC'),
        ]);

        $contact->refresh();

        $this->assertTrue($contact->isOverdue());
    }

    public function test_leader_comment_on_overdue_contact_restores_previous_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $user = User::factory()->create();

        $contact = $this->createContact(ContactStatus::OVERDUE, [
            ['old_status' => null, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::ASSIGNED->value, 'new_status' => ContactStatus::IN_PROGRESS->value, 'created_at' => Carbon::parse('2026-01-05 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::IN_PROGRESS->value, 'new_status' => ContactStatus::OVERDUE->value, 'created_at' => Carbon::parse('2026-02-15 10:00:00', 'UTC')],
        ], Carbon::parse('2026-01-05 10:00:00', 'UTC'), $user);

        ContactComment::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'comment' => 'Продолжаю работу',
            'created_at' => Carbon::parse('2026-06-11 11:00:00', 'UTC'),
        ]);

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertFalse($contact->isOverdue());
    }

    public function test_reassignment_resets_overdue_timer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $oldLeader = User::factory()->create();
        $newLeader = User::factory()->create();

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
        ], Carbon::parse('2026-01-01 10:00:00', 'UTC'), $oldLeader);

        $this->assertTrue($contact->isOverdue());

        $contact->update(['assigned_leader_id' => $newLeader->id]);

        $contact->refresh();

        $this->assertFalse($contact->isOverdue());
        $this->assertTrue(now('UTC')->equalTo($contact->processing_activity_at));
    }

    public function test_previous_leader_comment_does_not_count_after_reassignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-11 12:00:00', 'UTC'));

        $oldLeader = User::factory()->create();
        $newLeader = User::factory()->create();

        $contact = $this->createContact(ContactStatus::ASSIGNED, [
            ['old_status' => null, 'new_status' => ContactStatus::NOT_PROCESSED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
            ['old_status' => ContactStatus::NOT_PROCESSED->value, 'new_status' => ContactStatus::ASSIGNED->value, 'created_at' => Carbon::parse('2026-01-01 10:00:00', 'UTC')],
        ], Carbon::parse('2026-01-01 10:00:00', 'UTC'), $oldLeader);

        $contact->update(['assigned_leader_id' => $newLeader->id]);

        Carbon::setTestNow(Carbon::parse('2026-07-12 12:00:00', 'UTC'));

        $contact->refresh();

        $this->assertTrue($contact->isOverdue());

        ContactComment::create([
            'contact_id' => $contact->id,
            'user_id' => $oldLeader->id,
            'comment' => 'Комментарий после переназначения',
            'created_at' => Carbon::parse('2026-07-12 11:30:00', 'UTC'),
        ]);

        $contact->refresh();

        $this->assertTrue($contact->isOverdue());
    }

    /**
     * @param  list<array{old_status: ?string, new_status: string, created_at: Carbon}>  $history
     */
    private function createContact(
        ContactStatus $status,
        array $history,
        ?Carbon $activityAt = null,
        ?User $leader = null,
    ): Contact {
        $user = $leader ?? User::factory()->create();

        $contact = Contact::withoutEvents(function () use ($user, $status, $activityAt): Contact {
            $contact = Contact::create([
                'full_name' => 'Test Contact',
                'phone' => '+79990001122',
                'status' => $status,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);

            if ($activityAt !== null) {
                $contact->forceFill([
                    'processing_activity_at' => $activityAt,
                    'overdue_at' => $activityAt->copy()->addDays(30),
                ])->saveQuietly();
            }

            return $contact;
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
