<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * Who sees and manages which agents in the dashboard: the agent list shows a
 * teammate only the agents their role reaches; only the team owner creates
 * agents; and pages behind a team permission refuse a teammate without it.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentExternalIds  empty with $allAgents = true
 */
function listTeammate(User $owner, array $permissions, bool $allAgents = true, array $agentExternalIds = []): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(8),
        permissions: $permissions,
        allAgents: $allAgents,
        agentExternalIds: $agentExternalIds,
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->granted = createAgent($this->owner, ['name' => 'Granted']);
    $this->other = createAgent($this->owner, ['name' => 'Other']);
});

it('lists every team agent for the owner and only granted agents for a teammate', function (): void {
    $teammate = listTeammate($this->owner, [], allAgents: false, agentExternalIds: [$this->granted->external_id]);

    $this->actingAs($this->owner)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonCount(2);

    $this->actingAs($teammate)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', $this->granted->external_id);
});

it('lets only the team owner create an agent', function (): void {
    $teammate = listTeammate($this->owner, ['agent.setup']);

    $this->actingAs($teammate)
        ->postJson(route('agents.store'), ['name' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only team owners can create agents.');

    $this->actingAs($this->owner)
        ->post(route('agents.store'), ['name' => 'Fresh'])
        ->assertRedirect();

    expect($this->owner->agents()->where('name', 'Fresh')->exists())->toBeTrue();
});

it('gates the identity and rate-limit pages on agent.identity.manage', function (string $routeName): void {
    $blocked = listTeammate($this->owner, ['agent.setup']);
    $allowed = listTeammate($this->owner, ['agent.identity.manage']);

    $this->actingAs($blocked)->get(route($routeName, $this->granted))->assertForbidden();
    $this->actingAs($allowed)->get(route($routeName, $this->granted))->assertOk();
})->with(['agents.identity', 'agents.security.rate-limit']);
