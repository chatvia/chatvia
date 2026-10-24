<?php

use Domain\Agent\Events\AgentToolsChanged;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * When an agent's tools change, the tool layer drops that agent's cached
 * tool pool, so its next turn sees the new tools.
 */
it('drops the agent\'s cached tool pool when its tools change', function (): void {
    $agent = Agent::factory()->create();

    $registry = Mockery::mock(ToolRegistry::class);
    $registry->shouldReceive('forget')->once()->with($agent->id);
    app()->instance(ToolRegistry::class, $registry);

    AgentToolsChanged::dispatch($agent->id);
});
