<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);
});

// ---------------------------------------------------------------------------
// Health endpoint
// ---------------------------------------------------------------------------

it('reports healthy status when the database is reachable', function () {
    $response = $this->getJson(route('health'));

    expect($response->status())->toBe(200)
        ->and($response->json('status'))->toBe('ok')
        ->and($response->json('database'))->toBe('ok');
});

it('returns 503 without leaking details when the database is unreachable', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('pg_connect failed with secret-password-123'));

    $response = $this->getJson(route('health'));
    $body = (string) $response->getContent();

    expect($response->status())->toBe(503)
        ->and($response->json('database'))->toBe('unavailable')
        ->and(str_contains($body, 'secret-password-123'))->toBeFalse()
        ->and(str_contains($body, 'RuntimeException'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Production check command
// ---------------------------------------------------------------------------

it('runs successfully in a sane environment', function () {
    artisan('app:production-check')->assertSuccessful();
});

it('emits pass lines and a result verdict', function () {
    artisan('app:production-check')
        ->expectsOutputToContain('[PASS] APP_KEY configurée');
});

it('fails safely when APP_KEY is missing', function () {
    config(['app.key' => null]);

    artisan('app:production-check')
        ->expectsOutputToContain('[FAIL] APP_KEY manquante')
        ->expectsOutputToContain('RESULT: NOT READY');
});

it('treats debug mode as a failure in production but only a warning elsewhere', function () {
    config([
        'app.env' => 'production',
        'app.debug' => true,
    ]);

    artisan('app:production-check')
        ->expectsOutputToContain('[FAIL] APP_DEBUG=true est interdit en production (fuites de stack traces)')
        ->expectsOutputToContain('RESULT: NOT READY');

    config(['app.env' => 'local']);

    artisan('app:production-check')->assertSuccessful();
});

it('detects database unavailability as a failure', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('connection refused'));

    artisan('app:production-check')
        ->expectsOutputToContain('[FAIL] Base de données injoignable')
        ->expectsOutputToContain('RESULT: NOT READY');
});

it('does not print configured secrets', function () {
    $password = 'un-mot-de-passe-tres-secret';
    $key = 'base64:Q0FOQVJZU0VDUkVUS0VZRk9SVEVTVA==';

    config([
        'database.connections.pgsql.password' => $password,
        'app.key' => $key,
        'app.env' => 'production',
        'app.debug' => false,
    ]);

    Artisan::call('app:production-check');
    $output = Artisan::output();

    expect($output)->toContain('[PASS]')
        ->and($output)->toContain('RESULT:')
        ->and(str_contains($output, $password))->toBeFalse()
        ->and(str_contains($output, $key))->toBeFalse();
});

it('leaves application data untouched after running', function () {
    $usersBefore = DB::table('users')->count();

    artisan('app:production-check')->assertSuccessful();

    expect(DB::table('users')->count())->toBe($usersBefore);
});
