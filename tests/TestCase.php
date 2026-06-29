<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Tests;

use mbarky\Ngenius\Facades\Ngenius;
use mbarky\Ngenius\NgeniusServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NgeniusServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Ngenius' => Ngenius::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ngenius.environment', 'sandbox');
        $app['config']->set('ngenius.sandbox.base_url', 'https://api-gateway.sandbox.ngenius-payments.com');
        $app['config']->set('ngenius.sandbox.api_key', 'test-api-key');
        $app['config']->set('ngenius.sandbox.outlet_reference', 'test-outlet-ref');
        $app['config']->set('ngenius.cache.store', 'array');
        $app['config']->set('ngenius.cache.token_key', 'ngenius_access_token');
        $app['config']->set('ngenius.cache.ttl_seconds', 270);
        $app['config']->set('ngenius.persist_transactions', false);
        $app['config']->set('ngenius.webhook.enabled', true);
        $app['config']->set('ngenius.webhook.header_name', 'X-Ngenius-Hmac-Sha256');
        $app['config']->set('ngenius.webhook.header_value', 'test-webhook-secret');
        $app['config']->set('ngenius.logging.enabled', false);
    }
}
