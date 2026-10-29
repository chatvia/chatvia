<?php

use App\Identity\Workspace\Actions\DataExport\PruneExpiredDataExportsAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * The nightly prune deletes expired exports (archive and row) and failed ones
 * older than a week; a ready export still in its download window and a recent
 * failure stay.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->owner = User::factory()->workspace()->create();
});

/** A ready export of the owner whose archive is on the local disk. */
function pedeExportWithFile(User $owner, string $path, array $attributes = []): DataExport
{
    Storage::disk('local')->put($path, 'zip-bytes');

    return DataExport::factory()->ready($path)->create(['user_id' => $owner->id, ...$attributes]);
}

it('prunes expired exports, even without a file, and failed ones older than a week, then finds nothing more', function (): void {
    $expired = pedeExportWithFile($this->owner, 'exports/expired.zip', ['expires_at' => now()->subHour()]);
    $gone = pedeExportWithFile($this->owner, 'exports/gone.zip', ['expires_at' => now()->subHour()]);
    Storage::disk('local')->delete('exports/gone.zip');
    $current = pedeExportWithFile($this->owner, 'exports/current.zip');
    $oldFailure = DataExport::factory()->failed()->create(['user_id' => $this->owner->id]);
    $oldFailure->forceFill(['created_at' => now()->subDays(8)])->save();
    $recentFailure = DataExport::factory()->failed()->create(['user_id' => $this->owner->id]);
    $prune = app(PruneExpiredDataExportsAction::class);

    $pruned = $prune->execute();

    expect($pruned)->toBe(3)
        ->and($prune->execute())->toBe(0)
        ->and(DataExport::query()->whereKey([$expired->id, $gone->id, $oldFailure->id])->exists())->toBeFalse()
        ->and($current->fresh()->status)->toBe(DataExportStatus::Ready)
        ->and($recentFailure->fresh()->status)->toBe(DataExportStatus::Failed);
    Storage::disk('local')->assertMissing('exports/expired.zip');
    Storage::disk('local')->assertExists('exports/current.zip');
});
