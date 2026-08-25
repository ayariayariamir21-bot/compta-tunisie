<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Thresholds
    |--------------------------------------------------------------------------
    |
    | Internal operational-health thresholds. These are operational defaults,
    | not legal/business requirements; tune them per deployment.
    |
    */

    // Latest valid backup older than this many hours is flagged.
    'backup_max_age_hours' => env('BACKUP_HEALTH_MAX_AGE_HOURS', 24),

    // More failed jobs than this count raises an alert condition.
    'failed_jobs_threshold' => (int) env('MONITORING_FAILED_JOBS_THRESHOLD', 5),

];
