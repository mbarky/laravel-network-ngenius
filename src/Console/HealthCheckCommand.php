<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use mbarky\Ngenius\Client\TokenManager;
use mbarky\Ngenius\Enums\Environment;

class HealthCheckCommand extends Command
{
    protected $signature = 'ngenius:health';

    protected $description = 'Verify N-Genius configuration, connectivity, and credentials.';

    public function handle(TokenManager $tokenManager): int
    {
        $this->info('N-Genius Health Check');
        $this->line('─────────────────────');

        $env = Environment::fromConfig();
        $passed = true;

        // 1. Environment
        $this->check('Environment', $env->value, true);

        // 2. API key present
        $apiKey = $env->apiKey();
        $keyOk = $apiKey !== '';
        $passed = $this->check('API key present', $keyOk ? 'yes' : 'MISSING', $keyOk);

        // 3. Outlet reference present
        $outlet = $env->outletReference();
        $outletOk = $outlet !== '';
        $passed = $this->check('Outlet reference present', $outletOk ? 'yes' : 'MISSING', $outletOk) && $passed;

        // 4. Base URL reachable (HEAD request, no auth needed)
        $baseUrl = $env->baseUrl();
        $reachable = false;

        try {
            $response = Http::timeout(5)->head($baseUrl);
            $reachable = $response->status() < 500;
        } catch (\Throwable) {
            $reachable = false;
        }

        $passed = $this->check("Base URL reachable ({$baseUrl})", $reachable ? 'yes' : 'UNREACHABLE', $reachable) && $passed;

        // 5. Token fetch
        $tokenOk = false;

        if ($keyOk) {
            try {
                $tokenManager->forgetToken();
                $token = $tokenManager->getToken();
                $tokenOk = strlen($token) > 10;
            } catch (\Throwable $e) {
                $this->error('   Token error: '.$e->getMessage());
            }
        }

        $passed = $this->check('Access token fetch', $tokenOk ? 'OK' : 'FAILED', $tokenOk) && $passed;

        // 6. Webhook route
        $webhookEnabled = (bool) config('ngenius.webhook.enabled');
        $webhookRouteOk = $webhookEnabled && Route::has('ngenius.webhook');
        $this->check(
            'Webhook route registered',
            ! $webhookEnabled ? 'disabled' : ($webhookRouteOk ? 'yes' : 'NOT FOUND'),
            ! $webhookEnabled || $webhookRouteOk
        );

        $this->line('─────────────────────');
        $this->line($passed ? '<info>All checks passed.</info>' : '<error>Some checks failed — review above.</error>');

        return $passed ? Command::SUCCESS : Command::FAILURE;
    }

    private function check(string $label, string $value, bool $ok): bool
    {
        $status = $ok ? '<info>✓</info>' : '<error>✗</error>';
        $this->line("  {$status} {$label}: {$value}");

        return $ok;
    }
}
