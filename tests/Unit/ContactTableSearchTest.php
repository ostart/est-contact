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

        $this->assertStringContainsString('`contacts`.`email` like ?', $query->toSql());
    }
}
