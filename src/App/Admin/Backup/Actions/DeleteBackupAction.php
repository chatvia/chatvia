<?php

declare(strict_types=1);

namespace App\Admin\Backup\Actions;

use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Deletes one backup an admin picked: its archive first, then its row, so
 * the list never shows a backup whose file is gone while the file stays. A
 * backup that is being made cannot be deleted: its archive is not there yet.
 */
class DeleteBackupAction
{
    public function execute(Backup $backup): void
    {
        if ($backup->status === BackupStatus::Running) {
            throw new InvalidArgumentException('This backup is being made. Delete it when it is done.');
        }

        if (filled($backup->disk) && filled($backup->path)) {
            Storage::disk($backup->disk)->delete($backup->path);
        }

        $backup->delete();
    }
}
