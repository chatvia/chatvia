<?php

/**
 * The account API for team roles: listing teams, creating and updating roles,
 * and assigning teammates (staff accounts of the workspace) to a role.
 * Chatters are not staff, so their ids never resolve here.
 */

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = tap(User::factory()->create(), fn (User $u) => app(TeamService::class)->createDefaultTeam($u));
    $this->team = $this->user->primaryTeam();
    $this->token = $this->user->createToken('account')->plainTextToken;
});

/** A permission-less, all-agents role on the user's primary team. */
function rolesApiRole(User $user, string $name): Role
{
    return teamRole($user->primaryTeam(), $name, permissions: []);
}

describe('GET /api/v1/teams', function (): void {

    it('returns each team once with agents and roles nested', function (): void {
        makeTeamAgent($this->user, 'Bot');
        rolesApiRole($this->user, 'Support');

        $this->withToken($this->token)
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
        $agent = makeTeamAgent($this->user, 'Sales');

        $body = $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'Support',
                'agent_ids' => [$agent->external_id],
                'permissions' => ['agent.chat.view'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonPath('data.all_agents', false)
            ->assertJsonPath('data.agent_ids', [$agent->external_id])
            ->assertJsonPath('data.permissions', ['agent.chat.view'])
            ->json('data');

        expect($body['id'])->toBeString()
            ->and(Role::where('external_id', $body['id'])->exists())->toBeTrue();
    });

    it('creates an all-agents role', function (): void {
        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'Admins',
                'all_agents' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.all_agents', true)
            ->assertJsonPath('data.agent_ids', []);
    });

    it('rejects an invalid permission code', function (): void {
        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", [
                'name' => 'X',
                'permissions' => ['not.a.real.permission'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    });

    it('rejects a duplicate role name', function (): void {
        rolesApiRole($this->user, 'Dup');

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", ['name' => 'Dup'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('forbids a workspace member without permission from creating a role', function (): void {
        $memberToken = teammateWithPermissions($this->user, [], $this->team)->createToken('account')->plainTextToken;

        withApiToken($memberToken)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles", ['name' => 'Sneaky'])
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/teams/{team}/roles/{role}', function (): void {

    it('updates a role’s name and swaps all-agents for a specific agent', function (): void {
        $role = rolesApiRole($this->user, 'Old');
        $agent = makeTeamAgent($this->user, 'Bot');

        $this->withToken($this->token)
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}", [
                'name' => 'New',
                // PATCH is partial: switching off all-agents must be said,
                // not implied by leaving it out.
                'all_agents' => false,
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
        $otherRole = rolesApiRole($other, 'Theirs');

        $this->withToken($this->token)
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$otherRole->external_id}", ['name' => 'X'])
            ->assertNotFound();
    });

    it('forbids a workspace member without permission from updating a role', function (): void {
        $role = rolesApiRole($this->user, 'Target');
        $memberToken = teammateWithPermissions($this->user, [], $this->team)->createToken('account')->plainTextToken;

        withApiToken($memberToken)
            ->patchJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        expect($role->fresh()->name)->toBe('Target');
    });

    it('404s when updating a role through a different team in the same workspace', function (): void {
        $role = rolesApiRole($this->user, 'Primary role');
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->user->workspace(), name: 'Second team'));

        $this->withToken($this->token)
            ->patchJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}", ['name' => 'X'])
            ->assertNotFound();
    });
});

describe('POST /api/v1/teams/{team}/roles/{role}/users', function (): void {

    it('assigns a teammate to a role', function (): void {
        $role = rolesApiRole($this->user, 'Support');
        $endUser = User::factory()->employee($this->user)->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $endUser->external_id);

        expect($this->team->members()->where('users.id', $endUser->id)->wherePivot('role_id', $role->id)->exists())->toBeTrue();
    });

    it('moves a teammate to the new role on reassignment (one role per team)', function (): void {
        $roleA = rolesApiRole($this->user, 'A');
        $roleB = rolesApiRole($this->user, 'B');
        $endUser = User::factory()->employee($this->user)->create();

        foreach ([$roleA, $roleB] as $role) {
            $this->withToken($this->token)
                ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                    'user_id' => $endUser->external_id,
                ])
                ->assertOk();
        }

        expect($this->team->members()->where('users.id', $endUser->id)->count())->toBe(1)
            ->and($this->team->members()->where('users.id', $endUser->id)->wherePivot('role_id', $roleB->id)->exists())->toBeTrue();
    });

    it('forbids a workspace member without permission from assigning users to a role', function (): void {
        $role = rolesApiRole($this->user, 'Support');
        $endUser = User::factory()->employee($this->user)->create();
        $memberToken = teammateWithPermissions($this->user, [], $this->team)->createToken('account')->plainTextToken;

        withApiToken($memberToken)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertForbidden();

        expect($this->team->members()->where('users.id', $endUser->id)->exists())->toBeFalse();
    });

    it('404s when assigning through a different team in the same workspace', function (): void {
        $role = rolesApiRole($this->user, 'Primary-team role');
        $endUser = User::factory()->employee($this->user)->create();
        $teamB = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $this->user->workspace(), name: 'Other team'));

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$teamB->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $endUser->external_id,
            ])
            ->assertNotFound();
    });

    it('rejects a chatter id with a validation error: chatters are not staff', function (): void {
        $role = rolesApiRole($this->user, 'Support');
        $agent = makeTeamAgent($this->user, 'Bot');
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->user->workspace(), $agent, 'Jane'));

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $chatter->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        expect($this->team->members()->where('users.external_id', $chatter->external_id)->exists())->toBeFalse();
    });

    it('rejects a teammate of another workspace with the same validation error', function (): void {
        $role = rolesApiRole($this->user, 'Support');
        $foreign = User::factory()->employee(User::factory()->create())->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$this->team->external_id}/roles/{$role->external_id}/users", [
                'user_id' => $foreign->external_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        expect($this->team->members()->where('users.id', $foreign->id)->exists())->toBeFalse();
    });
});
