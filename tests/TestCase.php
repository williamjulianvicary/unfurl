<?php

declare(strict_types=1);

namespace WilliamJulianVicary\Ogify\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;
use WilliamJulianVicary\Ogify\OgImageServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [OgImageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $app['config']->set('og-image.route.enabled', true);
        $app['config']->set('og-image.driver', 'cloudflare');
        $app['config']->set('og-image.drivers.cloudflare', [
            'account_id' => 'test-account-id',
            'api_token' => 'test-api-token',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
