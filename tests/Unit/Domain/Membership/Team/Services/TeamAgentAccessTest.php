<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Services\TeamAgentAccess;

/**
 * Which agents a team role reaches: the owner every agent of the workspace,
 * a scoped role its own list, an all-agents role the team's agents, and
 * nobody another workspace's agent.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->granted = makeTeamAgent($this->owner, 'Granted', $this->team);
    $this->other = makeTeamAgent($this->owner, 'Other', $this->team);
    $this->member = User::factory()->employee($this->owner)->create();
});

function taaJoinWithRole(object $test, bool $allAgents, array $agentIds = []): void
{
    $role = teamRole($test->team, 'Role '.$test->team->roles()->count(), permissions: [], allAgents: $allAgents, agentExternalIds: $agentIds);

    $test->team->members()->attach($test->member->id, ['role_id' => $role->id]);
}

it('lets the owner reach every agent of the team', function (): void {
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->owner, $this->team, $this->other))->toBeTrue()
        ->and($access->accessibleAgents($this->owner, $this->team)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->granted->id, $this->other->id])->sort()->values()->all());
});

it('limits a scoped role to its own agents', function (): void {
    taaJoinWithRole($this, allAgents: false, agentIds: [$this->granted->external_id]);
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->member, $this->team, $this->granted))->toBeTrue()
        ->and($access->canAccess($this->member, $this->team, $this->other))->toBeFalse()
        ->and($access->accessibleAgents($this->member, $this->team)->pluck('id')->all())->toBe([$this->granted->id]);
});

it('lets an all-agents role reach every agent of the team but none outside it', function (): void {
    taaJoinWithRole($this, allAgents: true);
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
    // The owner shortcut covers the owner's own agents only, never another
    // workspace's agent, whatever team is current.
    $otherOwner = User::factory()->create();
    $foreignAgent = makeTeamAgent($otherOwner, 'Foreign');
    $access = app(TeamAgentAccess::class);

    expect($access->canAccess($this->owner, $this->team, $foreignAgent))->toBeFalse()
        ->and(app(TeamAgentAccess::class)->canAccess($this->owner, $this->team, $foreignAgent))->toBeFalse()
        ->and($access->canAccess($this->owner, $this->team, $this->granted))->toBeTrue();
});

it('lets a team owner reach their own agent in another of their teams', function (): void {
    $secondTeam = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $agentInSecondTeam = makeTeamAgent($this->owner, 'Second', $secondTeam);

    expect(app(TeamAgentAccess::class)->canAccess($this->owner, $this->team, $agentInSecondTeam))->toBeTrue();
});
