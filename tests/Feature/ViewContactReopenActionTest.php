<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource\Pages\ViewContact;
use App\Livewire\ContactReopenButton;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewContactReopenActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_reopen_button_updates_failed_contact(): void
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

        $this->actingAs($leaderB);

        Livewire::test(ContactReopenButton::class, ['contactId' => $contact->getKey()])
            ->callAction('takeToWork')
            ->assertNotified();

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertSame($leaderB->id, $contact->assigned_leader_id);
    }

    public function test_take_to_work_button_updates_not_processed_contact(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001155',
            'status' => ContactStatus::NOT_PROCESSED,
            'assigned_leader_id' => null,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ContactReopenButton::class, ['contactId' => $contact->getKey()])
            ->callAction('takeToWork')
            ->assertNotified();

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertSame($leader->id, $contact->assigned_leader_id);
    }

    public function test_return_to_work_button_updates_overdue_contact(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001166',
            'status' => ContactStatus::OVERDUE,
            'assigned_leader_id' => $leader->id,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ContactReopenButton::class, ['contactId' => $contact->getKey()])
            ->callAction('takeToWork')
            ->assertNotified();

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertSame($leader->id, $contact->assigned_leader_id);
    }

    public function test_return_to_work_button_updates_frozen_contact(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001177',
            'status' => ContactStatus::FROZEN,
            'frozen_until' => now()->addDay(),
            'assigned_leader_id' => $leader->id,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ContactReopenButton::class, ['contactId' => $contact->getKey()])
            ->callAction('takeToWork')
            ->assertNotified();

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertNull($contact->frozen_until);
        $this->assertSame($leader->id, $contact->assigned_leader_id);
    }

    public function test_reopen_button_works_for_user_with_manager_role(): void
    {
        $leader = $this->createLeader('Leader Manager');
        $leader->assignRole('manager');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001144',
            'status' => ContactStatus::FAILED,
            'assigned_leader_id' => $leader->id,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ContactReopenButton::class, ['contactId' => $contact->getKey()])
            ->callAction('takeToWork')
            ->assertNotified();

        $contact->refresh();

        $this->assertSame(ContactStatus::IN_PROGRESS, $contact->status);
        $this->assertSame($leader->id, $contact->assigned_leader_id);
    }

    public function test_reopen_button_is_rendered_on_failed_contact_page(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001133',
            'status' => ContactStatus::FAILED,
            'assigned_leader_id' => $leader->id,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ViewContact::class, [
            'record' => $contact->getKey(),
        ])
            ->assertSeeLivewire(ContactReopenButton::class)
            ->assertSee('Вернуть в работу');
    }

    public function test_take_to_work_button_is_rendered_on_not_processed_contact_page(): void
    {
        $leader = $this->createLeader('Leader');

        $contact = Contact::create([
            'full_name' => 'Test Contact',
            'phone' => '+79990001188',
            'status' => ContactStatus::NOT_PROCESSED,
            'assigned_leader_id' => null,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(ViewContact::class, [
            'record' => $contact->getKey(),
        ])
            ->assertSeeLivewire(ContactReopenButton::class)
            ->assertSee('Взять в работу')
            ->assertDontSee('Изменить статус');
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
