<?php

namespace Domain\Membership\Team\Actions\Roles;

use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Creates a custom team role from the role editor (or the API). The role code
 * is derived from the name and must be unique in the team; the permissions and
 * agent access are applied by {@see SyncRoleGrantsAction} in the same
 * transaction.
 */
class CreateTeamRoleAction
{
    public function __construct(
        private readonly SyncRoleGrantsAction $syncGrants,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(SaveTeamRoleData $data): Role
    {
        $team = $data->team;
        $code = strtolower(str_replace(' ', '_', $data->name));

        if ($team->roles()->where('code', $code)->exists()) {
            throw new DomainValidationException('name', __('A role with this name already exists.'));
        }

        return DB::transaction(function () use ($data, $code): Role {
            /** @var Role $role */
            $role = Role::query()->create([
                'name' => $data->name,
                'code' => $code,
                'team_id' => $data->team->id,
                'guard_name' => Permission::GUARD_NAME,
            ]);

            $this->syncGrants->execute($role, $data);

            return $role;
        });
    }
}
