<?php

namespace App\Services\Modules;


use App\Jobs\SendWaAttendanceNotificationJob;
use App\Models\Absensi;
use App\Models\AbsensiPelajaran;
use App\Models\AuthToken;
use App\Models\HariLibur;
use App\Models\IzinSakitRequest;
use App\Models\JadwalHarian;
use App\Models\JadwalPelajaran;
use App\Models\KartuAbsensi;
use App\Models\Kelas;
use App\Models\Konfigurasi;
use App\Models\SesiPelajaran;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AttendanceCardService;
use App\Services\StudentAttendanceService;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

class StaffRecordService extends BaseActionService
{
    public function getGuruList($auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $data = User::role('wakel')
            ->orderBy('username')
            ->get()
            ->map(function (User $user) {
                $tanggalLahir = null;
                if (!empty($user->tanggal_lahir)) {
                    try {
                        $tanggalLahir = Carbon::parse((string) $user->tanggal_lahir)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $tanggalLahir = null;
                    }
                }

                return [
                    'username' => $user->username,
                    'name' => $user->name ?: $user->username,
                    'email' => $user->email,
                    'kelas' => $user->kelas,
                    'jenisKelamin' => $user->jenis_kelamin,
                    'tanggalLahir' => $tanggalLahir,
                    'agama' => $user->agama,
                    'noHp' => $user->no_hp,
                    'alamat' => $user->alamat,
                    'role' => $this->getPrimaryRoleForUser($user),
                    'password' => '******',
                ];
            })
            ->values()
            ->all();

        return ['success' => true, 'data' => $data];
    }


    public function getPiketList($auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $data = User::role('piket')
            ->orderBy('username')
            ->get()
            ->map(function (User $user) {
                $tanggalLahir = null;
                if (!empty($user->tanggal_lahir)) {
                    try {
                        $tanggalLahir = Carbon::parse((string) $user->tanggal_lahir)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $tanggalLahir = null;
                    }
                }

                return [
                    'username' => $user->username,
                    'name' => $user->name ?: $user->username,
                    'email' => $user->email,
                    'kelas' => $user->kelas,
                    'jenisKelamin' => $user->jenis_kelamin,
                    'tanggalLahir' => $tanggalLahir,
                    'agama' => $user->agama,
                    'noHp' => $user->no_hp,
                    'alamat' => $user->alamat,
                    'role' => $this->getPrimaryRoleForUser($user),
                    'password' => '******',
                ];
            })
            ->values()
            ->all();

        return ['success' => true, 'data' => $data];
    }


    public function addGuru(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $username = ltrim(trim((string) ($args[0] ?? '')), "'");
        $password = ltrim(trim((string) ($args[1] ?? '')), "'");
        $kelas = $this->syncKelasValue($args[2] ?? null);
        $name = trim((string) ($args[3] ?? ''));
        $email = $this->normalizeEmailValue($args[4] ?? null);
        $jenisKelamin = $this->normalizeOptionalString($args[5] ?? null);
        $tanggalLahirInput = trim((string) ($args[6] ?? ''));
        $agama = $this->normalizeOptionalString($args[7] ?? null);
        $noHp = $this->normalizeOptionalString($args[8] ?? null);
        $alamat = $this->normalizeOptionalString($args[9] ?? null);

        $tanggalLahir = null;
        if ($tanggalLahirInput !== '') {
            try {
                $tanggalLahir = Carbon::parse($tanggalLahirInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Format tanggal lahir guru tidak valid.'];
            }
        }

        if ($username === '' || $password === '') {
            return ['success' => false, 'message' => 'Username dan password wajib diisi.'];
        }

        if ($name === '') {
            $name = $username;
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }

        if (User::query()->where('username', $username)->exists()) {
            return ['success' => false, 'message' => 'Username sudah digunakan.'];
        }

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            return ['success' => false, 'message' => 'Email sudah digunakan.'];
        }

        if ($this->isDuplicateNoHp($noHp)) {
            return ['success' => false, 'message' => 'No HP sudah digunakan.'];
        }

        DB::transaction(function () use ($username, $name, $email, $password, $kelas, $jenisKelamin, $tanggalLahir, $agama, $noHp, $alamat): void {
            $user = User::query()->create([
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password' => bcrypt($password),
                'kelas' => $kelas,
                'jenis_kelamin' => $jenisKelamin,
                'tanggal_lahir' => $tanggalLahir,
                'agama' => $agama,
                'no_hp' => $noHp,
                'alamat' => $alamat,
            ]);
            $this->syncSpatieRoleForUser($user, 'wakel');
            $this->syncGuruClassBinding($user, $kelas, null);
        });

        return ['success' => true, 'message' => 'Akun guru berhasil ditambahkan.'];
    }


    public function updateGuru(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $oldUsername = ltrim(trim((string) ($args[0] ?? '')), "'");
        $newUsername = ltrim(trim((string) ($args[1] ?? '')), "'");
        $password = ltrim(trim((string) ($args[2] ?? '')), "'");
        $kelas = $this->syncKelasValue($args[3] ?? null);
        $name = trim((string) ($args[4] ?? ''));
        $email = $this->normalizeEmailValue($args[5] ?? null);
        $jenisKelamin = $this->normalizeOptionalString($args[6] ?? null);
        $tanggalLahirInput = trim((string) ($args[7] ?? ''));
        $agama = $this->normalizeOptionalString($args[8] ?? null);
        $noHp = $this->normalizeOptionalString($args[9] ?? null);
        $alamat = $this->normalizeOptionalString($args[10] ?? null);

        $tanggalLahir = null;
        if ($tanggalLahirInput !== '') {
            try {
                $tanggalLahir = Carbon::parse($tanggalLahirInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Format tanggal lahir guru tidak valid.'];
            }
        }

        if ($oldUsername === '' || $newUsername === '') {
            return ['success' => false, 'message' => 'Username tidak valid.'];
        }

        $user = User::query()->where('username', $oldUsername)->first();
        if (!$user) {
            return ['success' => false, 'message' => 'Akun guru tidak ditemukan.'];
        }
        if (!$user->hasRole('wakel')) {
            return ['success' => false, 'message' => 'Akun ini bukan guru/wali kelas yang dapat dikelola dari menu ini.'];
        }

        if ($newUsername !== $oldUsername && User::query()->where('username', $newUsername)->exists()) {
            return ['success' => false, 'message' => 'Username baru sudah digunakan.'];
        }

        if ($name === '') {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $name = $newUsername;
            }
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }

        if (
            $email !== null &&
            User::query()
                ->where('email', $email)
                ->where('username', '!=', $oldUsername)
                ->exists()
        ) {
            return ['success' => false, 'message' => 'Email sudah digunakan.'];
        }

        if ($this->isDuplicateNoHp($noHp, (int) $user->id)) {
            return ['success' => false, 'message' => 'No HP sudah digunakan.'];
        }

        $previousKelas = $this->normalizeKelasValue($user->kelas);

        $payload = [
            'username' => $newUsername,
            'name' => $name,
            'email' => $email,
            'kelas' => $kelas,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'agama' => $agama,
            'no_hp' => $noHp,
            'alamat' => $alamat,
        ];

        if ($password !== '') {
            $payload['password'] = bcrypt($password);
        }

        DB::transaction(function () use ($user, $payload, $kelas, $previousKelas): void {
            $user->update($payload);
            $this->syncSpatieRoleForUser($user, 'wakel');
            $this->syncGuruClassBinding($user, $kelas, $previousKelas);
        });

        return ['success' => true, 'message' => 'Akun guru berhasil diperbarui.'];
    }


    public function deleteGuru(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $username = ltrim(trim((string) ($args[0] ?? '')), "'");
        if ($username === '') {
            return ['success' => false, 'message' => 'Username wajib diisi.'];
        }

        $user = User::query()->where('username', $username)->first();
        if (!$user) {
            return ['success' => false, 'message' => 'Akun guru tidak ditemukan.'];
        }
        if (!$user->hasRole('wakel')) {
            return ['success' => false, 'message' => 'Akun ini bukan guru/wali kelas yang dapat dihapus dari menu ini.'];
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return ['success' => false, 'message' => 'Akun admin/super-admin tidak dapat dihapus lewat menu ini.'];
        }

        DB::transaction(function () use ($user): void {
            Kelas::query()
                ->where('wali_kelas', (int) $user->id)
                ->update(['wali_kelas' => null]);
            $user->delete();
        });
        return ['success' => true, 'message' => 'Akun guru berhasil dihapus.'];
    }


    public function addPiket(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $username = ltrim(trim((string) ($args[0] ?? '')), "'");
        $password = ltrim(trim((string) ($args[1] ?? '')), "'");
        $kelas = $this->syncKelasValue($args[2] ?? null);
        $name = trim((string) ($args[3] ?? ''));
        $email = $this->normalizeEmailValue($args[4] ?? null);
        $jenisKelamin = $this->normalizeOptionalString($args[5] ?? null);
        $tanggalLahirInput = trim((string) ($args[6] ?? ''));
        $agama = $this->normalizeOptionalString($args[7] ?? null);
        $noHp = $this->normalizeOptionalString($args[8] ?? null);
        $alamat = $this->normalizeOptionalString($args[9] ?? null);

        $tanggalLahir = null;
        if ($tanggalLahirInput !== '') {
            try {
                $tanggalLahir = Carbon::parse($tanggalLahirInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Format tanggal lahir piket tidak valid.'];
            }
        }

        if ($username === '' || $password === '') {
            return ['success' => false, 'message' => 'Username dan password wajib diisi.'];
        }

        if ($name === '') {
            $name = $username;
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }

        if (User::query()->where('username', $username)->exists()) {
            return ['success' => false, 'message' => 'Username sudah digunakan.'];
        }

        if ($email !== null && User::query()->where('email', $email)->exists()) {
            return ['success' => false, 'message' => 'Email sudah digunakan.'];
        }

        if ($this->isDuplicateNoHp($noHp)) {
            return ['success' => false, 'message' => 'No HP sudah digunakan.'];
        }

        $user = User::query()->create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'kelas' => $kelas,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'agama' => $agama,
            'no_hp' => $noHp,
            'alamat' => $alamat,
        ]);
        $this->syncSpatieRoleForUser($user, 'piket');

        return ['success' => true, 'message' => 'Akun piket berhasil ditambahkan.'];
    }


    public function updatePiket(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $oldUsername = ltrim(trim((string) ($args[0] ?? '')), "'");
        $newUsername = ltrim(trim((string) ($args[1] ?? '')), "'");
        $password = ltrim(trim((string) ($args[2] ?? '')), "'");
        $kelas = $this->syncKelasValue($args[3] ?? null);
        $name = trim((string) ($args[4] ?? ''));
        $email = $this->normalizeEmailValue($args[5] ?? null);
        $jenisKelamin = $this->normalizeOptionalString($args[6] ?? null);
        $tanggalLahirInput = trim((string) ($args[7] ?? ''));
        $agama = $this->normalizeOptionalString($args[8] ?? null);
        $noHp = $this->normalizeOptionalString($args[9] ?? null);
        $alamat = $this->normalizeOptionalString($args[10] ?? null);

        $tanggalLahir = null;
        if ($tanggalLahirInput !== '') {
            try {
                $tanggalLahir = Carbon::parse($tanggalLahirInput)->format('Y-m-d');
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Format tanggal lahir piket tidak valid.'];
            }
        }

        if ($oldUsername === '' || $newUsername === '') {
            return ['success' => false, 'message' => 'Username tidak valid.'];
        }

        $user = User::query()->where('username', $oldUsername)->first();
        if (!$user) {
            return ['success' => false, 'message' => 'Akun piket tidak ditemukan.'];
        }
        if (!$user->hasRole('piket')) {
            return ['success' => false, 'message' => 'Akun ini bukan akun piket yang dapat dikelola dari menu ini.'];
        }

        if ($newUsername !== $oldUsername && User::query()->where('username', $newUsername)->exists()) {
            return ['success' => false, 'message' => 'Username baru sudah digunakan.'];
        }

        if ($name === '') {
            $name = trim((string) ($user->name ?? ''));
            if ($name === '') {
                $name = $newUsername;
            }
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }

        if (
            $email !== null &&
            User::query()
                ->where('email', $email)
                ->where('username', '!=', $oldUsername)
                ->exists()
        ) {
            return ['success' => false, 'message' => 'Email sudah digunakan.'];
        }

        if ($this->isDuplicateNoHp($noHp, (int) $user->id)) {
            return ['success' => false, 'message' => 'No HP sudah digunakan.'];
        }

        $payload = [
            'username' => $newUsername,
            'name' => $name,
            'email' => $email,
            'kelas' => $kelas,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'agama' => $agama,
            'no_hp' => $noHp,
            'alamat' => $alamat,
        ];

        if ($password !== '') {
            $payload['password'] = bcrypt($password);
        }

        $user->update($payload);
        $this->syncSpatieRoleForUser($user, 'piket');

        return ['success' => true, 'message' => 'Akun piket berhasil diperbarui.'];
    }


    public function deletePiket(array $args, $auth): array
    {
        if ($denied = $this->requireAdminOrKepsek($auth)) {
            return $denied;
        }

        $args = $this->stripTokenArg($args);
        $username = ltrim(trim((string) ($args[0] ?? '')), "'");
        if ($username === '') {
            return ['success' => false, 'message' => 'Username wajib diisi.'];
        }

        $user = User::query()->where('username', $username)->first();
        if (!$user) {
            return ['success' => false, 'message' => 'Akun piket tidak ditemukan.'];
        }
        if (!$user->hasRole('piket')) {
            return ['success' => false, 'message' => 'Akun ini bukan akun piket yang dapat dihapus dari menu ini.'];
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return ['success' => false, 'message' => 'Akun admin/super-admin tidak dapat dihapus lewat menu ini.'];
        }

        $user->delete();
        return ['success' => true, 'message' => 'Akun piket berhasil dihapus.'];
    }


    public function importGuruBulk(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin', 'kepsek'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $dataArray = $args[0] ?? [];
        $existing = \App\Models\User::query()->pluck('username')->map(fn ($u) => trim((string) $u))->flip();
        $existingEmails = \App\Models\User::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->flip();
        $existingNoHp = User::query()
            ->whereNotNull('no_hp')
            ->pluck('no_hp')
            ->map(fn ($noHp) => $this->normalizeOptionalString($noHp))
            ->filter()
            ->flip();
        $rowsToAdd = [];
        $addedCount = 0;
        $skippedCount = 0;
        $kelasToSync = [];

        foreach ($dataArray as $item) {
            $username = isset($item['username']) ? trim((string) $item['username']) : '';
            $name = trim((string) ($item['name'] ?? $item['nama'] ?? ''));
            $email = $this->normalizeEmailValue($item['email'] ?? null);
            $jenisKelamin = $this->normalizeOptionalString($item['jenisKelamin'] ?? $item['jenis_kelamin'] ?? null);
            $tanggalLahirRaw = $item['tanggalLahir'] ?? $item['tanggal_lahir'] ?? null;
            $agama = $this->normalizeOptionalString($item['agama'] ?? null);
            $noHp = $this->normalizeOptionalString($item['noHp'] ?? $item['no_hp'] ?? null);
            $alamat = $this->normalizeOptionalString($item['alamat'] ?? null);
            if ($username === '' || empty($item['password'])) {
                $skippedCount++;
                continue;
            }

            if ($name === '') {
                $name = $username;
            }

            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }

            if (isset($existing[$username])) {
                $skippedCount++;
                continue;
            }

            if ($email !== null && isset($existingEmails[$email])) {
                $skippedCount++;
                continue;
            }

            if ($noHp !== null && isset($existingNoHp[$noHp])) {
                $skippedCount++;
                continue;
            }

            $tanggalLahir = $this->normalizeDateValue($tanggalLahirRaw);
            if (trim((string) ($tanggalLahirRaw ?? '')) !== '' && $tanggalLahir === null) {
                $skippedCount++;
                continue;
            }

            $kelas = $this->normalizeKelasValue($item['kelas'] ?? null);
            if ($kelas !== null) {
                $kelasToSync[] = $kelas;
            }

            $rowsToAdd[] = [
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password' => bcrypt((string) $item['password']),
                'kelas' => $kelas,
                'jenis_kelamin' => $jenisKelamin,
                'tanggal_lahir' => $tanggalLahir,
                'agama' => $agama,
                'no_hp' => $noHp,
                'alamat' => $alamat,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing[$username] = true;
            if ($email !== null) {
                $existingEmails[$email] = true;
            }
            if ($noHp !== null) {
                $existingNoHp[$noHp] = true;
            }
            $addedCount++;
        }

        if (!empty($rowsToAdd)) {
            $this->syncKelasValues($kelasToSync);
            \App\Models\User::query()->insert($rowsToAdd);
            $insertedUsernames = collect($rowsToAdd)
                ->pluck('username')
                ->filter()
                ->values()
                ->all();
            if (!empty($insertedUsernames)) {
                User::query()
                    ->whereIn('username', $insertedUsernames)
                    ->get()
                    ->each(fn (User $user) => $this->syncSpatieRoleForUser($user, 'wakel'));
            }
        }

        $message = "Import selesai. Berhasil: {$addedCount}, Duplikat/Gagal: {$skippedCount}";
        if ($addedCount === 0 && $skippedCount > 0) {
            $message .= '. Cek data: Username/Password wajib, email valid, No HP unik, dan tanggal lahir gunakan format tanggal valid.';
        }

        return [
            'success' => true,
            'added' => $addedCount,
            'skipped' => $skippedCount,
            'message' => $message,
        ];
    }


    public function importPiketBulk(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin', 'kepsek'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $dataArray = $args[0] ?? [];
        $existing = \App\Models\User::query()->pluck('username')->map(fn ($u) => trim((string) $u))->flip();
        $existingEmails = \App\Models\User::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->flip();
        $existingNoHp = User::query()
            ->whereNotNull('no_hp')
            ->pluck('no_hp')
            ->map(fn ($noHp) => $this->normalizeOptionalString($noHp))
            ->filter()
            ->flip();
        $rowsToAdd = [];
        $addedCount = 0;
        $skippedCount = 0;
        $kelasToSync = [];

        foreach ($dataArray as $item) {
            $username = isset($item['username']) ? trim((string) $item['username']) : '';
            $name = trim((string) ($item['name'] ?? $item['nama'] ?? ''));
            $email = $this->normalizeEmailValue($item['email'] ?? null);
            $jenisKelamin = $this->normalizeOptionalString($item['jenisKelamin'] ?? $item['jenis_kelamin'] ?? null);
            $tanggalLahirRaw = $item['tanggalLahir'] ?? $item['tanggal_lahir'] ?? null;
            $agama = $this->normalizeOptionalString($item['agama'] ?? null);
            $noHp = $this->normalizeOptionalString($item['noHp'] ?? $item['no_hp'] ?? null);
            $alamat = $this->normalizeOptionalString($item['alamat'] ?? null);
            if ($username === '' || empty($item['password'])) {
                $skippedCount++;
                continue;
            }

            if ($name === '') {
                $name = $username;
            }

            if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }

            if (isset($existing[$username])) {
                $skippedCount++;
                continue;
            }

            if ($email !== null && isset($existingEmails[$email])) {
                $skippedCount++;
                continue;
            }

            if ($noHp !== null && isset($existingNoHp[$noHp])) {
                $skippedCount++;
                continue;
            }

            $tanggalLahir = $this->normalizeDateValue($tanggalLahirRaw);
            if (trim((string) ($tanggalLahirRaw ?? '')) !== '' && $tanggalLahir === null) {
                $skippedCount++;
                continue;
            }

            $kelas = $this->normalizeKelasValue($item['kelas'] ?? null);
            if ($kelas !== null) {
                $kelasToSync[] = $kelas;
            }

            $rowsToAdd[] = [
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'password' => bcrypt((string) $item['password']),
                'kelas' => $kelas,
                'jenis_kelamin' => $jenisKelamin,
                'tanggal_lahir' => $tanggalLahir,
                'agama' => $agama,
                'no_hp' => $noHp,
                'alamat' => $alamat,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing[$username] = true;
            if ($email !== null) {
                $existingEmails[$email] = true;
            }
            if ($noHp !== null) {
                $existingNoHp[$noHp] = true;
            }
            $addedCount++;
        }

        if (!empty($rowsToAdd)) {
            $this->syncKelasValues($kelasToSync);
            \App\Models\User::query()->insert($rowsToAdd);
            $insertedUsernames = collect($rowsToAdd)
                ->pluck('username')
                ->filter()
                ->values()
                ->all();
            if (!empty($insertedUsernames)) {
                User::query()
                    ->whereIn('username', $insertedUsernames)
                    ->get()
                    ->each(fn (User $user) => $this->syncSpatieRoleForUser($user, 'piket'));
            }
        }

        $message = "Import selesai. Berhasil: {$addedCount}, Duplikat/Gagal: {$skippedCount}";
        if ($addedCount === 0 && $skippedCount > 0) {
            $message .= '. Cek data: Username/Password wajib, email valid, No HP unik, dan tanggal lahir gunakan format tanggal valid.';
        }

        return [
            'success' => true,
            'added' => $addedCount,
            'skipped' => $skippedCount,
            'message' => $message,
        ];
    }

}