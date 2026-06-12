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

        $jadwalPermission = Permission::findOrCreate('jadwal-pelajaran.manage', $guard);
        $jurnalPermission = Permission::findOrCreate('jurnal-mengajar.manage', $guard);

        $this->grantBySourcePermission(
            'kelas.manage',
            $jadwalPermission,
            $guard,
            ['super-admin', 'admin', 'kepsek']
        );

        $this->grantBySourcePermission(
            'absen.manage',
            $jurnalPermission,
            $guard,
            ['super-admin', 'admin', 'kepsek']
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

        foreach (['jadwal-pelajaran.manage', 'jurnal-mengajar.manage'] as $permissionName) {
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
     * @param  array<int, string>  $fallbackRoleNames
     */
    private function grantBySourcePermission(
        string $sourcePermission,
        Permission $targetPermission,
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
            if (!$role->hasPermissionTo($targetPermission)) {
                $role->givePermissionTo($targetPermission);
            }
        }
    }
};
