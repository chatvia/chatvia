<?php

use App\Identity\Workspace\Actions\DataExport\DeleteDataExportAction;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a data export removes its archive from the local disk together
 * with its row.
 */
it('deletes the export file and its row', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('exports/delete-me.zip', 'zip-bytes');
    $export = DataExport::factory()->ready('exports/delete-me.zip')->create(['user_id' => User::factory()->create()->id]);

    app(DeleteDataExportAction::class)->execute($export);

    expect(DataExport::query()->whereKey($export->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('exports/delete-me.zip');
});
