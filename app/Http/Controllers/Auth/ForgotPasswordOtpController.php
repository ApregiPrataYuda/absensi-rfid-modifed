<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Konfigurasi;
use App\Models\PasswordResetOtpCode;
use App\Models\Siswa;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ForgotPasswordOtpController extends Controller
{
    protected const OTP_LENGTH = 6;
    protected const OTP_EXPIRE_MINUTES = 10;
    protected const OTP_MAX_VERIFY_ATTEMPTS = 5;
    protected const OTP_REQUEST_LIMIT = 3;
    protected const OTP_REQUEST_DECAY_SECONDS = 300;

    public function requestOtp(Request $request, WaGatewayService $waGatewayService): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:191'],
        ]);

        $loginIdentifier = trim((string) ($validated['username'] ?? ''));
        $tenantId = $this->currentTenantId();
        $rateKey = $this->requestRateKey($request, $tenantId, $loginIdentifier);

        if (RateLimiter::tooManyAttempts($rateKey, self::OTP_REQUEST_LIMIT)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan OTP. Coba lagi dalam ' . $seconds . ' detik.',
            ], 429);
        }

        $user = $this->resolveUser($loginIdentifier, $tenantId);
        if (!$user) {
            RateLimiter::hit($rateKey, self::OTP_REQUEST_DECAY_SECONDS);
            return response()->json([
                'success' => false,
                'message' => 'Username, email, atau NIK tidak ditemukan.',
            ], 422);
        }

        $noHp = $this->resolvePhoneNumber($user, $tenantId);
        if ($noHp === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp pada akun belum diisi. Hubungi admin untuk pembaruan data.',
            ], 422);
        }

        $otpCode = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_EXPIRE_MINUTES);

        $message = $this->buildOtpMessage($user, $otpCode, self::OTP_EXPIRE_MINUTES);
        $sendResult = $waGatewayService->sendCustomMessage($noHp, $message);
        if (!($sendResult['success'] ?? false)) {
            RateLimiter::hit($rateKey, self::OTP_REQUEST_DECAY_SECONDS);
            return response()->json([
                'success' => false,
                'message' => (string) ($sendResult['message'] ?? 'Gagal mengirim OTP ke WhatsApp.'),
            ], 422);
        }

        PasswordResetOtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        PasswordResetOtpCode::query()->create([
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'user_id' => (int) $user->id,
            'username' => (string) $user->username,
            'no_hp' => $noHp,
            'otp_hash' => Hash::make($otpCode),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ]);

        RateLimiter::hit($rateKey, self::OTP_REQUEST_DECAY_SECONDS);

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil dikirim ke WhatsApp ' . $this->maskPhone($noHp) . '.',
            'data' => [
                'expires_in_minutes' => self::OTP_EXPIRE_MINUTES,
                'masked_phone' => $this->maskPhone($noHp),
            ],
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:191'],
            'otp_code' => ['required', 'digits:' . self::OTP_LENGTH],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $loginIdentifier = trim((string) ($validated['username'] ?? ''));
        $tenantId = $this->currentTenantId();
        $user = $this->resolveUser($loginIdentifier, $tenantId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Username, email, atau NISN tidak ditemukan.',
            ], 422);
        }

        $otp = PasswordResetOtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP tidak valid atau sudah kedaluwarsa.',
            ], 422);
        }

        if ((int) $otp->attempts >= self::OTP_MAX_VERIFY_ATTEMPTS) {
            $otp->used_at = now();
            $otp->save();

            return response()->json([
                'success' => false,
                'message' => 'Kode OTP sudah melebihi batas percobaan. Silakan minta OTP baru.',
            ], 422);
        }

        $otpCode = trim((string) ($validated['otp_code'] ?? ''));
        if (!Hash::check($otpCode, (string) $otp->otp_hash)) {
            $otp->attempts = min(self::OTP_MAX_VERIFY_ATTEMPTS, ((int) $otp->attempts) + 1);
            if ((int) $otp->attempts >= self::OTP_MAX_VERIFY_ATTEMPTS) {
                $otp->used_at = now();
            }
            $otp->save();

            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah.',
            ], 422);
        }

        $user->password = (string) ($validated['password'] ?? '');
        $user->setRememberToken(Str::random(60));
        $user->save();

        PasswordResetOtpCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login menggunakan password baru.',
        ]);
    }

    protected function resolveUser(string $loginIdentifier, int $tenantId): ?User
    {
        $query = User::query()->where(function ($innerQuery) use ($loginIdentifier): void {
            $innerQuery
                ->where('username', $loginIdentifier)
                ->orWhereRaw('LOWER(email) = ?', [Str::lower($loginIdentifier)]);
        });
        if ($tenantId > 0 && $this->usersHasTenantColumn()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }

    protected function requestRateKey(Request $request, int $tenantId, string $username): string
    {
        return 'forgot-password-otp-request|'
            . $tenantId
            . '|'
            . Str::lower(trim($username))
            . '|'
            . $request->ip();
    }

    protected function currentTenantId(): int
    {
        if (class_exists(\App\Support\TenantResolver::class)) {
            try {
                return max(0, (int) \App\Support\TenantResolver::currentId());
            } catch (\Throwable $e) {
                // Fallback below.
            }
        }

        $sessionTenantId = (int) request()->session()->get('tenant_id', 0);
        if ($sessionTenantId > 0) {
            return $sessionTenantId;
        }

        return max(0, (int) config('tenancy.default_tenant_id', 0));
    }

    protected function usersHasTenantColumn(): bool
    {
        static $hasTenantColumn = null;
        if ($hasTenantColumn !== null) {
            return $hasTenantColumn;
        }

        try {
            $hasTenantColumn = Schema::hasColumn('users', 'tenant_id');
        } catch (\Throwable $e) {
            $hasTenantColumn = false;
        }

        return $hasTenantColumn;
    }

    protected function siswaHasTenantColumn(): bool
    {
        static $hasTenantColumn = null;
        if ($hasTenantColumn !== null) {
            return $hasTenantColumn;
        }

        try {
            $hasTenantColumn = Schema::hasColumn('siswa', 'tenant_id');
        } catch (\Throwable $e) {
            $hasTenantColumn = false;
        }

        return $hasTenantColumn;
    }

    protected function resolvePhoneNumber(User $user, int $tenantId): string
    {
        $noHp = trim((string) ($user->no_hp ?? ''));
        if ($noHp !== '') {
            return $noHp;
        }

        $nisn = trim((string) ($user->username ?? ''));
        if ($nisn === '') {
            return '';
        }

        $siswaQuery = Siswa::query()->where('nisn', $nisn);
        if ($tenantId > 0 && $this->siswaHasTenantColumn()) {
            $siswaQuery->where('tenant_id', $tenantId);
        }

        $siswaNoHp = trim((string) ($siswaQuery->value('no_hp') ?? ''));
        if ($siswaNoHp === '') {
            return '';
        }

        try {
            $user->no_hp = $siswaNoHp;
            $user->save();
        } catch (\Throwable $e) {
            // Abaikan jika update profil nomor gagal, OTP tetap memakai nomor siswa.
        }

        return $siswaNoHp;
    }

    protected function generateOtp(): string
    {
        $max = (10 ** self::OTP_LENGTH) - 1;
        return str_pad((string) random_int(0, $max), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    protected function buildOtpMessage(User $user, string $otpCode, int $expireMinutes): string
    {
        $template = trim((string) (Konfigurasi::query()
            ->where('key', 'wa_template_forgot_password_otp')
            ->value('value') ?? ''));

        if ($template === '') {
            $template = "Halo {nama},\n\nKode OTP untuk reset password akun {username} adalah *{otp_code}*.\nKode berlaku {otp_expired_minutes} menit.\nWaktu permintaan: {otp_request_time}.\n\nJangan berikan kode ini kepada siapa pun.";
        }

        $websiteName = trim((string) (Konfigurasi::query()
            ->where('key', 'website_nama')
            ->value('value') ?? config('app.name', 'Absensindo')));
        if ($websiteName === '') {
            $websiteName = 'Absensindo';
        }

        $replace = [
            '{nama}' => (string) ($user->name ?: $user->username),
            '{username}' => (string) $user->username,
            '{otp_code}' => $otpCode,
            '{otp_expired_minutes}' => (string) $expireMinutes,
            '{otp_request_time}' => now()->format('d-m-Y H:i'),
            '{website_name}' => $websiteName,
        ];

        return strtr($template, $replace);
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '-';
        }

        $length = strlen($digits);
        if ($length <= 6) {
            return str_repeat('*', max(0, $length - 2)) . substr($digits, -2);
        }

        return substr($digits, 0, 4) . str_repeat('*', max(0, $length - 7)) . substr($digits, -3);
    }

}
