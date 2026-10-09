<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamAgentAccess;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->granted = makeTeamAgent($this->owner, 'Granted', $this->team);
    $this->other = makeTeamAgent($this->owner, 'Other', $this->team);
    $this->member = User::factory()->create(['type' => UserType::Employee, 'workspace_id' => $this->owner->id]);
});

function joinWithRole(object $test, bool $allAgents, array $agentIds = []): void
{
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $test->team,
        name: 'Role '.$test->team->roles()->count(),
        permissions: [],
        allAgents: $allAgents,
        agentExternalIds: $agentIds,
    ));

    $test->team->members()->attach($test->member->id, ['role_id' => $role->id]);
}

it('lets the owner reach every agent of the team', function (): void {
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->owner, $this->team, $this->other))->toBeTrue()
        ->and($access->accessibleAgents($this->owner, $this->team)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->granted->id, $this->other->id])->sort()->values()->all());
});

it('limits a scoped role to its own agents', function (): void {
    joinWithRole($this, allAgents: false, agentIds: [$this->granted->external_id]);
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->member, $this->team, $this->granted))->toBeTrue()
        ->and($access->canAccess($this->member, $this->team, $this->other))->toBeFalse()
        ->and($access->accessibleAgents($this->member, $this->team)->pluck('id')->all())->toBe([$this->granted->id]);
});

it('lets an all-agents role reach every agent of the team but none outside it', function (): void {
    joinWithRole($this, allAgents: true);
    $outside = makeTeamAgent(User::factory()->create(), 'Outside');
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->member, $this->team, $this->other))->toBeTrue()
        ->and($access->canAccess($this->member, $this->team, $outside))->toBeFalse();
});

it('gives a user without a role in the team nothing', function (): void {
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->member, $this->team, $this->granted))->toBeFalse()
        ->and($access->accessibleAgents($this->member, $this->team))->toBeEmpty();
});

it('never lets a team owner reach another workspace agent', function (): void {
    // Regression: the owner shortcut used to pass for ANY agent while the
    // owner had a current team, including other tenants' agents.
    $otherOwner = User::factory()->create();
    $foreignAgent = makeTeamAgent($otherOwner, 'Foreign');
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->owner, $this->team, $foreignAgent))->toBeFalse()
        ->and(app(TeamService::class)->canAccessAgent($this->owner, $this->team, $foreignAgent))->toBeFalse()
        ->and($access->canAccess($this->owner, $this->team, $this->granted))->toBeTrue();
});

it('lets a team owner reach their own agent in another of their teams', function (): void {
    $secondTeam = app(TeamService::class)->createDefaultTeam($this->owner);
    $agentInSecondTeam = makeTeamAgent($this->owner, 'Second', $secondTeam);

    expect(app(TeamAgentAccess::class)->canAccess($this->owner, $this->team, $agentInSecondTeam))->toBeTrue();
});
