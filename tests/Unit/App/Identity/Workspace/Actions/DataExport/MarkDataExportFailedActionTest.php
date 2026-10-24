<?php

use App\Identity\Workspace\Actions\DataExport\MarkDataExportFailedAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Str;

/**
 * Marking a data export failed: the status flips to Failed and the stopping
 * error is kept, truncated so an oversized message cannot break the row.
 */
it('marks the export failed and records the truncated error', function (): void {
    $export = DataExport::factory()->processing()->create();

    app(MarkDataExportFailedAction::class)->execute($export, new RuntimeException(Str::repeat('x', 2000)));

    $fresh = $export->fresh();

    expect($fresh->status)->toBe(DataExportStatus::Failed)
        ->and($fresh->error_message)->toEndWith('...')
        ->and(Str::length((string) $fresh->error_message))->toBeLessThan(2000);
});
