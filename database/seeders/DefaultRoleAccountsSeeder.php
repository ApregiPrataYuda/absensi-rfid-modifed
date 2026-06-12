<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultRoleAccountsSeeder extends Seeder
{
    public static function defaultAccounts(): array
    {
        return [
            [
                'role' => 'super-admin',
                'username' => 'superadmin',
                'name' => 'Super Admin',
                'password' => 'superadmin123',
                'kelas' => null,
            ],
            [
                'role' => 'admin',
                'username' => 'admin',
                'name' => 'Admin',
                'password' => 'admin123',
                'kelas' => null,
            ],
            [
                'role' => 'bendahara',
                'username' => 'bendahara',
                'name' => 'Bendahara',
                'password' => 'bendahara123',
                'kelas' => null,
            ],
            [
                'role' => 'kepsek',
                'username' => 'kepsek',
                'name' => 'Kepala Sekolah',
                'password' => 'kepsek123',
                'kelas' => null,
            ],
            [
                'role' => 'wakasek',
                'username' => 'wakasek',
                'name' => 'Wakil Kepala Sekolah',
                'password' => 'wakasek123',
                'kelas' => null,
            ],
            [
                'role' => 'wakel',
                'username' => 'mandor',
                'name' => 'Mandor Gedung 60 Lantai 1',
                'password' => 'guru123',
                'kelas' => 'Gedung 60',
            ],
            [
                'role' => 'piket',
                'username' => 'piket',
                'name' => 'Petugas Piket',
                'password' => 'piket123',
                'kelas' => null,
            ],
        ];
    }

    /**
     * Seed default accounts for all non-student roles.
     */
    public function run(): void
    {
        $accounts = self::defaultAccounts();

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['username' => $account['username']],
                [
                    'name' => $account['name'],
                    'email' => null,
                    'password' => Hash::make($account['password']),
                    'kelas' => $account['kelas'],
                ]
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
