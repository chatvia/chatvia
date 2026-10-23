<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * A role answers from its own permissions only: never from Spatie's cached
 * list of every permission with every role of every workspace.
 */
function roleTestRole(array $permissions, bool $allAgents = false, array $agentExternalIds = []): Role
{
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);

    return teamRole($team, permissions: $permissions, allAgents: $allAgents, agentExternalIds: $agentExternalIds);
}

it('grants the permissions the role holds and nothing else', function (): void {
    $role = roleTestRole(['agent.chat.view'])->fresh();

    expect($role->grants('agent.chat.view'))->toBeTrue()
        ->and($role->grants('agent.chat.delete'))->toBeFalse()
        ->and($role->grants('no.such.permission'))->toBeFalse();
});

it('loads its own permissions once and never the permission cache', function (): void {
    $role = roleTestRole(['agent.chat.view'])->fresh();

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $role->grants('agent.chat.view');
    $role->grants('agent.chat.delete');
    $role->grantsAllAgents();

    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toContain('role_has_permissions');
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

it('checks its own agent list once per agent', function (): void {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $listed = createAgent($owner, ['team_id' => $team->id]);
    $other = createAgent($owner, ['team_id' => $team->id]);
    $role = teamRole($team, allAgents: false, agentExternalIds: [$listed->external_id])->fresh();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($role->listsAgent($listed))->toBeTrue()
        ->and($role->listsAgent($listed))->toBeTrue()
        ->and($role->listsAgent($other))->toBeFalse()
        ->and($queries)->toBe(2);
});
