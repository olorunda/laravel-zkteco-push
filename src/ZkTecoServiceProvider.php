<?php

namespace ZkTeco\Push;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use ZkTeco\Push\Storage\ZkTecoStorageInterface;
use ZkTeco\Push\Storage\ZkTecoPdoStorage;
use ZkTeco\Push\Http\Controllers\ZkTecoPushController;

class ZkTecoServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings in container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/zkteco-push.php', 'zkteco-push');

        // Bind Storage Interface using Laravel DB PDO
        $this->app->singleton(ZkTecoStorageInterface::class, function ($app) {
            $pdo = DB::connection()->getPdo();
            $tablePrefix = config('zkteco-push.table_prefix', 'zkteco_');
            return new ZkTecoPdoStorage($pdo, $tablePrefix);
        });

        // Bind Config Manager
        $this->app->singleton(ZkTecoConfigManager::class, function ($app) {
            return new ZkTecoConfigManager(config_path('zkteco-push.php'));
        });

        // Bind Push Middleware
        $this->app->singleton(ZkTecoPushMiddleware::class, function ($app) {
            return new ZkTecoPushMiddleware(
                $app->make(ZkTecoStorageInterface::class),
                $app->make(ZkTecoConfigManager::class)
            );
        });

        // Facade alias binding
        $this->app->alias(ZkTecoPushMiddleware::class, 'zkteco-push');
    }

    /**
     * Bootstrap package services, routes, and migrations.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish Config
            $this->publishes([
                __DIR__ . '/../config/zkteco-push.php' => config_path('zkteco-push.php'),
            ], 'zkteco-config');

            // Publish Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'zkteco-migrations');

            // Load Migrations
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->registerRoutes();
    }

    /**
     * Register package routes for hardware ADMS, REST JSON API, and Admin UI.
     */
    protected function registerRoutes(): void
    {
        $devicePrefix = config('zkteco-push.device_route_prefix', 'iclock');
        $apiPrefix = config('zkteco-push.api_route_prefix', 'api/zkteco');
        $adminPrefix = config('zkteco-push.admin_route_prefix', 'zkteco/admin');

        $deviceMiddleware = $this->parseMiddleware(config('zkteco-push.device_middleware'), ['web']);
        $apiMiddleware = $this->parseMiddleware(config('zkteco-push.api_middleware'), ['api']);
        $adminMiddleware = $this->parseMiddleware(config('zkteco-push.admin_middleware'), ['web']);

        // 1. ZKTeco Device ADMS Protocol Routes (/iclock/cdata, /iclock/getrequest, etc.)
        Route::group([
            'prefix' => $devicePrefix,
            'middleware' => $deviceMiddleware,
        ], function () {
            Route::any('{endpoint}', [ZkTecoPushController::class, 'handleDevice'])
                ->where('endpoint', '.*');
        });

        // 2. External REST JSON API Routes (/api/zkteco/*)
        Route::group([
            'prefix' => $apiPrefix,
            'middleware' => $apiMiddleware,
        ], function () {
            Route::any('{endpoint?}', [ZkTecoPushController::class, 'handleApi'])
                ->where('endpoint', '.*');
        });

        // 3. Admin UI Configuration Dashboard Routes
        if (config('zkteco-push.enable_admin_ui', true)) {
            Route::group([
                'prefix' => $adminPrefix,
                'middleware' => $adminMiddleware,
            ], function () {
                Route::any('{endpoint?}', [ZkTecoPushController::class, 'handleAdmin'])
                    ->where('endpoint', '.*');
            });
        }
    }

    /**
     * Parse middleware input (string or array) into normalized array.
     */
    protected function parseMiddleware(mixed $middleware, array $default = ['web']): array
    {
        if (is_string($middleware)) {
            $middleware = array_map('trim', explode(',', $middleware));
        }

        $middleware = array_filter((array) ($middleware ?? $default));

        return !empty($middleware) ? array_values($middleware) : $default;
    }
}
