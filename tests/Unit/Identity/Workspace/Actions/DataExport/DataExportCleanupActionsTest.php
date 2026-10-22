<?php

use App\Identity\Workspace\Actions\DataExport\DeleteDataExportAction;
use App\Identity\Workspace\Actions\DataExport\PruneExpiredDataExportsAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
    $this->owner = User::factory()->workspace()->create();
});

function dataExportWithFile(User $owner, string $path, array $attributes = []): DataExport
{
    Storage::disk('local')->put($path, 'zip-bytes');

    return DataExport::query()->create([
        'user_id' => $owner->id,
        'download_token' => Str::random(64),
        'status' => DataExportStatus::Ready,
        'file_path' => $path,
        'file_size' => 9,
        'expires_at' => now()->addDay(),
        ...$attributes,
    ]);
}

it('deletes the export file and its row', function (): void {
    $export = dataExportWithFile($this->owner, 'exports/delete-me.zip');

    app(DeleteDataExportAction::class)->execute($export);

    expect(DataExport::query()->whereKey($export->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('exports/delete-me.zip');
});

it('prunes expired exports and failed ones older than a week, keeping the rest', function (): void {
    $expired = dataExportWithFile($this->owner, 'exports/expired.zip', ['expires_at' => now()->subHour()]);
    $current = dataExportWithFile($this->owner, 'exports/current.zip');
    $oldFailure = DataExport::query()->create([
        'user_id' => $this->owner->id,
        'download_token' => Str::random(64),
        'status' => DataExportStatus::Failed,
    ]);
    $oldFailure->forceFill(['created_at' => now()->subDays(8)])->save();

    $recentFailure = DataExport::query()->create([
        'user_id' => $this->owner->id,
        'download_token' => Str::random(64),
        'status' => DataExportStatus::Failed,
    ]);

    expect(app(PruneExpiredDataExportsAction::class)->execute())->toBe(2)
        ->and(DataExport::query()->whereKey([$expired->id, $oldFailure->id])->exists())->toBeFalse()
        ->and($current->fresh()->status)->toBe(DataExportStatus::Ready)
        ->and($recentFailure->fresh()->status)->toBe(DataExportStatus::Failed);

    Storage::disk('local')->assertMissing('exports/expired.zip');
    Storage::disk('local')->assertExists('exports/current.zip');
});

it('finds nothing to prune on the next run', function (): void {
    dataExportWithFile($this->owner, 'exports/expired.zip', ['expires_at' => now()->subHour()]);

    $prune = app(PruneExpiredDataExportsAction::class);

    expect($prune->execute())->toBe(1)
        ->and($prune->execute())->toBe(0);
});

it('prunes an expired export whose file is already gone', function (): void {
    $export = dataExportWithFile($this->owner, 'exports/gone.zip', ['expires_at' => now()->subHour()]);
    Storage::disk('local')->delete('exports/gone.zip');

    expect(app(PruneExpiredDataExportsAction::class)->execute())->toBe(1)
        ->and(DataExport::query()->whereKey($export->id)->exists())->toBeFalse();
});
