<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use PHPUnit\Framework\TestCase;

class ContactStatusTest extends TestCase
{
    public function test_final_status_leader_can_reopen_to_in_progress_or_switch_result(): void
    {
        $this->assertSame(
            [ContactStatus::IN_PROGRESS, ContactStatus::FAILED],
            ContactStatus::SUCCESS->allowedTransitions(),
        );

        $this->assertSame(
            [ContactStatus::IN_PROGRESS, ContactStatus::SUCCESS],
            ContactStatus::FAILED->allowedTransitions(),
        );

        $this->assertTrue(ContactStatus::SUCCESS->canTransitionTo(ContactStatus::FAILED));
        $this->assertTrue(ContactStatus::SUCCESS->canTransitionTo(ContactStatus::IN_PROGRESS));
        $this->assertFalse(ContactStatus::FAILED->canTransitionTo(ContactStatus::NOT_PROCESSED));
        $this->assertFalse(ContactStatus::SUCCESS->canTransitionTo(ContactStatus::ASSIGNED));
    }

    public function test_not_processed_transition_only_for_manager(): void
    {
        $this->assertTrue(
            ContactStatus::FAILED->canTransitionTo(ContactStatus::NOT_PROCESSED, forManager: true),
        );
        $this->assertTrue(
            ContactStatus::IN_PROGRESS->canTransitionTo(ContactStatus::NOT_PROCESSED, forManager: true),
        );
        $this->assertFalse(
            ContactStatus::IN_PROGRESS->canTransitionTo(ContactStatus::NOT_PROCESSED, forManager: false),
        );
        $this->assertFalse(
            ContactStatus::ASSIGNED->canTransitionTo(ContactStatus::NOT_PROCESSED, forManager: false),
        );
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

    public function test_system_can_mark_queue_statuses_as_overdue(): void
    {
        $this->assertTrue(
            ContactStatus::ASSIGNED->canTransitionTo(ContactStatus::OVERDUE, system: true),
        );

        $this->assertTrue(
            ContactStatus::IN_PROGRESS->canTransitionTo(ContactStatus::OVERDUE, system: true),
        );
    }

    public function test_assigned_moves_to_in_progress_and_in_progress_can_freeze(): void
    {
        $this->assertSame(
            [ContactStatus::NOT_PROCESSED, ContactStatus::IN_PROGRESS],
            ContactStatus::ASSIGNED->allowedTransitions(forManager: true),
        );

        $this->assertSame(
            [ContactStatus::IN_PROGRESS],
            ContactStatus::ASSIGNED->allowedTransitions(),
        );

        $this->assertContains(ContactStatus::FROZEN, ContactStatus::IN_PROGRESS->allowedTransitions());
        $this->assertFalse(ContactStatus::ASSIGNED->canTransitionTo(ContactStatus::FROZEN));
        $this->assertTrue(ContactStatus::IN_PROGRESS->canTransitionTo(ContactStatus::FROZEN));
    }

    public function test_frozen_can_return_to_assigned_or_in_progress(): void
    {
        $this->assertSame(
            [ContactStatus::ASSIGNED, ContactStatus::IN_PROGRESS],
            ContactStatus::FROZEN->allowedTransitions(forManager: true),
        );

        $this->assertSame(
            [ContactStatus::IN_PROGRESS],
            ContactStatus::FROZEN->allowedTransitions(),
        );

        $this->assertTrue(ContactStatus::FROZEN->canTransitionTo(ContactStatus::IN_PROGRESS));
        $this->assertFalse(ContactStatus::FROZEN->canTransitionTo(ContactStatus::ASSIGNED));
        $this->assertTrue(
            ContactStatus::FROZEN->canTransitionTo(ContactStatus::ASSIGNED, forManager: true),
        );
        $this->assertTrue(
            ContactStatus::FROZEN->canTransitionTo(ContactStatus::IN_PROGRESS, system: true),
        );
        $this->assertFalse(ContactStatus::FROZEN->canTransitionTo(ContactStatus::SUCCESS, forManager: true));
        $this->assertFalse(ContactStatus::OVERDUE->canTransitionTo(ContactStatus::FROZEN));
    }

    public function test_frozen_unfreeze_label(): void
    {
        $this->assertSame(
            'Вернуть в работу',
            ContactStatus::IN_PROGRESS->getTransitionLabel(ContactStatus::FROZEN),
        );
    }

    public function test_status_labels_and_colors(): void
    {
        $this->assertSame('Назначено', ContactStatus::ASSIGNED->getLabel());
        $this->assertSame('В работе', ContactStatus::IN_PROGRESS->getLabel());
        $this->assertSame('purple', ContactStatus::FROZEN->getColor());
        $this->assertSame('info', ContactStatus::ASSIGNED->getColor());
        $this->assertSame('azure', ContactStatus::IN_PROGRESS->getColor());
        $this->assertNotSame(ContactStatus::FROZEN->getColor(), ContactStatus::ASSIGNED->getColor());
    }

    public function test_not_processed_can_enter_queue_or_work(): void
    {
        $this->assertSame(
            [ContactStatus::ASSIGNED, ContactStatus::IN_PROGRESS],
            ContactStatus::NOT_PROCESSED->allowedTransitions(forManager: true),
        );

        $this->assertSame(
            [ContactStatus::IN_PROGRESS],
            ContactStatus::NOT_PROCESSED->allowedTransitions(),
        );
    }

    public function test_processing_queue_values(): void
    {
        $this->assertSame(
            ['assigned', 'in_progress'],
            ContactStatus::processingQueueValues(),
        );
    }

    public function test_default_table_sort_group_order(): void
    {
        $this->assertSame(1, ContactStatus::defaultTableSortGroup(ContactStatus::NOT_PROCESSED));
        $this->assertSame(2, ContactStatus::defaultTableSortGroup(ContactStatus::ASSIGNED));
        $this->assertSame(3, ContactStatus::defaultTableSortGroup(ContactStatus::IN_PROGRESS));
        $this->assertSame(4, ContactStatus::defaultTableSortGroup(ContactStatus::OVERDUE));
        $this->assertSame(5, ContactStatus::defaultTableSortGroup(ContactStatus::FROZEN));
        $this->assertSame(6, ContactStatus::defaultTableSortGroup(ContactStatus::SUCCESS));
        $this->assertSame(7, ContactStatus::defaultTableSortGroup(ContactStatus::FAILED));
        $this->assertSame(8, ContactStatus::defaultTableSortGroup(null));

        $statuses = [
            ContactStatus::NOT_PROCESSED,
            ContactStatus::ASSIGNED,
            ContactStatus::IN_PROGRESS,
            ContactStatus::OVERDUE,
            ContactStatus::FROZEN,
            ContactStatus::SUCCESS,
            ContactStatus::FAILED,
        ];

        for ($i = 1; $i < count($statuses); $i++) {
            $this->assertLessThan(
                ContactStatus::defaultTableSortGroup($statuses[$i]),
                ContactStatus::defaultTableSortGroup($statuses[$i - 1]),
            );
        }
    }
}
