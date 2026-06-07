<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
});

/*
 * Regression: `agents.access-all` is a meta-permission represented to the UI as
 * the "All Agents" toggle, NOT as a member of the permissions list. The edit
 * form round-trips the full permission list back on save, so when the user
 * unchecks "All Agents" the payload STILL carries `agents.access-all`. The
 * action must derive the permission's presence purely from allAgents — anything
 * less leaves the role silently seeing every agent.
 */
it('drops agents.access-all when scoping an all-agents role, even if the payload still carries it', function (): void {
    $kept = makeTeamAgent($this->owner, 'Kept', $this->team);
    $dropped = makeTeamAgent($this->owner, 'Dropped', $this->team);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    expect($role->hasPermissionTo('agents.access-all'))->toBeTrue();

    // Mirrors the edit form: "All Agents" unchecked + one agent picked, but the
    // round-tripped permissions array still contains the meta-permission.
    $updated = app(UpdateTeamRoleAction::class)->execute($role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Support',
        permissions: ['agent.chat.view', 'agents.access-all'],
        allAgents: false,
        agentExternalIds: [$kept->external_id],
    ));

    expect($updated->hasPermissionTo('agents.access-all'))->toBeFalse()
        ->and($updated->hasPermissionTo('agent.chat.view'))->toBeTrue()
        ->and($updated->agents()->pluck('agents.id')->all())->toBe([$kept->id]);

    // The scoped role now resolves to exactly the one agent — not all of them.
    $roleAgents = app(TeamService::class)->roleAccessibleAgents($updated, $this->team);

    expect($roleAgents->pluck('id')->all())->toBe([$kept->id])
        ->and($roleAgents->pluck('id')->all())->not->toContain($dropped->id);
});

it('grants agents.access-all when switching a scoped role back to all agents', function (): void {
    $agent = makeTeamAgent($this->owner, 'Bot', $this->team);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Scoped',
        permissions: ['agent.chat.view'],
        allAgents: false,
        agentExternalIds: [$agent->external_id],
    ));

    expect($role->hasPermissionTo('agents.access-all'))->toBeFalse()
        ->and($role->agents()->pluck('agents.id')->all())->toBe([$agent->id]);

    $updated = app(UpdateTeamRoleAction::class)->execute($role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Scoped',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    // Flipping to all-agents grants the meta-permission and clears the now-moot
    // per-agent pivot (access-all supersedes a scoped list).
    expect($updated->hasPermissionTo('agents.access-all'))->toBeTrue()
        ->and($updated->agents()->pluck('agents.id')->all())->toBe([]);
});
