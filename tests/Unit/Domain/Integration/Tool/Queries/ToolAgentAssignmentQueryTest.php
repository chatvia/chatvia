<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Queries\ToolAgentAssignmentQuery;

/**
 * The "Manage agents" sheet splits the agents the viewer may manage into the
 * ones that use a tool source and the ones that could — the same for MCP
 * servers and webhook functions.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->support = makeTeamAgent($this->owner, 'Support Bot');
    $this->sales = makeTeamAgent($this->owner, 'Sales Bot');
});

it('splits the workspace agents for an MCP server', function (): void {
    $server = AgentMcpServer::factory()->forOwner($this->owner)->create();
    $this->support->attachConnector($server);

    $result = app(ToolAgentAssignmentQuery::class)->get($this->owner, $server);

    expect(collect($result->attached->toArray())->pluck('id')->all())->toBe([$this->support->external_id])
        ->and(collect($result->available->toArray())->pluck('id')->all())->toBe([$this->sales->external_id]);
});

it('splits the workspace agents for a webhook function, also for a teammate whose role reaches every agent', function (): void {
    $function = AgentFunction::factory()->forOwner($this->owner)->create();
    $this->sales->attachFunction($function);
    $teammate = teammateWithPermissions($this->owner, ['account.functions']);

    $result = app(ToolAgentAssignmentQuery::class)->get($teammate, $function);

    expect(collect($result->attached->toArray())->pluck('id')->all())->toBe([$this->sales->external_id])
        ->and(collect($result->available->toArray())->pluck('id')->all())->toBe([$this->support->external_id]);
});

it('leaves out the agents a teammate\'s role does not reach', function (): void {
    $function = AgentFunction::factory()->forOwner($this->owner)->create();
    $teammate = teammateWithPermissions($this->owner, ['account.functions'], allAgents: false, agentExternalIds: [$this->support->external_id]);

    $result = app(ToolAgentAssignmentQuery::class)->get($teammate, $function);

    expect($result->attached->count())->toBe(0)
        ->and(collect($result->available->toArray())->pluck('id')->all())->toBe([$this->support->external_id]);
});
