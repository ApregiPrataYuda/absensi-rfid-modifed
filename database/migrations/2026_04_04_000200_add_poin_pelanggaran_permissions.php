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

        $viewPermission = Permission::findOrCreate('poin-pelanggaran.view', $guard);
        $managePermission = Permission::findOrCreate('poin-pelanggaran.manage', $guard);

        $this->grantBySourcePermission(
            'monitoring.view',
            [$viewPermission],
            $guard,
            ['admin', 'kepsek', 'wakasek', 'wakel']
        );

        $this->grantBySourcePermission(
            'absen.manage',
            [$managePermission],
            $guard,
            ['admin', 'kepsek', 'wakel']
        );

        // Jika bisa manage, otomatis bisa view.
        $this->grantBySourcePermission(
            'poin-pelanggaran.manage',
            [$viewPermission],
            $guard,
            ['admin', 'kepsek', 'wakel']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $guard = (string) config('auth.defaults.guard', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['poin-pelanggaran.manage', 'poin-pelanggaran.view'] as $permissionName) {
            $permission = Permission::query()
                ->where('name', $permissionName)
                ->where('guard_name', $guard)
                ->first();

            if (!$permission) {
                continue;
            }

            foreach ($permission->roles as $role) {
                if ($role->guard_name === $guard && $role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }

            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<int, Permission>  $targetPermissions
     * @param  array<int, string>  $fallbackRoleNames
     */
    private function grantBySourcePermission(
        string $sourcePermission,
        array $targetPermissions,
        string $guard,
        array $fallbackRoleNames
    ): void {
        $source = Permission::query()
            ->where('name', $sourcePermission)
            ->where('guard_name', $guard)
            ->first();

        $roles = $source
            ? $source->roles()->where('guard_name', $guard)->get()
            : collect();

        if ($roles->isEmpty()) {
            $roles = Role::query()
                ->whereIn('name', $fallbackRoleNames)
                ->where('guard_name', $guard)
                ->get();
        }

        foreach ($roles as $role) {
            foreach ($targetPermissions as $permission) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
};
