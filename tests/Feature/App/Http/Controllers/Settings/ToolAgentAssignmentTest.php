<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The "Manage agents" dialog of a workspace tool, the same for a connector
 * (ConnectorAgentController) and a webhook function (FunctionAgentController):
 * it lists the agents the viewer may manage, split into attached and
 * available, and attaches one, answered with the new split. Attaching carries
 * `can.access.agent`, so a teammate only reaches the agents their role does.
 */
dataset('workspace tools', [
    'connector' => 'connector',
    'function' => 'function',
]);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

/**
 * The tool of the given kind in the owner's workspace, with the route names,
 * the route key of the tool and the team permission its page needs.
 *
 * @return array{tool: AgentFunction|AgentMcpServer, routes: string, key: array<string, string>, permission: string}
 */
function taaTool(string $kind, User $owner): array
{
    return match ($kind) {
        'connector' => [
            'tool' => $server = AgentMcpServer::factory()->forOwner($owner)->create(['slug' => 'weather']),
            'routes' => 'integrations.connectors.agents',
            'key' => ['slug' => $server->slug],
            'permission' => 'account.connectors',
        ],
        'function' => [
            'tool' => $function = AgentFunction::factory()->forOwner($owner)->create(),
            'routes' => 'integrations.functions.agents',
            'key' => ['function' => $function->external_id],
            'permission' => 'account.functions',
        ],
    };
}

/** @param  array{routes: string, key: array<string, string>}  $tool */
function taaUrl(array $tool, string $action, ?Agent $agent = null): string
{
    return route("{$tool['routes']}.{$action}", [...$tool['key'], ...($agent instanceof Agent ? ['agent' => $agent->external_id] : [])]);
}

it('attaches the tool to an agent', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);

    $this->actingAs($this->owner)
        ->postJson(taaUrl($tool, 'attach', $this->agent))
        ->assertSuccessful()
        ->assertJsonCount(1, 'attached')
        ->assertJsonCount(0, 'available')
        ->assertJsonPath('attached.0.id', $this->agent->external_id);

    expect($tool['tool']->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
})->with('workspace tools');

it('lists and attaches for a teammate only the agents their team role reaches', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);
    $outsideRole = makeTeamAgent($this->owner, 'Billing Bot');
    $teammate = teammateWithPermissions($this->owner, [$tool['permission']], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $this->actingAs($teammate)
        ->getJson(taaUrl($tool, 'index'))
        ->assertOk()
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);

    $this->actingAs($teammate)
        ->postJson(taaUrl($tool, 'attach', $outsideRole))
        ->assertForbidden();

    expect($tool['tool']->agents()->whereKey($outsideRole->id)->exists())->toBeFalse();
})->with('workspace tools');
