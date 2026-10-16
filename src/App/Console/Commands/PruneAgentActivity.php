<?php

namespace App\Console\Commands;

use Domain\Conversation\Actions\Retention\PruneAgentActivityAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily sweep of old governance audit rows (see PruneAgentActivityAction).
 */
#[Description('Delete governance audit rows (agent_activities) older than the retention window, keeping the tool-call audit log bounded.')]
#[Signature('app:prune-agent-activity {--days= : Retention in days; defaults to config(chatvia.governance.retention_days)}')]
class PruneAgentActivity extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 90;

    public function handle(PruneAgentActivityAction $pruneActivity): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('chatvia.governance.retention_days', self::DEFAULT_RETENTION_DAYS);

        $deleted = $pruneActivity->execute($days);

        $this->info("Pruned {$deleted} agent-activity audit row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
