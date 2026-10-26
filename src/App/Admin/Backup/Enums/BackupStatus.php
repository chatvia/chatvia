<?php

declare(strict_types=1);

namespace App\Admin\Backup\Enums;

/**
 * Where a backup is: waiting for a worker, being made, stored, or failed.
 * Stored in `backups.status`, which has a CHECK of these values.
 */
enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
