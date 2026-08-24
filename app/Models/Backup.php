<?php

namespace App\Models;

/**
 * Value object representing a database backup for authorization purposes.
 *
 * Backups are filesystem records (dump + sidecar metadata) without a
 * database table; this class gives the access layer a stable resource type
 * so BackupPolicy can be resolved through the standard Gate machinery.
 * The filename is never trusted on its own: services re-validate it against
 * the deterministic naming pattern before any filesystem operation.
 */
final class Backup
{
    public function __construct(
        public readonly string $filename,
    ) {}
}
