<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Retention;

use Carbon\CarbonInterface;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * The install-wide analytics retention. Deletes, older than a cutoff:
 *
 *  - raw `analytics_events` rows. Feedback rows are kept: they are the
 *    current reaction of a message, not history.
 *  - per-conversation metrics (`analytics_conversation_metrics`) of
 *    conversations that started and last had a message before the cutoff.
 *    One still in use keeps its row, so its next message is not counted as
 *    a new conversation.
 *  - first-seen visitor sessions (`analytics_session_firsts`).
 *
 * The hourly and daily rollups stay forever: they hold the lifetime totals.
 * Rows go in batches, so one run never holds a huge delete. The metrics and
 * session tables have no `id`, so their batches are picked by `ctid` (the
 * row's physical address); the BRIN indexes on their time columns find the
 * old rows.
 */
class PruneAnalyticsEventsAction
{
    /**
     * How many days of raw rows the daily prune keeps by default (about 13
     * months, so year-over-year charts still have raw rows behind them).
     */
    public const int DEFAULT_RETENTION_DAYS = 395;

    private const int BATCH_SIZE = 50000;

    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    /**
     * @return int how many rows were deleted, over the three tables
     */
    public function execute(CarbonInterface $olderThan): int
    {
        return $this->deleteInBatches(
            'DELETE FROM analytics_events
             WHERE id IN (
                 SELECT id FROM analytics_events
                 WHERE occurred_at < ?
                   AND event_type != ?
                 LIMIT '.self::BATCH_SIZE.'
             )',
            [$olderThan, AnalyticsEventType::FeedbackGiven->value],
        ) + $this->deleteInBatches(
            'DELETE FROM analytics_conversation_metrics
             WHERE ctid = ANY(ARRAY(
                 SELECT ctid FROM analytics_conversation_metrics
                 WHERE started_at < ?
                   AND COALESCE(ended_at, started_at) < ?
                 LIMIT '.self::BATCH_SIZE.'
             ))',
            [$olderThan, $olderThan],
        ) + $this->deleteInBatches(
            'DELETE FROM analytics_session_firsts
             WHERE ctid = ANY(ARRAY(
                 SELECT ctid FROM analytics_session_firsts
                 WHERE first_seen_at < ?
                 LIMIT '.self::BATCH_SIZE.'
             ))',
            [$olderThan],
        );
    }

    /**
     * Run one bounded DELETE until it stops matching.
     *
     * @param  list<mixed>  $bindings
     */
    private function deleteInBatches(string $sql, array $bindings): int
    {
        $total = 0;

        do {
            $deleted = $this->analytics->connection()->affectingStatement($sql, $bindings);

            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
