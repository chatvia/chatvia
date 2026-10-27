<?php

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Actions\StartBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Jobs\RunBackupJob;
use App\Admin\Backup\Models\Backup;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Queue;

/**
 * "Back up now": a waiting backup made by hand, handed to a worker that runs
 * it once, on a queue the long `media` supervisor works, within that
 * connection's retry_after.
 */
it('creates a waiting backup made by hand and queues it', function (): void {
    Queue::fake();

    $backup = app(StartBackupAction::class)->execute();

    expect($backup->trigger)->toBe(BackupTrigger::Manual)
        ->and($backup->status)->toBe(BackupStatus::Pending);
    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job): bool => $job->backup->is($backup));
});

it('queues the backup on the long media supervisor, once', function (): void {
    expect(jobQueue(RunBackupJob::class))->toBe('backups')
        ->and(config('horizon.defaults.media.queue'))->toContain('backups')
        ->and(jobTimeout(RunBackupJob::class))->toBeLessThan((int) config('queue.connections.redis-media.retry_after'))
        ->and(jobSetting(RunBackupJob::class, Tries::class, 'tries'))->toBe(1);
});

it('runs the queued backup through the backup action', function (): void {
    $this->mock(RunBackupAction::class)
        ->shouldReceive('execute')->once()
        ->withArgs(fn (Backup $given): bool => $given->trigger === BackupTrigger::Manual)
        ->andReturnUsing(fn (Backup $given): Backup => $given);

    app(StartBackupAction::class)->execute();
});
