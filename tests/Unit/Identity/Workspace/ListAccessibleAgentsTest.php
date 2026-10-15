<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/*
 * Pins User::listAccessibleAgents(), the agent set the chat switcher lists for
 * a staff account. It is the plural companion to canAccessAgent() and must
 * agree with it: workspace owners see the whole account, employees resolve
 * through their current team (or their first team) and role. A chatter grant
 * never reaches a staff account (AgentAccessPolicyTest covers that).
 */

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->update(['current_team_id' => $this->team->id]);
});

it('lists every account agent for a workspace owner, regardless of team', function (): void {
    $a = makeTeamAgent($this->owner, 'A', $this->team);

    $secondTeam = app(TeamService::class)->createDefaultTeam($this->owner);
    $b = makeTeamAgent($this->owner, 'B', $secondTeam);

    expect($this->owner->listAccessibleAgents()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('lists all current-team agents for an employee whose role grants all agents', function (): void {
    $a = makeTeamAgent($this->owner, 'A', $this->team);
    $b = makeTeamAgent($this->owner, 'B', $this->team);

    $employee = teammateWithPermissions($this->owner, [], $this->team);

    expect($employee->listAccessibleAgents()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('lists only the role-scoped agents for an employee with specific-agent access', function (): void {
    $allowed = makeTeamAgent($this->owner, 'Allowed', $this->team);
    $denied = makeTeamAgent($this->owner, 'Denied', $this->team);

    $employee = teammateWithPermissions($this->owner, [], $this->team, allAgents: false, agentExternalIds: [$allowed->external_id]);

    $ids = $employee->listAccessibleAgents()->pluck('id')->all();

    expect($ids)->toBe([$allowed->id])
        ->and($ids)->not->toContain($denied->id);
});

it('lists the first team agents for an employee without a current team', function (): void {
    $a = makeTeamAgent($this->owner, 'A', $this->team);
    $otherTeam = app(TeamService::class)->createDefaultTeam($this->owner);
    makeTeamAgent($this->owner, 'Elsewhere', $otherTeam);

    $employee = teammateWithPermissions($this->owner, [], $this->team);
    $employee->update(['current_team_id' => null]);

    expect($employee->fresh()->listAccessibleAgents()->pluck('id')->all())->toBe([$a->id]);
});

it('lists nothing for an employee who belongs to no team', function (): void {
    makeTeamAgent($this->owner, 'A', $this->team);

    $employee = User::factory()->employee($this->owner)->create(['current_team_id' => null]);

    expect($employee->listAccessibleAgents()->all())->toBe([]);
});
