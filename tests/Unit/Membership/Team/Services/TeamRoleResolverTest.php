<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Domain\Membership\Team\Services\TeamRoleResolver;
use Domain\Membership\Team\Services\TeamService;

it('maps every owned team to all codes and every joined team to its role permissions', function (): void {
    $user = User::factory()->create();
    $ownTeam = app(TeamService::class)->createDefaultTeam($user);

    $otherTeam = app(TeamService::class)->createDefaultTeam(User::factory()->create());
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $otherTeam,
        name: 'Viewer',
        permissions: ['agent.chat.view'],
        allAgents: false,
        agentExternalIds: [],
    ));
    $otherTeam->members()->attach($user->id, ['role_id' => $role->id]);

    $map = app(TeamRoleResolver::class)->permissionsByTeam($user);

    expect($map[$ownTeam->id])->toBe(app(TeamPermissionCatalog::class)->codes())
        ->and($map[$otherTeam->id])->toBe(['agent.chat.view']);
});
