<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->fail('Tests must run against sqlite only. Check phpunit.xml DB_* settings.');
        }

        if (config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Tests must use sqlite :memory: database. Never run tests against a real database file or server.');
        }
    }
}
