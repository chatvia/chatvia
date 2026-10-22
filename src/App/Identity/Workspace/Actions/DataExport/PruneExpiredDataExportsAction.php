<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes every data export whose download window has passed, and failed
 * exports older than a week: first the ZIP, then the row. Nothing shows an
 * export past that point (the export page lists only one being prepared, a
 * failed one or a ready one that has not expired), so the row would only make
 * every later run read it again. The two kinds are read apart, so the expiry
 * read can use the `expires_at` index. Returns how many exports were pruned.
 */
class PruneExpiredDataExportsAction
{
    /** Failed exports are kept this long so the owner can see the error. */
    private const int FAILED_RETENTION_DAYS = 7;

    public function execute(): int
    {
        $disk = Storage::disk('local');

        return $this->prune($disk, DataExport::query()->where('expires_at', '<', now()))
            + $this->prune($disk, DataExport::query()
                ->where('status', DataExportStatus::Failed)
                ->where('created_at', '<', now()->subDays(self::FAILED_RETENTION_DAYS)));
    }

    /**
     * @param  Builder<DataExport>  $exports
     */
    private function prune(Filesystem $disk, Builder $exports): int
    {
        $pruned = 0;

        $exports->chunkById(100, function (Collection $batch) use ($disk, &$pruned): void {
            foreach ($batch as $export) {
                if ($export->file_path !== null) {
                    $disk->delete($export->file_path);
                }

                $export->delete();
                $pruned++;
            }
        });

        return $pruned;
    }
}
