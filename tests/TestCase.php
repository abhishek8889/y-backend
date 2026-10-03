<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Cached config ignores phpunit.xml DB_* env and can point RefreshDatabase at MySQL.
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            unlink($cachedConfig);
        }

        parent::setUp();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
