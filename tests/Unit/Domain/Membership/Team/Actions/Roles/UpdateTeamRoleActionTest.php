<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\UpdateTeamRoleAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamAgentAccess;
use Support\Exceptions\DomainValidationException;

/**
 * Editing a team role: the all-agents meta-permission follows the "All
 * Agents" toggle alone, even when the round-tripped permission list still
 * carries it; rejected grants keep the old name and grants.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
});

it('drops agents.access-all when scoping an all-agents role, even if the payload still carries it', function (): void {
    $kept = makeTeamAgent($this->owner, 'Kept', $this->team);
    $dropped = makeTeamAgent($this->owner, 'Dropped', $this->team);

    $role = teamRole($this->team);
    expect($role->hasPermissionTo('agents.access-all'))->toBeTrue();

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

    $roleAgents = app(TeamAgentAccess::class)->roleAccessibleAgents($updated, $this->team);

    expect($roleAgents->pluck('id')->all())->toBe([$kept->id])
        ->and($roleAgents->pluck('id')->all())->not->toContain($dropped->id);
});

it('keeps the old name and grants when the new grants are rejected', function (): void {
    $agent = makeTeamAgent($this->owner, 'Bot', $this->team);
    $role = teamRole($this->team, allAgents: false, agentExternalIds: [$agent->external_id]);

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
