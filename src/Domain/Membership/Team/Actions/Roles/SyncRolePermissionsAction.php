<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;

/**
 * Makes a role hold exactly the given permission codes. Permission rows are
 * created on first use (the catalog lives in config, not the database), then
 * the role's permissions are replaced — codes not in the list are removed.
 */
class SyncRolePermissionsAction
{
    /**
     * @param  list<string>  $permissionCodes
     */
    public function execute(Role $role, array $permissionCodes): void
    {
        $permissions = collect($permissionCodes)
            ->unique()
            ->map(fn (string $code): Permission => Permission::firstOrCreate([
                'name' => $code,
                'guard_name' => Permission::GUARD_NAME,
            ]));

        $role->syncPermissions($permissions);
    }
}
