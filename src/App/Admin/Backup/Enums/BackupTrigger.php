<?php

declare(strict_types=1);

namespace App\Admin\Backup\Enums;

/**
 * Why a backup was made: on the admin's schedule, or by an admin's "Back up
 * now". Only scheduled backups set when the next one is due. Stored in
 * `backups.trigger`, which has a CHECK of these values.
 */
enum BackupTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
}
