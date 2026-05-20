<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use PHPUnit\Framework\TestCase;

class ContactStatusTest extends TestCase
{
    public function test_final_status_can_reopen_or_switch_result(): void
    {
        $this->assertSame(
            [ContactStatus::NOT_PROCESSED, ContactStatus::FAILED],
            ContactStatus::SUCCESS->allowedTransitions(),
        );

        $this->assertSame(
            [ContactStatus::NOT_PROCESSED, ContactStatus::SUCCESS],
            ContactStatus::FAILED->allowedTransitions(),
        );

        $this->assertTrue(ContactStatus::SUCCESS->canTransitionTo(ContactStatus::FAILED));
        $this->assertTrue(ContactStatus::FAILED->canTransitionTo(ContactStatus::NOT_PROCESSED));
        $this->assertFalse(ContactStatus::SUCCESS->canTransitionTo(ContactStatus::ASSIGNED));
    }

    public function test_manager_can_return_final_contact_to_assigned(): void
    {
        $this->assertContains(
            ContactStatus::ASSIGNED,
            ContactStatus::FAILED->allowedTransitions(forManager: true),
        );

        $this->assertTrue(
            ContactStatus::SUCCESS->canTransitionTo(ContactStatus::ASSIGNED, forManager: true),
        );
    }

    public function test_system_can_mark_assigned_as_overdue(): void
    {
        $this->assertTrue(
            ContactStatus::ASSIGNED->canTransitionTo(ContactStatus::OVERDUE, system: true),
        );
    }
}
