<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignableAsContactLeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_scope_excludes_banned_leaders(): void
    {
        $active = $this->createLeader('Active Leader', banned: false);
        $banned = $this->createLeader('Banned Leader', banned: true);

        $ids = User::query()->assignableAsContactLeader()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($banned->id));
    }

    public function test_scope_excludes_unapproved_and_non_leaders(): void
    {
        $unapproved = User::factory()->create([
            'name' => 'Unapproved Leader',
            'is_approved' => false,
            'is_banned' => false,
        ]);
        $unapproved->assignRole('leader');

        $manager = User::factory()->create([
            'name' => 'Manager',
            'is_approved' => true,
            'is_banned' => false,
        ]);
        $manager->assignRole('manager');

        $ids = User::query()->assignableAsContactLeader()->pluck('id');

        $this->assertFalse($ids->contains($unapproved->id));
        $this->assertFalse($ids->contains($manager->id));
    }

    private function createLeader(string $name, bool $banned = false): User
    {
        $leader = User::factory()->create([
            'name' => $name,
            'is_approved' => true,
            'is_banned' => $banned,
        ]);
        $leader->assignRole('leader');

        return $leader;
    }
}
