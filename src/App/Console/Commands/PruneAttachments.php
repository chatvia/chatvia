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
     * Attachments live at `users/{external_id}/agents/{external_id}/attachments/…`
     * on the public bucket. The `users/` tree is shared with other per-user
     * assets (profile photos, etc.), so we walk it but only ever delete files
     * inside an `/attachments/` segment — everything else is left alone.
     */
    private const ROOT_PREFIX = 'users';

    private const ATTACHMENT_SEGMENT = '/attachments/';

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

        foreach ($disk->allFiles(self::ROOT_PREFIX) as $path) {
            // Never touch sibling per-user assets that share the `users/`
            // prefix (profile photos, etc.) — only files under `…/attachments/…`.
            if (! str_contains($path, self::ATTACHMENT_SEGMENT)) {
                continue;
            }

            $lastModified = $disk->lastModified($path);
            if ($lastModified < $cutoff) {
                $disk->delete($path);
                $deleted++;
            } else {
                $kept++;
            }
        }

        $this->info("Pruned {$deleted} attachment(s) older than {$days} day(s). Kept {$kept}.");

        return self::SUCCESS;
    }
}
