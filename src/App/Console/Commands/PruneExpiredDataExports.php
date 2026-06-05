<?php

namespace App\Console\Commands;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Description('Delete GDPR data-export ZIPs whose 24-hour download window has passed.')]
#[Signature('app:prune-expired-data-exports')]
class PruneExpiredDataExports extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;

        DataExport::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<', now())
                    ->orWhere(function ($q): void {
                        $q->where('status', DataExportStatus::Failed)
                            ->where('created_at', '<', now()->subDays(7));
                    });
            })
            ->chunkById(100, function ($chunk) use ($disk, &$deleted): void {
                foreach ($chunk as $export) {
                    if ($export->file_path && $disk->exists($export->file_path)) {
                        $disk->delete($export->file_path);
                    }

                    $export->update([
                        'status' => DataExportStatus::Expired,
                        'file_path' => null,
                        'file_size' => null,
                    ]);

                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} expired data export(s).");

        return self::SUCCESS;
    }
}
