<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $guard = (string) config('auth.defaults.guard', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('kartu-absensi.manage', $guard);

        $sourcePermission = Permission::query()
            ->where('name', 'absen.manage')
            ->where('guard_name', $guard)
            ->first();

        $roles = $sourcePermission
            ? $sourcePermission->roles()->where('guard_name', $guard)->get()
            : collect();

        if ($roles->isEmpty()) {
            $roles = Role::query()
                ->whereIn('name', ['super-admin', 'admin'])
                ->where('guard_name', $guard)
                ->get();
        }

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $guard = (string) config('auth.defaults.guard', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', 'kartu-absensi.manage')
            ->where('guard_name', $guard)
            ->first();

        if ($permission) {
            foreach ($permission->roles as $role) {
                if ($role->guard_name === $guard && $role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }

            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
