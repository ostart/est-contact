<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource\Pages\ViewContact;
use App\Filament\Resources\ManagementResource\Pages\CreateManagement;
use App\Filament\Resources\ManagementResource\Pages\EditManagement;
use App\Filament\Support\ContactInfoCopy;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

class ContactInfoCopyActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_view_contact_copies_name_phone_and_district(): void
    {
        $leader = $this->createUser('leader');
        $contact = $this->createContact($leader);

        $this->actingAs($leader);

        Livewire::test(ViewContact::class, [
            'record' => $contact->getKey(),
        ])
            ->assertSee('Скопировать')
            ->assertSee($this->clipboardPayload($contact), false);
    }

    public function test_edit_contact_copies_name_phone_and_district(): void
    {
        $manager = $this->createUser('manager');
        $contact = $this->createContact($manager);

        $this->actingAs($manager);

        Livewire::test(EditManagement::class, [
            'record' => $contact->getKey(),
        ])
            ->assertSee('Скопировать')
            ->assertSee($this->clipboardPayload($contact), false)
            ->assertSee('wire:model="data.full_name"', false)
            ->assertSee('wire:model="data.phone"', false)
            ->assertSee('wire:model="data.district"', false);
    }

    public function test_create_contact_does_not_show_copy_button(): void
    {
        $manager = $this->createUser('manager');

        $this->actingAs($manager);

        Livewire::test(CreateManagement::class)
            ->assertDontSee('Скопировать');
    }

    private function clipboardPayload(Contact $contact): string
    {
        return (string) Js::from(ContactInfoCopy::format(
            $contact->full_name,
            $contact->phone,
            $contact->district,
        ));
    }

    private function createContact(User $creator): Contact
    {
        return Contact::create([
            'full_name' => 'Иванов Иван',
            'phone' => '+79990001122',
            'district' => 'Центральный',
            'status' => ContactStatus::NOT_PROCESSED,
            'created_by' => $creator->id,
        ]);
    }

    private function createUser(string $role): User
    {
        $user = User::factory()->create([
            'is_approved' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
