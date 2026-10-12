<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Actions\Assignment\ListToolAgentAssignmentAction;
use Domain\Membership\Team\Services\TeamService;

/**
 * The "Manage agents" sheet splits every workspace agent into the ones that
 * use a tool source and the ones that could — the same for MCP servers and
 * webhook functions.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->support = makeTeamAgent($this->owner, 'Support Bot');
    $this->sales = makeTeamAgent($this->owner, 'Sales Bot');
});

it('splits the workspace agents for an MCP server', function (): void {
    $server = $this->owner->workspace()->mcpServers()->create(['name' => 'CRM', 'slug' => 'crm', 'url' => 'https://crm.example.test/mcp', 'auth_type' => 'none']);
    $this->support->attachConnector($server);

    $result = app(ListToolAgentAssignmentAction::class)->execute($this->owner, $server);

    expect(collect($result->attached->toArray())->pluck('id')->all())->toBe([$this->support->external_id])
        ->and(collect($result->available->toArray())->pluck('id')->all())->toBe([$this->sales->external_id]);
});

it('splits the workspace agents for a webhook function, also for a teammate', function (): void {
    $function = AgentFunction::query()->create([
        'workspace_id' => $this->owner->workspace()->id,
        'internal_name' => 'lookup',
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/hook',
    ]);
    $this->sales->attachFunction($function);
    $teammate = User::factory()->employee($this->owner)->create();

    $result = app(ListToolAgentAssignmentAction::class)->execute($teammate, $function);

    expect(collect($result->attached->toArray())->pluck('id')->all())->toBe([$this->sales->external_id])
        ->and(collect($result->available->toArray())->pluck('id')->all())->toBe([$this->support->external_id]);
});
