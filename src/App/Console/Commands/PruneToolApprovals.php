<?php

namespace App\Console\Commands;

use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete tool-approval checkpoints that are resolved or expired. Each row stores a full conversation snapshot, so the tool_approval_requests table bloats quickly without this sweep.')]
#[Signature('app:prune-tool-approvals {--days=3 : Grace period in days for resolved checkpoints; expired/abandoned ones are always pruned}')]
class PruneToolApprovals extends Command
{
    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = ToolApprovalCheckpoint::query()
            // Resolved checkpoints — kept for a short grace window (debugging),
            // then removed.
            ->where(function ($query) use ($cutoff): void {
                $query->where('status', '!=', ToolApprovalCheckpoint::STATUS_PENDING)
                    ->where('updated_at', '<', $cutoff);
            })
            // Abandoned checkpoints whose TTL has lapsed — they can never be
            // resumed, so there is no reason to keep them.
            ->orWhere('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$deleted} tool-approval checkpoint(s).");

        return self::SUCCESS;
    }
}
