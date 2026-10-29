<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Role;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * A team's roles through the public API: creating one that reaches some
 * agents with a set of permissions and updating it partially, a role named
 * through another team of the same workspace (a 404), and a role members
 * still hold, which cannot be deleted.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
    $this->uri = "/api/v1/teams/{$this->team->external_id}/roles";
});

it('creates a role with specific agents and permissions and keeps them through a partial update', function (): void {
    $id = $this->withToken($this->token)
        ->postJson($this->uri, ['name' => 'Support', 'agent_ids' => [$this->agent->external_id], 'permissions' => ['agent.chat.view']])
        ->assertCreated()
        ->assertJsonPath('data.all_agents', false)
        ->json('data.id');

    $this->withToken($this->token)
        ->patchJson("{$this->uri}/{$id}", ['name' => 'Support v2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Support v2')
        ->assertJsonPath('data.agent_ids', [$this->agent->external_id])
        ->assertJsonPath('data.permissions', ['agent.chat.view']);

    expect(Role::where('external_id', $id)->value('name'))->toBe('Support v2');
});

it('404s a role named through another team of the same workspace', function (): void {
    $role = teamRole($this->team, 'Primary role', permissions: []);
    $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Second team'));

    $this->withToken($this->token)
        ->patchJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}", ['name' => 'X'])
        ->assertNotFound();

    expect($role->fresh()->name)->toBe('Primary role');
});

it('answers a role members still hold with a field error and keeps it', function (): void {
    $held = teamRole($this->team, 'Held role', permissions: []);
    $this->team->members()->attach(User::factory()->employee($this->owner)->create()->id, ['role_id' => $held->id]);

    $this->withToken($this->token)
        ->deleteJson("{$this->uri}/{$held->external_id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect($held->fresh())->not->toBeNull();
});
