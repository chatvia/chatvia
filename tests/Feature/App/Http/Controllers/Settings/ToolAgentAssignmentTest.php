<?php

/**
 * The "Manage agents" dialog of a workspace tool, the same for a connector
 * (ConnectorAgentController) and a webhook function (FunctionAgentController):
 * list the agents the viewer may manage, split into attached and available,
 * attach one, detach one, each answered with the new split. Attach and detach
 * carry `can.access.agent`, so a teammate only reaches the agents their role
 * does.
 */

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Services\TeamService;

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

it('lists the accessible agents as available while none is attached', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);

    $this->actingAs($this->owner)
        ->getJson(taaUrl($tool, 'index'))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
})->with('workspace tools');

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

it('detaches the tool from an agent', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);
    $tool['tool']->agents()->attach($this->agent->id);

    $this->actingAs($this->owner)
        ->deleteJson(taaUrl($tool, 'detach', $this->agent))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available');

    expect($tool['tool']->agents()->whereKey($this->agent->id)->exists())->toBeFalse();
})->with('workspace tools');

it('lists and attaches the agents of another team of the workspace', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);
    $otherTeamAgent = makeTeamAgent($this->owner, 'Sales Bot', app(TeamService::class)->createDefaultTeam($this->owner));

    $this->actingAs($this->owner)
        ->getJson(taaUrl($tool, 'index'))
        ->assertOk()
        ->assertJsonCount(2, 'available');

    $this->actingAs($this->owner)
        ->postJson(taaUrl($tool, 'attach', $otherTeamAgent))
        ->assertSuccessful();

    expect($tool['tool']->agents()->whereKey($otherTeamAgent->id)->exists())->toBeTrue();
})->with('workspace tools');

it('refuses a teammate an agent their team role does not reach', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);
    $outsideRole = makeTeamAgent($this->owner, 'Billing Bot');
    $teammate = teammateWithPermissions($this->owner, [$tool['permission']], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $this->actingAs($teammate)
        ->postJson(taaUrl($tool, 'attach', $outsideRole))
        ->assertForbidden();

    expect($tool['tool']->agents()->whereKey($outsideRole->id)->exists())->toBeFalse();
})->with('workspace tools');

it('lists only the agents the teammate may manage', function (string $kind): void {
    $tool = taaTool($kind, $this->owner);
    makeTeamAgent($this->owner, 'Billing Bot');
    $teammate = teammateWithPermissions($this->owner, [$tool['permission']], allAgents: false, agentExternalIds: [$this->agent->external_id]);

    $this->actingAs($teammate)
        ->getJson(taaUrl($tool, 'index'))
        ->assertOk()
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
})->with('workspace tools');
