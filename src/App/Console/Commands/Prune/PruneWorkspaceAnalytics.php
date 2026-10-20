<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use Domain\Analytics\Actions\Retention\PruneWorkspaceAnalyticsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily analytics retention per workspace: deletes the analytics of each
 * workspace that set an analytics retention window
 * ({@see PruneWorkspaceAnalyticsAction}).
 */
#[Description('Delete analytics rows (raw events, rollups, conversation metrics, first-seen sessions) older than the analytics retention each workspace set in its security policy.')]
#[Signature('chatvia:prune:workspace-analytics {--batch=10000 : How many rows to delete per batch}')]
class PruneWorkspaceAnalytics extends Command
{
    public function handle(PruneWorkspaceAnalyticsAction $prune): int
    {
        $deleted = $prune->execute(max(1, (int) $this->option('batch')));

        $this->info("Pruned {$deleted} analytics row(s) past their workspace's retention.");

        return self::SUCCESS;
    }
}
