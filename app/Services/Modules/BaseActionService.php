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

abstract class BaseActionService
{
    protected array $authUserCache = [];

    protected function resolveAuth(?string $token)
    {
        if (!$token) {
            return null;
        }

        $auth = AuthToken::query()
            ->with(['user.roles'])
            ->where('token', $token)
            ->first();
        if (!$auth || Carbon::parse($auth->expires_at)->isPast()) {
            return false;
        }

        return $auth;
    }


    protected function stripTokenArg(array $args): array
    {
        if (count($args) === 0) {
            return $args;
        }

        $first = $args[0] ?? null;
        if ($first === null) {
            array_shift($args);
            return array_values($args);
        }

        if (is_string($first) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $first)) {
            array_shift($args);
            return array_values($args);
        }

        return $args;
    }


    protected function requireAdmin($auth): ?array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        return null;
    }


    protected function requireAdminOrKepsek($auth): ?array
    {
        if (!$this->authHasAnyRole($auth, ['admin', 'kepsek'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        return null;
    }


    protected function getAuthUserFromToken($auth): ?User
    {
        if (!$auth) {
            return null;
        }

        if (method_exists($auth, 'relationLoaded') && $auth->relationLoaded('user') && $auth->user instanceof User) {
            return $auth->user;
        }

        $userId = (int) ($auth->user_id ?? 0);
        if ($userId <= 0) {
            return null;
        }

        if (array_key_exists($userId, $this->authUserCache)) {
            return $this->authUserCache[$userId];
        }

        $this->authUserCache[$userId] = User::query()->find($userId);

        return $this->authUserCache[$userId];
    }


    protected function getPrimaryRoleForUser(?User $user): string
    {
        if (!$user) {
            return '';
        }

        $roleName = $user->getRoleNames()->first();
        if ($roleName !== null) {
            $roleName = strtolower(trim((string) $roleName));
            if ($roleName !== '') {
                return $roleName;
            }
        }

        return '';
    }


    protected function authHasAnyRole($auth, array $roles): bool
    {
        if (!$auth) {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($role) => strtolower(trim((string) $role)),
            $roles
        )));
        if (empty($allowed)) {
            return false;
        }

        $userId = (int) ($auth->user_id ?? 0);
        $user = $this->getAuthUserFromToken($auth);
        if ($userId > 0 && !$user) {
            return false;
        }

        if ($user) {
            if ($user->hasRole('super-admin')) {
                return true;
            }

            if ($user->hasAnyRole($allowed)) {
                return true;
            }

            $userRole = $this->getPrimaryRoleForUser($user);

            return $userRole !== '' && in_array($userRole, $allowed, true);
        }

        $tokenRole = strtolower(trim((string) ($auth->role ?? '')));
        if ($tokenRole === 'super-admin') {
            return true;
        }

        return $tokenRole !== '' && in_array($tokenRole, $allowed, true);
    }


    protected function getRoleFromAuth($auth): string
    {
        if (!$auth) {
            return '';
        }

        $siswaId = (int) ($auth->siswa_id ?? 0);
        $userId = (int) ($auth->user_id ?? 0);
        if ($siswaId > 0 && $userId <= 0) {
            return 'siswa';
        }

        if ($userId > 0) {
            $userRole = $this->getPrimaryRoleForUser($this->getAuthUserFromToken($auth));
            if ($userRole !== '') {
                if ($userRole === 'super-admin') {
                    return 'admin';
                }

                return $userRole;
            }

            return '';
        }

        $tokenRole = strtolower(trim((string) ($auth->role ?? '')));
        if ($tokenRole === 'super-admin') {
            return 'admin';
        }

        return $tokenRole;
    }


    protected function syncSpatieRoleForUser(User $user, ?string $role = null): void
    {
        $roleName = strtolower(trim((string) ($role ?? $this->getPrimaryRoleForUser($user))));
        if ($roleName === '') {
            return;
        }

        try {
            $guard = config('auth.defaults.guard', 'web');
            Role::findOrCreate($roleName, $guard);
            $user->syncRoles([$roleName]);
        } catch (\Throwable $e) {
            // Ignore sync errors while permission tables are not ready.
        }
    }


    protected function getWakelKelasFromAuth($auth): ?string
    {
        if ($this->getRoleFromAuth($auth) !== 'wakel') {
            return null;
        }

        $user = $this->getAuthUserFromToken($auth);
        if (!$user) {
            return null;
        }

        return $this->normalizeKelasValue($user->kelas);
    }


    protected function getPiketKelasFromAuth($auth): ?string
    {
        if ($this->getRoleFromAuth($auth) !== 'piket') {
            return null;
        }

        $user = $this->getAuthUserFromToken($auth);
        if (!$user) {
            return null;
        }

        return $this->normalizeKelasValue($user->kelas);
    }


    protected function getPelajaranPiketKelasIds($auth): ?array
    {
        if ($this->getRoleFromAuth($auth) !== 'piket') {
            return null;
        }

        $piketKelas = $this->getPiketKelasFromAuth($auth);
        if ($piketKelas === null) {
            return null;
        }

        return Kelas::query()
            ->get(['id', 'nama'])
            ->filter(fn (Kelas $kelas): bool => $this->normalizeKelasValue($kelas->nama) === $piketKelas)
            ->pluck('id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();
    }


    protected function ensurePelajaranPiketConfigured($auth): ?array
    {
        if ($this->getRoleFromAuth($auth) !== 'piket') {
            return null;
        }

        if ($this->getPiketKelasFromAuth($auth) !== null) {
            return null;
        }

        return [
            'success' => false,
            'message' => 'Akun piket belum ditautkan ke gedung.',
        ];
    }


    protected function ensurePelajaranPiketKelasAccess($auth, ?string $kelasNama): ?array
    {
        if ($this->getRoleFromAuth($auth) !== 'piket') {
            return null;
        }

        $piketKelas = $this->getPiketKelasFromAuth($auth);
        if ($piketKelas === null) {
            return [
                'success' => false,
                'message' => 'Akun piket belum ditautkan ke gedung.',
            ];
        }

        if ($this->normalizeKelasValue($kelasNama) === $piketKelas) {
            return null;
        }

        return [
            'success' => false,
            'message' => 'Akun piket ini hanya bisa mengakses gedung ' . $piketKelas . '.',
        ];
    }


    protected function applyPelajaranReportRoleScope($query, $auth, string $role): ?array
    {
        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $userId = (int) $user->id;
            $query->where(function ($builder) use ($userId): void {
                $builder
                    ->where('guru_id', $userId)
                    ->orWhereHas('jadwalPelajaran', fn ($jadwalQuery) => $jadwalQuery->where('guru_id', $userId));
            });

            return null;
        }

        if ($role === 'piket') {
            $kelasIds = $this->getPelajaranPiketKelasIds($auth);
            if ($kelasIds === null) {
                return ['success' => false, 'message' => 'Akun piket belum ditautkan ke gedung.'];
            }

            if (count($kelasIds) === 0) {
                $query->whereRaw('1 = 0');
                return null;
            }

            $query->whereIn('kelas_id', $kelasIds);
        }

        return null;
    }


    protected function ensurePelajaranReportSessionAccess(SesiPelajaran $sesi, $auth, string $role): ?array
    {
        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'gedung sesi pekerjaan tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        if ($role !== 'wakel') {
            return null;
        }

        $user = $this->getAuthUserFromToken($auth);
        if (!$user) {
            return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
        }

        $jadwalGuruId = (int) ($sesi->jadwalPelajaran?->guru_id ?? $sesi->guru_id ?? 0);
        if ($jadwalGuruId <= 0) {
            return ['success' => false, 'message' => 'Jadwal pekerjaan belum ditautkan ke mandor.'];
        }

        if ($jadwalGuruId !== (int) $user->id) {
            return ['success' => false, 'message' => 'Sesi ini bukan jadwal pekerjaan Anda.'];
        }

        return null;
    }


    protected function findSiswaUserByUsername(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $user = User::query()->where('username', $username)->first();
        if (!$user || !$user->hasRole('siswa')) {
            return null;
        }

        return $user;
    }


    protected function syncSiswaUserFromSiswa(string $oldNisn, Siswa $siswa): void
    {
        $oldNisn = trim($oldNisn);
        $newNisn = trim((string) $siswa->nisn);
        if ($newNisn === '') {
            return;
        }

        $user = $this->findSiswaUserByUsername($oldNisn);
        if (!$user && $oldNisn !== $newNisn) {
            $user = $this->findSiswaUserByUsername($newNisn);
        }
        if (!$user) {
            return;
        }

        $payload = [
            'name' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'tanggal_lahir' => $siswa->tanggal_lahir,
            'agama' => $siswa->agama,
            'no_hp' => $siswa->no_hp,
            'alamat' => $siswa->alamat,
        ];

        if ($user->username !== $newNisn) {
            $usernameTaken = User::query()
                ->where('username', $newNisn)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($usernameTaken) {
                return;
            }

            $payload['username'] = $newNisn;
        }

        $user->update($payload);
    }


    protected function deleteSiswaUserByNisn(string $nisn): void
    {
        $user = $this->findSiswaUserByUsername($nisn);
        if ($user) {
            $user->delete();
        }
    }


    protected function getSiswaFromAuth($auth): ?Siswa
    {
        if (!$auth) {
            return null;
        }

        $siswaId = (int) ($auth->siswa_id ?? 0);
        if ($siswaId > 0) {
            $siswa = Siswa::query()->find($siswaId);
            if ($siswa) {
                return $siswa;
            }
        }

        $user = $this->getAuthUserFromToken($auth);
        if (!$user) {
            return null;
        }

        $nisn = trim((string) ($user->username ?? ''));
        if ($nisn === '') {
            return null;
        }

        return Siswa::query()->where('nisn', $nisn)->first();
    }


    protected function getSiswaKelasFromAuth($auth): ?string
    {
        if ($this->getRoleFromAuth($auth) !== 'siswa') {
            return null;
        }

        $siswa = $this->getSiswaFromAuth($auth);
        if ($siswa) {
            return $this->normalizeKelasValue($siswa->kelas);
        }

        $user = $this->getAuthUserFromToken($auth);
        if (!$user) {
            return null;
        }

        return $this->normalizeKelasValue($user->kelas);
    }


    protected function normalizeDateValue($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            try {
                return Carbon::instance($value)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        // Excel serial date (contoh: 40179) -> tanggal valid.
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $text) === 1) {
            $serial = (float) $text;
            if ($serial > 0 && $serial < 600000) {
                try {
                    $days = (int) floor($serial);
                    return Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC')
                        ->addDays($days)
                        ->toDateString();
                } catch (\Throwable $e) {
                    // Lanjutkan ke parser format lain.
                }
            }
        }

        $knownFormats = [
            'Y-m-d',
            'Y/m/d',
            'd-m-Y',
            'd/m/Y',
            'd.m.Y',
            'm/d/Y',
        ];
        foreach ($knownFormats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $text);
                if ($parsed !== false) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable $e) {
                // Coba format berikutnya.
            }
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }


    protected function getHolidayRanges(string $startDate, string $endDate): array
    {
        $rows = $this->buildHariLiburOverlapQuery($startDate, $endDate)
            ->select([
                'id',
                'tanggal',
                'tanggal_mulai',
                'tanggal_selesai',
                'kelas',
                'keterangan',
            ])
            ->orderBy('tanggal_mulai')
            ->orderBy('tanggal')
            ->get();

        return $rows
            ->map(function (HariLibur $row) {
                $mulai = $this->normalizeDateValue($row->tanggal_mulai) ?? $this->normalizeDateValue($row->tanggal);
                $selesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $mulai;
                $kelas = $this->normalizeKelasValue($row->kelas);
                $keterangan = trim((string) ($row->keterangan ?? 'Libur'));
                if ($keterangan === '') {
                    $keterangan = 'Libur';
                }

                if ($mulai === null) {
                    return null;
                }

                if ($selesai === null || $selesai < $mulai) {
                    $selesai = $mulai;
                }

                return [
                    'id' => (int) $row->id,
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                    'kelas' => $kelas,
                    'keterangan' => $keterangan,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }


    protected function buildHariLiburOverlapQuery(string $startDate, string $endDate)
    {
        $start = $this->normalizeDateValue($startDate) ?? $startDate;
        $end = $this->normalizeDateValue($endDate) ?? $endDate;

        return HariLibur::query()
            ->whereRaw('DATE(COALESCE(tanggal_mulai, tanggal)) <= ?', [$end])
            ->whereRaw('DATE(COALESCE(tanggal_selesai, tanggal_mulai, tanggal)) >= ?', [$start]);
    }


    protected function resolveHolidayNameForDate(string $date, ?string $kelas, array $holidayRanges): ?string
    {
        $kelasTarget = $this->normalizeKelasValue($kelas);
        $globalMatch = null;

        foreach ($holidayRanges as $range) {
            $mulai = $range['tanggal_mulai'] ?? null;
            $selesai = $range['tanggal_selesai'] ?? null;
            if ($mulai === null || $selesai === null) {
                continue;
            }

            if ($date < $mulai || $date > $selesai) {
                continue;
            }

            $rangeKelas = $range['kelas'] ?? null;
            if ($rangeKelas === null) {
                $globalMatch = $range['keterangan'] ?? 'Libur';
                continue;
            }

            if ($kelasTarget !== null && $kelasTarget === $rangeKelas) {
                return $range['keterangan'] ?? 'Libur';
            }
        }

        return $globalMatch;
    }


    protected function getJadwalLiburMapByKelas(array $kelasValues): array
    {
        $kelasNames = collect($kelasValues)
            ->map(fn ($value) => $this->normalizeKelasValue($value))
            ->filter()
            ->unique()
            ->values();

        if ($kelasNames->isEmpty()) {
            return [];
        }

        $kelasRows = Kelas::query()
            ->whereIn('nama', $kelasNames->all())
            ->get(['id', 'nama']);

        if ($kelasRows->isEmpty()) {
            return [];
        }

        $idToNama = $kelasRows
            ->mapWithKeys(function (Kelas $kelas) {
                $nama = $this->normalizeKelasValue($kelas->nama);
                if ($nama === null) {
                    return [];
                }

                return [(int) $kelas->id => $nama];
            })
            ->all();

        if (count($idToNama) === 0) {
            return [];
        }

        $rows = JadwalHarian::query()
            ->whereIn('kelas_id', array_keys($idToNama))
            ->where('is_libur', true)
            ->get(['kelas_id', 'hari', 'keterangan']);

        $result = [];
        foreach ($rows as $row) {
            $kelasNama = $idToNama[(int) $row->kelas_id] ?? null;
            if ($kelasNama === null) {
                continue;
            }

            $hari = (int) $row->hari;
            if ($hari < 1 || $hari > 7) {
                continue;
            }

            $keterangan = trim((string) ($row->keterangan ?? ''));
            if ($keterangan === '') {
                $keterangan = 'Libur';
            }

            $result[$kelasNama][$hari] = $keterangan;
        }

        return $result;
    }


    protected function resolveJadwalLiburNameForDate(string $date, ?string $kelas, array $jadwalLiburMap): ?string
    {
        $kelasNama = $this->normalizeKelasValue($kelas);
        if ($kelasNama === null) {
            return null;
        }

        try {
            $hari = (int) Carbon::parse($date)->dayOfWeekIso;
        } catch (\Throwable $e) {
            return null;
        }

        if ($hari < 1 || $hari > 7) {
            return null;
        }

        return $jadwalLiburMap[$kelasNama][$hari] ?? null;
    }


    protected function normalizeKelasValue($kelas): ?string
    {
        $value = trim((string) ($kelas ?? ''));
        return $value === '' ? null : $value;
    }


    protected function normalizeScannedNisn($rawCode): string
    {
        $value = trim((string) ($rawCode ?? ''));
        if ($value === '') {
            return '';
        }

        if (!str_starts_with(strtoupper($value), 'B64:')) {
            return $value;
        }

        $encoded = trim(substr($value, 4));
        if ($encoded === '') {
            return '';
        }

        $base64 = strtr($encoded, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return '';
        }

        return trim($decoded);
    }


    protected function normalizeOptionalString($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }


    protected function payloadOptionalStringOrCurrent(array $payload, string $key, $currentValue = null): ?string
    {
        if (! array_key_exists($key, $payload)) {
            return $this->normalizeOptionalString($currentValue);
        }

        return $this->normalizeOptionalString($payload[$key] ?? null);
    }


    protected function isDuplicateNoHp(?string $noHp, ?int $exceptUserId = null): bool
    {
        if ($noHp === null) {
            return false;
        }

        $query = User::query()->where('no_hp', $noHp);
        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }


    protected function normalizeEmailValue($email): ?string
    {
        $value = trim((string) ($email ?? ''));
        if ($value === '') {
            return null;
        }

        return strtolower($value);
    }


    protected function normalizeJamValue($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $text)) {
            return $text;
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $text)) {
            return substr($text, 0, 5);
        }

        return null;
    }


    protected function syncKelasValue($kelas): ?string
    {
        $value = $this->normalizeKelasValue($kelas);
        if ($value === null) {
            return null;
        }

        Kelas::query()->firstOrCreate(['nama' => $value]);
        return $value;
    }


    protected function syncGuruClassBinding(User $user, ?string $targetKelas, ?string $previousKelas = null): void
    {
        $userId = (int) $user->id;
        if ($userId <= 0) {
            return;
        }

        $targetKelas = $this->normalizeKelasValue($targetKelas);
        $previousKelas = $this->normalizeKelasValue($previousKelas);

        $detachQuery = Kelas::query()->where('wali_kelas', $userId);
        if ($targetKelas !== null) {
            $detachQuery->where('nama', '!=', $targetKelas);
        }
        $detachQuery->update(['wali_kelas' => null]);

        if ($targetKelas === null) {
            if ($previousKelas !== null) {
                Kelas::query()
                    ->where('nama', $previousKelas)
                    ->where('wali_kelas', $userId)
                    ->update(['wali_kelas' => null]);
            }

            if ($this->normalizeKelasValue($user->kelas) !== null) {
                $user->kelas = null;
                $user->save();
            }

            return;
        }

        $targetKelas = $this->syncKelasValue($targetKelas);
        if ($targetKelas === null) {
            return;
        }

        $kelasRow = Kelas::query()->where('nama', $targetKelas)->first();
        if (!$kelasRow) {
            return;
        }

        $existingWaliId = (int) ($kelasRow->wali_kelas ?? 0);
        if ($existingWaliId > 0 && $existingWaliId !== $userId) {
            User::query()
                ->whereKey($existingWaliId)
                ->where('kelas', $targetKelas)
                ->update(['kelas' => null]);
        }

        if ($existingWaliId !== $userId) {
            $kelasRow->wali_kelas = $userId;
            $kelasRow->save();
        }

        if ($this->normalizeKelasValue($user->kelas) !== $targetKelas) {
            $user->kelas = $targetKelas;
            $user->save();
        }
    }


    protected function syncKelasValues(array $kelasValues): void
    {
        $normalized = collect($kelasValues)
            ->map(fn ($value) => $this->normalizeKelasValue($value))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $normalized
            ->map(fn (string $nama) => [
                'nama' => $nama,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        Kelas::query()->upsert($rows, ['nama'], ['updated_at']);
    }


    protected function getDefaultJamConfig(): array
    {
        return [
            'jam_masuk_mulai' => '06:00',
            'jam_masuk_akhir' => '07:15',
            'jam_masuk_telat' => '07:15',
            'jam_pulang_mulai' => '15:00',
            'jam_pulang_akhir' => '17:00',
        ];
    }


    protected function getGlobalJamConfig(): array
    {
        $config = $this->getDefaultJamConfig();

        $rows = Konfigurasi::query()
            ->whereIn('key', array_keys($config))
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->key;
            $value = $this->normalizeJamValue($row->value);
            if (array_key_exists($key, $config) && $value !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
    }


    protected function getJamConfigForKelas(?string $kelas): array
    {
        $config = $this->getGlobalJamConfig();
        $kelasNama = $this->normalizeKelasValue($kelas);
        if ($kelasNama === null) {
            return $config;
        }

        $kelasRow = Kelas::query()
            ->select(['id'])
            ->where('nama', $kelasNama)
            ->first();

        if (!$kelasRow) {
            return $config;
        }

        $hariSekarang = (int) Carbon::now()->dayOfWeekIso;
        $jadwalHarian = JadwalHarian::query()
            ->select([
                'is_libur',
                'jam_masuk_mulai',
                'jam_masuk_akhir',
                'jam_masuk_telat',
                'jam_pulang_mulai',
                'jam_pulang_akhir',
            ])
            ->where('kelas_id', (int) $kelasRow->id)
            ->where('hari', $hariSekarang)
            ->first();

        if (!$jadwalHarian) {
            return $config;
        }

        foreach (array_keys($config) as $key) {
            $value = $this->normalizeJamValue($jadwalHarian->{$key} ?? null);
            if ($value !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

}