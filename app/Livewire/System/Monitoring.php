<?php

namespace App\Livewire\System;

use App\Enums\HealthStatus;
use App\Models\User;
use App\Services\Security\BackupService;
use App\Services\System\HealthCheckService;
use App\Services\System\SystemAlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Operational monitoring dashboard (admin-only).
 *
 * Calls HealthCheckService/SystemAlertService directly — never its own HTTP
 * endpoints. Read-only: viewing this page performs no writes and no audit
 * events, and triggers no notifications.
 */
#[Layout('layouts.app')]
#[Title('Monitoring')]
final class Monitoring extends Component
{
    public function refresh(): void
    {
        // Manual refresh simply re-renders; checks run on every render.
    }

    private function ensureAuthorized(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('viewSystemMonitoring');
    }

    /**
     * @return array{status: HealthStatus, message: string, duration_ms: float}
     */
    private function card(HealthStatus $status, string $message, float $duration): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'duration_ms' => $duration,
        ];
    }

    public function render(): View
    {
        if (! $this->ensureAuthorized()) {
            abort(403);
        }

        $health = app(HealthCheckService::class);

        $application = $health->checkApplication();
        $database = $health->checkDatabase();
        $cache = $health->checkCache();
        $queue = $health->checkQueue();
        $storage = $health->checkStorage();
        $backups = $health->checkBackups();
        $scheduler = $health->checkScheduler();

        $overall = HealthStatus::worst([
            $application['status'],
            $database['status'],
            $cache['status'],
            $queue['status'],
            $storage['status'],
            $backups['status'],
            $scheduler['status'],
        ]);

        $latestBackup = is_array($backups['latest'] ?? null) ? $backups['latest'] : null;

        $cards = [
            'application' => $this->card($application['status'], $application['message'], $application['duration_ms']),
            'database' => $this->card($database['status'], $database['message'], $database['duration_ms']),
            'cache' => $this->card($cache['status'], $cache['message'], $cache['duration_ms']),
            'queue' => [
                ...$this->card($queue['status'], $queue['message'], $queue['duration_ms']),
                'driver' => $queue['driver'],
                'failed_jobs' => $queue['failed_jobs'],
            ],
            'storage' => $this->card($storage['status'], $storage['message'], $storage['duration_ms']),
            'backups' => [
                ...$this->card($backups['status'], $backups['message'], $backups['duration_ms']),
                'count' => $backups['count'],
                'latest' => $latestBackup,
            ],
            'scheduler' => $this->card($scheduler['status'], $scheduler['message'], $scheduler['duration_ms']),
        ];

        $alertsService = app(SystemAlertService::class);
        $alertList = $alertsService->getAlerts();

        return view('livewire.system.monitoring', [
            'overall' => $overall,
            'cards' => $cards,
            'alerts' => $alertList,
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'memory_current_mb' => round(memory_get_usage(true) / 1048576, 1),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'https_url' => str_starts_with((string) config('app.url'), 'https://'),
                'backup_tools' => app(BackupService::class)->toolsAvailable(),
                'failed_jobs_last' => $health->getFailedJobSummary(),
            ],
        ]);
    }
}
