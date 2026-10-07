<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Filament\Resources\ManagementResource\Pages\ListManagement;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactCommentSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_management_search_finds_contact_by_comment(): void
    {
        $manager = $this->createUser('manager');
        $leader = $this->createUser('leader');

        $matched = $this->createContact('Без совпадения в карточке', $leader, '+79990001001');
        $other = $this->createContact('Другой контакт', $leader, '+79990001002');

        $this->addComment($matched, $manager, 'xmcommenttoken в заметке');
        $this->addComment($other, $manager, 'Обычный комментарий');

        $this->actingAs($manager);

        Livewire::test(ListManagement::class)
            ->searchTable('xmcommenttoken')
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_my_contacts_search_finds_only_own_contact_by_comment(): void
    {
        $leader = $this->createUser('leader');
        $otherLeader = $this->createUser('leader');

        $own = $this->createContact('Свой контакт', $leader, '+79990001003');
        $foreign = $this->createContact('Чужой контакт', $otherLeader, '+79990001004');

        $this->addComment($own, $leader, 'xmowncomment');
        $this->addComment($foreign, $otherLeader, 'xmowncomment');

        $this->actingAs($leader);

        Livewire::test(ListContacts::class)
            ->searchTable('xmowncomment')
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create([
            'is_approved' => true,
            'has_dashboard_access' => true,
            'can_use_contact_filters' => false,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createContact(string $name, User $leader, string $phone): Contact
    {
        return Contact::withoutEvents(function () use ($name, $leader, $phone): Contact {
            return Contact::create([
                'full_name' => $name,
                'phone' => $phone,
                'status' => ContactStatus::ASSIGNED,
                'assigned_leader_id' => $leader->id,
                'created_by' => $leader->id,
            ]);
        });
    }

    private function addComment(Contact $contact, User $author, string $comment): void
    {
        $contact->comments()->create([
            'user_id' => $author->id,
            'comment' => $comment,
        ]);
    }
}
