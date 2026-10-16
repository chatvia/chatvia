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

    expect(app(PruneExpiredDataExportsAction::class)->execute())->toBe(2)
        ->and($expired->fresh()->status)->toBe(DataExportStatus::Expired)
        ->and($expired->fresh()->file_path)->toBeNull()
        ->and($oldFailure->fresh()->status)->toBe(DataExportStatus::Expired)
        ->and($current->fresh()->status)->toBe(DataExportStatus::Ready);

    Storage::disk('local')->assertMissing('exports/expired.zip');
    Storage::disk('local')->assertExists('exports/current.zip');
});
