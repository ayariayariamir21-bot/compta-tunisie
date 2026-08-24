<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Company;
use App\Models\User;
use App\Services\Security\BackupService;
use App\Services\Security\ProcessOutcome;
use App\Services\Security\ProcessRunner;
use App\Services\Security\SymfonyProcessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->company = Company::create(['name' => 'Societe Principale', 'currency' => 'TND', 'is_active' => true]);

    $this->admin = User::factory()->create();
    $this->accountant = User::factory()->create();
    $this->viewer = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->company->users()->attach($this->admin, ['role' => 'admin', 'is_active' => true]);
    $this->company->users()->attach($this->accountant, ['role' => 'accountant', 'is_active' => true]);
    $this->company->users()->attach($this->viewer, ['role' => 'viewer', 'is_active' => true]);

    // Restore execution is environment-gated; tests run in "testing".
    config(['backups.allowed_environments' => ['testing']]);

    $this->fakeRunner = new class implements ProcessRunner
    {
        /** @var list<list<string>> */
        public array $commands = [];

        public int $pgDumpCalls = 0;

        public ?int $failPgDumpFromCall = null;

        public bool $failPgRestore = false;

        public function run(array $command, array $env = [], ?int $timeout = null): ProcessOutcome
        {
            $this->commands[] = $command;

            if (in_array('--version', $command, true)) {
                return new ProcessOutcome($command, true, 0, 'pg_dump (PostgreSQL) 18.0', '');
            }

            if (in_array('--format=custom', $command, true)) {
                $this->pgDumpCalls++;

                if ($this->failPgDumpFromCall !== null && $this->pgDumpCalls >= $this->failPgDumpFromCall) {
                    return new ProcessOutcome($command, false, 1, '', 'pg_dump: erreur simulée.');
                }

                $index = array_search('--file', $command, true);

                if ($index !== false && isset($command[$index + 1])) {
                    file_put_contents($command[$index + 1], random_bytes(512));
                }

                return new ProcessOutcome($command, true, 0, '', '');
            }

            if (in_array('--list', $command, true)) {
                return new ProcessOutcome($command, true, 0, 'PGDMP archive entries', '');
            }

            if (in_array('--clean', $command, true)) {
                if ($this->failPgRestore) {
                    return new ProcessOutcome($command, false, 1, '', 'pg_restore: erreur simulée.');
                }

                return new ProcessOutcome($command, true, 0, '', '');
            }

            return new ProcessOutcome($command, true, 0, '', '');
        }
    };

    $this->app->bind(ProcessRunner::class, fn (): ProcessRunner => $this->fakeRunner);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create one backup through the service with the fake runner bound above.
 *
 * @return array{ok: bool, filename?: string, metadata?: array<string, mixed>, error?: string}
 */
function backupCreateOne(User $actor): array
{
    return app(BackupService::class)->createDatabaseBackup($actor);
}

/**
 * @return array{dsn: string, user: string, password: string}
 */
function backupRootConnection(): array
{
    $config = config('database.connections.pgsql');
    assert(is_array($config));

    return [
        'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'] ?? '127.0.0.1', $config['port'] ?? '5432'),
        'user' => (string) ($config['username'] ?? 'postgres'),
        'password' => (string) ($config['password'] ?? ''),
    ];
}

function backupCreateScratchDatabase(string $name): void
{
    $root = backupRootConnection();
    $pdo = new PDO($root['dsn'], $root['user'], $root['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(sprintf('CREATE DATABASE "%s"', str_replace('"', '', $name)));
    $pdo = null;
}

function backupDropScratchDatabase(string $name): void
{
    $root = backupRootConnection();
    $pdo = new PDO($root['dsn'], $root['user'], $root['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', str_replace('"', '', $name)));
    $pdo = null;
}

// ---------------------------------------------------------------------------
// Access control & UI
// ---------------------------------------------------------------------------

it('denies viewers access to the backups page', function () {
    actingAs($this->viewer)
        ->get(route('backups.index'))
        ->assertForbidden();
});

it('allows accountants to view backups but not to create them', function () {
    actingAs($this->accountant)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Gestion des sauvegardes')
        ->assertDontSee('Créer une sauvegarde');
});

it('allows admins to view backups and offers creation', function () {
    actingAs($this->admin)
        ->get(route('backups.index'))
        ->assertOk()
        ->assertSee('Gestion des sauvegardes')
        ->assertSee('Créer une sauvegarde');
});

it('denies outsiders without any company membership', function () {
    actingAs($this->outsider)
        ->get(route('backups.index'))
        ->assertForbidden();
});

it('lets admins create a backup through the HTTP endpoint', function () {
    actingAs($this->admin)
        ->post(route('backups.create'))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Storage::disk('local')->files('backups'))
        ->toHaveCount(2); // dump + sidecar json

    expect(AuditLog::query()->where('action', AuditAction::BackupCreated->value)->exists())->toBeTrue();
});

it('forbids accountants from creating backups over HTTP', function () {
    actingAs($this->accountant)
        ->post(route('backups.create'))
        ->assertForbidden();

    expect(AuditLog::query()->where('action', AuditAction::BackupCreated->value)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Metadata, checksum and validation
// ---------------------------------------------------------------------------

it('writes the dump file, sidecar metadata and SHA-256 checksum', function () {
    $result = backupCreateOne($this->admin);

    expect($result['ok'])->toBeTrue();

    $filename = (string) $result['filename'];

    expect(Storage::disk('local')->exists('backups/'.$filename))->toBeTrue()
        ->and(Storage::disk('local')->exists('backups/'.$filename.'.json'))->toBeTrue();

    $metadata = app(BackupService::class)->getBackup($filename);

    expect($metadata)->not->toBeNull()
        ->and($metadata['format'])->toBe('custom')
        ->and($metadata['database'])->toBe(config('database.connections.pgsql.database'))
        ->and($metadata['checksum'])->toBeString()
        ->and(strlen((string) $metadata['checksum']))->toBe(64)
        ->and((int) $metadata['size'])->toBeGreaterThan(0);
});

it('validates checksums against recomputed values', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];
    $service = app(BackupService::class);

    expect($service->verifyChecksum($filename))->toBeTrue();

    // Tamper with the stored bytes.
    $absolutePath = Storage::disk('local')->path('backups/'.$filename);
    file_put_contents($absolutePath, random_bytes(300));

    expect($service->verifyChecksum($filename))->toBeFalse();

    $validation = $service->validateBackup($filename);

    expect($validation['valid'])->toBeFalse()
        ->and($validation['errors'])->toContain('L\'empreinte SHA-256 ne correspond pas au fichier.');
});

it('rejects empty or corrupt files during validation', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];
    $service = app(BackupService::class);

    $absolutePath = Storage::disk('local')->path('backups/'.$filename);
    file_put_contents($absolutePath, '');

    $validation = $service->validateBackup($filename);

    expect($validation['valid'])->toBeFalse()
        ->and($validation['errors'])->toContain('Fichier vide.')
        ->and($validation['errors'])->toContain('L\'empreinte SHA-256 ne correspond pas au fichier.');
});

it('flags dumps without trusted metadata as unknown in listings', function () {
    backupCreateOne($this->admin);

    // Orphan dump without sidecar.
    Storage::disk('local')->put('backups/compta-tunisie-2026-01-01-000000-deadbeef.dump', 'orphan');

    $rows = app(BackupService::class)->listBackups();

    expect($rows)->toHaveCount(2);

    $orphan = array_values(array_filter($rows, fn (array $row): bool => str_contains((string) $row['filename'], 'deadbeef')));

    expect($orphan[0]['checksum_status'])->toBe('sans_metadonnees')
        ->and($orphan[0]['status'])->toBe('inconnu');
});

it('refuses arbitrary or traversal filenames', function () {
    $service = app(BackupService::class);

    expect($service->getBackup('../../../.env'))->toBeNull()
        ->and($service->getBackup('compta-tunisie-not-a-real-file.dump'))->toBeNull()
        ->and($service->getBackup('random.txt'))->toBeNull();

    get(route('backups.download', 'evil.txt'))->assertNotFound();
});

// ---------------------------------------------------------------------------
// Secure download
// ---------------------------------------------------------------------------

it('protects downloads and streams without exposing filesystem paths', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];
    $original = Storage::disk('local')->get('backups/'.$filename);

    get(route('backups.download', $filename))
        ->assertRedirect(route('login'));

    actingAs($this->viewer)
        ->get(route('backups.download', $filename))
        ->assertForbidden();

    $response = actingAs($this->accountant)
        ->get(route('backups.download', $filename))
        ->assertOk();

    $disposition = (string) $response->headers->get('Content-Disposition');

    expect($disposition)->toContain('attachment')
        ->toContain($filename)
        ->not->toContain((string) config('filesystems.disks.local.root'))
        ->and(base64_encode((string) $response->streamedContent()))
        ->toBe(base64_encode((string) $original));

    expect(AuditLog::query()->where('action', AuditAction::BackupDownloaded->value)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Audit trail
// ---------------------------------------------------------------------------

it('audits backup creation and validation', function () {
    actingAs($this->admin)->post(route('backups.create'))->assertRedirect();

    $created = AuditLog::query()->where('action', AuditAction::BackupCreated->value)->first();

    expect($created)->not->toBeNull()
        ->and($created?->user_id)->toBe($this->admin->id)
        ->and($created?->metadata['reason'] ?? null)->toBe('manual');

    $filename = (string) ($created?->metadata['filename'] ?? '');

    actingAs($this->admin)->post(route('backups.validate', $filename))->assertRedirect()->assertSessionHas('status');

    $validated = AuditLog::query()->where('action', AuditAction::BackupValidated->value)->first();

    expect($validated)->not->toBeNull()
        ->and($validated?->user_id)->toBe($this->admin->id);

    $metadata = app(BackupService::class)->getBackup($filename);

    expect($metadata['status'])->toBe('validated');
});

it('rejects validating unknown filenames', function () {
    actingAs($this->admin)
        ->post(route('backups.validate', 'compta-tunisie-2026-01-01-000000-cafebabe.dump'))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Restore safety gates (simulated processes)
// ---------------------------------------------------------------------------

it('restricts restore actions to admins', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];

    foreach ([$this->accountant, $this->viewer, $this->outsider] as $actor) {
        actingAs($actor)
            ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
            ->assertForbidden();
    }

    expect(AuditLog::query()->where('action', AuditAction::BackupRestoreStarted->value)->exists())->toBeFalse();
});

it('requires the exact confirmation phrase', function () {
    config(['backups.confirmation_phrase' => 'RESTAURER LA BASE']);

    $filename = (string) backupCreateOne($this->admin)['filename'];

    // Ignore the commands already logged by the creation above.
    $this->fakeRunner->commands = [];

    actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'oui je confirme'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AuditLog::query()->where('action', AuditAction::BackupRestoreFailed->value)->count())->toBe(1)
        ->and($this->fakeRunner->commands)->toBeEmpty();
});

it('blocks restores outside allowed environments', function () {
    config(['backups.allowed_environments' => ['production']]);

    $filename = (string) backupCreateOne($this->admin)['filename'];

    actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(session('error'))->toBe('Restauration de production désactivée depuis l\'interface.');

    // No pg_dump/pg_restore was ever executed for the restore itself.
    $restoreCommands = array_filter($this->fakeRunner->commands, fn (array $c): bool => in_array('--clean', $c, true));

    expect($restoreCommands)->toBeEmpty();
});

it('creates a verified safety backup before restoring', function () {
    // Post-restore integrity checks run through the "pgsql" connection;
    // route them to the already-migrated default connection so the success
    // path stays hermetic (in-memory databases are per-connection).
    $real = DB::getFacadeRoot();

    DB::shouldReceive('connection')
        ->with('pgsql')
        ->andReturn($real->connection());

    DB::shouldReceive('connection')
        ->andReturnUsing(fn (?string $name = null) => $real->connection($name));

    $filename = (string) backupCreateOne($this->admin)['filename'];

    $response = actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
        ->assertRedirect();

    $response->assertSessionHas('status');

    $commands = $this->fakeRunner->commands;

    $dumpIndexes = array_keys(array_filter($commands, fn (array $c): bool => in_array('--format=custom', $c, true)));
    $restoreIndex = (int) key(array_filter($commands, fn (array $c): bool => in_array('--clean', $c, true)));

    // One initial dump + one mandatory safety dump before pg_restore.
    expect(count($dumpIndexes))->toBeGreaterThanOrEqual(2)
        ->and(max($dumpIndexes))->toBeLessThan($restoreIndex);

    expect(AuditLog::query()->where('action', AuditAction::BackupRestoreSucceeded->value)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::BackupRestoreStarted->value)->exists())->toBeTrue();
});

it('aborts the restore when the safety backup fails', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];

    // Every subsequent pg_dump (i.e. the safety backup) fails.
    $this->fakeRunner->failPgDumpFromCall = 2;

    actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
        ->assertRedirect()
        ->assertSessionHas('error');

    $restoreCommands = array_filter($this->fakeRunner->commands, fn (array $c): bool => in_array('--clean', $c, true));

    expect($restoreCommands)->toBeEmpty();

    $failure = AuditLog::query()->where('action', AuditAction::BackupRestoreFailed->value)->latest('id')->first();

    expect($failure)->not->toBeNull()
        ->and((string) ($failure?->metadata['error'] ?? ''))->toContain('sécurité');
});

it('audits failed pg_restore executions', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];

    $this->fakeRunner->failPgRestore = true;

    actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
        ->assertRedirect()
        ->assertSessionHas('error');

    $failure = AuditLog::query()->where('action', AuditAction::BackupRestoreFailed->value)->latest('id')->first();

    expect($failure)->not->toBeNull()
        ->and((string) ($failure?->metadata['error'] ?? ''))->toContain('erreur simulée');
});

it('refuses restoring archives from another database', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];
    $service = app(BackupService::class);

    $sidecarPath = 'backups/'.$filename.'.json';
    $metadata = json_decode((string) Storage::disk('local')->get($sidecarPath), true);
    $metadata['database'] = 'some_other_database';
    Storage::disk('local')->put($sidecarPath, json_encode($metadata));

    actingAs($this->admin)
        ->post(route('backups.restore', $filename), ['confirmation' => 'RESTAURER LA BASE'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(session('error'))->toContain('base de données courante');
});

// ---------------------------------------------------------------------------
// Secrets hygiene & storage security
// ---------------------------------------------------------------------------

it('never stores credentials in metadata or audit entries', function () {
    $result = backupCreateOne($this->admin);
    $filename = (string) $result['filename'];

    $configuredPassword = (string) config('database.connections.pgsql.password');

    $sidecar = (string) Storage::disk('local')->get('backups/'.$filename.'.json');

    expect(str_contains($sidecar, 'password'))->toBeFalse()
        ->and(str_contains($sidecar, 'PGPASSWORD'))->toBeFalse()
        ->and($configuredPassword === '' || str_contains($sidecar, $configuredPassword) === false)->toBeTrue();

    foreach (AuditLog::query()->whereIn('action', [
        AuditAction::BackupCreated->value,
        AuditAction::BackupValidated->value,
        AuditAction::BackupDownloaded->value,
    ])->get() as $row) {
        $encoded = json_encode($row->metadata).''.$row->description;

        expect(str_contains($encoded, 'PGPASSWORD'))->toBeFalse()
            ->and(str_contains(strtolower($encoded), 'password'))->toBeFalse()
            ->and($configuredPassword === '' || str_contains($encoded, $configuredPassword) === false)->toBeTrue();
    }
});

it('keeps backups out of public storage and HTTP reach', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];

    $diskRoot = (string) config('filesystems.disks.local.root');

    expect(str_starts_with($diskRoot, public_path()))->toBeFalse();

    // The local disk is served through signed URLs only: an unsigned
    // public URL must be rejected, never leak the dump.
    get('/storage/backups/'.$filename)->assertForbidden();

    $publicFiles = glob(public_path('storage/backups/*')) ?: [];

    expect($publicFiles)->toBeEmpty();
});

it('deletes both the dump and its metadata when authorized', function () {
    $filename = (string) backupCreateOne($this->admin)['filename'];
    $service = app(BackupService::class);

    expect($service->deleteBackup($filename))->toBeTrue()
        ->and(Storage::disk('local')->exists('backups/'.$filename))->toBeFalse()
        ->and(Storage::disk('local')->exists('backups/'.$filename.'.json'))->toBeFalse()
        ->and($service->deleteBackup($filename))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Maintenance-mode wrapper
// ---------------------------------------------------------------------------

it('enters and exits maintenance mode around restore operations', function () {
    Artisan::shouldReceive('call')->once()->with('down');
    Artisan::shouldReceive('call')->once()->with('up');

    $result = app(BackupService::class)->underMaintenance(fn (): string => 'done');

    expect($result)->toBe('done');
});

it('exits maintenance mode even when the operation throws', function () {
    Artisan::shouldReceive('call')->once()->with('down');
    Artisan::shouldReceive('call')->once()->with('up');

    try {
        app(BackupService::class)->underMaintenance(function (): never {
            throw new RuntimeException('boom');
        });

        $this->fail('Exception attendue non levée.');
    } catch (RuntimeException) {
        expect(true)->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// Opt-in integration: real pg_dump / pg_restore against throwaway databases.
// Skipped automatically when the PostgreSQL client tools are unavailable.
// Never touches the application database.
// ---------------------------------------------------------------------------

it('performs a full backup and restore roundtrip on scratch databases', function () {
    // Rebind the REAL process runner first so tool detection is genuine.
    $this->app->bind(ProcessRunner::class, SymfonyProcessRunner::class);

    $service = app(BackupService::class);

    if (! $service->toolsAvailable()) {
        $this->markTestSkipped('Outils PostgreSQL indisponibles : integration ignoree.');
    }

    $suffix = bin2hex(random_bytes(4));
    $sourceDb = 'compta_backup_src_'.$suffix;
    $targetDb = 'compta_backup_dst_'.$suffix;

    $root = backupRootConnection();

    backupCreateScratchDatabase($sourceDb);

    try {
        $pdo = new PDO(str_replace('dbname=postgres', "dbname=$sourceDb", $root['dsn']), $root['user'], $root['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('create table backup_probe (id int primary key, label text)');
        $pdo->exec("insert into backup_probe values (1, 'alpha'), (2, 'beta')");
        $pdo = null;

        // Point the service at the scratch database for the dump only.
        config(['database.connections.pgsql.database' => $sourceDb]);

        $result = $service->createDatabaseBackup($this->admin, 'integration_test');

        expect($result['ok'])->toBeTrue();

        $filename = (string) $result['filename'];
        $metadata = $service->getBackup($filename);

        expect($metadata)->not->toBeNull()
            ->and($metadata['database'])->toBe($sourceDb)
            ->and((string) $metadata['postgresql_version'])->toStartWith('PostgreSQL');

        $validation = $service->validateBackup($filename);

        expect($validation['valid'])->toBeTrue(implode(' | ', $validation['errors']));

        backupCreateScratchDatabase($targetDb);

        $restore = $service->restoreArchiveIntoDatabase(
            Storage::disk('local')->path('backups/'.$filename),
            $targetDb,
        );

        expect($restore['ok'])->toBeTrue((string) ($restore['error'] ?? ''));

        $restoredPdo = new PDO(
            str_replace('dbname=postgres', "dbname=$targetDb", $root['dsn']),
            $root['user'],
            $root['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $count = (int) $restoredPdo->query('select count(*) from backup_probe')->fetchColumn();
        $labels = (string) $restoredPdo->query("select string_agg(label, ',' order by id) from backup_probe")->fetchColumn();

        expect($count)->toBe(2)
            ->and($labels)->toBe('alpha,beta');

        $restoredPdo = null;
    } finally {
        backupDropScratchDatabase($sourceDb);
        backupDropScratchDatabase($targetDb);
    }
});
