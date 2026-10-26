<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Retention;

use Carbon\CarbonInterface;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * The install-wide analytics retention, and the upkeep of the raw event log's
 * monthly partitions. Deletes, older than a cutoff:
 *
 *  - raw `analytics_events` rows. With pg_partman, a month that ended before
 *    the cutoff is dropped whole (its rows counted first), and pg_partman's
 *    maintenance then makes the coming months' partitions ahead of time.
 *    What is left before the cutoff (the month it falls in, rows that went
 *    to the default partition) is deleted in batches. Feedback lives in its
 *    own table (`analytics_message_feedback`) and is kept: it is the current
 *    reaction of a message, not history.
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
 * old rows. The event log's batches are picked by its key, `(id,
 * occurred_at)`: a `ctid` is only unique within one partition.
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
        return $this->dropExpiredPartitions($olderThan) + $this->deleteInBatches(
            'DELETE FROM analytics_events
             WHERE (id, occurred_at) IN (
                 SELECT id, occurred_at FROM analytics_events
                 WHERE occurred_at < ?
                 LIMIT '.self::BATCH_SIZE.'
             )',
            [$olderThan],
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
     * Drop the monthly partitions that ended before the cutoff, then let
     * pg_partman make the next months' partitions. Without pg_partman the log
     * has only its default partition, and the batched delete does it all.
     *
     * @return int the rows the dropped partitions held
     */
    private function dropExpiredPartitions(CarbonInterface $olderThan): int
    {
        $db = $this->analytics->connection();
        $schema = $db->scalar("SELECT extnamespace::regnamespace::text FROM pg_extension WHERE extname = 'pg_partman'");

        if (blank($schema)) {
            return 0;
        }

        $expired = $db->select(<<<SQL
            SELECT p.partition_schemaname || '.' || p.partition_tablename AS child
            FROM {$schema}.show_partitions('public.analytics_events') p
            CROSS JOIN LATERAL {$schema}.show_partition_info(p.partition_schemaname || '.' || p.partition_tablename) i
            WHERE i.child_end_time <= ?
        SQL, [$olderThan]);

        $dropped = 0;

        foreach ($expired as $partition) {
            $dropped += (int) $db->scalar("SELECT COUNT(*) FROM {$partition->child}");
            $db->statement("DROP TABLE {$partition->child}");
        }

        $db->statement("SELECT {$schema}.run_maintenance('public.analytics_events')");

        return $dropped;
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
