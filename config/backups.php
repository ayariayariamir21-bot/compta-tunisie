<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage
    |--------------------------------------------------------------------------
    |
    | Backups are written to a private filesystem disk. The default "local"
    | disk points to storage/app/private which is never exposed over HTTP.
    | Never point this disk at "public" or any symlinked location.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    'directory' => env('BACKUP_DIRECTORY', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Client Tools
    |--------------------------------------------------------------------------
    |
    | Native pg_dump / pg_restore binaries. Only the binary path is
    | configurable; database credentials always come from the existing
    | "pgsql" database connection configuration and are passed to the
    | processes through the PGPASSWORD environment variable, never as
    | command line arguments.
    |
    */

    'pg_dump_binary' => env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),

    'pg_restore_binary' => env('BACKUP_PG_RESTORE_BINARY', 'pg_restore'),

    /*
    |--------------------------------------------------------------------------
    | Restore Safety
    |--------------------------------------------------------------------------
    |
    | Restores executed from the web interface are restricted to the listed
    | application environments (production is intentionally excluded) and
    | require typing CONFIRMATION_PHRASE exactly. A safety backup of the
    | current database is mandatory before any restore.
    |
    */

    'allowed_environments' => array_values(array_filter(explode(',', (string) env('BACKUP_RESTORE_ENVIRONMENTS', 'local,staging')))),

    'confirmation_phrase' => env('BACKUP_CONFIRMATION_PHRASE', 'RESTAURER LA BASE'),

    /*
    |--------------------------------------------------------------------------
    | Process Behaviour
    |--------------------------------------------------------------------------
    |
    | timeout: maximum seconds for one pg_dump / pg_restore process.
    | maintenance_during_restore: when enabled, the restore controller puts
    | the application into maintenance mode ("php artisan down") for the
    | duration of the restore and brings it back up afterwards, in a
    | try/finally block. Keep it disabled until an operational runbook
    | decides how downtime is communicated; a timed-out request would
    | otherwise leave the site down.
    |
    */

    'timeout' => (int) env('BACKUP_TIMEOUT', 900),

    'maintenance_during_restore' => (bool) env('BACKUP_MAINTENANCE_DURING_RESTORE', false),

];
