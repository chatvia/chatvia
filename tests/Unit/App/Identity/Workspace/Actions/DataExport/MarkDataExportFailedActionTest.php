<?php

use App\Identity\Workspace\Actions\DataExport\MarkDataExportFailedAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;

/**
 * Marking a data export failed: the status flips to Failed and the stopping
 * error is kept, truncated so an oversized message cannot break the row.
 */
it('marks the export failed and records the truncated error', function (): void {
    $user = User::factory()->create();

    $export = DataExport::create([
        'user_id' => $user->id,
        'status' => DataExportStatus::Processing,
        'download_token' => str_repeat('a', 64),
    ]);

    app(MarkDataExportFailedAction::class)->execute($export, new RuntimeException(str_repeat('x', 2000)));

    $fresh = $export->fresh();

    expect($fresh->status)->toBe(DataExportStatus::Failed)
        ->and($fresh->error_message)->toEndWith('...')
        ->and(mb_strlen((string) $fresh->error_message))->toBeLessThan(2000);
});
