<?php

namespace App\Services\Security;

use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Database backup and restore foundation built on native PostgreSQL tools.
 *
 * Backups are pg_dump custom archives (restorable and inspectable through
 * pg_restore) written to a private storage directory, each accompanied by a
 * sidecar JSON metadata file containing its SHA-256 checksum. Restores are
 * heavily gated: admin-only, explicit confirmation phrase, allowed
 * environments only, mandatory verified safety backup.
 *
 * This service performs system-level operations only; it never mutates
 * accounting data except when an authorized restore intentionally replaces
 * the whole database content.
 */
final class BackupService
{
    private const FILENAME_PATTERN = '/^compta-tunisie-\d{4}-\d{2}-\d{2}-\d{6}-[0-9a-f]{8}\.dump$/';

    private const MAX_FILENAME_ATTEMPTS = 5;

    private ?ProcessRunner $runner = null;

    private ?string $cachedPgDumpVersion = null;

    public function __construct(
        private readonly ?AuditLogService $auditLogService = null,
    ) {}

    /**
     * Create a new database backup of the configured PostgreSQL connection.
     *
     * @param  array<array-key, mixed>  $extraMetadata
     * @return array{ok: true, filename: string, metadata: array<string, mixed>}|array{ok: false, error: string}
     */
    public function createDatabaseBackup(User $actor, string $reason = 'manual', array $extraMetadata = []): array
    {
        $toolsError = $this->toolsUnavailableReason();

        if ($toolsError !== null) {
            return ['ok' => false, 'error' => $toolsError];
        }

        $filename = $this->generateFilename();
        $disk = $this->disk();
        $relativePath = $this->relativePath($filename);
        $absolutePath = $this->absolutePath($filename);

        $disk->makeDirectory($this->directory());

        $outcome = $this->runner()->run($this->pgDumpCommand($absolutePath), $this->processEnvironment());

        if (! $outcome->successful || ! is_file($absolutePath) || filesize($absolutePath) === 0) {
            $disk->delete($relativePath);

            return ['ok' => false, 'error' => $this->truncateProcessError($outcome->error !== '' ? $outcome->error : 'pg_dump n\'a produit aucun fichier.')];
        }

        $checksum = $this->calculateChecksum($absolutePath);

        if ($checksum === null) {
            $disk->delete($relativePath);

            return ['ok' => false, 'error' => 'Impossible de calculer l\'empreinte de la sauvegarde.'];
        }

        $metadata = [
            ...$this->baseMetadata($filename),
            ...$extraMetadata,
            'size' => (int) filesize($absolutePath),
            'created_at' => now()->toIso8601String(),
            'database' => $this->databaseName(),
            'postgresql_version' => $this->serverVersion(),
            'pg_dump_version' => $this->clientVersion(),
            'application_commit' => $this->applicationCommit(),
            'format' => 'custom',
            'checksum' => $checksum,
            'status' => 'created',
            'reason' => $reason,
            'database_reachable_after' => $this->databaseReachable(),
        ];

        try {
            $disk->put($this->sidecarRelativePath($filename), $this->encodeMetadata($metadata));
        } catch (\JsonException) {
            $disk->delete($relativePath);

            return ['ok' => false, 'error' => 'Impossible d\'écrire les métadonnées de la sauvegarde.'];
        }

        $this->audit()->log(
            AuditAction::BackupCreated,
            description: 'Sauvegarde de base de données créée.',
            metadata: [
                'filename' => $filename,
                'size' => $metadata['size'],
                'checksum' => $checksum,
                'database' => $metadata['database'],
                'reason' => $reason,
                'environment' => app()->environment(),
            ],
            user: $actor,
        );

        return ['ok' => true, 'filename' => $filename, 'metadata' => $metadata];
    }

    /**
     * List every backup on the private disk, newest first. Checksums are not
     * recomputed here: listing only reports whether trusted metadata exists
     * and still matches the file size.
     *
     * @return list<array<string, mixed>>
     */
    public function listBackups(): array
    {
        $disk = $this->disk();

        $files = collect($disk->files($this->directory()))
            ->filter(fn (string $path): bool => str_ends_with($path, '.dump'))
            ->sortDesc()
            ->values();

        $rows = [];

        foreach ($files as $path) {
            $filename = basename($path);

            if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
                continue;
            }

            $metadata = $this->getBackup($filename);

            if ($metadata === null) {
                $rows[] = [
                    'filename' => $filename,
                    'metadata' => false,
                    'size' => (int) $disk->size($path),
                    'created_at' => null,
                    'database' => null,
                    'postgresql_version' => null,
                    'application_commit' => null,
                    'status' => 'inconnu',
                    'checksum_status' => 'sans_metadonnees',
                ];

                continue;
            }

            $sizeMatches = isset($metadata['size']) && (int) $metadata['size'] === (int) $disk->size($path);

            $rows[] = [
                'filename' => $filename,
                'metadata' => true,
                'size' => (int) $metadata['size'],
                'created_at' => $metadata['created_at'] ?? null,
                'database' => $metadata['database'] ?? null,
                'postgresql_version' => $metadata['postgresql_version'] ?? null,
                'application_commit' => $metadata['application_commit'] ?? null,
                'status' => $metadata['status'] ?? 'inconnu',
                'checksum_status' => ($sizeMatches && is_string($metadata['checksum'] ?? null)) ? 'ok' : 'douteux',
            ];
        }

        return $rows;
    }

    /**
     * Resolve one backup by filename. The name must match the deterministic
     * pattern generated by this service, so arbitrary paths or traversal
     * sequences can never reach the filesystem layer.
     *
     * @return array<string, mixed>|null
     */
    public function getBackup(string $filename): ?array
    {
        return $this->getBackupMetadata($filename);
    }

    /**
     * Read the sidecar metadata of one backup.
     *
     * @return array<string, mixed>|null
     */
    public function getBackupMetadata(string $filename): ?array
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }

        $disk = $this->disk();
        $dumpPath = $this->relativePath($filename);

        if (! $disk->exists($dumpPath)) {
            return null;
        }

        $sidecarPath = $this->sidecarRelativePath($filename);

        if (! $disk->exists($sidecarPath)) {
            return null;
        }

        /** @var string $raw */
        $raw = $disk->get($sidecarPath);

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Compute the SHA-256 checksum of a backup file; null when unreadable.
     */
    public function calculateChecksum(string $absolutePath): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $checksum = hash_file('sha256', $absolutePath);

        return $checksum === false ? null : $checksum;
    }

    /**
     * Recompute the SHA-256 checksum of a stored backup and compare it with
     * the value recorded in its metadata.
     */
    public function verifyChecksum(string $filename): bool
    {
        $metadata = $this->getBackupMetadata($filename);

        if ($metadata === null || ! is_string($metadata['checksum'] ?? null)) {
            return false;
        }

        $actual = $this->calculateChecksum($this->absolutePath($filename));

        return $actual !== null && hash_equals($metadata['checksum'], $actual);
    }

    /**
     * Full validation: existence, readability, format, size, metadata,
     * recomputed checksum and, when tooling allows, inspection of the
     * custom archive through "pg_restore --list". Never restores anything.
     *
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateBackup(string $filename): array
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return ['valid' => false, 'errors' => ['Nom de fichier invalide.']];
        }

        $errors = [];
        $disk = $this->disk();
        $dumpPath = $this->relativePath($filename);
        $absolutePath = $this->absolutePath($filename);

        if (! $disk->exists($dumpPath)) {
            return ['valid' => false, 'errors' => ['Fichier introuvable.']];
        }

        if (! is_readable($absolutePath)) {
            $errors[] = 'Fichier illisible.';
        }

        $size = (int) filesize($absolutePath);

        if ($size === 0) {
            $errors[] = 'Fichier vide.';
        }

        $metadata = $this->getBackupMetadata($filename);

        if ($metadata === null) {
            $errors[] = 'Métadonnées absentes ou corrompues.';
        } else {
            if (($metadata['format'] ?? null) !== 'custom') {
                $errors[] = 'Format de sauvegarde inattendu.';
            }

            if ((int) ($metadata['size'] ?? -1) !== $size) {
                $errors[] = 'La taille du fichier ne correspond pas aux métadonnées.';
            }

            if (! $this->verifyChecksum($filename)) {
                $errors[] = 'L\'empreinte SHA-256 ne correspond pas au fichier.';
            }
        }

        $toolsError = $this->toolsUnavailableReason();

        if ($toolsError !== null) {
            $errors[] = $toolsError;
        } else {
            $outcome = $this->runner()->run($this->pgRestoreListCommand($absolutePath), $this->processEnvironment(), 120);

            if (! $outcome->successful) {
                $errors[] = 'L\'archive ne peut pas être inspectée par pg_restore.';
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * Record a successful validation in the sidecar metadata and audit it.
     * The caller must have already run validateBackup() and received a
     * positive result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function markValidated(string $filename, User $actor, array $metadata = []): void
    {
        $current = $this->getBackupMetadata($filename);

        if ($current === null) {
            return;
        }

        $current['status'] = 'validated';
        $current['validated_at'] = now()->toIso8601String();

        foreach ($metadata as $key => $value) {
            $current[$key] = $value;
        }

        $this->disk()->put(
            $this->sidecarRelativePath($filename),
            $this->encodeMetadata($current),
        );

        $this->audit()->log(
            AuditAction::BackupValidated,
            description: 'Sauvegarde vérifiée.',
            metadata: [
                'filename' => $filename,
                'checksum' => is_string($current['checksum'] ?? null) ? $current['checksum'] : null,
                'environment' => app()->environment(),
            ],
            user: $actor,
        );
    }

    /**
     * Audit one authorized backup download (no filesystem content).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function auditDownloaded(string $filename, array $metadata, User $actor): void
    {
        $this->audit()->log(
            AuditAction::BackupDownloaded,
            description: 'Sauvegarde téléchargée.',
            metadata: [
                'filename' => $filename,
                'size' => $metadata['size'] ?? null,
                'checksum' => $metadata['checksum'] ?? null,
                'environment' => app()->environment(),
            ],
            user: $actor,
        );
    }

    /**
     * Delete one backup and its metadata. The caller must enforce the
     * admin-only authorization; no retention policy runs automatically.
     */
    public function deleteBackup(string $filename): bool
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return false;
        }

        $disk = $this->disk();

        if (! $disk->exists($this->relativePath($filename))) {
            return false;
        }

        return $disk->delete([$this->relativePath($filename), $this->sidecarRelativePath($filename)]);
    }

    /**
     * Restore a validated backup into the current database after every
     * safety gate passed. Only callable in allowed environments; production
     * is refused outright.
     *
     * @param  array<string, mixed>  $context  extra audit metadata (e.g. actor ip)
     * @return array{ok: true, safety_backup: string, restored_at: string}|array{ok: false, error: string, safety_backup?: string}
     */
    public function restoreDatabaseBackup(string $filename, User $actor, string $confirmationPhrase, array $context = []): array
    {
        $fail = fn (string $error): array => $this->failRestore($error, $actor, $filename, $context);

        $allowedEnvironments = config('backups.allowed_environments', []);

        if (! is_array($allowedEnvironments) || ! in_array(app()->environment(), $allowedEnvironments, true)) {
            return ['ok' => false, 'error' => 'Restauration de production désactivée depuis l\'interface.'];
        }

        $expectedPhrase = config('backups.confirmation_phrase');

        if (! is_string($expectedPhrase) || ! hash_equals($expectedPhrase, trim($confirmationPhrase))) {
            return $fail('Phrase de confirmation incorrecte.');
        }

        $toolsError = $this->toolsUnavailableReason();

        if ($toolsError !== null) {
            return $fail($toolsError);
        }

        $metadata = $this->getBackup($filename);

        if ($metadata === null) {
            return $fail('Sauvegarde introuvable.');
        }

        $validation = $this->validateBackup($filename);

        if (! $validation['valid']) {
            return $fail('Sauvegarde invalide : '.implode(' ', $validation['errors']));
        }

        $targetDatabase = $this->databaseName();

        if (($metadata['database'] ?? null) !== $targetDatabase) {
            return $fail('Cette sauvegarde ne provient pas de la base de données courante.');
        }

        $this->audit()->log(
            AuditAction::BackupRestoreStarted,
            description: 'Restauration démarrée.',
            metadata: [
                'filename' => $filename,
                'checksum' => $metadata['checksum'] ?? null,
                'size' => $metadata['size'] ?? null,
                'environment' => app()->environment(),
                ...$context,
            ],
            user: $actor,
        );

        $safety = $this->createDatabaseBackup($actor, 'safety_before_restore');

        if (! $safety['ok']) {
            return $this->failRestore(
                'La sauvegarde de sécurité avant restauration a échoué : '.$safety['error'],
                $actor,
                $filename,
                $context,
            );
        }

        $safetyFilename = $safety['filename'];
        $safetyValidation = $this->validateBackup($safetyFilename);

        if (! $safetyValidation['valid']) {
            $this->deleteBackup($safetyFilename);

            return $this->failRestore('La sauvegarde de sécurité est invalide, restauration annulée.', $actor, $filename, $context);
        }

        $outcome = $this->runner()->run(
            $this->pgRestoreCommand($this->absolutePath($filename), $targetDatabase),
            $this->processEnvironment(),
        );

        if (! $outcome->successful) {
            return $this->failRestore(
                $this->truncateProcessError($outcome->error !== '' ? $outcome->error : 'pg_restore a échoué.'),
                $actor,
                $filename,
                $context,
                $safetyFilename,
            );
        }

        if (! $this->verifyRestoredDatabase()) {
            return $this->failRestore('La base restaurée ne passe pas les contrôles d\'intégrité.', $actor, $filename, $context, $safetyFilename);
        }

        $this->clearApplicationCaches();

        $this->audit()->log(
            AuditAction::BackupRestoreSucceeded,
            description: 'Base de données restaurée avec succès.',
            metadata: [
                'filename' => $filename,
                'safety_backup' => $safetyFilename,
                'environment' => app()->environment(),
                ...$context,
            ],
            user: $actor,
        );

        return ['ok' => true, 'safety_backup' => $safetyFilename, 'restored_at' => now()->toIso8601String()];
    }

    /**
     * Restore a dump archive into an arbitrary target database. Exposed so
     * integration tests can exercise real restores against throwaway
     * databases; never call this against the application database outside
     * of restoreDatabaseBackup().
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function restoreArchiveIntoDatabase(string $absoluteDumpPath, string $targetDatabase): array
    {
        $toolsError = $this->toolsUnavailableReason();

        if ($toolsError !== null) {
            return ['ok' => false, 'error' => $toolsError];
        }

        if (! is_file($absoluteDumpPath) || filesize($absoluteDumpPath) === 0) {
            return ['ok' => false, 'error' => 'Archive introuvable ou vide.'];
        }

        $outcome = $this->runner()->run(
            $this->pgRestoreCommand($absoluteDumpPath, $targetDatabase),
            $this->processEnvironment(),
        );

        if (! $outcome->successful) {
            return ['ok' => false, 'error' => $this->truncateMessage($outcome->error !== '' ? $outcome->error : 'pg_restore a échoué.', 500)];
        }

        return ['ok' => true];
    }

    /**
     * Run the given operation inside Laravel maintenance mode. Maintenance
     * mode is always exited, even when the operation throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $operation
     * @return TReturn
     */
    public function underMaintenance(callable $operation): mixed
    {
        Artisan::call('down');

        try {
            return $operation();
        } finally {
            Artisan::call('up');
        }
    }

    /**
     * Whether external backup tooling is usable in this environment.
     */
    public function toolsAvailable(): bool
    {
        return $this->toolsUnavailableReason() === null;
    }

    /**
     * Post-restore integrity checks on the configured pgsql connection.
     *
     * @return array{ok: bool, checks?: array<string, bool>, error?: string}
     */
    public function verifyRestoredDatabaseDetailed(): array
    {
        if (! $this->databaseReachable()) {
            return ['ok' => false, 'error' => 'Connexion à la base impossible.'];
        }

        $checks = [
            'migrations' => $this->tableExists('migrations'),
            'companies' => $this->tableExists('companies'),
            'users' => $this->tableExists('users'),
            'journal_entries' => $this->tableExists('journal_entries'),
            'audit_logs' => $this->tableExists('audit_logs'),
        ];

        foreach ($checks as $check) {
            if (! $check) {
                return ['ok' => false, 'checks' => $checks, 'error' => 'Tables essentielles manquantes après restauration.'];
            }
        }

        return ['ok' => true, 'checks' => $checks];
    }

    private function verifyRestoredDatabase(): bool
    {
        return $this->verifyRestoredDatabaseDetailed()['ok'];
    }

    /**
     * Record one audited restore failure.
     *
     * @param  array<string, mixed>  $context
     * @return array{ok: false, error: string, safety_backup?: string}
     */
    private function failRestore(string $error, User $actor, string $filename, array $context = [], ?string $safetyFilename = null): array
    {
        $this->audit()->log(
            AuditAction::BackupRestoreFailed,
            description: 'Restauration échouée.',
            metadata: [
                'filename' => $filename,
                'error' => $this->truncateMessage($error, 500),
                'environment' => app()->environment(),
                ...$context,
            ],
            user: $actor,
        );

        if ($safetyFilename === null) {
            return ['ok' => false, 'error' => $error];
        }

        return ['ok' => false, 'error' => $error, 'safety_backup' => $safetyFilename];
    }

    /**
     * @return list<string>
     */
    private function pgDumpCommand(string $absolutePath): array
    {
        $connection = $this->connectionConfig();

        return [
            $this->binary(config('backups.pg_dump_binary'), 'pg_dump'),
            '--host',
            $connection['host'],
            '--port',
            $connection['port'],
            '--username',
            $connection['username'],
            '--format=custom',
            '--file',
            $absolutePath,
            $connection['database'],
        ];
    }

    /**
     * @return list<string>
     */
    private function pgRestoreCommand(string $absolutePath, string $targetDatabase): array
    {
        $connection = $this->connectionConfig();

        return [
            $this->binary(config('backups.pg_restore_binary'), 'pg_restore'),
            '--host',
            $connection['host'],
            '--port',
            $connection['port'],
            '--username',
            $connection['username'],
            '--dbname',
            $targetDatabase,
            '--clean',
            '--if-exists',
            '--no-owner',
            $absolutePath,
        ];
    }

    /**
     * @return list<string>
     */
    private function pgRestoreListCommand(string $absolutePath): array
    {
        $connection = $this->connectionConfig();

        return [
            $this->binary(config('backups.pg_restore_binary'), 'pg_restore'),
            '--host',
            $connection['host'],
            '--port',
            $connection['port'],
            '--username',
            $connection['username'],
            '--list',
            $absolutePath,
        ];
    }

    /**
     * @return array{host: string, port: string, username: string, password: string, database: string}
     */
    private function connectionConfig(): array
    {
        $config = config('database.connections.pgsql');

        if (! is_array($config)) {
            throw new RuntimeException('La connexion PostgreSQL n\'est pas configurée.');
        }

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '5432'),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
            'database' => (string) ($config['database'] ?? ''),
        ];
    }

    private function databaseName(): string
    {
        return $this->connectionConfig()['database'];
    }

    /**
     * Credentials travel through the process environment only, never as
     * command arguments which would be visible in process listings.
     *
     * @return array<string, string>
     */
    private function processEnvironment(): array
    {
        $connection = $this->connectionConfig();

        return [
            'PGPASSWORD' => $connection['password'],
            'PGCONNECT_TIMEOUT' => '15',
        ];
    }

    private function binary(mixed $configured, string $default): string
    {
        return is_string($configured) && $configured !== '' ? $configured : $default;
    }

    /**
     * @return array{filename: string}
     */
    private function baseMetadata(string $filename): array
    {
        return ['filename' => $filename];
    }

    /**
     * Encode one metadata payload for sidecar storage; throws instead of
     * ever writing a truncated or empty JSON file.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws \JsonException
     */
    private function encodeMetadata(array $metadata): string
    {
        return json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function generateFilename(): string
    {
        for ($attempt = 0; $attempt < self::MAX_FILENAME_ATTEMPTS; $attempt++) {
            $filename = sprintf(
                'compta-tunisie-%s-%s.dump',
                now()->format('Y-m-d-His'),
                bin2hex((string) random_bytes(4)),
            );

            if (! $this->disk()->exists($this->relativePath($filename))) {
                return $filename;
            }

            Sleep::for(1)->millisecond();
        }

        throw new RuntimeException('Impossible de générer un nom de sauvegarde unique.');
    }

    private function disk(): Filesystem
    {
        $diskName = config('backups.disk');

        return Storage::disk(is_string($diskName) && $diskName !== '' ? $diskName : 'local');
    }

    private function directory(): string
    {
        $directory = config('backups.directory');

        return is_string($directory) && $directory !== '' ? $directory : 'backups';
    }

    private function relativePath(string $filename): string
    {
        return $this->directory().'/'.$filename;
    }

    private function sidecarRelativePath(string $filename): string
    {
        return $this->directory().'/'.$filename.'.json';
    }

    private function absolutePath(string $filename): string
    {
        return $this->disk()->path($this->relativePath($filename));
    }

    private function runner(): ProcessRunner
    {
        return $this->runner ??= app(ProcessRunner::class);
    }

    private function audit(): AuditLogService
    {
        return $this->auditLogService ?? app(AuditLogService::class);
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection('pgsql')->select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::connection('pgsql')->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function serverVersion(): ?string
    {
        try {
            $result = DB::connection('pgsql')->select('select version() as version');
        } catch (\Throwable) {
            return null;
        }

        $version = $result[0]->version ?? null;

        if (! is_string($version)) {
            return null;
        }

        if (preg_match('/PostgreSQL [\d.]+/', $version, $matches) === 1) {
            return $matches[0];
        }

        return Str::limit($version, 60);
    }

    private function clientVersion(): ?string
    {
        if ($this->cachedPgDumpVersion !== null) {
            return $this->cachedPgDumpVersion;
        }

        $outcome = $this->runner()->run([$this->binary(config('backups.pg_dump_binary'), 'pg_dump'), '--version'], [], 30);

        if (! $outcome->successful) {
            return null;
        }

        $version = trim($outcome->output);

        return $version !== '' ? $this->cachedPgDumpVersion = $version : null;
    }

    private function applicationCommit(): ?string
    {
        $outcome = $this->runner()->run(['git', 'rev-parse', '--short', 'HEAD'], [], 10);

        $commit = trim($outcome->output);

        return $outcome->successful && $commit !== '' ? $commit : null;
    }

    private function clearApplicationCaches(): void
    {
        try {
            Artisan::call('cache:clear');
        } catch (\Throwable) {
            // Cache clearing must never mask a successful restore.
        }
    }

    private function truncateProcessError(string $message): string
    {
        return $this->truncateMessage($message, 500);
    }

    private function truncateMessage(string $message, int $limit): string
    {
        $trimmed = trim($message);

        return mb_strlen($trimmed) > $limit ? mb_substr($trimmed, 0, $limit).'…' : $trimmed;
    }

    private function toolsUnavailableReason(): ?string
    {
        foreach ([config('backups.pg_dump_binary'), config('backups.pg_restore_binary')] as $tool) {
            $outcome = $this->runner()->run([$this->binary($tool, 'pg_dump'), '--version'], [], 30);

            if (! $outcome->successful) {
                return 'Outils PostgreSQL indisponibles sur ce serveur.';
            }
        }

        return null;
    }
}
