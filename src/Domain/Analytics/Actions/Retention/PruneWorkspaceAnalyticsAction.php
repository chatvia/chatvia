<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Retention;

use Carbon\CarbonInterface;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Deletes a workspace's analytics older than the analytics retention it chose
 * in its security policy: the raw events, the hourly and daily rollups, the
 * per-conversation metrics and the first-seen sessions. Workspaces that left
 * it blank keep the install defaults ({@see PruneAnalyticsEventsAction} trims
 * only the raw events). Unlike that install-wide prune, old feedback events go
 * too: the workspace asked for all of its older analytics to be deleted.
 *
 * Rows go in batches, so one run never holds a huge delete. Most of these
 * tables have a composite key and no `id`, so a batch is picked by `ctid`
 * (the row's physical address), which works the same for every table.
 */
class PruneWorkspaceAnalyticsAction
{
    /**
     * Each analytics table and the time column a row is aged by. Every table
     * has a `(workspace_id, <time column>)` index (the raw events through
     * `(workspace_id, event_type, occurred_at)`).
     *
     * @var array<string, string>
     */
    private const array TABLES = [
        'analytics_events' => 'occurred_at',
        'analytics_agent_hourly_rollups' => 'bucket_hour',
        'analytics_conversation_metrics' => 'started_at',
        'analytics_session_firsts' => 'first_seen_at',
        'analytics_agent_sentiment_daily' => 'bucket_day',
        'analytics_agent_tone_daily' => 'bucket_day',
        'analytics_agent_topic_daily' => 'bucket_day',
        'analytics_agent_source_daily' => 'bucket_day',
        'analytics_agent_function_daily' => 'bucket_day',
        'analytics_agent_mcp_tool_daily' => 'bucket_day',
    ];

    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    /**
     * @return int how many rows were deleted, over all tables
     */
    public function execute(int $batchSize = 10000): int
    {
        // Never below the floor, even for a value saved before it was raised.
        $floor = (int) config('chatvia.retention.analytics_floor_days', 30);

        $windows = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_analytics_days')
            ->pluck('retention_analytics_days', 'workspace_id');

        $deleted = 0;

        foreach ($windows as $workspaceId => $days) {
            $cutoff = now()->subDays(max($floor, (int) $days));

            foreach (self::TABLES as $table => $timeColumn) {
                // A daily bucket is kept until the whole day is past the cutoff.
                $olderThan = $timeColumn === 'bucket_day' ? $cutoff->toDateString() : $cutoff;

                $deleted += $this->deleteOlderRows($table, $timeColumn, (int) $workspaceId, $olderThan, $batchSize);
            }
        }

        return $deleted;
    }

    private function deleteOlderRows(string $table, string $timeColumn, int $workspaceId, CarbonInterface|string $olderThan, int $batchSize): int
    {
        $batchSize = max(1, $batchSize);
        $deleted = 0;

        do {
            $batch = $this->analytics->connection()->affectingStatement(
                "DELETE FROM {$table}
                 WHERE ctid = ANY(ARRAY(
                     SELECT ctid FROM {$table}
                     WHERE workspace_id = ?
                       AND {$timeColumn} < ?
                     LIMIT {$batchSize}
                 ))",
                [$workspaceId, $olderThan],
            );

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
