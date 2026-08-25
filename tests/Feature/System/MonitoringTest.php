<?php

use App\Enums\HealthStatus;
use App\Livewire\System\Monitoring as MonitoringComponent;
use App\Models\Company;
use App\Models\User;
use App\Services\Security\BackupService;
use App\Services\Security\ProcessOutcome;
use App\Services\Security\ProcessRunner;
use App\Services\System\HealthCheckService;
use App\Services\System\SystemAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);

    Storage::fake('local');

    $this->admin = verifiedMonitorUser();
    $this->accountant = verifiedMonitorUser();
    $this->viewer = verifiedMonitorUser();

    $this->company = Company::create(['name' => 'Societe Monitor', 'currency' => 'TND', 'is_active' => true]);
    $this->company->users()->attach($this->admin, ['role' => 'admin', 'is_active' => true]);
    $this->company->users()->attach($this->accountant, ['role' => 'accountant', 'is_active' => true]);
    $this->company->users()->attach($this->viewer, ['role' => 'viewer', 'is_active' => true]);
});

function verifiedMonitorUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

/**
 * Seed one valid backup (dump + sidecar metadata) through the real service
 * with a fake process runner.
 */
function seedValidBackup($test): string
{
    bindFakeProcessRunner();

    return (string) app(BackupService::class)->createDatabaseBackup($test->admin, 'monitoring_test')['filename'];
}

function bindFakeProcessRunner(): void
{
    $runner = new class implements ProcessRunner
    {
        public function run(array $command, array $env = [], ?int $timeout = null): ProcessOutcome
        {
            if (in_array('--version', $command, true)) {
                return new ProcessOutcome($command, true, 0, 'pg_dump (PostgreSQL) 18.0', '');
            }

            if (in_array('--format=custom', $command, true)) {
                $index = array_search('--file', $command, true);

                if ($index !== false && isset($command[$index + 1])) {
                    file_put_contents($command[$index + 1], random_bytes(128));
                }

                return new ProcessOutcome($command, true, 0, '', '');
            }

            return new ProcessOutcome($command, true, 0, '', '');
        }
    };

    app()->bind(ProcessRunner::class, fn () => $runner);
}

// ---------------------------------------------------------------------------
// Public endpoints
// ---------------------------------------------------------------------------

it('keeps the liveness endpoint healthy and minimal', function () {
    $response = $this->getJson(route('health'));

    expect($response->status())->toBe(200)
        ->and($response->json('status'))->toBe('ok')
        ->and($response->json('database'))->toBe('ok')
        ->and(count($response->json()))->toBe(2);
});

it('reports readiness with database and storage status', function () {
    $response = $this->getJson(route('health.ready'));

    expect($response->status())->toBe(200)
        ->and($response->json('status'))->toBe('ready')
        ->and($response->json('database'))->toBe('ok')
        ->and($response->json('storage'))->toBe('ok');
});

it('returns 503 on readiness when the database is unavailable without leaking details', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('FATAL: password authentication for db prod-secret'));

    $response = $this->getJson(route('health.ready'));
    $body = (string) $response->getContent();

    expect($response->status())->toBe(503)
        ->and(str_contains($body, 'prod-secret'))->toBeFalse()
        ->and(str_contains($body, 'RuntimeException'))->toBeFalse();
});

it('never exposes secrets through health endpoints', function () {
    config()->set([
        'database.connections.pgsql.password' => 'monitoring-secret-pass',
    ]);

    foreach ([route('health'), route('health.ready')] as $url) {
        $body = (string) $this->getJson($url)->getContent();

        expect(str_contains($body, 'monitoring-secret-pass'))->toBeFalse();
    }
});

// ---------------------------------------------------------------------------
// Monitoring dashboard access
// ---------------------------------------------------------------------------

it('allows admins to open the monitoring dashboard', function () {
    actingAs($this->admin)
        ->get(route('monitoring.index'))
        ->assertOk()
        ->assertSee('Monitoring');
});

it('blocks viewers from the monitoring dashboard', function () {
    actingAs($this->viewer)
        ->get(route('monitoring.index'))
        ->assertForbidden();
});

it('blocks accountants from the monitoring dashboard per policy', function () {
    actingAs($this->accountant)
        ->get(route('monitoring.index'))
        ->assertForbidden();
});

it('blocks guests from the monitoring dashboard', function () {
    get(route('monitoring.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Health checks
// ---------------------------------------------------------------------------

it('checks database connectivity and latency', function () {
    $result = app(HealthCheckService::class)->checkDatabase();

    expect($result['status'])->toBe(HealthStatus::Ok)
        ->and($result['duration_ms'])->toBeGreaterThanOrEqual(0.0);
});

it('checks cache with a self-cleaning probe', function () {
    $service = app(HealthCheckService::class);
    $before = DB::table('cache')->count();

    $result = $service->checkCache();

    expect($result['status'])->toBe(HealthStatus::Ok)
        ->and(DB::table('cache')->count())->toBeLessThanOrEqual($before);
});

it('checks storage writability without exposing paths', function () {
    $result = app(HealthCheckService::class)->checkStorage();

    expect($result['status'])->toBe(HealthStatus::Ok)
        ->and(str_contains($result['message'], storage_path('')))->toBeFalse();
});

it('reports failed job information without payloads', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => '11111111-1111-1111-1111-111111111111',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{"displayName":"App\\\\Jobs\\\\SensitiveJob","secrets":"hidden"}',
        'exception' => "RuntimeException: something broke\nstack line",
        'failed_at' => now(),
    ]);

    $summary = app(HealthCheckService::class)->getFailedJobSummary();

    expect($summary['count'])->toBe(1)
        ->and($summary['last_failed_at'])->not->toBeNull()
        ->and(str_contains((string) $summary['last_job'], 'hidden'))->toBeFalse()
        ->and(str_contains((string) $summary['last_job'], 'something broke'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Backup health
// ---------------------------------------------------------------------------

it('reports the latest valid backup from stored metadata', function () {
    seedValidBackup($this);

    $backups = app(HealthCheckService::class)->checkBackups();

    expect($backups['status'])->toBe(HealthStatus::Ok)
        ->and($backups['count'])->toBe(1)
        ->and(is_array($backups['latest']))->toBeTrue()
        ->and(str_contains((string) ($backups['latest']['checksum_status'] ?? ''), 'ok'))->toBeTrue();
});

it('warns when no backup exists', function () {
    bindFakeProcessRunner();

    $backups = app(HealthCheckService::class)->checkBackups();

    expect($backups['status'])->toBe(HealthStatus::Warning)
        ->and($backups['latest'])->toBeNull();
});

it('warns when the latest backup is older than the configured threshold', function () {
    $filename = seedValidBackup($this);

    // Age the sidecar metadata beyond the threshold instead of waiting.
    $metadata = json_decode((string) Storage::disk('local')->get("backups/{$filename}.json"), true);
    $metadata['created_at'] = now()->subHours(72)->toIso8601String();
    Storage::disk('local')->put("backups/{$filename}.json", json_encode($metadata));

    $backups = app(HealthCheckService::class)->checkBackups();

    expect($backups['status'])->toBe(HealthStatus::Warning);
});

it('flags corrupted backup metadata as not valid', function () {
    $filename = seedValidBackup($this);

    Storage::disk('local')->put("backups/{$filename}.json", '{corrupt');

    $backups = app(HealthCheckService::class)->checkBackups();

    expect($backups['status'])->toBe(HealthStatus::Warning)
        ->and($backups['latest'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Scheduler honesty
// ---------------------------------------------------------------------------

it('honestly reports that no scheduled tasks are configured', function () {
    $scheduler = app(HealthCheckService::class)->checkScheduler();

    expect($scheduler['status'])->toBe(HealthStatus::Ok)
        ->and($scheduler['message'])->toContain('Aucune tâche planifiée');
});

// ---------------------------------------------------------------------------
// Dashboard behaviour
// ---------------------------------------------------------------------------

it('renders runtime metrics on the dashboard for admins', function () {
    actingAs($this->admin);

    Livewire::test(MonitoringComponent::class)
        ->assertOk()
        ->assertSee('Exécution')
        ->assertSee(PHP_VERSION)
        ->assertSee('Jobs échoués');
});

it('supports manual refresh without errors or duplicate notifications', function () {
    actingAs($this->admin);
    bindFakeProcessRunner();
    config(['backups.allowed_environments' => ['testing']]);

    // Produce one alert condition (no backups at all).
    $component = Livewire::test(MonitoringComponent::class)->assertOk();

    $notificationsBefore = DB::table('notifications')->count();

    $component->call('refresh');

    expect(DB::table('notifications')->count())->toBe($notificationsBefore);
});

it('does not write accounting data from health checks or the dashboard', function () {
    $invoicesBefore = DB::table('invoices')->count() + DB::table('journal_entries')->count() + DB::table('customers')->count();

    seedValidBackup($this);
    actingAs($this->admin);
    Livewire::test(MonitoringComponent::class);
    app(HealthCheckService::class)->getReadiness();

    expect(DB::table('invoices')->count() + DB::table('journal_entries')->count() + DB::table('customers')->count())
        ->toBe($invoicesBefore);
});

it('delivers system alerts to admins once per day bucket without spamming', function () {
    config(['monitoring.failed_jobs_threshold' => -1]); // force an alert condition

    // No failed-jobs rows exist; force one via insert so count > threshold.
    DB::table('failed_jobs')->insert([
        'uuid' => '22222222-2222-2222-2222-222222222222',
        'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
    ]);

    $alertService = app(SystemAlertService::class);

    expect($alertService->hasCriticalAlerts())->toBeFalse()
        ->and(count($alertService->getAlerts()))->toBeGreaterThan(0);

    $sent = $alertService->notifyAdminsOfNewAlerts($this->admin);
    $afterFirst = DB::table('notifications')->count();

    expect($sent)->toBeGreaterThan(0)
        ->and($afterFirst)->toBeGreaterThanOrEqual($sent);

    $alertService->notifyAdminsOfNewAlerts($this->admin);

    expect(DB::table('notifications')->count())->toBe($afterFirst); // deduplicated
});

it('never leaks cross-company data through monitoring output', function () {
    $otherCompany = Company::create(['name' => 'Societe Secrete XYZ', 'currency' => 'TND', 'is_active' => true]);

    actingAs($this->admin);

    $html = (string) Livewire::test(MonitoringComponent::class)->html();

    expect(str_contains($html, 'Societe Secrete XYZ'))->toBeFalse()
        ->and(str_contains($html, config('database.connections.pgsql.password', 'no-secret-set')))->toBeFalse();
});
