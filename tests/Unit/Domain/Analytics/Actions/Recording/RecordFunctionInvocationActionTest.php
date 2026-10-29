<?php

use Domain\Analytics\Actions\Recording\RecordFunctionInvocationAction;
use Domain\Analytics\Data\Recording\FunctionInvocationRecordData;
use Domain\Analytics\Events\FunctionInvoked;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each function call counts in the agent's daily function rollup, one counter
 * per function.
 */
function rfiRollup(int $agentId): Builder
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_function_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function rfiInvoke(int $agentId, int $functionId): void
{
    app(RecordFunctionInvocationAction::class)->execute(FunctionInvocationRecordData::fromEvent(new FunctionInvoked(
        agentId: $agentId,
        workspaceId: 700000,
        functionId: $functionId,
        name: 'get_weather',
        description: null,
        occurredAt: Carbon::parse('2026-06-01 09:00'),
    )));
}

it('counts each call in a per-day counter of its function', function (): void {
    $agentId = 951002;

    rfiInvoke($agentId, 20);
    rfiInvoke($agentId, 21);
    rfiInvoke($agentId, 21);

    expect((int) rfiRollup($agentId)->where('function_id', 20)->value('invocation_count'))->toBe(1)
        ->and((int) rfiRollup($agentId)->where('function_id', 21)->value('invocation_count'))->toBe(2);
});
