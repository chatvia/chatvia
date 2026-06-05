<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\SourceRetrieved;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the per-day knowledge-source retrieval rollup. Unlike the all-time
 * `agent_sources.retrieval_count` counter, this carries a day dimension so the
 * Sources analytics page can filter by date range and show usage over time.
 * One +1 per (agent, day, source) per retrieval batch.
 */
class RecordSourceRetrievalAction
{
    public function execute(SourceRetrieved $event): void
    {
        if (empty($event->sources)) {
            return;
        }

        $db = DB::connection(config('analytics.connection'));
        $day = $event->occurredAt->format('Y-m-d');

        $db->transaction(function () use ($db, $event, $day): void {
            foreach ($event->sources as $source) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_source_daily (agent_id, user_id, bucket_day, sourceable_type, sourceable_id, retrieval_count)
                    VALUES (?, ?, ?::date, ?, ?, 1)
                    ON CONFLICT (agent_id, bucket_day, sourceable_type, sourceable_id) DO UPDATE SET
                        retrieval_count = analytics_agent_source_daily.retrieval_count + 1
                SQL, [$event->agentId, $event->userId, $day, $source['type'], $source['id']]);
            }
        });
    }
}
