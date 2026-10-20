<?php

namespace App\Console\Commands\Prune;

use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled retention for the raw analytics event log (see
 * {@see PruneAnalyticsEventsAction}).
 */
#[Description('Delete raw analytics_events older than the retention window. Rollups/metrics keep the aggregates; feedback rows are current-state and are kept.')]
#[Signature('chatvia:prune:analytics-events {--days='.PruneAnalyticsEventsAction::DEFAULT_RETENTION_DAYS.' : Retention in days (default ~13 months so year-over-year charts keep raw backing)}')]
class PruneAnalyticsEvents extends Command
{
    public function handle(PruneAnalyticsEventsAction $prune): int
    {
        $days = max(0, (int) $this->option('days'));

        $pruned = $prune->execute(now()->subDays($days));

        $this->info("Pruned {$pruned} analytics event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
