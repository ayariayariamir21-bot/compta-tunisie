<?php

namespace App\Console\Commands;

use App\Services\Security\ProcessRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Non-destructive production deployment readiness check.
 *
 * Verifies environment, secrets, connectivity, storage writability,
 * public-storage safety, backup tooling, PHP extensions, OPcache and the
 * frontend build manifest. Never mutates application data; cache probing
 * writes and deletes one throwaway key.
 *
 * Exit code: 0 when no FAIL was raised (warnings allowed), 1 otherwise.
 */
final class ProductionCheck extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Run non-destructive production deployment readiness checks';

    /** @var list<string> */
    private array $passes = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $failures = [];

    public function handle(ProcessRunner $processRunner): int
    {
        // Command instances may be reused across invocations; always start
        // from a clean slate.
        $this->passes = [];
        $this->warnings = [];
        $this->failures = [];

        $isProduction = config('app.env') === 'production';

        $this->checkEnvironment($isProduction);
        $this->checkDatabase();
        $this->checkCache();
        $this->checkQueue();
        $this->checkStorageWritability();
        $this->checkPublicSafety();
        $this->checkBackups($processRunner, $isProduction);
        $this->checkExtensions();
        $this->checkOpcache();
        $this->checkFrontendBuild();

        foreach ($this->passes as $line) {
            $this->info("[PASS] {$line}");
        }

        foreach ($this->warnings as $line) {
            $this->warn("[WARN] {$line}");
        }

        foreach ($this->failures as $line) {
            $this->error("[FAIL] {$line}");
        }

        if ($this->failures !== []) {
            $this->error('RESULT: NOT READY');
            $this->line('Failures: '.count($this->failures).', warnings: '.count($this->warnings));

            return 1;
        }

        if ($this->warnings !== []) {
            $this->warn('RESULT: READY WITH WARNINGS');
            $this->line('Warnings: '.count($this->warnings));

            return 0;
        }

        $this->info('RESULT: READY');

        return 0;
    }

    private function checkEnvironment(bool $isProduction): void
    {
        if ($isProduction) {
            $this->passes[] = 'APP_ENV=production';
        } else {
            $this->warnings[] = "APP_ENV='".config('app.env')."' — la validation stricte s'applique en production uniquement";
        }

        if (! config('app.debug')) {
            $this->passes[] = 'APP_DEBUG=false';
        } elseif ($isProduction) {
            $this->failures[] = 'APP_DEBUG=true est interdit en production (fuites de stack traces)';
        } else {
            $this->warnings[] = 'APP_DEBUG=true — doit être false en production';
        }

        if (is_string(config('app.key')) && config('app.key') !== '') {
            $this->passes[] = 'APP_KEY configurée';
        } else {
            $this->failures[] = 'APP_KEY manquante';
        }

        $url = (string) config('app.url');

        if (str_starts_with($url, 'https://')) {
            $this->passes[] = 'APP_URL utilise HTTPS';
        } elseif ($isProduction) {
            $this->warnings[] = "APP_URL n'utilise pas HTTPS ('{$url}')";
        } else {
            $this->warnings[] = "APP_URL local — fournir l'URL HTTPS de production via l'environnement";
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->select('select 1');
            $this->passes[] = 'Base de données joignable';
        } catch (Throwable) {
            $this->failures[] = 'Base de données injoignable';

            return;
        }

        try {
            $migrated = DB::table('migrations')->count() > 0;
            $this->passes[] = $migrated ? 'Migrations présentes' : 'Aucune migration enregistrée';
        } catch (Throwable) {
            $this->warnings[] = 'Table migrations illisible';
        }
    }

    private function checkCache(): void
    {
        try {
            $key = 'production_check_probe_'.bin2hex(random_bytes(4));
            Cache::store()->put($key, true, 10);
            $worked = Cache::store()->get($key) === true;
            Cache::store()->forget($key);

            if ($worked) {
                $this->passes[] = "Cache '".config('cache.default')."' opérationnel";
            } else {
                $this->failures[] = "Cache '".config('cache.default')."' ne conserve pas les valeurs";
            }
        } catch (Throwable) {
            $this->failures[] = "Cache '".config('cache.default')."' indisponible";
        }
    }

    private function checkQueue(): void
    {
        $driver = (string) config('queue.default');

        if (in_array($driver, ['database', 'redis', 'sqs', 'sync'], true)) {
            $this->passes[] = "Queue '{$driver}' configurée".($driver === 'sync' ? ' (exécution synchrone)' : '');
        } else {
            $this->warnings[] = "Queue inconnue : '{$driver}'";
        }
    }

    private function checkStorageWritability(): void
    {
        $unwritable = [];

        foreach ([
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('logs'),
            storage_path('app/private'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            try {
                if (! is_dir($directory)) {
                    $unwritable[] = "{$directory} (absent)";

                    continue;
                }

                $probe = rtrim($directory, '/\\').'/production-check-'.bin2hex(random_bytes(3)).'.tmp';

                if (@file_put_contents($probe, 'probe') === false || ! @unlink($probe)) {
                    $unwritable[] = $directory;
                }
            } catch (Throwable) {
                $unwritable[] = $directory;
            }
        }

        if ($unwritable === []) {
            $this->passes[] = 'Stockage et bootstrap/cache accessibles en écriture';
        } else {
            foreach ($unwritable as $directory) {
                $this->failures[] = "Répertoire non accessible en écriture : {$directory}";
            }
        }
    }

    private function checkPublicSafety(): void
    {
        if (file_exists(public_path('.env'))) {
            $this->failures[] = '.env exposé dans public/ — danger immédiat';
        } else {
            $this->passes[] = '.env non exposé dans public/';
        }

        $publicStorage = public_path('storage');

        if (! file_exists($publicStorage)) {
            $this->passes[] = 'public/storage absent (aucun fichier servi depuis le stockage)';

            return;
        }

        $dumps = array_merge(
            glob($publicStorage.'/*.dump') ?: [],
            glob($publicStorage.'/**/*.dump') ?: [],
        );

        if ($dumps === []) {
            $this->passes[] = 'public/storage ne contient aucune sauvegarde .dump';
        } else {
            $this->failures[] = count($dumps).' sauvegarde(s) .dump exposée(s) sous public/storage';
        }
    }

    private function checkBackups(ProcessRunner $processRunner, bool $isProduction): void
    {
        $toolsAvailable = true;

        foreach ([config('backups.pg_dump_binary'), config('backups.pg_restore_binary')] as $binary) {
            $binary = is_string($binary) && $binary !== '' ? $binary : '';

            if ($binary === '' || ! $processRunner->run([$binary, '--version'])->successful) {
                $toolsAvailable = false;
            }
        }

        if ($toolsAvailable) {
            $this->passes[] = 'Outils PostgreSQL (pg_dump/pg_restore) disponibles';
        } else {
            $this->warnings[] = 'Outils PostgreSQL introuvables — les sauvegardes seront refusées';
        }

        $allowed = config('backups.allowed_environments');

        if (is_array($allowed) && ! in_array('production', $allowed, true)) {
            $this->passes[] = 'Restauration désactivée depuis l\'interface en production';
        } else {
            $this->failures[] = 'BACKUP_RESTORE_ENVIRONMENTS autorise la restauration en production — interdit';
        }
    }

    /**
     * @return list<string>
     */
    private function requiredExtensions(): array
    {
        return ['pdo_pgsql', 'mbstring', 'openssl', 'bcmath', 'dom', 'fileinfo', 'filter'];
    }

    private function checkExtensions(): void
    {
        $missing = array_values(array_filter(
            $this->requiredExtensions(),
            fn (string $extension): bool => ! extension_loaded($extension),
        ));

        if ($missing === []) {
            $this->passes[] = 'Extensions PHP requises présentes';
        } else {
            $this->failures[] = 'Extensions PHP manquantes : '.implode(', ', $missing);
        }
    }

    private function checkOpcache(): void
    {
        if (function_exists('opcache_get_status')) {
            $status = opcache_get_status(false);

            if (is_array($status) && ($status['opcache_enabled'] ?? false)) {
                $this->passes[] = 'OPcache activé';

                return;
            }
        }

        $this->warnings[] = 'OPcache non actif dans ce runtime — recommandé en production (validate_timestamps=0, memory_consumption>=128)';
    }

    private function checkFrontendBuild(): void
    {
        if (file_exists(public_path('build/manifest.json'))) {
            $this->passes[] = 'Build Vite présent (public/build/manifest.json)';
        } else {
            $this->warnings[] = 'Build Vite absent — exécuter npm run build avant le déploiement';
        }
    }
}
