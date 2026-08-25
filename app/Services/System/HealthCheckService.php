<?php

namespace App\Services\System;

use App\Enums\HealthStatus;
use App\Services\Security\BackupService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lightweight internal health probing for the running application.
 *
 * Contract:
 *  - every check is read-only or self-cleaning (cache probe key is deleted)
 *  - checks are cheap enough for frequent use (no pg_dump, no checksum
 *    recomputation, no log parsing)
 *  - results never contain credentials, hosts, ports, database names,
 *    filesystem paths or raw exception messages
 *
 * The monitoring dashboard calls this service directly — never its own
 * HTTP endpoints.
 */
final class HealthCheckService
{
    /**
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    public function checkApplication(): array
    {
        $start = microtime(true);

        return [
            'status' => HealthStatus::Ok,
            'message' => 'Application opérationnelle',
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * Read-only connectivity + latency probe (SELECT 1).
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            return [
                'status' => HealthStatus::Critical,
                'message' => 'Base de données injoignable',
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        }

        return [
            'status' => HealthStatus::Ok,
            'message' => 'Base de données joignable',
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * Short-lived write/read/delete probe on the configured store. A cache
     * failure degrades to WARNING when the default store is not the primary
     * session/queue backend; here sessions and queue use their own tables,
     * so a cache outage is treated as WARNING.
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    public function checkCache(): array
    {
        $start = microtime(true);
        $key = 'health_probe_'.bin2hex(random_bytes(4));

        try {
            Cache::store()->put($key, true, 10);

            if (Cache::store()->get($key) !== true) {
                return [
                    'status' => HealthStatus::Warning,
                    'message' => "Cache '".config('cache.default')."' ne conserve pas les valeurs",
                    'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                ];
            }

            Cache::store()->forget($key);
        } catch (Throwable) {
            return [
                'status' => HealthStatus::Warning,
                'message' => "Cache '".config('cache.default')."' indisponible",
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        }

        return [
            'status' => HealthStatus::Ok,
            'message' => "Cache '".config('cache.default')."' opérationnel",
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * Queue infrastructure status: driver validity plus failed-jobs count.
     * No worker heartbeat is invented; synchronous notifications mean no
     * worker is strictly required today.
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float, driver: string, failed_jobs: int}
     */
    public function checkQueue(): array
    {
        $start = microtime(true);
        $driver = (string) config('queue.default');

        if (! in_array($driver, ['database', 'redis', 'sqs', 'sync'], true)) {
            return [
                'status' => HealthStatus::Warning,
                'message' => "Driver de file inconnu : '{$driver}'",
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                'driver' => $driver,
                'failed_jobs' => 0,
            ];
        }

        $failedJobs = 0;

        try {
            if ($this->failedJobsTableExists()) {
                $failedJobs = (int) DB::table('failed_jobs')->count();
            }
        } catch (Throwable) {
            // Table introspection must never fail the whole check.
        }

        $threshold = max(0, (int) config('monitoring.failed_jobs_threshold', 5));
        $status = HealthStatus::Ok;
        $message = match (true) {
            $driver === 'sync' => 'Exécution synchrone — aucun worker requis',
            $failedJobs === 0 => "File '{$driver}' saine, aucun job échoué",
            $failedJobs <= $threshold => "{$failedJobs} job(s) échoué(s)",
            default => "{$failedJobs} job(s) échoué(s) — seuil dépassé",
        };

        if ($failedJobs > $threshold) {
            $status = HealthStatus::Warning;
        }

        return [
            'status' => $status,
            'message' => $message,
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
            'driver' => $driver,
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * Most recent failed-job information without payload contents.
     *
     * @return array{count: int, last_failed_at: ?string, last_job: ?string}
     */
    public function getFailedJobSummary(): array
    {
        try {
            if (! $this->failedJobsTableExists()) {
                return ['count' => 0, 'last_failed_at' => null, 'last_job' => null];
            }

            $row = DB::table('failed_jobs')
                ->orderByDesc('id')
                ->first(['id', 'uuid', 'exception', 'failed_at']);

            if ($row === null) {
                return ['count' => 0, 'last_failed_at' => null, 'last_job' => null];
            }

            // Derive a job label from the exception head line only; payloads
            // are never exposed.
            $head = strtok((string) $row->exception, "\n") ?: '';
            $label = strlen($head) > 80 ? substr($head, 0, 77).'…' : $head;

            return [
                'count' => (int) DB::table('failed_jobs')->count(),
                'last_failed_at' => $row->failed_at !== null
                    ? Carbon::parse($row->failed_at)->format('d/m/Y H:i')
                    : null,
                'last_job' => $label !== '' ? $label : null,
            ];
        } catch (Throwable) {
            return ['count' => 0, 'last_failed_at' => null, 'last_job' => null];
        }
    }

    /**
     * Storage and bootstrap/cache writability. Absolute paths are never
     * reported back.
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    public function checkStorage(): array
    {
        $start = microtime(true);

        $directories = [
            'framework/views',
            'framework/cache',
            'framework/sessions',
            'logs',
            'app/private',
        ];

        foreach ($directories as $relative) {
            if (! $this->isWritableProbe(storage_path($relative))) {
                return [
                    'status' => HealthStatus::Critical,
                    'message' => "Stockage non accessible en écriture : {$relative}",
                    'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                ];
            }
        }

        if (! $this->isWritableProbe(base_path('bootstrap/cache'))) {
            return [
                'status' => HealthStatus::Critical,
                'message' => 'bootstrap/cache non accessible en écriture',
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        }

        return [
            'status' => HealthStatus::Ok,
            'message' => 'Stockage accessible en écriture',
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * Backup health from stored sidecar metadata only — never runs pg_dump
     * nor recomputes checksums of large archives.
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float, count: int, latest: ?array<string, mixed>}
     */
    public function checkBackups(): array
    {
        $start = microtime(true);

        try {
            /** @var BackupService $backups */
            $backups = app(BackupService::class);

            $rows = collect($backups->listBackups());
            $toolsAvailable = $backups->toolsAvailable();
            $maxAgeHours = max(1, (int) config('monitoring.backup_max_age_hours', 24));

            $validRows = $rows->filter(
                fn (array $row): bool => $row['metadata'] === true && $row['checksum_status'] === 'ok',
            );

            $latest = $validRows->sortByDesc('filename')->first();

            if ($latest === null) {
                return [
                    'status' => HealthStatus::Warning,
                    'message' => $rows->isEmpty()
                        ? 'Aucune sauvegarde disponible'
                        : 'Aucune sauvegarde valide (métadonnées ou empreinte douteuse)',
                    'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                    'count' => $rows->count(),
                    'latest' => null,
                ];
            }

            /** @var array<string, mixed> $latest */
            $createdAt = isset($latest['created_at']) && is_string($latest['created_at'])
                ? CarbonImmutable::parse($latest['created_at'])
                : null;

            $ageHours = $createdAt !== null ? $createdAt->diffInHours(now()) : null;

            $sizeBytes = max(0, (int) ($latest['size'] ?? 0));
            $summary = [
                'filename' => is_string($latest['filename'] ?? null) ? $latest['filename'] : '',
                'created_at' => $createdAt?->format('d/m/Y H:i'),
                'age_hours' => $ageHours !== null ? round($ageHours, 1) : null,
                'size_human' => $sizeBytes >= 1048576
                    ? round($sizeBytes / 1048576, 1).' Mo'
                    : round($sizeBytes / 1024, 1).' Ko',
                'checksum_status' => is_string($latest['checksum_status'] ?? null)
                    ? $latest['checksum_status']
                    : 'inconnu',
            ];

            if ($ageHours === null || $ageHours > $maxAgeHours) {
                return [
                    'status' => HealthStatus::Warning,
                    'message' => 'Dernière sauvegarde trop ancienne (> '.$maxAgeHours.' h)',
                    'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                    'count' => $rows->count(),
                    'latest' => $summary,
                ];
            }

            return [
                'status' => HealthStatus::Ok,
                'message' => 'Sauvegardes récentes et valides'.($toolsAvailable ? '' : ' (outils pg_dump indisponibles)'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                'count' => $rows->count(),
                'latest' => $summary,
            ];
        } catch (Throwable) {
            return [
                'status' => HealthStatus::Warning,
                'message' => 'État des sauvegardes indéterminable',
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
                'count' => 0,
                'latest' => null,
            ];
        }
    }

    /**
     * Scheduler status. Honest by design: with no scheduled tasks there is
     * nothing to monitor and no fake heartbeat is produced.
     *
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    public function checkScheduler(): array
    {
        $start = microtime(true);

        $scheduled = collect(app(Schedule::class)->events());

        if ($scheduled->isEmpty()) {
            return [
                'status' => HealthStatus::Ok,
                'message' => 'Aucune tâche planifiée configurée',
                'duration_ms' => round((microtime(true) - $start) * 1000, 1),
            ];
        }

        return [
            'status' => HealthStatus::Ok,
            'message' => $scheduled->count().' tâche(s) planifiée(s)',
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /**
     * Liveness payload for GET /health — minimal by contract.
     *
     * @return array{status: string, database: string}
     */
    public function getHealth(): array
    {
        $database = $this->checkDatabase();

        return [
            'status' => $database['status'] === HealthStatus::Ok ? 'ok' : 'unavailable',
            'database' => $database['status'] === HealthStatus::Ok ? 'ok' : 'unavailable',
        ];
    }

    /**
     * Readiness payload for GET /health/ready — critical dependencies only.
     *
     * @return array{status: string, database: string, storage: string, ready: bool}
     */
    public function getReadiness(): array
    {
        $database = $this->checkDatabase();
        $storage = $this->checkStorage();

        $ready = $database['status'] !== HealthStatus::Critical
            && $storage['status'] !== HealthStatus::Critical;

        return [
            'status' => $ready ? 'ready' : 'unavailable',
            'database' => $database['status'] === HealthStatus::Critical ? 'unavailable' : 'ok',
            'storage' => $storage['status'] === HealthStatus::Critical ? 'unavailable' : 'ok',
            'ready' => $ready,
        ];
    }

    private function isWritableProbe(string $directory): bool
    {
        try {
            if (! is_dir($directory)) {
                return false;
            }

            $probe = rtrim($directory, '/\\').'/health-probe-'.bin2hex(random_bytes(3)).'.tmp';

            return @file_put_contents($probe, 'probe') !== false && @unlink($probe);
        } catch (Throwable) {
            return false;
        }
    }

    private function failedJobsTableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $exists = DB::connection()->getSchemaBuilder()->hasTable('failed_jobs');
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
