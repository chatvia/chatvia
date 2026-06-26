<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

        $channelsId = DB::table($permissionsTable)
            ->where('name', 'agent.channels')->where('guard_name', 'web')->value('id')
            ?? DB::table($permissionsTable)->insertGetId(['name' => 'agent.channels', 'guard_name' => 'web']);

        $setupId = DB::table($permissionsTable)
            ->where('name', 'agent.setup')->where('guard_name', 'web')->value('id');

        if ($setupId !== null) {
            $roleIds = DB::table($pivotTable)
                ->where($permissionKey, $setupId)
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from("{$pivotTable} as granted")
                    ->whereColumn("granted.{$roleKey}", "{$pivotTable}.{$roleKey}")
                    ->where("granted.{$permissionKey}", $channelsId))
                ->pluck($roleKey);

            foreach ($roleIds as $roleId) {
                DB::table($pivotTable)->insert([$permissionKey => $channelsId, $roleKey => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        $channelsId = DB::table($permissionsTable)
            ->where('name', 'agent.channels')->where('guard_name', 'web')->value('id');

        if ($channelsId !== null) {
            DB::table($pivotTable)->where($permissionKey, $channelsId)->delete();
            DB::table($permissionsTable)->where('id', $channelsId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
