<?php

use Domain\Analytics\Actions\Recording\RecordFunctionInvocationAction;
use Domain\Analytics\Data\Recording\FunctionInvocationRecordData;
use Domain\Analytics\Events\FunctionInvoked;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Each function call counts in the agent's daily function rollup, one counter
 * per function, with the name and description it had at its latest call.
 */
function rfiRollup(int $agentId): Builder
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_function_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function rfiInvoke(int $agentId, int $functionId, string $name, ?string $description): void
{
    app(RecordFunctionInvocationAction::class)->execute(FunctionInvocationRecordData::fromEvent(new FunctionInvoked(
        agentId: $agentId,
        workspaceId: 700000,
        functionId: $functionId,
        name: $name,
        description: $description,
        occurredAt: Carbon::parse('2026-06-01 09:00'),
    )));
}

it('upserts and increments the per-day function rollup on conflict', function (): void {
    $agentId = 951001;

    rfiInvoke($agentId, 10, 'get_weather', 'Looks up weather');
    rfiInvoke($agentId, 10, 'get_weather', 'Looks up weather');

    expect((int) rfiRollup($agentId)->where('function_id', 10)->value('invocation_count'))->toBe(2);
});

it('keeps a separate counter per function id', function (): void {
    $agentId = 951002;

    rfiInvoke($agentId, 20, 'a', null);
    rfiInvoke($agentId, 21, 'b', null);
    rfiInvoke($agentId, 21, 'b', null);

    expect((int) rfiRollup($agentId)->where('function_id', 20)->value('invocation_count'))->toBe(1)
        ->and((int) rfiRollup($agentId)->where('function_id', 21)->value('invocation_count'))->toBe(2);
});

it('refreshes the captured label fields on each invocation', function (): void {
    $agentId = 951003;

    rfiInvoke($agentId, 30, 'old_name', 'old description');
    rfiInvoke($agentId, 30, 'new_name', 'new description');

    $row = rfiRollup($agentId)->where('function_id', 30)->first();

    expect($row->function_name)->toBe('new_name')
        ->and($row->function_description)->toBe('new description')
        ->and((int) $row->invocation_count)->toBe(2);
});
