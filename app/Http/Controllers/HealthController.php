<?php

namespace App\Http\Controllers;

use App\Services\System\HealthCheckService;
use Illuminate\Http\JsonResponse;

/**
 * Minimal system health endpoints for load balancers and uptime probes.
 *
 * Returns only application/database/storage status — never credentials,
 * hostnames, versions, paths or stack traces. 200 when healthy/ready, 503
 * when a critical dependency is unavailable.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $health = app(HealthCheckService::class)->getHealth();
        $healthy = $health['status'] === 'ok';

        return response()->json($health, $healthy ? 200 : 503);
    }

    /**
     * Readiness probe: critical dependencies only (database, storage).
     */
    public function ready(): JsonResponse
    {
        $readiness = app(HealthCheckService::class)->getReadiness();

        return response()->json(
            [
                'status' => $readiness['status'],
                'database' => $readiness['database'],
                'storage' => $readiness['storage'],
            ],
            $readiness['ready'] ? 200 : 503,
        );
    }
}
