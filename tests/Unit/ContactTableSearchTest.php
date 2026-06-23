<?php

namespace Tests\Unit;

use App\Filament\Support\ContactTableSearch;
use App\Models\Contact;
use Tests\TestCase;

class ContactTableSearchTest extends TestCase
{
    public function test_apply_like_qualifies_column_for_joined_queries(): void
    {
        $query = Contact::query()->defaultTableOrder();

        ContactTableSearch::applyLike($query, 'email', 'test@example.com');

        $sql = $query->toSql();

        $this->assertStringContainsString('like ?', $sql);
        $this->assertMatchesRegularExpression('/contacts(?:["`.]|\\.)+email(?:["`.])?\s+like \?/i', $sql);
    }

    public function test_apply_status_search_matches_russian_labels(): void
    {
        $query = Contact::query();

        ContactTableSearch::applyStatusSearch($query, 'в работе');

        $this->assertStringContainsString('"status" in (?)', $query->toSql());
        $this->assertSame(['in_progress'], $query->getBindings());
    }

    public function test_apply_status_search_with_unknown_label_returns_no_rows(): void
    {
        $query = Contact::query();

        ContactTableSearch::applyStatusSearch($query, 'несуществующий');

        $this->assertStringContainsString('0 = 1', $query->toSql());
    }
}
