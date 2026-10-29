<?php

namespace App\Console\Commands\Prune;

use App\Admin\Issue\Actions\PruneIssuesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled retention for Radar, the admin panel's issues page (see
 * {@see PruneIssuesAction}).
 */
#[Description('Delete issue occurrences, and issues not seen since, older than the retention window.')]
#[Signature('chatvia:prune:issues {--days= : Retention in days; defaults to config(chatvia.issues.retention_days)}')]
class PruneIssues extends Command
{
    public function handle(PruneIssuesAction $prune): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : max(1, (int) config('chatvia.issues.retention_days', 30));

        $deleted = $prune->execute($days);

        $this->info("Pruned {$deleted} issue(s) not seen in {$days} days.");

        return self::SUCCESS;
    }
}
