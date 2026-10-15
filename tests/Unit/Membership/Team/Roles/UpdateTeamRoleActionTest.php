<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamAgentAccess;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
});

/*
 * `agents.access-all` is a meta-permission shown in the UI as the "All Agents"
 * toggle, not as an entry of the permissions list. The edit form sends the
 * full permission list back on save, so when the user unchecks "All Agents"
 * the payload still carries `agents.access-all`. The permission must follow
 * allAgents alone, or the role would quietly keep seeing every agent.
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
    $roleAgents = app(TeamAgentAccess::class)->roleAccessibleAgents($updated, $this->team);

    expect($roleAgents->pluck('id')->all())->toBe([$kept->id])
        ->and($roleAgents->pluck('id')->all())->not->toContain($dropped->id);
});

it('renames the role', function (): void {
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    $updated = app(UpdateTeamRoleAction::class)->execute($role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Customer care',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    expect($updated->name)->toBe('Customer care')
        ->and($role->fresh()->name)->toBe('Customer care');
});

it('keeps the old name and grants when the new grants are rejected', function (): void {
    $agent = makeTeamAgent($this->owner, 'Bot', $this->team);
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $this->team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: false,
        agentExternalIds: [$agent->external_id],
    ));

    expect(fn () => app(UpdateTeamRoleAction::class)->execute($role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Renamed',
        permissions: [],
        allAgents: false,
        agentExternalIds: ['not-an-agent'],
    )))->toThrow(DomainValidationException::class);

    $fresh = $role->fresh();

    expect($fresh->name)->toBe('Support')
        ->and($fresh->hasPermissionTo('agent.chat.view'))->toBeTrue()
        ->and($fresh->agents()->pluck('agents.id')->all())->toBe([$agent->id]);
});
