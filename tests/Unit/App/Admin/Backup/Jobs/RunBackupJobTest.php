<?php

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Jobs\RunBackupJob;
use App\Admin\Backup\Models\Backup;
use Illuminate\Queue\Attributes\Tries;

/**
 * The "Back up now" job: it runs the backup it was given once, on a queue
 * the long `media` supervisor works, within that connection's retry_after.
 */
it('runs the backup on the long media supervisor, once', function (): void {
    expect(jobQueue(RunBackupJob::class))->toBe('backups')
        ->and(config('horizon.defaults.media.queue'))->toContain('backups')
        ->and(jobTimeout(RunBackupJob::class))->toBeLessThan((int) config('queue.connections.redis-media.retry_after'))
        ->and(jobSetting(RunBackupJob::class, Tries::class, 'tries'))->toBe(1);
});

it('hands the backup to the action', function (): void {
    $backup = Backup::factory()->create();
    $this->mock(RunBackupAction::class)
        ->shouldReceive('execute')->once()->withArgs(fn (Backup $given): bool => $given->is($backup))->andReturn($backup);

    app()->call([new RunBackupJob($backup), 'handle']);
});
