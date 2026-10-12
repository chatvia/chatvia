<?php

use Domain\Agent\Events\AgentToolsChanged;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\Event;

/**
 * Pivot writes fire no Eloquent events, so attaching or detaching a tool
 * announces it; the tool layer then drops the agent's cached pool.
 */
it('announces a tool change when a function is attached or detached', function (): void {
    Event::fake([AgentToolsChanged::class]);

    $agent = Agent::factory()->create();
    $function = AgentFunction::query()->create([
        'workspace_id' => $agent->workspace_id,
        'internal_name' => 'fn',
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'requires_approval' => false,
    ]);

    $agent->attachFunction($function);
    $agent->detachFunction($function);

    Event::assertDispatchedTimes(AgentToolsChanged::class, 2);
    Event::assertDispatched(AgentToolsChanged::class, fn (AgentToolsChanged $event): bool => $event->agentId === $agent->id);
});

it('drops the cached tool pool when the tools change', function (): void {
    $agent = Agent::factory()->create();

    $registry = Mockery::mock(ToolRegistry::class);
    $registry->shouldReceive('forget')->once()->with($agent->id);
    app()->instance(ToolRegistry::class, $registry);

    AgentToolsChanged::dispatch($agent->id);
});
