<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

/**
 * Feature tests use RefreshDatabase on sqlite :memory: only.
 * The guard must live on this class (not the parent) so it overrides the trait method.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Abort before RefreshDatabase runs if tests are not on sqlite :memory:.
     * Prevents accidentally wiping the local MySQL database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Tests must use sqlite :memory: to protect MySQL. Got [{$connection}] / [{$database}]. "
                .'Run `php artisan config:clear` and avoid `config:cache` on local.'
            );
        }
    }
}
