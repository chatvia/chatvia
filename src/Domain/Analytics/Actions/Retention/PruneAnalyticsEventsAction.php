<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Retention;

use Carbon\CarbonInterface;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Deletes raw `analytics_events` rows older than a cutoff, in batches so one
 * run never holds a huge delete. The rollups and per-conversation metrics keep
 * the aggregates. Feedback rows are kept: they are the current reaction of a
 * message, not history.
 */
class PruneAnalyticsEventsAction
{
    private const int BATCH_SIZE = 50000;

    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    /**
     * @return int how many rows were deleted
     */
    public function execute(CarbonInterface $olderThan): int
    {
        $total = 0;

        do {
            $deleted = $this->analytics->connection()->affectingStatement(
                'DELETE FROM analytics_events
                 WHERE id IN (
                     SELECT id FROM analytics_events
                     WHERE occurred_at < ?
                       AND event_type != ?
                     LIMIT '.self::BATCH_SIZE.'
                 )',
                [$olderThan, AnalyticsEventType::FeedbackGiven->value],
            );

            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
