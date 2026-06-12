<?php

namespace App\Http\Requests\Auth;

use App\Models\Siswa;
use App\Models\User;
use App\Support\StudentPhoneValue;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->input('login_type') === 'siswa') {
            return [
                'login_type' => ['required', 'in:siswa,admin'],
                'nisn' => ['required', 'string'],
                'password' => ['nullable', 'string'],
                'new_password' => ['nullable', 'string'],
                'new_password_confirmation' => ['nullable', 'string'],
            ];
        }

        return [
            'login_type' => ['required', 'in:siswa,admin'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if ($this->input('login_type') === 'siswa') {
            $nisn = $this->string('nisn')->toString();

            $siswa = Siswa::query()->where('nisn', $nisn)->first();
            if (! $siswa) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'nisn' => 'NISN tidak ditemukan.',
                ]);
            }

            $user = User::query()->where('username', $nisn)->first();
            if ($user) {
                $password = $this->string('password')->toString();
                if ($password === '') {
                    RateLimiter::hit($this->throttleKey());

                    throw ValidationException::withMessages([
                        'password' => 'Password wajib diisi.',
                    ]);
                }

                if (! Hash::check($password, (string) $user->password)) {
                    RateLimiter::hit($this->throttleKey());

                    throw ValidationException::withMessages([
                        'password' => trans('auth.failed'),
                    ]);
                }

                $user->update([
                    'name' => $siswa->nama ?? $user->name,
                    'kelas' => $siswa->kelas ?? $user->kelas,
                    'jenis_kelamin' => $siswa->jenis_kelamin ?? $user->jenis_kelamin,
                    'tanggal_lahir' => $siswa->tanggal_lahir ?? $user->tanggal_lahir,
                    'agama' => $siswa->agama ?? $user->agama,
                    'no_hp' => StudentPhoneValue::resolveForUserSync($siswa->no_hp ?? null, $user->no_hp ?? null),
                    'alamat' => $siswa->alamat ?? $user->alamat,
                ]);
            } else {
                $newPassword = $this->string('new_password')->toString();
                $newPasswordConfirmation = $this->string('new_password_confirmation')->toString();

                if ($newPassword === '') {
                    RateLimiter::hit($this->throttleKey());

                    throw ValidationException::withMessages([
                        'new_password' => 'Akun belum pernah login. Buat password baru terlebih dahulu.',
                    ]);
                }

                if (Str::length($newPassword) < 8) {
                    RateLimiter::hit($this->throttleKey());

                    throw ValidationException::withMessages([
                        'new_password' => 'Password baru minimal 8 karakter.',
                    ]);
                }

                if ($newPassword !== $newPasswordConfirmation) {
                    RateLimiter::hit($this->throttleKey());

                    throw ValidationException::withMessages([
                        'new_password_confirmation' => 'Konfirmasi password baru tidak sama.',
                    ]);
                }

                $user = User::query()->create([
                    'username' => $nisn,
                    'name' => $siswa->nama ?? 'Siswa',
                    'email' => null,
                    'kelas' => $siswa->kelas ?? null,
                    'jenis_kelamin' => $siswa->jenis_kelamin ?? null,
                    'tanggal_lahir' => $siswa->tanggal_lahir ?? null,
                    'agama' => $siswa->agama ?? null,
                    'no_hp' => StudentPhoneValue::normalize($siswa->no_hp ?? null),
                    'alamat' => $siswa->alamat ?? null,
                    'password' => Hash::make($newPassword),
                ]);
            }
            $this->syncSpatieRole($user, 'siswa');

            Auth::login($user, $this->boolean('remember'));
            RateLimiter::clear($this->throttleKey());
            return;
        }

        if (! Auth::attempt($this->only('username', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        if ($user instanceof User && $this->isBlockedOnAdminLogin($user)) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Akun siswa hanya bisa login lewat tab Siswa.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function isBlockedOnAdminLogin(User $user): bool
    {
        $isSiswaByRole = false;
        try {
            if (method_exists($user, 'hasRole')) {
                $isSiswaByRole = (bool) $user->hasRole('siswa');
            }
        } catch (\Throwable $e) {
            $isSiswaByRole = false;
        }

        if ($isSiswaByRole) {
            return true;
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username === '') {
            return false;
        }

        return Siswa::query()->where('nisn', $username)->exists();
    }

    protected function syncSpatieRole(User $user, ?string $roleName = null): void
    {
        $roleName = strtolower(trim((string) ($roleName ?? '')));
        if ($roleName === '') {
            return;
        }

        try {
            $guard = config('auth.defaults.guard', 'web');
            Role::findOrCreate($roleName, $guard);
            $user->syncRoles([$roleName]);
        } catch (\Throwable $e) {
            // Ignore permission sync failures during bootstrap/migration phase.
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $identifier = $this->input('login_type') === 'siswa'
            ? $this->string('nisn')
            : $this->string('username');

        return Str::transliterate(Str::lower($identifier).'|'.$this->ip());
    }
}
