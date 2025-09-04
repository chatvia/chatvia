<?php

namespace App\Data;

use App\Models\UserPermission;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

class UserPermissionData extends Data
{
    public function __construct(
        public string $permission,
    ) {}

    public static function fromUserPermissions(Collection $permissions)
    {
        return $permissions->map(function (UserPermission $permission) {
            return new self(
                $permission->permission->resource.'.'.$permission->permission->action
            );
        })->flatten();
    }
}
