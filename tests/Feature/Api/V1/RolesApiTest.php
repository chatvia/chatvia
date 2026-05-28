<?php

use App\Auth\Models\User;
use App\Teams\Models\Role;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Auth\Actions\CreateExternalUserAction;
use Domain\Teams\Actions\CreateTeamAction;
use Domain\Teams\Actions\CreateTeamRoleAction;
use Domain\Teams\Data\CreateTeamData;
use Domain\Teams\Data\SaveTeamRoleData;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
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
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'collect_leads' => true,
        'analytics_enabled' => true,
        'created_via' => 'external',
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
                'agents' => [['id', 'name', 'type']],
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
});

describe('POST /api/v1/teams/{team}/roles/{role}/users', function (): void {

    it('assigns a user to a role', function (): void {
        $role = makeRole($this->user, 'Support');
        $endUser = app(CreateExternalUserAction::class)->execute($this->user, 'Jane');

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
        $endUser = app(CreateExternalUserAction::class)->execute($this->user, 'Jane');

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
});

describe('chat-only team roles', function (): void {

    it('drops dashboard permissions but keeps agent access', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(owner: $this->user, name: 'Customers', canAccessDashboard: false),
        );
        $agent = makeRoleAgent($this->user, 'Bot');

        $this->withHeaders(roleHeaders($this->token))
            ->postJson("/api/v1/teams/{$team->external_id}/roles", [
                'name' => 'Support',
                'agent_ids' => [$agent->external_id],
                'permissions' => ['agent.chat.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', [])
            ->assertJsonPath('data.agent_ids', [$agent->external_id]);
    });
});
