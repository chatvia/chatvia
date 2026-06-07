<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;

/*
 * Pins User::listAccessibleAgents() — the agent set the chat switcher lists —
 * across the three account types. It is the plural companion to canAccessAgent()
 * and must stay in lock-step with it: chatters resolve through the `agent_user`
 * grants, workspace owners see the whole account, employees resolve through
 * their current team + role. The bug this guards against: the switcher used the
 * chatter-only `agent_user` relation for everyone, so employees saw "No agents".
 */

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->update(['current_team_id' => $this->team->id]);
});

function employeeWithRole(User $owner, $team, $role): User
{
    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

it('lists every account agent for a workspace owner, regardless of team', function (): void {
    $a = makeTeamAgent($this->owner, 'A', $this->team);

    $secondTeam = app(TeamService::class)->createDefaultTeam($this->owner);
    $b = makeTeamAgent($this->owner, 'B', $secondTeam);

    expect($this->owner->listAccessibleAgents()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('lists only the granted agents for a chatter, never the rest of the account', function (): void {
    $granted = makeTeamAgent($this->owner, 'Granted', $this->team);
    $other = makeTeamAgent($this->owner, 'Other', $this->team);

    $chatter = User::factory()->chatter($this->owner)->create();
    $chatter->accessibleAgents()->attach($granted->id);

    $ids = $chatter->listAccessibleAgents()->pluck('id')->all();

    expect($ids)->toBe([$granted->id])
        ->and($ids)->not->toContain($other->id);
});

it('lists all current-team agents for an employee whose role grants all agents', function (): void {
    $a = makeTeamAgent($this->owner, 'A', $this->team);
    $b = makeTeamAgent($this->owner, 'B', $this->team);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'All',
        permissions: [],
        allAgents: true,
        agentExternalIds: [],
    ));

    $employee = employeeWithRole($this->owner, $this->team, $role);

    expect($employee->listAccessibleAgents()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('lists only the role-scoped agents for an employee with specific-agent access', function (): void {
    $allowed = makeTeamAgent($this->owner, 'Allowed', $this->team);
    $denied = makeTeamAgent($this->owner, 'Denied', $this->team);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Scoped',
        permissions: [],
        allAgents: false,
        agentExternalIds: [$allowed->external_id],
    ));

    $employee = employeeWithRole($this->owner, $this->team, $role);

    $ids = $employee->listAccessibleAgents()->pluck('id')->all();

    expect($ids)->toBe([$allowed->id])
        ->and($ids)->not->toContain($denied->id);
});

it('lists nothing for an employee without a current team', function (): void {
    makeTeamAgent($this->owner, 'A', $this->team);

    $employee = User::factory()->employee($this->owner)->create(['current_team_id' => null]);

    expect($employee->listAccessibleAgents()->all())->toBe([]);
});
