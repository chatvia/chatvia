<?php

namespace App\Console\Commands;

use Domain\Conversation\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Prune chat conversations past their expires_at horizon (per-plan retention): soft-delete at the horizon, hard-purge after a grace window so the chat tables actually shrink.')]
#[Signature('app:prune-expired-conversations {--chunk=200 : How many rows to delete per batch} {--grace-days=30 : Days a soft-deleted conversation is kept before the hard purge}')]
class PruneExpiredConversations extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $graceDays = max(0, (int) $this->option('grace-days'));

        $softDeleted = $this->softDeleteExpired($chunk);
        $purged = $this->purgeTrashed($chunk, $graceDays);
        $orphanSessions = $this->deleteOrphanSessions();

        $this->info("Pruned {$softDeleted} expired conversation(s); purged {$purged} past the grace window; removed {$orphanSessions} orphaned session(s).");

        return self::SUCCESS;
    }

    private function softDeleteExpired(int $chunk): int
    {
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

        return $deleted;
    }

    /**
     * Hard-delete conversations soft-deleted past the grace window so the
     * chat tables actually shrink; chat_messages go via the FK cascade.
     */
    private function purgeTrashed(int $chunk, int $graceDays): int
    {
        $purged = 0;

        Conversation::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($graceDays))
            ->orderBy('id')
            ->chunkById($chunk, function ($conversations) use (&$purged): void {
                foreach ($conversations as $conversation) {
                    $conversation->forceDelete();
                    $purged++;
                }
            });

        return $purged;
    }

    /** Anonymous sessions with no conversations left, idle 90+ days. */
    private function deleteOrphanSessions(): int
    {
        return DB::table('chat_sessions')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('chat_conversations')
                    ->whereColumn('chat_conversations.chat_session_id', 'chat_sessions.id');
            })
            ->whereNull('user_id')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }
}
