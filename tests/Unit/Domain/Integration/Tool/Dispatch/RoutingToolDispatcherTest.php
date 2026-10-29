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
 * admin's longest tool result (never below 1,000 characters), so one large
 * reply cannot fill the model's context; a result within the limit is left
 * as it is.
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

it('cuts a result longer than the limit and tells the model, keeping its blocks and its error flag', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 1000]);
    $artifacts = [['kind' => 'echarts_option', 'data' => []]];
    $dispatcher = routingDispatcher(new ToolDispatchResultData(Str::repeat('é', 1500), $artifacts), ToolDispatchResultData::error(Str::repeat('x', 5000)));

    $result = $dispatcher->dispatch(new Agent, ToolCallData::named('mcp__notion__fetch', '{}'));
    $error = $dispatcher->dispatch(new Agent, ToolCallData::named('lookup_order', '{}'));

    expect($result->text)->toStartWith(Str::repeat('é', 1000)."\n\n")
        ->and($result->text)->toEndWith(sprintf(RoutingToolDispatcher::CUT_NOTE, 1000))
        ->and(mb_substr_count($result->text, 'é'))->toBe(1000)
        ->and($result->artifacts)->toBe($artifacts)
        ->and($result->isError)->toBeFalse()
        ->and($error->isError)->toBeTrue()
        ->and(Str::length($error->text))->toBeLessThan(1200);
});

it('never cuts below 1,000 characters, whatever the setting says', function (): void {
    config(['chatvia.agent.tools.max_result_chars' => 10]);
    $within = ToolDispatchResultData::text(Str::repeat('a', 1000));
    $dispatcher = routingDispatcher(ToolDispatchResultData::text(Str::repeat('a', 1500)), $within);

    $cut = $dispatcher->dispatch(new Agent, ToolCallData::named('mcp__notion__fetch', '{}'));
    $kept = $dispatcher->dispatch(new Agent, ToolCallData::named('lookup_order', '{}'));

    expect($cut->text)->toStartWith(Str::repeat('a', 1000)."\n\n")
        ->and($kept)->toBe($within);
});
