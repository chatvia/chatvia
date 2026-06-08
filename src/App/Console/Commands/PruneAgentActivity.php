<?php

namespace App\Console\Commands;

use Domain\Conversation\Models\AgentActivity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete governance audit rows (agent_activity) older than the retention window, keeping the tool-call audit log bounded.')]
#[Signature('app:prune-agent-activity {--days= : Retention in days; defaults to config(chatvia.governance.retention_days)}')]
class PruneAgentActivity extends Command
{
    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : (int) config('chatvia.governance.retention_days', 90);

        $deleted = AgentActivity::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} agent-activity audit row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
