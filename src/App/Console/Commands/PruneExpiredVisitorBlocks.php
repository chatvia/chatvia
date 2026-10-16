<?php

namespace App\Console\Commands;

use Domain\Security\Actions\PruneExpiredVisitorBlocksAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of visitor blocks that ended long ago (see
 * {@see PruneExpiredVisitorBlocksAction}).
 */
#[Description('Delete visitor blocks (blocked_visitors) that ended more than the given number of days ago.')]
#[Signature('app:prune-expired-visitor-blocks {--days=30 : Keep ended blocks this many days}')]
class PruneExpiredVisitorBlocks extends Command
{
    public function handle(PruneExpiredVisitorBlocksAction $prune): int
    {
        $deleted = $prune->execute(max(0, (int) $this->option('days')));

        $this->info("Pruned {$deleted} ended visitor block(s).");

        return self::SUCCESS;
    }
}
