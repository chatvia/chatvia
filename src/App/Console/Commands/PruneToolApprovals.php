<?php

namespace App\Console\Commands;

use Domain\Conversation\Actions\Retention\PruneToolApprovalCheckpointsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily sweep of tool-approval checkpoints that can never resume (see
 * PruneToolApprovalCheckpointsAction).
 */
#[Description('Delete tool-approval checkpoints that are resolved or expired. Each row stores a full conversation snapshot, so the tool_approval_requests table bloats quickly without this sweep.')]
#[Signature('app:prune-tool-approvals {--days=3 : Grace period in days for resolved checkpoints; expired/abandoned ones are always pruned}')]
class PruneToolApprovals extends Command
{
    public function handle(PruneToolApprovalCheckpointsAction $pruneCheckpoints): int
    {
        $deleted = $pruneCheckpoints->execute(max(0, (int) $this->option('days')));

        $this->info("Pruned {$deleted} tool-approval checkpoint(s).");

        return self::SUCCESS;
    }
}
