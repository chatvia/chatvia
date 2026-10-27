<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamPermissionCatalog;
use Domain\Membership\Team\Services\TeamRoleResolver;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The permissions a person holds per team: an owner every code on every team
 * (without reading `team_user`), a teammate their role's permissions on each
 * team they joined.
 */
it('maps every team of the owner to all codes, without reading team_user', function (): void {
    $owner = User::factory()->create();
    $ownTeam = app(CreateDefaultTeamAction::class)->execute($owner);
    $spare = app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $owner->workspace(), name: 'Spare'));
    $owner->isWorkspaceOwner(); // load the membership before counting queries

    $teamUserQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$teamUserQueries): void {
        $teamUserQueries += (int) Str::contains($query->sql, 'team_user');
    });

    $map = app(TeamRoleResolver::class)->permissionsByTeam($owner);
    $codes = app(TeamPermissionCatalog::class)->codes();

    expect($map)->toBe([$ownTeam->id => $codes, $spare->id => $codes])
        ->and($teamUserQueries)->toBe(0);
});

it('maps every joined team of a teammate to its role permissions', function (): void {
    $owner = User::factory()->create();
    $team = app(CreateDefaultTeamAction::class)->execute($owner);
    $role = teamRole($team, 'Viewer', allAgents: false);
    $teammate = User::factory()->employee($owner)->create();
    $team->members()->attach($teammate->id, ['role_id' => $role->id]);

    expect(app(TeamRoleResolver::class)->permissionsByTeam($teammate))->toBe([$team->id => ['agent.chat.view']]);
});
