<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Minimal system health endpoint for load balancers and uptime probes.
 *
 * Returns only an application/database status — never credentials,
 * hostnames, versions, paths or stack traces. 200 when healthy, 503 when
 * the database is unreachable.
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            $database = 'unavailable';
        }

        return response()->json([
            'status' => $database === 'ok' ? 'ok' : 'unavailable',
            'database' => $database,
        ], $database === 'ok' ? 200 : 503);
    }
}
