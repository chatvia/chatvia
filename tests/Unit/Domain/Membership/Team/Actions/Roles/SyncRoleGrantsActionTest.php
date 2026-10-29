<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\SyncRoleGrantsAction;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Support\Exceptions\DomainValidationException;

/**
 * A role's agent grants refuse agents outside the team.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->role = Role::query()->create([
        'name' => 'Agents',
        'code' => 'agents',
        'team_id' => $this->team->id,
        'guard_name' => Permission::GUARD_NAME,
    ]);
});

it('rejects agents that are not in the team', function (): void {
    expect(fn () => app(SyncRoleGrantsAction::class)->execute($this->role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Agents',
        permissions: [],
        allAgents: false,
        agentExternalIds: ['not-an-agent'],
    )))->toThrow(DomainValidationException::class, 'One or more selected agents are invalid.');
});
