<?php

declare(strict_types=1);

namespace App\Admin\Backup\Actions;

use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Jobs\RunBackupJob;
use App\Admin\Backup\Models\Backup;

/**
 * "Back up now": a backup made by hand, waiting in the list until a worker
 * makes it ({@see RunBackupJob}). It does not move the automatic schedule.
 */
class StartBackupAction
{
    public function execute(): Backup
    {
        $backup = Backup::query()->create([
            'trigger' => BackupTrigger::Manual,
            'status' => BackupStatus::Pending,
        ]);

        RunBackupJob::dispatch($backup);

        return $backup;
    }
}
