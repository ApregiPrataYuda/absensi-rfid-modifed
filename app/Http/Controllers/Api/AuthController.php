<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'nisn' => 'nullable|string',
        ]);

        if ($request->filled('nisn')) {
            $siswa = Siswa::where('nisn', $request->nisn)->first();
            if (!$siswa) {
                return response()->json(['success' => false, 'message' => 'NISN tidak ditemukan'], 404);
            }

            $token = Str::uuid()->toString();
            $expiry = Carbon::now()->addDay();

            AuthToken::create([
                'token' => $token,
                'siswa_id' => $siswa->id,
                'role' => 'siswa',
                'expires_at' => $expiry,
                'created_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'role' => 'siswa',
                'username' => $siswa->nisn,
                'nama' => $siswa->nama,
                'kelas' => $siswa->kelas,
                'nisn' => $siswa->nisn,
            ]);
        }

        $user = User::where('username', $request->username)->first();
        if (!$user || !password_verify((string) $request->password, (string) $user->password)) {
            return response()->json(['success' => false, 'message' => 'Username atau password salah'], 401);
        }
        $roleName = $this->toEffectiveRole($this->resolveRoleForUser($user));
        if ($roleName === '') {
            return response()->json(['success' => false, 'message' => 'Role akun belum dikonfigurasi.'], 403);
        }

        $token = Str::uuid()->toString();
        $expiry = Carbon::now()->addDay();

        AuthToken::create([
            'token' => $token,
            'user_id' => $user->id,
            'role' => $roleName,
            'expires_at' => $expiry,
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'role' => $roleName,
            'username' => $user->username,
            'nama' => $user->name ?? $user->username,
            'kelas' => $user->kelas,
            'nisn' => null,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'required_role' => 'nullable|string',
        ]);

        $auth = AuthToken::query()
            ->with(['user.roles'])
            ->where('token', $request->token)
            ->first();
        if (!$auth) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid atau tidak ditemukan.'], 401);
        }

        if (Carbon::parse($auth->expires_at)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'], 401);
        }

        $roleName = $this->resolveRoleForToken($auth);
        $requiredRole = strtolower(trim((string) $request->input('required_role', '')));
        if ($requiredRole !== '' && $roleName !== $requiredRole && $roleName !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'], 403);
        }

        return response()->json(['success' => true]);
    }

    protected function resolveRoleForToken(AuthToken $auth): string
    {
        $siswaId = (int) ($auth->siswa_id ?? 0);
        $userId = (int) ($auth->user_id ?? 0);
        if ($siswaId > 0 && $userId <= 0) {
            return 'siswa';
        }

        if ($auth->relationLoaded('user') && $auth->user) {
            $roleName = $this->toEffectiveRole($this->resolveRoleForUser($auth->user));
            if ($roleName !== '') {
                return $roleName;
            }
        }

        if ($userId > 0) {
            $user = User::query()->find($userId);
            if ($user) {
                $roleName = $this->toEffectiveRole($this->resolveRoleForUser($user));
                if ($roleName !== '') {
                    return $roleName;
                }
            }

            return '';
        }

        return $this->toEffectiveRole((string) ($auth->role ?? ''));
    }

    protected function resolveRoleForUser(User $user): string
    {
        $roleName = $user->getRoleNames()->first();
        if ($roleName !== null) {
            $roleName = strtolower(trim((string) $roleName));
            if ($roleName !== '') {
                return $roleName;
            }
        }

        return '';
    }

    protected function toEffectiveRole(string $roleName): string
    {
        $roleName = strtolower(trim($roleName));

        return $roleName === 'super-admin' ? 'admin' : $roleName;
    }
}
