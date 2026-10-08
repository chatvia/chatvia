<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Connectors belong to the workspace owner. A teammate with
 * account.connectors must see and manage the owner's connectors, and rows
 * previously saved under a teammate are moved to the owner by a data
 * migration.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);

    $team = $this->owner->primaryTeam();
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: ['account.connectors'],
        allAgents: true,
        agentExternalIds: [],
    ));
    $this->member = User::factory()->employee($this->owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($this->member->id, ['role_id' => $role->id]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ownerConnector(User $owner, string $slug = 'crm'): AgentMcpServer
{
    return $owner->mcpServers()->create([
        'name' => 'CRM',
        'slug' => $slug,
        'url' => 'https://crm.example.test/mcp',
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

it('shows the owner\'s connectors to a teammate', function (): void {
    $server = ownerConnector($this->owner);

    $this->actingAs($this->member)
        ->getJson(route('integrations.connectors.data'))
        ->assertOk()
        ->assertJsonFragment(['external_id' => $server->external_id]);
});

it('lets a teammate manage an owner connector by slug instead of 404ing', function (): void {
    $server = ownerConnector($this->owner);

    $this->actingAs($this->member)
        ->postJson(route('integrations.connectors.enable', ['slug' => 'crm']))
        ->assertOk();
    expect($server->fresh()->is_enabled)->toBeFalse();

    $this->actingAs($this->member)
        ->getJson(route('integrations.connectors.agents.index', ['slug' => 'crm']))
        ->assertOk();
});

it('moves teammate-owned connectors and functions to the workspace owner, skipping name clashes', function (): void {
    // A connector the owner already has under the same slug stays put.
    ownerConnector($this->owner, 'shared');
    $clash = ownerConnector($this->member, 'shared');
    $moved = ownerConnector($this->member, 'notion');

    $fnMoved = $this->member->functions()->create([
        'internal_name' => 'team_only',
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
    ]);

    // Someone else's private rows are untouched.
    $stranger = User::factory()->create();
    $strangerServer = ownerConnector($stranger, 'notion');

    (require database_path('migrations/2026_10_08_583914_rekey_teammate_connectors_and_functions_to_workspace_owner.php'))->up();

    expect($moved->fresh()->user_id)->toBe($this->owner->id)
        ->and($clash->fresh()->user_id)->toBe($this->member->id)
        ->and(AgentFunction::find($fnMoved->id)->user_id)->toBe($this->owner->id)
        ->and($strangerServer->fresh()->user_id)->toBe($stranger->id);
});
