<?php

namespace App\Console\Commands;

use Domain\Conversation\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Description('Delete chat attachments older than the configured retention window from the public Scaleway bucket.')]
#[Signature('app:prune-chat-attachments {--days=30 : Retention in days; files older than this are deleted}')]
class PruneAttachments extends Command
{
    /**
     * Top-level prefixes the upload action writes to. Anything outside these
     * is left alone — the bucket is shared with other Chatvia assets.
     */
    private const PREFIXES = ['widget-uploads', 'chat-uploads'];

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('Days must be >= 1.');

            return self::FAILURE;
        }

        $cutoff = time() - ($days * 86400);
        $disk = Storage::disk(Attachment::DISK);

        $deleted = 0;
        $kept = 0;

        foreach (self::PREFIXES as $prefix) {
            foreach ($disk->allFiles($prefix) as $path) {
                $lastModified = $disk->lastModified($path);
                if ($lastModified < $cutoff) {
                    $disk->delete($path);
                    $deleted++;
                } else {
                    $kept++;
                }
            }
        }

        $this->info("Pruned {$deleted} attachment(s) older than {$days} day(s). Kept {$kept}.");

        return self::SUCCESS;
    }
}
