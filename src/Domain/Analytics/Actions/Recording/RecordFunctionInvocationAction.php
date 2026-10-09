<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\FunctionInvocationRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Upserts the per-day function-invocation rollup. Durable counterpart to the
 * live `agent_function.invocation_count` pivot: keyed by id with no FK, so a
 * function that's since been deleted still shows in usage insights, with the
 * label captured at invocation time.
 */
class RecordFunctionInvocationAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(FunctionInvocationRecordData $invocation): void
    {
        $this->analytics->connection()->statement(<<<'SQL'
            INSERT INTO analytics_agent_function_daily (agent_id, workspace_id, bucket_day, function_id, invocation_count, function_name, function_description)
            VALUES (?, ?, ?::date, ?, 1, ?, ?)
            ON CONFLICT (agent_id, bucket_day, function_id) DO UPDATE SET
                invocation_count = analytics_agent_function_daily.invocation_count + 1,
                function_name = EXCLUDED.function_name,
                function_description = EXCLUDED.function_description
        SQL, [
            $invocation->agentId,
            $invocation->workspaceId,
            $invocation->occurredAt->format('Y-m-d'),
            $invocation->functionId,
            $invocation->name,
            $invocation->description,
        ]);
    }
}
