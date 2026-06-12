<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::assertSafeTestingDatabase();
    }

    public function createApplication()
    {
        self::enforceTestingEnvironmentVariables();

        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        self::assertSafeTestingDatabase();

        return $app;
    }

    protected function beforeRefreshingDatabase()
    {
        self::assertSafeTestingDatabase();
    }

    /**
     * @param  array<string, string>  $overrides
     */
    public static function enforceTestingEnvironmentVariables(array $overrides = []): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            ...$overrides,
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function assertSafeTestingDatabase(): void
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return;
        }

        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Tests refused to run: unsafe database [{$default}:{$database}]. "
                . 'Tests may only use sqlite :memory:. '
                . 'Run from project root via: composer test'
            );
        }
    }
}
