<?php

use App\Admin\Backup\Actions\StartBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Jobs\RunBackupJob;
use Illuminate\Support\Facades\Queue;

/**
 * "Back up now": a waiting backup made by hand, handed to a worker.
 */
it('creates a waiting backup made by hand and queues it', function (): void {
    Queue::fake();

    $backup = app(StartBackupAction::class)->execute();

    expect($backup->trigger)->toBe(BackupTrigger::Manual)
        ->and($backup->status)->toBe(BackupStatus::Pending);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backup->is($backup));
});
