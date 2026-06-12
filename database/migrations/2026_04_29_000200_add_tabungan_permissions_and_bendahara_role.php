<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'web');
        $permissions = [
            'tabungan-siswa.view',
            'tabungan-siswa.manage',
            'tabungan-siswa.report',
            'tabungan-siswa.jenis.manage',
            'tabungan-siswa.self.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        $bendaharaRole = Role::findOrCreate('bendahara', $guard);

        $rolePermissions = [
            'super-admin' => [
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
                'tabungan-siswa.self.view',
            ],
            'admin' => [
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
            ],
            'bendahara' => [
                'dashboard.view',
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
            ],
            'kepsek' => [
                'tabungan-siswa.view',
                'tabungan-siswa.report',
            ],
            'wakasek' => [
                'tabungan-siswa.view',
                'tabungan-siswa.report',
            ],
            'wakel' => [
                'tabungan-siswa.view',
            ],
            'siswa' => [
                'tabungan-siswa.self.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = $roleName === 'bendahara'
                ? $bendaharaRole
                : Role::findOrCreate($roleName, $guard);

            $permissionsToGive = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $permissionNames)
                ->get();

            $role->givePermissionTo($permissionsToGive);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'web');
        $permissionNames = [
            'tabungan-siswa.view',
            'tabungan-siswa.manage',
            'tabungan-siswa.report',
            'tabungan-siswa.jenis.manage',
            'tabungan-siswa.self.view',
        ];

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->whereIn('name', ['super-admin', 'admin', 'bendahara', 'kepsek', 'wakasek', 'wakel', 'siswa'])
            ->get();

        foreach ($roles as $role) {
            $role->revokePermissionTo($permissionNames);
        }

        Role::query()
            ->where('guard_name', $guard)
            ->where('name', 'bendahara')
            ->delete();

        Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissionNames)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
