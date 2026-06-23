<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactOverdueAtSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_by_overdue_at_sorts_relevant_statuses_and_puts_nulls_last(): void
    {
        $user = User::factory()->create();

        $earliest = Contact::withoutEvents(function () use ($user): Contact {
            $contact = Contact::create([
                'full_name' => 'Earliest',
                'phone' => '+79990001101',
                'status' => ContactStatus::ASSIGNED,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);

            $contact->forceFill([
                'overdue_at' => Carbon::parse('2026-03-01 12:00:00', 'UTC'),
            ])->saveQuietly();

            return $contact;
        });

        $latest = Contact::withoutEvents(function () use ($user): Contact {
            $contact = Contact::create([
                'full_name' => 'Latest',
                'phone' => '+79990001102',
                'status' => ContactStatus::IN_PROGRESS,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);

            $contact->forceFill([
                'overdue_at' => Carbon::parse('2026-09-01 12:00:00', 'UTC'),
            ])->saveQuietly();

            return $contact;
        });

        $withoutDate = Contact::withoutEvents(function () use ($user): Contact {
            $contact = Contact::create([
                'full_name' => 'Without date',
                'phone' => '+79990001103',
                'status' => ContactStatus::SUCCESS,
                'assigned_leader_id' => $user->id,
                'created_by' => $user->id,
            ]);

            $contact->forceFill([
                'overdue_at' => Carbon::parse('2026-01-01 12:00:00', 'UTC'),
            ])->saveQuietly();

            return $contact;
        });

        $ascIds = Contact::query()->orderByOverdueAt('asc')->pluck('id')->all();

        $this->assertSame(
            [$earliest->id, $latest->id, $withoutDate->id],
            $ascIds,
        );

        $descIds = Contact::query()->orderByOverdueAt('desc')->pluck('id')->all();

        $this->assertSame(
            [$latest->id, $earliest->id, $withoutDate->id],
            $descIds,
        );
    }
}
