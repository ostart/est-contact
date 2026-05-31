<?php

namespace Tests\Unit;

use App\Enums\CommentsContext;
use PHPUnit\Framework\TestCase;

class CommentsContextTest extends TestCase
{
    public function test_leader_view_allows_add_only(): void
    {
        $context = CommentsContext::LeaderView;

        $this->assertTrue($context->canAdd());
        $this->assertFalse($context->canEdit());
        $this->assertFalse($context->canDelete());
    }

    public function test_manager_edit_allows_all_operations(): void
    {
        $context = CommentsContext::ManagerEdit;

        $this->assertTrue($context->canAdd());
        $this->assertTrue($context->canEdit());
        $this->assertTrue($context->canDelete());
    }

    public function test_manager_view_allows_view_only(): void
    {
        $context = CommentsContext::ManagerView;

        $this->assertFalse($context->canAdd());
        $this->assertFalse($context->canEdit());
        $this->assertFalse($context->canDelete());
    }
}
