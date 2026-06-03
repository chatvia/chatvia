<?php

namespace App\Console\Commands;

use Domain\Conversation\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Soft-delete chat conversations past their expires_at horizon (per-plan retention). Cascades to messages, attachments, and S3 objects via Eloquent.')]
#[Signature('app:prune-expired-conversations {--chunk=200 : How many rows to delete per batch}')]
class PruneExpiredConversations extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $deleted = 0;

        // Iterate one chunk at a time and call ->delete() per row so the
        // Conversation::booted cascade fires — that's what wipes the
        // chat_attachments rows (and their S3 objects). A bulk UPDATE
        // would skip the model events, leaving files orphaned.
        Conversation::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunk, function ($conversations) use (&$deleted): void {
                foreach ($conversations as $conversation) {
                    $conversation->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} expired conversation(s).");

        return self::SUCCESS;
    }
}
