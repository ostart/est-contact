<?php

namespace Tests\Feature;

use App\Enums\ContactStatus;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Filament\Widgets\ContactsStatsWidget;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class ContactsStatsWidgetFilterNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_status_tiles_link_to_filtered_contacts_when_leader_can_use_filters(): void
    {
        $leader = $this->createUser('leader', canUseContactFilters: true);
        $this->actingAs($leader);

        $urlsByLabel = $this->statUrlsByLabel();

        $this->assertNull($urlsByLabel['Всего контактов']);
        $this->assertNull($urlsByLabel['Новых за неделю']);

        foreach ($this->statusTileLabels() as $label => $status) {
            $this->assertSame(
                $this->expectedFilteredContactsUrl($status),
                $urlsByLabel[$label],
            );
        }
    }

    public function test_status_tiles_are_not_links_when_leader_cannot_use_filters(): void
    {
        $leader = $this->createUser('leader', canUseContactFilters: false);
        $this->actingAs($leader);

        foreach ($this->statUrlsByLabel() as $url) {
            $this->assertNull($url);
        }
    }

    public function test_status_tiles_link_to_filtered_contacts_for_manager_without_filter_flag(): void
    {
        $manager = $this->createUser('manager', canUseContactFilters: false);
        $this->actingAs($manager);

        $urlsByLabel = $this->statUrlsByLabel();

        $this->assertSame(
            $this->expectedFilteredContactsUrl(ContactStatus::FROZEN),
            $urlsByLabel[ContactStatus::FROZEN->getLabel()],
        );
    }

    public function test_contacts_list_shows_only_contacts_with_status_from_query_filters(): void
    {
        $manager = $this->createUser('manager');
        $otherLeader = $this->createUser('leader');

        $frozen = $this->createContact('Frozen Contact', ContactStatus::FROZEN, $otherLeader);
        $this->createContact('Assigned Contact', ContactStatus::ASSIGNED, $otherLeader);

        $this->actingAs($manager);

        Livewire::test(ListContacts::class, [
            'tableFilters' => [
                'status' => [
                    'value' => ContactStatus::FROZEN->value,
                ],
                'my_contacts' => [
                    'isActive' => false,
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$frozen])
            ->assertCanNotSeeTableRecords(
                Contact::query()->whereKeyNot($frozen->getKey())->get(),
            );
    }

    /**
     * @return array<string, string|null>
     */
    private function statUrlsByLabel(): array
    {
        $widget = Livewire::test(ContactsStatsWidget::class)->instance();
        $method = new ReflectionMethod(ContactsStatsWidget::class, 'getStats');
        $stats = $method->invoke($widget);

        $urls = [];

        foreach ($stats as $stat) {
            $this->assertInstanceOf(Stat::class, $stat);
            $urls[$stat->getLabel()] = $stat->getUrl();
        }

        return $urls;
    }

    /**
     * @return array<string, ContactStatus>
     */
    private function statusTileLabels(): array
    {
        return [
            'Ожидают обработки' => ContactStatus::NOT_PROCESSED,
            ContactStatus::ASSIGNED->getLabel() => ContactStatus::ASSIGNED,
            ContactStatus::IN_PROGRESS->getLabel() => ContactStatus::IN_PROGRESS,
            ContactStatus::FROZEN->getLabel() => ContactStatus::FROZEN,
            'Просрочено' => ContactStatus::OVERDUE,
            ContactStatus::SUCCESS->getLabel() => ContactStatus::SUCCESS,
            ContactStatus::FAILED->getLabel() => ContactStatus::FAILED,
        ];
    }

    private function expectedFilteredContactsUrl(ContactStatus $status): string
    {
        return ContactResource::getUrl('index', [
            'filters' => [
                'status' => [
                    'value' => $status->value,
                ],
                'my_contacts' => [
                    'isActive' => false,
                ],
            ],
        ]);
    }

    private function createUser(string $role, bool $canUseContactFilters = false): User
    {
        $user = User::factory()->create([
            'is_approved' => true,
            'has_dashboard_access' => true,
            'can_use_contact_filters' => $canUseContactFilters,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createContact(string $name, ContactStatus $status, User $leader): Contact
    {
        return Contact::withoutEvents(function () use ($name, $status, $leader): Contact {
            return Contact::create([
                'full_name' => $name,
                'phone' => fake()->unique()->numerify('+7999#######'),
                'status' => $status,
                'frozen_until' => $status === ContactStatus::FROZEN ? now()->addDay() : null,
                'assigned_leader_id' => $leader->id,
                'created_by' => $leader->id,
            ]);
        });
    }
}
