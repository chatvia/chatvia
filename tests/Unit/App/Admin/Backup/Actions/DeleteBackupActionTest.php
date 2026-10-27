<?php

use App\Admin\Backup\Actions\DeleteBackupAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a backup: the archive and the row go; a backup being made stays.
 */
beforeEach(function (): void {
    Storage::fake('backups');
});

it('deletes the archive and the row', function (): void {
    $backup = Backup::factory()->completed()->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');

    app(DeleteBackupAction::class)->execute($backup);

    expect(Backup::query()->exists())->toBeFalse()
        ->and(Storage::disk('backups')->exists((string) $backup->path))->toBeFalse();
});

it('deletes a failed backup that has no archive', function (): void {
    $backup = Backup::factory()->create(['status' => BackupStatus::Failed]);

    app(DeleteBackupAction::class)->execute($backup);

    expect(Backup::query()->exists())->toBeFalse();
});

it('keeps a backup that is being made', function (): void {
    $backup = Backup::factory()->create(['status' => BackupStatus::Running]);

    expect(fn () => app(DeleteBackupAction::class)->execute($backup))->toThrow(InvalidArgumentException::class);
    expect($backup->fresh())->not->toBeNull();
});
