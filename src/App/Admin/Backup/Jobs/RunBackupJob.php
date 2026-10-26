<?php

declare(strict_types=1);

namespace App\Admin\Backup\Jobs;

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Makes a backup an admin asked for ("Back up now") on a worker. The
 * scheduled backup runs in the scheduler instead (chatvia:backup:create).
 */
// Worked by the long `media` supervisor, through `redis-media`, whose
// retry_after outlasts the timeout below.
#[Queue('backups')]
// One try: the action saves a failure on the backup, and a retry would only
// make a second archive.
#[Tries(1)]
#[Timeout(8 * 3600)]
// A backup row deleted before the job runs needs no archive.
#[DeleteWhenMissingModels]
class RunBackupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Backup $backup) {}

    public function handle(RunBackupAction $action): void
    {
        $action->execute($this->backup);
    }
}
