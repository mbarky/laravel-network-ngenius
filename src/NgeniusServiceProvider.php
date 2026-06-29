<?php

declare(strict_types=1);

namespace mbarky\Ngenius;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use mbarky\Ngenius\Client\NgeniusClient;
use mbarky\Ngenius\Client\TokenManager;
use mbarky\Ngenius\Console\HealthCheckCommand;
use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\Contracts\PaymentRepositoryContract;
use mbarky\Ngenius\Repositories\NgeniusPaymentRepository;
use mbarky\Ngenius\Services\OrderService;
use mbarky\Ngenius\Services\PaymentService;
use mbarky\Ngenius\Services\WebhookService;

class NgeniusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ngenius.php',
            'ngenius'
        );

        $this->app->singleton(TokenManager::class, function (Application $app) {
            $rawStore = config('ngenius.cache.store');
            $store = is_string($rawStore) ? $rawStore : null;

            return new TokenManager(
                cache: $app->make(CacheManager::class)->store($store),
                httpClient: $app->make(HttpFactory::class),
            );
        });

        $this->app->singleton(NgeniusClientContract::class, NgeniusClient::class);
        $this->app->singleton(NgeniusClient::class, function (Application $app) {
            return new NgeniusClient(
                tokenManager: $app->make(TokenManager::class),
            );
        });

        $this->app->singleton(OrderService::class, function (Application $app) {
            return new OrderService(
                client: $app->make(NgeniusClientContract::class),
            );
        });

        $this->app->singleton(WebhookService::class, function (Application $app) {
            return new WebhookService(
                client: $app->make(NgeniusClientContract::class),
                repository: config('ngenius.persist_transactions')
                    ? $app->make(PaymentRepositoryContract::class)
                    : null,
            );
        });

        $this->app->singleton(PaymentService::class, function (Application $app) {
            return new PaymentService(
                orderService: $app->make(OrderService::class),
                repository: config('ngenius.persist_transactions')
                    ? $app->make(PaymentRepositoryContract::class)
                    : null,
            );
        });

        if (config('ngenius.persist_transactions', true)) {
            $this->app->singleton(PaymentRepositoryContract::class, NgeniusPaymentRepository::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ngenius.php' => config_path('ngenius.php'),
            ], 'ngenius-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'ngenius-migrations');

            $this->commands([
                HealthCheckCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('ngenius.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
