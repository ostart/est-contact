<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactStatusHistory;
use App\Models\User;
use App\Support\ContactReopenService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderReopenFailedContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_leader_can_reopen_failed_contact_assigned_to_another_leader(): void
    {
        $leaderA = $this->createLeader('Leader A');
        $leaderB = $this->createLeader('Leader B');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001122',
            'status' => ContactStatus::FAILED,
            'assigned_leader_id' => $leaderA->id,
            'created_by' => $leaderA->id,
        ]);

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $leaderA->id,
            'old_status' => ContactStatus::IN_PROGRESS->value,
            'new_status' => ContactStatus::FAILED->value,
            'created_at' => now('UTC'),
        ]);

        $this->actingAs($leaderB);

        ContactReopenService::reopenFromFailed($contact, $leaderB);

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertSame($leaderB->id, $contact->assigned_leader_id);
        $this->assertNotNull($contact->processing_activity_at);

        $this->assertDatabaseHas('contact_status_histories', [
            'contact_id' => $contact->id,
            'old_status' => ContactStatus::FAILED->value,
            'new_status' => ContactStatus::IN_PROGRESS->value,
            'user_id' => $leaderB->id,
        ]);
    }

    public function test_failed_contact_has_latest_failed_status_history_relation(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001133',
            'status' => ContactStatus::FAILED,
            'assigned_leader_id' => $leader->id,
            'created_by' => $leader->id,
        ]);

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $leader->id,
            'old_status' => null,
            'new_status' => ContactStatus::NOT_PROCESSED->value,
            'created_at' => now('UTC')->subDays(2),
        ]);

        ContactStatusHistory::create([
            'contact_id' => $contact->id,
            'user_id' => $leader->id,
            'old_status' => ContactStatus::IN_PROGRESS->value,
            'new_status' => ContactStatus::FAILED->value,
            'created_at' => now('UTC')->subDay(),
        ]);

        $contact->load('latestFailedStatusHistory');

        $this->assertNotNull($contact->latestFailedStatusHistory);
        $this->assertSame(ContactStatus::FAILED->value, $contact->latestFailedStatusHistory->new_status);
    }

    private function createLeader(string $name): User
    {
        $leader = User::factory()->create([
            'name' => $name,
            'is_approved' => true,
        ]);
        $leader->assignRole('leader');

        return $leader;
    }
}
