<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Conversation\Actions\Retention\PruneExpiredConversationsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily conversation retention sweep (see PruneExpiredConversationsAction).
 */
#[Description('Prune chat conversations past their expires_at horizon (per-plan retention): soft-delete at the horizon, hard-purge after a grace window so the chat tables actually shrink.')]
#[Signature('app:prune-expired-conversations {--chunk=200 : How many rows to delete per batch} {--grace-days=30 : Days a soft-deleted conversation is kept before the hard purge}')]
class PruneExpiredConversations extends Command
{
    public function handle(PruneExpiredConversationsAction $pruneConversations): int
    {
        $result = $pruneConversations->execute(
            chunk: max(1, (int) $this->option('chunk')),
            graceDays: max(0, (int) $this->option('grace-days')),
        );

        $this->info("Pruned {$result->expired} expired + {$result->overridden} retention-override conversation(s); purged {$result->purged} past the grace window; removed {$result->orphanSessions} orphaned session(s).");

        return self::SUCCESS;
    }
}
