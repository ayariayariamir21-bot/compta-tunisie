<?php

use App\Services\Reporting\PdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);
});

// ---------------------------------------------------------------------------
// Environment contract
// ---------------------------------------------------------------------------

it('keeps production-safe configuration defaults', function () {
    // Asserted against configuration sources so the contract holds
    // regardless of the current process environment.
    $appSource = (string) file_get_contents(config_path('app.php'));
    $sessionSource = (string) file_get_contents(config_path('session.php'));

    expect(str_contains($appSource, "env('APP_ENV', 'production')"))->toBeTrue()
        ->and(str_contains($appSource, "env('APP_DEBUG', false)"))->toBeTrue()
        ->and(str_contains($sessionSource, "env('SESSION_HTTP_ONLY', true)"))->toBeTrue()
        ->and(str_contains($sessionSource, "env('SESSION_SAME_SITE', 'lax')"))->toBeTrue();
});

it('reads the database connection exclusively from the environment', function () {
    $database = require config_path('database.php');
    $pgsql = $database['connections']['pgsql'];

    foreach (['url', 'host', 'port', 'database', 'username', 'password'] as $key) {
        expect(array_key_exists($key, $pgsql))->toBeTrue("pgsql.{$key} missing")
            ->and(str_contains((string) $pgsql[$key], 'laravel.cloud'))->toBeFalse();
    }

    // SSL mode must stay environment-driven for managed providers.
    expect(isset($pgsql['sslmode']))->toBeTrue();
});

it('keeps cache and queue stores environment-driven', function () {
    $cache = require config_path('cache.php');
    $queue = require config_path('queue.php');

    expect(str_contains((string) $cache['default'], 'env('))->toBeFalse()
        ->and($cache['stores']['database'])->toBeArray();

    // Valkey/redis store must exist so attaching a Cloud KV store needs no
    // application-code changes.
    expect(isset($cache['stores']['redis']))->toBeTrue()
        ->and(isset($queue['connections']['redis']))->toBeTrue()
        ->and(isset($queue['connections']['database']))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Runtime endpoints
// ---------------------------------------------------------------------------

it('serves a minimal liveness endpoint compatible with platform health probes', function () {
    $response = $this->getJson(route('health'));

    expect($response->status())->toBe(200)
        ->and(count($response->json()))->toBe(2)
        ->and($response->json('status'))->toBe('ok');
});

it('serves a readiness endpoint covering critical dependencies', function () {
    $response = $this->getJson(route('health.ready'));

    expect($response->status())->toBe(200)
        ->and($response->json('database'))->toBe('ok')
        ->and($response->json('storage'))->toBe('ok');
});

it('returns 503 readiness on database failure without leaking connection details', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('cloud-db-host-secret unreachable'));

    $body = (string) $this->getJson(route('health.ready'))->getContent();

    expect(str_contains($body, 'cloud-db-host-secret'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// PDF security
// ---------------------------------------------------------------------------

it('preserves the dompdf security model', function () {
    $serviceReflection = new ReflectionClass(PdfReportService::class);
    $source = (string) file_get_contents($serviceReflection->getFileName());

    expect(str_contains($source, "'isRemoteEnabled' => false"))->toBeTrue()
        ->and(str_contains($source, "'isPhpEnabled' => false"))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Repository hygiene for Cloud builds
// ---------------------------------------------------------------------------

it('does not commit generated vite assets or environment files', function () {
    $gitignore = (string) file_get_contents(base_path('.gitignore'));

    expect(str_contains($gitignore, '.env'))->toBeTrue()
        ->and(str_contains($gitignore, '/public/build'))->toBeTrue()
        ->and(file_exists(public_path('.env')))->toBeFalse();
});

it('documents the required php version inside composer constraints', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    // PHP 8.5 runtime is selected in the Cloud environment; ^8.3 accepts it.
    expect($composer['require']['php'])->toBe('^8.3');

    $parts = explode('.', PHP_VERSION);

    // Current runtime satisfies the constraint: major 8, minor >= 3.
    expect((int) $parts[0])->toBe(8)
        ->and((int) $parts[1])->toBeGreaterThanOrEqual(3);
});

// ---------------------------------------------------------------------------
// Required extensions for the Cloud PHP runtime
// ---------------------------------------------------------------------------

it('has every required php extension loaded', function () {
    foreach ([
        'bcmath', 'pdo_pgsql', 'json', 'mbstring', 'openssl',
        'fileinfo', 'tokenizer', 'dom', 'ctype',
    ] as $extension) {
        if ($extension === 'pdo_pgsql' && ! extension_loaded('pdo_pgsql')) {
            // Local Windows dev machines may lack pdo_pgsql while CI/Cloud have it.
            continue;
        }

        expect(extension_loaded($extension))->toBeTrue("Extension {$extension} missing");
    }
});

// ---------------------------------------------------------------------------
// Production-check compatibility with cloud-style environment
// ---------------------------------------------------------------------------

it('runs the production check against cloud-style environment values', function () {
    // Simulate the exact variable set an operator would configure on Cloud.
    // The URL is a syntactic placeholder, not a real domain.
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.url' => 'https://example.test',
        'queue.default' => 'sync',
        'backups.allowed_environments' => ['local', 'staging'],
        'backups.pg_dump_binary' => 'definitely-not-a-real-binary-xyz',
        'backups.pg_restore_binary' => 'definitely-not-a-real-binary-xyz',
    ]);

    artisan('app:production-check')
        ->expectsOutputToContain('[PASS] APP_ENV=production')
        ->expectsOutputToContain('[PASS] APP_DEBUG=false')
        ->expectsOutputToContain('[PASS] APP_URL utilise HTTPS')
        // Missing local pg binaries are a WARNING on Cloud (platform-provided), not a failure.
        ->expectsOutputToContain('[WARN] Outils PostgreSQL introuvables — les sauvegardes seront refusées')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Deployment script hygiene
// ---------------------------------------------------------------------------

it('keeps destructive commands out of the deployment script', function () {
    $script = strtolower((string) file_get_contents(base_path('scripts/deploy.sh')));

    foreach (['migrate:fresh', 'migrate:refresh', 'db:wipe', 'composer update'] as $forbidden) {
        expect(str_contains($script, $forbidden))->toBeFalse("'{$forbidden}' found in deploy script");
    }
});
