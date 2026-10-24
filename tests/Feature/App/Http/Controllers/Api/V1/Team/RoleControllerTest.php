<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Role;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * A team's roles through the public API: creating one that reaches some or
 * all agents with a set of permissions, updating it partially, and deleting
 * one nobody holds. A role is named through its own team: another team of
 * the same workspace does not reach it.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->team = ownerTeam($this->owner);
    $this->uri = "/api/v1/teams/{$this->team->external_id}/roles";
});

describe('POST /api/v1/teams/{team}/roles', function (): void {
    it('creates a role with specific agents and permissions', function (): void {
        $id = $this->withToken($this->token)
            ->postJson($this->uri, ['name' => 'Support', 'agent_ids' => [$this->agent->external_id], 'permissions' => ['agent.chat.view']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.all_agents', false)
            ->assertJsonPath('data.agent_ids', [$this->agent->external_id])
            ->assertJsonPath('data.permissions', ['agent.chat.view'])
            ->json('data.id');

        expect(Role::where('external_id', $id)->exists())->toBeTrue();
    });

    it('creates a role that reaches every agent', function (): void {
        $this->withToken($this->token)
            ->postJson($this->uri, ['name' => 'Admins', 'all_agents' => true])
            ->assertCreated()
            ->assertJsonPath('data.all_agents', true)
            ->assertJsonPath('data.agent_ids', []);
    });

    it('rejects an unknown permission code', function (): void {
        $this->withToken($this->token)
            ->postJson($this->uri, ['name' => 'X', 'permissions' => ['not.a.real.permission']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    });

    it('rejects a name the team already uses', function (): void {
        teamRole($this->team, 'Dup', permissions: []);

        $this->withToken($this->token)
            ->postJson($this->uri, ['name' => 'Dup'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('PATCH /api/v1/teams/{team}/roles/{role}', function (): void {
    it('renames a role and swaps every agent for a specific one', function (): void {
        $role = teamRole($this->team, 'Old', permissions: []);

        $this->withToken($this->token)
            ->patchJson("{$this->uri}/{$role->external_id}", [
                'name' => 'New',
                // PATCH is partial: switching off all-agents must be said,
                // not implied by leaving it out.
                'all_agents' => false,
                'agent_ids' => [$this->agent->external_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $role->external_id)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.all_agents', false)
            ->assertJsonPath('data.agent_ids', [$this->agent->external_id]);
    });

    it('keeps the fields an update leaves out', function (): void {
        $role = teamRole($this->team, 'Analysts', permissions: ['agent.chat.view'], allAgents: false, agentExternalIds: [$this->agent->external_id]);

        $this->withToken($this->token)
            ->patchJson("{$this->uri}/{$role->external_id}", ['name' => 'Analysts v2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Analysts v2')
            ->assertJsonPath('data.agent_ids.0', $this->agent->external_id)
            ->assertJsonPath('data.permissions.0', 'agent.chat.view');
    });

    it('404s a role named through another team of the same workspace', function (): void {
        $role = teamRole($this->team, 'Primary role', permissions: []);
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->owner->workspace(), name: 'Second team'));

        $this->withToken($this->token)
            ->patchJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}", ['name' => 'X'])
            ->assertNotFound();

        expect($role->fresh()->name)->toBe('Primary role');
    });
});

describe('DELETE /api/v1/teams/{team}/roles/{role}', function (): void {
    it('deletes a role nobody holds', function (): void {
        $role = $this->team->roles()->create(['name' => 'Ghost role']);

        $this->withToken($this->token)->deleteJson("{$this->uri}/{$role->external_id}")->assertNoContent();

        expect($role->fresh())->toBeNull();
    });

    it('answers a role members still hold with a field error', function (): void {
        $held = teamRole($this->team, 'Held role', permissions: []);
        $this->team->members()->attach(User::factory()->employee($this->owner)->create()->id, ['role_id' => $held->id]);

        $this->withToken($this->token)
            ->deleteJson("{$this->uri}/{$held->external_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });
});
