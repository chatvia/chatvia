<?php

use App\Admin\Backup\Actions\PruneBackupsAction;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use Illuminate\Support\Facades\Storage;

/**
 * Pruning backups: the newest stored ones stay, older ones lose their
 * archive and their row, and failed attempts older than the oldest kept
 * backup go too.
 */
beforeEach(function (): void {
    Storage::fake('backups');
});

function pbaStored(int $daysAgo): Backup
{
    $backup = Backup::factory()->completed(now()->subDays($daysAgo))->create();
    Storage::disk('backups')->put((string) $backup->path, 'archive');

    return $backup;
}

it('keeps the newest backups and deletes the older ones with their archives', function (): void {
    $oldest = pbaStored(3);
    $middle = pbaStored(2);
    $newest = pbaStored(1);

    app(PruneBackupsAction::class)->execute(2);

    expect(Backup::query()->orderBy('id')->pluck('id')->all())->toBe([$middle->id, $newest->id])
        ->and(Storage::disk('backups')->exists((string) $oldest->path))->toBeFalse()
        ->and(Storage::disk('backups')->exists((string) $middle->path))->toBeTrue();
});

it('deletes failed attempts older than the oldest kept backup', function (): void {
    $oldFailure = Backup::factory()->create(['status' => BackupStatus::Failed]);
    $kept = pbaStored(1);
    $newFailure = Backup::factory()->create(['status' => BackupStatus::Failed]);

    app(PruneBackupsAction::class)->execute(1);

    expect(Backup::query()->orderBy('id')->pluck('id')->all())->toBe([$kept->id, $newFailure->id])
        ->and(Backup::query()->whereKey($oldFailure->id)->exists())->toBeFalse();
});

it('keeps every failure while there are fewer backups than the number to keep', function (): void {
    $failure = Backup::factory()->create(['status' => BackupStatus::Failed]);
    pbaStored(1);

    app(PruneBackupsAction::class)->execute(5);

    expect(Backup::query()->whereKey($failure->id)->exists())->toBeTrue();
});
