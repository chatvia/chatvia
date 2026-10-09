<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the ZIP of every data export whose download window has passed (and
 * of failed exports older than a week) and marks the export expired. The row
 * is kept as a record that an export was made. Returns how many exports were
 * pruned.
 */
class PruneExpiredDataExportsAction
{
    /** Failed exports are kept this long so the owner can see the error. */
    private const int FAILED_RETENTION_DAYS = 7;

    public function execute(): int
    {
        $disk = Storage::disk('local');
        $pruned = 0;

        DataExport::query()
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '<', now())
                ->orWhere(fn (Builder $failed) => $failed
                    ->where('status', DataExportStatus::Failed)
                    ->where('created_at', '<', now()->subDays(self::FAILED_RETENTION_DAYS))))
            ->chunkById(100, function (Collection $exports) use ($disk, &$pruned): void {
                foreach ($exports as $export) {
                    if ($export->file_path && $disk->exists($export->file_path)) {
                        $disk->delete($export->file_path);
                    }

                    $export->update([
                        'status' => DataExportStatus::Expired,
                        'file_path' => null,
                        'file_size' => null,
                    ]);

                    $pruned++;
                }
            });

        return $pruned;
    }
}
