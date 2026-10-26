<?php

declare(strict_types=1);

namespace App\Admin\Backup\Actions;

use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the newest $keep stored backups and deletes the older ones, their
 * archive first and then the row, so a row never points at a deleted file
 * while its archive stays. Failed attempts older than the oldest kept
 * backup go too: they no longer explain anything.
 */
class PruneBackupsAction
{
    public function execute(int $keep): void
    {
        $kept = Backup::query()
            ->where('status', BackupStatus::Completed)
            ->latest('id')
            ->limit($keep)
            ->pluck('id');

        Backup::query()
            ->where('status', BackupStatus::Completed)
            ->whereNotIn('id', $kept)
            ->each(function (Backup $backup): void {
                if (filled($backup->disk) && filled($backup->path)) {
                    Storage::disk($backup->disk)->delete($backup->path);
                }

                $backup->delete();
            });

        $oldestKept = $kept->min();

        if ($kept->count() >= $keep && $oldestKept !== null) {
            Backup::query()
                ->where('status', BackupStatus::Failed)
                ->where('id', '<', $oldestKept)
                ->delete();
        }
    }
}
