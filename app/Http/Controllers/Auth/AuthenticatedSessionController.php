<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    public function lookupSiswa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nisn' => ['required', 'string'],
        ]);

        $nisn = trim((string) ($validated['nisn'] ?? ''));

        $siswa = Siswa::query()->where('nisn', $nisn)->first();
        if (! $siswa) {
            return response()->json([
                'message' => 'NISN tidak ditemukan.',
                'errors' => [
                    'nisn' => ['NISN tidak ditemukan.'],
                ],
            ], 422);
        }

        $userExists = User::query()
            ->where('username', $nisn)
            ->exists();

        return response()->json([
            'mode' => $userExists ? 'existing' : 'setup',
            'message' => $userExists
                ? ''
                : 'Password belum dibuat, Silahkan buat baru.',
            'siswa' => [
                'nama' => (string) ($siswa->nama ?? ''),
                'kelas' => (string) ($siswa->kelas ?? ''),
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $response = redirect()->intended(route('dashboard', absolute: false));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Login berhasil.',
                'redirect' => $response->getTargetUrl(),
            ]);
        }

        return $response;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

