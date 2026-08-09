<?php

namespace ZkTeco\Push\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ZkTeco\Push\ZkTecoServiceProvider;
use ZkTeco\Push\Facades\ZkTecoPush;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ZkTecoServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ZkTecoPush' => ZkTecoPush::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:6C8b2L990v1N8xX7kP3qR5sT7uV9wX1yZ3aB5cD7eF8=');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('zkteco-push.table_prefix', 'zkteco_');
        $app['config']->set('zkteco-push.api_key', 'zk_api_key_test_123');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
