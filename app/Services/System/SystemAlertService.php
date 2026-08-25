<?php

namespace App\Services\System;

use App\Enums\CompanyRole;
use App\Enums\HealthStatus;
use App\Enums\NotificationSeverity;
use App\Models\User;
use App\Notifications\SecurityNotification;
use App\Services\Security\NotificationService;
use Throwable;

/**
 * Internal alert-condition detection over the health checks.
 *
 * Detects conditions; delivery is deliberately NOT external: important
 * state transitions are surfaced once to company admins through the
 * existing in-app notification system (deduplication keys include the day,
 * so a persistent condition cannot spam recipients), and everything is
 * visible on the admin monitoring dashboard.
 */
final class SystemAlertService
{
    /**
     * Every currently-detected alert condition.
     *
     * @return list<array{key: string, status: HealthStatus, message: string}>
     */
    public function getAlerts(): array
    {
        /** @var HealthCheckService $health */
        $health = app(HealthCheckService::class);

        $alerts = [];

        $database = $health->checkDatabase();

        if ($database['status'] === HealthStatus::Critical) {
            $alerts[] = [
                'key' => 'database.critical',
                'status' => HealthStatus::Critical,
                'message' => 'Base de données injoignable.',
            ];
        }

        $storage = $health->checkStorage();

        if ($storage['status'] === HealthStatus::Critical) {
            $alerts[] = [
                'key' => 'storage.critical',
                'status' => HealthStatus::Critical,
                'message' => 'Stockage non accessible en écriture.',
            ];
        }

        $backups = $health->checkBackups();

        if ($backups['latest'] === null) {
            $alerts[] = [
                'key' => 'backup.missing',
                'status' => HealthStatus::Warning,
                'message' => 'Aucune sauvegarde valide disponible.',
            ];
        } elseif ($backups['status'] === HealthStatus::Warning) {
            $age = $backups['latest']['age_hours'];
            $key = is_numeric($age)
                ? 'backup.overdue.'.now()->format('Ymd')
                : 'backup.overdue';

            $alerts[] = [
                'key' => $key,
                'status' => HealthStatus::Warning,
                'message' => 'Dernière sauvegarde valide trop ancienne'.(is_numeric($age) ? " ({$age} h)" : '').'.',
            ];
        }

        $queue = $health->checkQueue();
        $failedJobs = $queue['failed_jobs'];
        $threshold = max(1, (int) config('monitoring.failed_jobs_threshold', 5));

        if ($failedJobs > $threshold) {
            $alerts[] = [
                'key' => 'queue.failed_jobs.'.$failedJobs,
                'status' => HealthStatus::Warning,
                'message' => "{$failedJobs} job(s) échoué(s) (seuil : {$threshold}).",
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array{key: string, status: HealthStatus, message: string}>
     */
    public function getCriticalAlerts(): array
    {
        return array_values(array_filter(
            $this->getAlerts(),
            fn (array $alert): bool => $alert['status'] === HealthStatus::Critical,
        ));
    }

    public function hasCriticalAlerts(): bool
    {
        return $this->getCriticalAlerts() !== [];
    }

    /**
     * Surface new critical/warning conditions to company admins through the
     * in-app notification system. Deduplication happens in the notification
     * layer (identical unread notification short-circuits) AND via the day
     * bucket embedded in recurring keys, so refreshing monitoring never
     * spams anyone.
     *
     * Intended to be invoked from scheduled/console contexts where an
     * operator has opted into condition surfacing — never from page views.
     */
    public function notifyAdminsOfNewAlerts(User $actor): int
    {
        $sent = 0;

        try {
            /** @var NotificationService $notifications */
            $notifications = app(NotificationService::class);
            $company = null;

            // System-level alerts are not company-scoped; deliver to every
            // active company admin of the first company the actor admins,
            // or skip silently when none applies.
            $adminCompanies = $actor->companies()
                ->wherePivot('is_active', true)
                ->wherePivot('role', CompanyRole::Admin->value)
                ->get();

            foreach ($this->getAlerts() as $alert) {
                if ($alert['status'] !== HealthStatus::Critical && $alert['status'] !== HealthStatus::Warning) {
                    continue;
                }

                foreach ($adminCompanies as $company) {
                    $notifications->notifyCompanyRoles(
                        $company,
                        [CompanyRole::Admin],
                        new SecurityNotification(
                            title: 'Alerte système',
                            message: $alert['message'],
                            severity: $alert['status'] === HealthStatus::Critical
                                ? NotificationSeverity::Danger
                                : NotificationSeverity::Warning,
                            dedupKey: 'system_alert.'.$alert['key'].'.'.now()->format('Ymd'),
                            companyId: $company->id,
                        ),
                    );
                    $sent++;
                }
            }
        } catch (Throwable) {
            // Alerting must never itself become an incident.
        }

        return $sent;
    }
}
