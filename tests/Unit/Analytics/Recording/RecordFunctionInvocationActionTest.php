<?php

use Domain\Analytics\Actions\Recording\RecordFunctionInvocationAction;
use Domain\Analytics\Data\Recording\FunctionInvocationRecordData;
use Domain\Analytics\Events\FunctionInvoked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function functionRollup(int $agentId)
{
    return DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_function_daily')
        ->where('agent_id', $agentId)
        ->where('bucket_day', '2026-06-01');
}

function invokeFunction(int $agentId, int $functionId, string $name, ?string $description): void
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

    invokeFunction($agentId, 10, 'get_weather', 'Looks up weather');
    invokeFunction($agentId, 10, 'get_weather', 'Looks up weather');

    expect((int) functionRollup($agentId)->where('function_id', 10)->value('invocation_count'))->toBe(2);
});

it('keeps a separate counter per function id', function (): void {
    $agentId = 951002;

    invokeFunction($agentId, 20, 'a', null);
    invokeFunction($agentId, 21, 'b', null);
    invokeFunction($agentId, 21, 'b', null);

    expect((int) functionRollup($agentId)->where('function_id', 20)->value('invocation_count'))->toBe(1)
        ->and((int) functionRollup($agentId)->where('function_id', 21)->value('invocation_count'))->toBe(2);
});

it('refreshes the captured label fields on each invocation', function (): void {
    $agentId = 951003;

    invokeFunction($agentId, 30, 'old_name', 'old description');
    invokeFunction($agentId, 30, 'new_name', 'new description');

    $row = functionRollup($agentId)->where('function_id', 30)->first();

    expect($row->function_name)->toBe('new_name')
        ->and($row->function_description)->toBe('new description')
        ->and((int) $row->invocation_count)->toBe(2);
});
