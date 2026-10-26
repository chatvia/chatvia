<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\BackupSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * `php artisan chatvia:backup:create`: backs up the database and Redis now
 * (Settings → Backups decides what else goes in and the encryption). With
 * `--if-due`, as the scheduler runs it every hour, it backs up only when the
 * automatic backup is on and due ({@see BackupSchedule}).
 */
#[Description('Back up the database and Redis to the private storage. --if-due backs up only when the automatic backup is on and due.')]
#[Signature('chatvia:backup:create {--if-due : Only when the automatic backup (Settings → Backups) is on and due}')]
class CreateBackup extends Command
{
    public function handle(RunBackupAction $run, BackupSchedule $schedule): int
    {
        $scheduled = (bool) $this->option('if-due');

        if ($scheduled && ! $schedule->isDue()) {
            return self::SUCCESS;
        }

        $backup = $run->execute(Backup::query()->create([
            'trigger' => $scheduled ? BackupTrigger::Scheduled : BackupTrigger::Manual,
            'status' => BackupStatus::Pending,
        ]));

        if ($backup->status !== BackupStatus::Completed) {
            $this->error('The backup failed: '.$backup->error);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backed up to %s:%s (%s%s).',
            $backup->disk,
            $backup->path,
            Number::fileSize((int) $backup->size, precision: 1),
            $backup->isEncrypted() ? ', encrypted' : '',
        ));

        return self::SUCCESS;
    }
}
