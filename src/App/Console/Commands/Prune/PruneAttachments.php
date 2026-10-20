<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use Domain\Conversation\Actions\Retention\PruneExpiredAttachmentsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily sweep of uploads that never joined a conversation (see
 * PruneExpiredAttachmentsAction). A conversation's own files go when the
 * conversation is deleted.
 */
#[Description('Delete chat uploads that never joined a conversation, together with their files.')]
#[Signature('chatvia:prune:attachments {--days=30 : Days an upload that never joined a conversation is kept} {--chunk=200 : How many rows to delete per batch}')]
class PruneAttachments extends Command
{
    public function handle(PruneExpiredAttachmentsAction $pruneAttachments): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be >= 1.');

            return self::FAILURE;
        }

        $deleted = $pruneAttachments->execute(orphanDays: $days, chunk: max(1, (int) $this->option('chunk')));

        $this->info("Pruned {$deleted} chat attachment(s).");

        return self::SUCCESS;
    }
}
