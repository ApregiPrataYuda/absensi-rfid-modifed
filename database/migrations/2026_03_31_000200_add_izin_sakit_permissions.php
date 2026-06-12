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

        $requestPermission = Permission::findOrCreate('izin-sakit.request', $guard);
        $approvePermission = Permission::findOrCreate('izin-sakit.approve', $guard);
        $managePermission = Permission::findOrCreate('izin-sakit.manage', $guard);

        // Role yang sebelumnya bisa kelola absen akan tetap bisa penuh.
        $this->grantBySourcePermission(
            'absen.manage',
            [$requestPermission, $approvePermission, $managePermission],
            $guard,
            ['super-admin', 'admin', 'kepsek']
        );

        // Wali kelas default bisa mengajukan dan menyetujui.
        $this->grantByRoleNames(
            ['wakel'],
            [$requestPermission, $approvePermission],
            $guard
        );

        // Siswa default bisa mengajukan izin/sakit.
        $this->grantByRoleNames(
            ['siswa'],
            [$requestPermission],
            $guard
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

        foreach (['izin-sakit.request', 'izin-sakit.approve', 'izin-sakit.manage'] as $permissionName) {
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

    /**
     * @param  array<int, string>  $roleNames
     * @param  array<int, Permission>  $targetPermissions
     */
    private function grantByRoleNames(array $roleNames, array $targetPermissions, string $guard): void
    {
        $roles = Role::query()
            ->whereIn('name', $roleNames)
            ->where('guard_name', $guard)
            ->get();

        foreach ($roles as $role) {
            foreach ($targetPermissions as $permission) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
};
