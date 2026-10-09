<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Role;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Renames a team role and replaces its permissions and agent access with the
 * values from the role editor (or the API), in one transaction. The grant
 * rules live in {@see SyncRoleGrantsAction}.
 */
class UpdateTeamRoleAction
{
    public function __construct(
        private readonly SyncRoleGrantsAction $syncGrants,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(Role $role, SaveTeamRoleData $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $role->update(['name' => $data->name]);

            $this->syncGrants->execute($role, $data);

            return $role->fresh();
        });
    }
}
