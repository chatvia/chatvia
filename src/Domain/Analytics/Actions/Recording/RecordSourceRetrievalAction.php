<?php

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\SourceRetrievalRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Upserts the per-day knowledge-source retrieval rollup. Unlike the all-time
 * `agent_sources.retrieval_count` counter, this carries a day dimension so the
 * Sources analytics page can filter by date range and show usage over time.
 * One +1 per (agent, day, source) per retrieval batch.
 */
class RecordSourceRetrievalAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(SourceRetrievalRecordData $retrieval): void
    {
        if ($retrieval->sources === []) {
            return;
        }

        $db = $this->analytics->connection();
        $day = $retrieval->occurredAt->format('Y-m-d');

        $db->transaction(function () use ($db, $retrieval, $day): void {
            foreach ($retrieval->sources as $source) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_source_daily (agent_id, workspace_id, bucket_day, sourceable_type, sourceable_id, retrieval_count, source_name, source_mime)
                    VALUES (?, ?, ?::date, ?, ?, 1, ?, ?)
                    ON CONFLICT (agent_id, bucket_day, sourceable_type, sourceable_id) DO UPDATE SET
                        retrieval_count = analytics_agent_source_daily.retrieval_count + 1,
                        source_name = EXCLUDED.source_name,
                        source_mime = EXCLUDED.source_mime
                SQL, [$retrieval->agentId, $retrieval->workspaceId, $day, $source->type, $source->id, $source->name, $source->mime]);
            }
        });
    }
}
