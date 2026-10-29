<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateDefaultRolesAction;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;

/**
 * A new team's default roles: the owner role with every permission code.
 */
it('gives a team the owner role with every permission code', function (): void {
    $owner = User::factory()->create();
    $team = Team::create(['name' => 'Bare', 'workspace_id' => $owner->workspace()->id]);

    app(CreateDefaultRolesAction::class)->execute($team);

    /** @var Role $role */
    $role = $team->roles()->where('code', Role::OWNER_CODE)->firstOrFail();

    expect($role->guard_name)->toBe(Permission::GUARD_NAME)
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(app(TeamPermissionCatalog::class)->codes())->sort()->values()->all())
        ->and($role->grantsAllAgents())->toBeTrue();
});
