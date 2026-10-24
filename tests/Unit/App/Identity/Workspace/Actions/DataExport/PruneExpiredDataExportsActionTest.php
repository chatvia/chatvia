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

it('prunes expired exports and failed ones older than a week, keeping the rest', function (): void {
    $expired = pedeExportWithFile($this->owner, 'exports/expired.zip', ['expires_at' => now()->subHour()]);
    $current = pedeExportWithFile($this->owner, 'exports/current.zip');
    $oldFailure = DataExport::factory()->failed()->create(['user_id' => $this->owner->id]);
    $oldFailure->forceFill(['created_at' => now()->subDays(8)])->save();

    $recentFailure = DataExport::factory()->failed()->create(['user_id' => $this->owner->id]);

    expect(app(PruneExpiredDataExportsAction::class)->execute())->toBe(2)
        ->and(DataExport::query()->whereKey([$expired->id, $oldFailure->id])->exists())->toBeFalse()
        ->and($current->fresh()->status)->toBe(DataExportStatus::Ready)
        ->and($recentFailure->fresh()->status)->toBe(DataExportStatus::Failed);

    Storage::disk('local')->assertMissing('exports/expired.zip');
    Storage::disk('local')->assertExists('exports/current.zip');
});

it('finds nothing to prune on the next run', function (): void {
    pedeExportWithFile($this->owner, 'exports/expired.zip', ['expires_at' => now()->subHour()]);

    $prune = app(PruneExpiredDataExportsAction::class);

    expect($prune->execute())->toBe(1)
        ->and($prune->execute())->toBe(0);
});

it('prunes an expired export whose file is already gone', function (): void {
    $export = pedeExportWithFile($this->owner, 'exports/gone.zip', ['expires_at' => now()->subHour()]);
    Storage::disk('local')->delete('exports/gone.zip');

    expect(app(PruneExpiredDataExportsAction::class)->execute())->toBe(1)
        ->and(DataExport::query()->whereKey($export->id)->exists())->toBeFalse();
});
