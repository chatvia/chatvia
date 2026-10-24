<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\SyncRolePermissionsAction;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;

/**
 * Saving a role's permissions creates the permission rows it needs and
 * replaces the role's list.
 */
it('creates missing permission rows and replaces the role permissions', function (): void {
    $team = app(TeamService::class)->createDefaultTeam(User::factory()->create());
    $role = Role::query()->create([
        'name' => 'Reviewer',
        'code' => 'reviewer',
        'team_id' => $team->id,
        'guard_name' => Permission::GUARD_NAME,
    ]);

    app(SyncRolePermissionsAction::class)->execute($role, ['agent.chat.view', 'agent.audit.view', 'agent.chat.view']);
    app(SyncRolePermissionsAction::class)->execute($role->fresh(), ['agent.audit.view']);

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['agent.audit.view'])
        ->and(Permission::query()->where('name', 'agent.chat.view')->where('guard_name', Permission::GUARD_NAME)->exists())->toBeTrue();
});
