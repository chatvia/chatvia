<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamPermissionCatalog;

/**
 * Gives a new team the default roles from config/team-permissions.php (today
 * only the owner role, which holds every permission). Idempotent: a role that
 * already exists is reused and its permissions are synced again.
 */
class CreateDefaultRolesAction
{
    public function __construct(
        private readonly TeamPermissionCatalog $catalog,
        private readonly SyncRolePermissionsAction $syncPermissions,
    ) {}

    public function execute(Team $team): void
    {
        foreach ($this->catalog->defaultRoles() as $code => $config) {
            $role = Role::firstOrCreate(
                [
                    'name' => $config->name,
                    'team_id' => $team->id,
                    'guard_name' => Permission::GUARD_NAME,
                ],
                [
                    'code' => $code,
                    'description' => $config->description,
                ],
            );

            $this->syncPermissions->execute($role, $this->catalog->expand($config->permissions));
        }
    }
}
