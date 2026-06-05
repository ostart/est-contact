<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserTablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTablePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_is_stored_per_user_and_table_key(): void
    {
        $user = User::factory()->create();

        UserTablePreference::query()->create([
            'user_id' => $user->id,
            'table_key' => 'contacts',
            'columns' => [
                [
                    'type' => 'column',
                    'name' => 'district',
                    'label' => 'Район',
                    'isHidden' => false,
                    'isToggled' => true,
                    'isToggleable' => true,
                    'isToggledHiddenByDefault' => false,
                ],
            ],
            'has_reordered_columns' => true,
            'sorts' => [
                ['column' => 'district', 'direction' => 'asc'],
                ['column' => 'status', 'direction' => 'desc'],
            ],
        ]);

        $preference = UserTablePreference::query()
            ->where('user_id', $user->id)
            ->where('table_key', 'contacts')
            ->first();

        $this->assertNotNull($preference);
        $this->assertTrue($preference->has_reordered_columns);
        $this->assertSame('district', $preference->sorts[0]['column']);
        $this->assertSame('asc', $preference->sorts[0]['direction']);
        $this->assertSame('status', $preference->sorts[1]['column']);
    }

    public function test_users_have_separate_preferences_for_same_table_key(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        UserTablePreference::query()->create([
            'user_id' => $firstUser->id,
            'table_key' => 'management',
            'sorts' => [['column' => 'full_name', 'direction' => 'asc']],
        ]);

        UserTablePreference::query()->create([
            'user_id' => $secondUser->id,
            'table_key' => 'management',
            'sorts' => [['column' => 'updated_at', 'direction' => 'desc']],
        ]);

        $this->assertSame('full_name', UserTablePreference::query()->where('user_id', $firstUser->id)->value('sorts')[0]['column']);
        $this->assertSame('updated_at', UserTablePreference::query()->where('user_id', $secondUser->id)->value('sorts')[0]['column']);
    }
}
