<?php

namespace Database\Seeders;

use App\Models\Konfigurasi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');
        $roles = ['super-admin', 'admin', 'bendahara', 'kepsek', 'wakasek', 'wakel', 'piket', 'siswa'];
        $permissionsByRole = [
            'super-admin' => [
                'dashboard.view',
                'siswa.view',
                'guru.view',
                'piket.view',
                'absen.manage',
                'kartu-absensi.manage',
                'kelas.manage',
                'jadwal-pelajaran.manage',
                'jurnal-mengajar.manage',
                'izin-sakit.request',
                'izin-sakit.approve',
                'izin-sakit.manage',
                'kenaikan-kelas.manage',
                'arsip.manage',
                'monitoring.view',
                'rekap-bulanan.view',
                'rekap-tahunan.view',
                'rekap-absensi.view',
                'rekap-absensi-pelajaran.view',
                'scanner.use',
                'kartu-siswa.view',
                'settings.roles.manage',
                'settings.users.manage',
                'settings.general.manage',
                'settings.devices.manage',
                'settings.notifications.manage',
                'settings.backup.manage',
                'settings.update.manage',
                'notifications.send',
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
                'tabungan-siswa.self.view',
                'poin-pelanggaran.manage',
                'poin-pelanggaran.view',
               
            ],
            'admin' => [
                'dashboard.view',
                'siswa.view',
                'guru.view',
                'piket.view',
                'absen.manage',
                'kartu-absensi.manage',
                'kelas.manage',
                'jadwal-pelajaran.manage',
                'jurnal-mengajar.manage',
                'izin-sakit.request',
                'izin-sakit.approve',
                'izin-sakit.manage',
                'kenaikan-kelas.manage',
                'arsip.manage',
                'monitoring.view',
                'rekap-bulanan.view',
                'rekap-tahunan.view',
                'rekap-absensi.view',
                'rekap-absensi-pelajaran.view',
                'scanner.use',
                'kartu-siswa.view',
                'settings.users.manage',
                'settings.general.manage',
                'settings.devices.manage',
                'settings.notifications.manage',
                'notifications.send',
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
                'tabungan-siswa.self.view',
                'poin-pelanggaran.manage',
                'poin-pelanggaran.view',
            ],
            'bendahara' => [
                'dashboard.view',
                'tabungan-siswa.view',
                'tabungan-siswa.manage',
                'tabungan-siswa.report',
                'tabungan-siswa.jenis.manage',
            ],
            'kepsek' => [
                'dashboard.view',
                'siswa.view',
                'guru.view',
                'piket.view',
                'absen.manage',
                'kartu-absensi.manage',
                'kelas.manage',
                'jadwal-pelajaran.manage',
                'jurnal-mengajar.manage',
                'izin-sakit.request',
                'izin-sakit.approve',
                'izin-sakit.manage',
                'kenaikan-kelas.manage',
                'arsip.manage',
                'monitoring.view',
                'rekap-bulanan.view',
                'rekap-tahunan.view',
                'rekap-absensi.view',
                'rekap-absensi-pelajaran.view',
                'tabungan-siswa.view',
                'tabungan-siswa.report',
            ],
            'wakasek' => [
                'dashboard.view',
                'monitoring.view',
                'rekap-bulanan.view',
                'rekap-tahunan.view',
                'rekap-absensi.view',
                'rekap-absensi-pelajaran.view',
                'tabungan-siswa.view',
                'tabungan-siswa.report',
            ],
            'wakel' => [
                'dashboard.view',
                'siswa.view',
                'monitoring.view',
                'rekap-bulanan.view',
                'rekap-tahunan.view',
                'scanner.use',
                'kartu-siswa.view',
                'rekap-absensi.view',
                'rekap-absensi-pelajaran.view',
                'izin-sakit.request',
                'izin-sakit.approve',
                'tabungan-siswa.view',
            ],
            'piket' => [
                'dashboard.view',
                'monitoring.view',
                'scanner.use',
                'rekap-absensi-pelajaran.view',
            ],
            'siswa' => [
                'dashboard.view',
                'kartu-siswa.view',
                'izin-sakit.request',
                'tabungan-siswa.self.view',
            ],
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, $guard);
        }

        $permissionNames = collect($permissionsByRole)
            ->flatten()
            ->unique()
            ->values()
            ->all();
        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissionMap = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissionNames)
            ->get()
            ->keyBy('name');

        foreach ($permissionsByRole as $roleName => $permissions) {
            $role = Role::findByName($roleName, $guard);
            $rolePermissions = collect($permissions)
                ->map(fn (string $permissionName) => $permissionMap->get($permissionName))
                ->filter()
                ->values()
                ->all();

            $role->syncPermissions($rolePermissions);
        }

        $this->call(DefaultRoleAccountsSeeder::class);

        $configs = [
            ['key' => 'jam_masuk_mulai', 'value' => '08:00', 'keterangan' => 'Waktu absen datang dibuka'],
            ['key' => 'jam_masuk_akhir', 'value' => '08:30', 'keterangan' => 'Batas waktu terlambat'],
            ['key' => 'jam_masuk_telat', 'value' => '09:00', 'keterangan' => 'Batas akhir absen masuk'],
            ['key' => 'jam_pulang_mulai', 'value' => '17:00', 'keterangan' => 'Waktu absen pulang dibuka'],
            ['key' => 'jam_pulang_akhir', 'value' => '17:30', 'keterangan' => 'Batas akhir absen pulang'],
        ];

        foreach ($configs as $config) {
            Konfigurasi::query()->updateOrCreate(
                ['key' => $config['key']],
                ['value' => $config['value'], 'keterangan' => $config['keterangan']]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
