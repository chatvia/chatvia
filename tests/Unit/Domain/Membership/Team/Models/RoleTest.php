<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;

/**
 * A role answers from its own permissions only: never from Spatie's cached
 * list of every permission with every role of every workspace. All agents
 * come from the meta-permission or the owner role.
 */
function roleTestRole(array $permissions, bool $allAgents = false, array $agentExternalIds = []): Role
{
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);

    return teamRole($team, permissions: $permissions, allAgents: $allAgents, agentExternalIds: $agentExternalIds);
}

it('grants the permissions the role holds and nothing else', function (): void {
    $role = roleTestRole(['agent.chat.view'])->fresh();

    expect($role->grants('agent.chat.view'))->toBeTrue()
        ->and($role->grants('agent.chat.delete'))->toBeFalse()
        ->and($role->grants('no.such.permission'))->toBeFalse();
});

it('grants all agents through the meta-permission or as the owner role', function (): void {
    $restricted = roleTestRole(['agent.chat.view'])->fresh();
    $allAgents = roleTestRole(['agent.chat.view'], allAgents: true)->fresh();
    $ownerRole = Role::query()->where('code', Role::OWNER_CODE)->firstOrFail();

    expect($restricted->grantsAllAgents())->toBeFalse()
        ->and($allAgents->grantsAllAgents())->toBeTrue()
        ->and($allAgents->grants(Permission::ACCESS_ALL_AGENTS))->toBeTrue()
        ->and($ownerRole->grantsAllAgents())->toBeTrue();
});
