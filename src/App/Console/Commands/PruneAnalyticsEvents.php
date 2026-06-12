<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Delete raw analytics_events older than the retention window. Rollups/metrics keep the aggregates; feedback rows are current-state and are kept.')]
#[Signature('app:prune-analytics-events {--days=395 : Retention in days (default ~13 months so year-over-year charts keep raw backing)}')]
class PruneAnalyticsEvents extends Command
{
    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $db = DB::connection(config('chatvia.analytics.connection'));

        $total = 0;

        // Chunked; feedback_given rows are live current-state and are kept.
        do {
            $deleted = $db->affectingStatement(
                "DELETE FROM analytics_events
                 WHERE id IN (
                     SELECT id FROM analytics_events
                     WHERE occurred_at < ?
                       AND event_type != 'feedback_given'
                     LIMIT 50000
                 )",
                [$cutoff],
            );

            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Pruned {$total} analytics event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
