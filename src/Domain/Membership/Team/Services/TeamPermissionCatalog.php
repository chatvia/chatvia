<?php

namespace Domain\Membership\Team\Services;

use Domain\Membership\Team\Data\Resources\Permissions\PermissionGroupData;
use Domain\Membership\Team\Data\Resources\Permissions\PermissionMetaData;
use Domain\Membership\Team\Data\Resources\Permissions\PermissionScopeData;
use Domain\Membership\Team\Data\Resources\Roles\RoleConfigData;

/**
 * Read-only view of config/team-permissions.php: every permission code with
 * its label, the scopes and UI groups that organise them, and the default roles
 * a new team starts with. The config file is the single source of truth; this
 * class only turns its arrays into typed DTOs for the role editor and the
 * validation rules.
 */
class TeamPermissionCatalog
{
    /**
     * Every permission code, in config order.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->rawPermissions());
    }

    /**
     * @return array<string, PermissionMetaData>
     */
    public function permissions(): array
    {
        return collect($this->rawPermissions())
            ->map(fn (mixed $data, string $code): PermissionMetaData => PermissionMetaData::fromConfig(is_array($data) ? $data : ['label' => $data], $code))
            ->all();
    }

    /**
     * @return array<string, PermissionGroupData>
     */
    public function groups(): array
    {
        return collect((array) config('team-permissions.groups', []))
            ->map(fn (array $data): PermissionGroupData => PermissionGroupData::fromConfig($data))
            ->all();
    }

    /**
     * @return array<string, PermissionScopeData>
     */
    public function scopes(): array
    {
        return collect((array) config('team-permissions.scopes', []))
            ->map(fn (array $data): PermissionScopeData => PermissionScopeData::fromConfig($data))
            ->all();
    }

    /**
     * The roles every new team is created with, keyed by role code. A role's
     * permission list may hold `*`, meaning "every code" — see
     * {@see self::expand()}.
     *
     * @return array<string, RoleConfigData>
     */
    public function defaultRoles(): array
    {
        return collect((array) config('team-permissions.roles', []))
            ->map(fn (array $data): RoleConfigData => RoleConfigData::fromConfig($data))
            ->all();
    }

    /**
     * Expand a configured permission list: `*` stands for every code.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    public function expand(array $permissions): array
    {
        return in_array('*', $permissions, true) ? $this->codes() : array_values($permissions);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawPermissions(): array
    {
        return (array) config('team-permissions.permissions', []);
    }
}
