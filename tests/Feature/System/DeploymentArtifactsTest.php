<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

const DEPLOY_SCRIPT = 'scripts/deploy.sh';

// ---------------------------------------------------------------------------
// Deployment script contract
// ---------------------------------------------------------------------------

it('ships a deployment script with the mandatory safety contract', function () {
    expect(file_exists(base_path(DEPLOY_SCRIPT)))->toBeTrue();

    $script = (string) file_get_contents(base_path(DEPLOY_SCRIPT));

    // Strict mode + immutable release identity.
    expect(str_contains($script, 'set -euo pipefail'))->toBeTrue()
        ->and(str_contains($script, 'git rev-parse --short HEAD'))->toBeTrue();

    // Production dependency discipline.
    expect(str_contains($script, 'install --no-dev --optimize-autoloader'))->toBeTrue()
        ->and(str_contains($script, 'composer update'))->toBeFalse()
        ->and(str_contains($script, 'npm ci'))->toBeTrue()
        ->and(str_contains($script, 'npm run build'))->toBeTrue();

    // Migrations only behind an explicitly verified external backup.
    expect(str_contains($script, 'migrate --force'))->toBeTrue()
        ->and(str_contains($script, 'BACKUP_VERIFIED'))->toBeTrue();

    // Post-deploy gates.
    expect(str_contains($script, 'artisan optimize'))->toBeTrue()
        ->and(str_contains($script, 'app:production-check'))->toBeTrue()
        ->and(str_contains($script, '/health'))->toBeTrue();
});

it('never uses destructive database commands in the deployment script', function () {
    $script = strtolower((string) file_get_contents(base_path(DEPLOY_SCRIPT)));

    foreach (['migrate:fresh', 'migrate:refresh', 'db:wipe'] as $forbidden) {
        expect(str_contains($script, $forbidden))->toBeFalse("deploy.sh must not contain '{$forbidden}'");
    }
});

it('passes bash syntax validation when bash is available', function () {
    $bash = null;

    foreach (['bash', 'C:\\Program Files\\Git\\bin\\bash.exe', 'D:\\progg\\Git\\bin\\bash.exe', '/usr/bin/bash'] as $candidate) {
        try {
            $probe = Process::timeout(10)->run([$candidate, '--version']);

            if ($probe->successful()) {
                $bash = $candidate;

                break;
            }
        } catch (Throwable) {
            continue;
        }
    }

    if ($bash === null) {
        test()->markTestSkipped('bash unavailable on this machine.');
    }

    $result = Process::timeout(30)->run([$bash, '-n', base_path(DEPLOY_SCRIPT)]);

    expect($result->successful())->toBeTrue($result->errorOutput());
});

// ---------------------------------------------------------------------------
// CI workflow contract
// ---------------------------------------------------------------------------

it('runs the complete verification pipeline in CI including the frontend build', function () {
    $workflow = (string) file_get_contents(base_path('.github/workflows/tests.yml'));

    foreach ([
        'composer setup',
        'npm ci',
        'npm run build',
        'composer ci:check',
    ] as $step) {
        expect(str_contains($workflow, $step))->toBeTrue("CI workflow must contain step '{$step}'");
    }
});
