<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileSettingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSiswa = $user->hasRole('siswa');

        if ($isSiswa) {
            $this->applySiswaDataToUser($user);
        }

        return view('pages.settings-profile', [
            'user' => $user,
            'avatarUrl' => $this->resolveAvatarUrl((string) ($user->avatar_path ?? '')),
            'roleLabel' => $this->formatRoleLabel((string) ($user->getRoleNames()->first() ?? '')),
            'joinedAtLabel' => $user->created_at ? $user->created_at->translatedFormat('d F Y') : '-',
            'isSiswa' => $isSiswa,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $isSiswa = $user->hasRole('siswa');

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'nullable',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'no_hp' => ['nullable', 'string', 'max:25'],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ];

        if (! $isSiswa) {
            $rules['username'] = [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ];
        }

        $validated = $request->validate($rules);

        $newPassword = (string) ($validated['new_password'] ?? '');
        if ($newPassword !== '') {
            $currentPassword = (string) ($validated['current_password'] ?? '');
            if ($currentPassword === '' || !Hash::check($currentPassword, (string) $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Password saat ini tidak sesuai.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $user, $validated, $newPassword, $isSiswa): void {
            $user->name = trim((string) ($validated['name'] ?? ''));
            if (! $isSiswa) {
                $user->username = trim((string) ($validated['username'] ?? ''));
            }
            $user->email = $this->nullableTrim($validated['email'] ?? null);
            $user->no_hp = $this->nullableTrim($validated['no_hp'] ?? null);
            $user->jenis_kelamin = $this->nullableTrim($validated['jenis_kelamin'] ?? null);
            $user->tanggal_lahir = $validated['tanggal_lahir'] ?? null;
            $user->agama = $this->nullableTrim($validated['agama'] ?? null);
            $user->alamat = $this->nullableTrim($validated['alamat'] ?? null);

            if ($request->hasFile('avatar')) {
                $this->deletePublicAvatar((string) ($user->avatar_path ?? ''));
                $user->avatar_path = $request->file('avatar')->store('profile-avatars', 'public');
            }

            if ($newPassword !== '') {
                $user->password = $newPassword;
            }

            $user->save();

            if ($isSiswa) {
                $this->syncSiswaProfileData($user);
            }
        });

        $user->refresh();

        $responseData = $this->profileResponseData($user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui.',
                'data' => $responseData,
            ]);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    protected function applySiswaDataToUser($user): void
    {
        $siswa = $this->findSiswaForUser($user);
        if (! $siswa) {
            return;
        }

        $user->name = $siswa->nama ?? $user->name;
        $user->kelas = $siswa->kelas ?? $user->kelas;
        $user->jenis_kelamin = $siswa->jenis_kelamin ?? $user->jenis_kelamin;
        $user->tanggal_lahir = $siswa->tanggal_lahir ?? $user->tanggal_lahir;
        $user->agama = $siswa->agama ?? $user->agama;
        $user->no_hp = $siswa->no_hp ?? $user->no_hp;
        $user->alamat = $siswa->alamat ?? $user->alamat;
    }

    protected function syncSiswaProfileData($user): void
    {
        $siswa = $this->findSiswaForUser($user);
        if (! $siswa) {
            return;
        }

        $siswa->nama = (string) ($user->name ?? '');
        $siswa->jenis_kelamin = $this->nullableTrim($user->jenis_kelamin ?? null);
        $siswa->tanggal_lahir = $user->tanggal_lahir;
        $siswa->agama = $this->nullableTrim($user->agama ?? null);
        $siswa->no_hp = $this->nullableTrim($user->no_hp ?? null);
        $siswa->alamat = $this->nullableTrim($user->alamat ?? null);
        $siswa->save();
    }

    protected function findSiswaForUser($user): ?Siswa
    {
        $nisn = trim((string) ($user->username ?? ''));
        if ($nisn === '') {
            return null;
        }

        return Siswa::query()->where('nisn', $nisn)->first();
    }

    protected function profileResponseData($user): array
    {
        return [
            'id' => $user->id,
            'name' => (string) $user->name,
            'username' => (string) $user->username,
            'email' => (string) ($user->email ?? ''),
            'no_hp' => (string) ($user->no_hp ?? ''),
            'jenis_kelamin' => (string) ($user->jenis_kelamin ?? ''),
            'tanggal_lahir' => $user->tanggal_lahir ? $user->tanggal_lahir->format('Y-m-d') : '',
            'agama' => (string) ($user->agama ?? ''),
            'alamat' => (string) ($user->alamat ?? ''),
            'avatar_url' => $this->resolveAvatarUrl((string) ($user->avatar_path ?? '')),
        ];
    }

    protected function nullableTrim($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    protected function resolveAvatarUrl(string $path): ?string
    {
        $cleanPath = trim($path);
        if ($cleanPath === '') {
            return null;
        }

        return Storage::disk('public')->url($cleanPath);
    }

    protected function deletePublicAvatar(string $path): void
    {
        $cleanPath = trim($path);
        if ($cleanPath === '') {
            return;
        }

        if (!str_starts_with($cleanPath, 'profile-avatars/')) {
            return;
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            Storage::disk('public')->delete($cleanPath);
        }
    }

    protected function formatRoleLabel(string $role): string
    {
        $normalized = strtolower(trim($role));
        if ($normalized === 'wakel') {
            return 'Wali Kelas';
        }
        if ($normalized === 'wakasek') {
            return 'Wakil Kepala Sekolah';
        }
        if ($normalized === 'kepsek') {
            return 'Kepala Sekolah';
        }
        if ($normalized === 'super-admin') {
            return 'Super Admin';
        }
        if ($normalized === '') {
            return 'User';
        }

        return ucwords(str_replace('-', ' ', $normalized));
    }
}
