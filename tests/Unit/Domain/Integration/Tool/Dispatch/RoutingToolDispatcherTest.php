<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Services\HttpToolDispatcher;
use Domain\Integration\Mcp\Services\McpToolDispatcher;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResultData;
use Domain\Integration\Tool\Dispatch\RoutingToolDispatcher;
use Illuminate\Support\Str;

/**
 * The router forwards each call to its backend and holds every result to the
 * admin's longest tool result, so one large reply cannot fill the model's
 * context.
 */
function routingDispatcher(ToolDispatchResultData $mcpResult, ToolDispatchResultData $httpResult): RoutingToolDispatcher
{
    $http = new class($httpResult) extends HttpToolDispatcher
    {
        public function __construct(private readonly ToolDispatchResultData $result) {}

        public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResultData
        {
            return $this->result;
        }
    };

    $mcp = new class($mcpResult) extends McpToolDispatcher
    {
        public function __construct(private readonly ToolDispatchResultData $result) {}

        public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResultData
        {
            return $this->result;
        }
    };

    return new RoutingToolDispatcher($http, $mcp);
}

it('cuts a result longer than the limit and tells the model it was cut', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 1000]);
    $artifacts = [['kind' => 'echarts_option', 'data' => []]];
    $dispatcher = routingDispatcher(new ToolDispatchResultData(Str::repeat('é', 1500), $artifacts), ToolDispatchResultData::text('ok'));

    $result = $dispatcher->dispatch(new Agent, ToolCallData::named('mcp__notion__fetch', '{}'));

    expect($result->text)->toStartWith(Str::repeat('é', 1000)."\n\n")
        ->and($result->text)->toEndWith(sprintf(RoutingToolDispatcher::CUT_NOTE, 1000))
        ->and(mb_substr_count($result->text, 'é'))->toBe(1000)
        ->and($result->artifacts)->toBe($artifacts)
        ->and($result->isError)->toBeFalse();
});

it('leaves a result within the limit as it is', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 1000]);
    $original = ToolDispatchResultData::text(Str::repeat('a', 1000));
    $dispatcher = routingDispatcher(ToolDispatchResultData::text('mcp'), $original);

    expect($dispatcher->dispatch(new Agent, ToolCallData::named('lookup_order', '{}')))->toBe($original);
});

it('keeps an error flagged when it is cut', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 1000]);
    $dispatcher = routingDispatcher(ToolDispatchResultData::error(Str::repeat('x', 5000)), ToolDispatchResultData::text('ok'));

    $result = $dispatcher->dispatch(new Agent, ToolCallData::named('mcp__github__search', '{}'));

    expect($result->isError)->toBeTrue()
        ->and(Str::length($result->text))->toBeLessThan(1200);
});

it('never cuts below 1,000 characters, whatever the setting says', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 10]);
    $dispatcher = routingDispatcher(ToolDispatchResultData::text(Str::repeat('a', 1500)), ToolDispatchResultData::text('ok'));

    $result = $dispatcher->dispatch(new Agent, ToolCallData::named('mcp__notion__fetch', '{}'));

    expect($result->text)->toStartWith(Str::repeat('a', 1000)."\n\n");
});
