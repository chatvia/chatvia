<?php

use App\Admin\Backup\Actions\DeleteBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a backup: the archive and the row go, also for a failed backup
 * with no archive; a backup being made stays.
 */
beforeEach(function (): void {
    Storage::fake('backups');
});

it('deletes the archive and the row, and a failed backup with no archive', function (): void {
    $backup = Backup::factory()->completed()->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');
    $failed = Backup::factory()->create(['status' => BackupStatus::Failed]);

    app(DeleteBackupAction::class)->execute($backup);
    app(DeleteBackupAction::class)->execute($failed);

    expect(Backup::query()->exists())->toBeFalse()
        ->and(Storage::disk('backups')->exists((string) $backup->path))->toBeFalse();
});

it('keeps a backup that is being made', function (): void {
    $backup = Backup::factory()->create(['status' => BackupStatus::Running]);

    expect(fn () => app(DeleteBackupAction::class)->execute($backup))->toThrow(InvalidArgumentException::class)
        ->and($backup->fresh())->not->toBeNull();
});
