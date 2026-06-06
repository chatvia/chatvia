<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\FunctionInvoked;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the per-day function-invocation rollup. Durable counterpart to the
 * live `agent_function.invocation_count` pivot: keyed by id with no FK, so a
 * function that's since been deleted still shows in usage insights, with the
 * label captured at invocation time.
 */
class RecordFunctionInvocationAction
{
    public function execute(FunctionInvoked $event): void
    {
        $db = DB::connection(config('chatvia.analytics.connection'));
        $day = $event->occurredAt->format('Y-m-d');

        $db->statement(<<<'SQL'
            INSERT INTO analytics_agent_function_daily (agent_id, workspace_id, bucket_day, function_id, invocation_count, function_name, function_description)
            VALUES (?, ?, ?::date, ?, 1, ?, ?)
            ON CONFLICT (agent_id, bucket_day, function_id) DO UPDATE SET
                invocation_count = analytics_agent_function_daily.invocation_count + 1,
                function_name = EXCLUDED.function_name,
                function_description = EXCLUDED.function_description
        SQL, [$event->agentId, $event->workspaceId, $day, $event->functionId, $event->name, $event->description]);
    }
}
