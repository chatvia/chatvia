<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
    $this->team = $this->user->primaryTeam();
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function roleHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

function makeRoleAgent(User $user, string $name): Agent
{
    return Agent::create([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => $name,
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'source' => 'api',
    ]);
}

function makeRole(User $user, string $name): Role
{
    return app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $user->primaryTeam(),
        name: $name,
        permissions: [],
        allAgents: true,
        agentExternalIds: [],
    ));
}

/**
 * Provision an employee of the owner's workspace, attached to the given team
 * under a permission-less role, and return an API token minted in their name.
 */
function makeMemberToken(User $owner, Team $team): string
{
    $member = User::factory()->employee($owner)->create();
    $role = makeRole($owner, 'Member role '.Str::random(6));
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member->createToken('account')->plainTextToken;
}

describe('GET /api/v1/teams', function (): void {

    it('returns each team once with agents and roles nested', function (): void {
        makeRoleAgent($this->user, 'Bot');
        makeRole($this->user, 'Support');

        $this->withHeaders(roleHeaders($this->token))
            ->getJson('/api/v1/teams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->team->external_id)
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'created_at',
                'agents' => [['id', 'name']],
                'roles' => [['id', 'name', 'all_agents', 'agent_ids', 'permissions']],
            ]]]);
    });
});

describe('POST /api/v1/teams/{team}/roles', function (): void {

    it('creates a role with specific agents and permissions', function (): void {
        $agent = makeRoleAgent($this->user, 'Sales');

        $body = $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'Support',
                'agent_ids' => [$agent->external_id],
                'permissions' => ['agent.chat.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.all_agents', false)
            ->assertJsonPath('data.agent_ids', [$agent->external_id])
            ->assertJsonPath('data.permissions', ['agent.chat.view'])
            ->json('data');

        expect($body['id'])->toBeString()
            ->and(Role::where('external_id', $body['id'])->exists())->toBeTrue();
    });

    it('creates an all-agents role', function (): void {
        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'Admins',
                'all_agents' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.all_agents', true)
            ->assertJsonPath('data.agent_ids', []);
    });

    it('rejects an invalid permission code', function (): void {
        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'X',
                'permissions' => ['not.a.real.permission'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    });

    it('rejects a duplicate role name', function (): void {
        makeRole($this->user, 'Dup');

        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", ['name' => 'Dup'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('forbids creating a role in another account’s team', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));

        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$other->primaryTeam()->external_id}/roles", ['name' => 'Evil'])
            ->assertForbidden();
    });

    it('forbids a workspace member without permission from creating a role', function (): void {
        $memberToken = makeMemberToken($this->user, $this->team);

        $this->withHeaders(roleHeaders($memberToken))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", ['name' => 'Sneaky'])
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/teams/{team}/roles/{role}', function (): void {

    it('updates a role’s name and swaps all-agents for a specific agent', function (): void {
        $role = makeRole($this->user, 'Old');
        $agent = makeRoleAgent($this->user, 'Bot');

        $this->withHeaders(roleHeaders($this->token))
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}", [
                'name' => 'New',
                'agent_ids' => [$agent->external_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $role->external_id)
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.all_agents', false)
            ->assertJsonPath('data.agent_ids', [$agent->external_id]);
    });

    it('404s for a role belonging to another team', function (): void {
        $other = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
        $otherRole = makeRole($other, 'Theirs');

        $this->withHeaders(roleHeaders($this->token))
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$otherRole->external_id}", ['name' => 'X'])
            ->assertNotFound();
    });

    it('forbids a workspace member without permission from updating a role', function (): void {
        $role = makeRole($this->user, 'Target');
        $memberToken = makeMemberToken($this->user, $this->team);

        $this->withHeaders(roleHeaders($memberToken))
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        expect($role->fresh()->name)->toBe('Target');
    });

    it('404s when updating a role through a different team in the same workspace', function (): void {
        $role = makeRole($this->user, 'Primary role');
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(owner: $this->user, name: 'Second team'));

        $this->withHeaders(roleHeaders($this->token))
            ->patchJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}", ['name' => 'X'])
            ->assertNotFound();
    });
});

describe('POST /api/v1/teams/{team}/roles/{role}/users', function (): void {

    it('assigns a user to a role', function (): void {
        $role = makeRole($this->user, 'Support');
        $agent = makeRoleAgent($this->user, 'Bot');
        $endUser = app(CreateChatterAction::class)->execute($this->user, $agent, 'Jane');

        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $endUser->external_id);

        expect($this->team->members()->where('users.id', $endUser->id)->wherePivot('role_id', $role->id)->exists())->toBeTrue();
    });

    it('moves a user to the new role on reassignment (one role per team)', function (): void {
        $roleA = makeRole($this->user, 'A');
        $roleB = makeRole($this->user, 'B');
        $agent = makeRoleAgent($this->user, 'Bot');
        $endUser = app(CreateChatterAction::class)->execute($this->user, $agent, 'Jane');

        foreach ([$roleA, $roleB] as $role) {
            $this->withHeaders(roleHeaders($this->token))
                ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                    'user_id' => $endUser->external_id,
                ])
                ->assertOk();
        }

        expect($this->team->members()->where('users.id', $endUser->id)->count())->toBe(1)
            ->and($this->team->members()->where('users.id', $endUser->id)->wherePivot('role_id', $roleB->id)->exists())->toBeTrue();
    });

    it('forbids a workspace member without permission from assigning users to a role', function (): void {
        $role = makeRole($this->user, 'Support');
        $agent = makeRoleAgent($this->user, 'Bot');
        $endUser = app(CreateChatterAction::class)->execute($this->user, $agent, 'Jane');
        $memberToken = makeMemberToken($this->user, $this->team);

        $this->withHeaders(roleHeaders($memberToken))
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertForbidden();

        expect($this->team->members()->where('users.id', $endUser->id)->exists())->toBeFalse();
    });

    it('404s when assigning through a different team in the same workspace', function (): void {
        $role = makeRole($this->user, 'Primary-team role');
        $agent = makeRoleAgent($this->user, 'Bot');
        $endUser = app(CreateChatterAction::class)->execute($this->user, $agent, 'Jane');
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(owner: $this->user, name: 'Other team'));

        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertNotFound();
    });
});
