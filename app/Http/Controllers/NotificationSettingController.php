<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Konfigurasi;
use App\Models\Siswa;
use App\Models\TelegramChatLink;
use App\Services\TelegramBotService;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class NotificationSettingController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $settings = $this->getSettings();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengaturan notifikasi berhasil dimuat.',
                'data' => $settings,
            ]);
        }

        return view('pages.settings-notifications', [
            'settings' => $settings,
        ]);
    }

    public function sendPage(): View
    {
        $pauseMinMs = (int) config('services.wa_gateway.broadcast_interval_min_ms', 5000);
        $pauseMaxMs = (int) config('services.wa_gateway.broadcast_interval_max_ms', 10000);
        $pauseMinMs = max(0, min($pauseMinMs, 60000));
        $pauseMaxMs = max($pauseMinMs, min($pauseMaxMs, 60000));

        return view('pages.notifications-send', [
            'recipientOptions' => $this->getNotificationRecipientOptions(),
            'pauseMinSec' => intdiv($pauseMinMs, 1000),
            'pauseMaxSec' => intdiv($pauseMaxMs, 1000),
        ]);
    }

    public function update(Request $request, TelegramBotService $telegramBotService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'attendance_notif_enabled' => ['nullable', 'boolean'],
            'attendance_notif_channel' => ['required', 'in:whatsapp,telegram,both'],
            'izin_sakit_notif_enabled' => ['nullable', 'boolean'],
            'izin_sakit_notif_channel' => ['required', 'in:whatsapp,telegram,both'],
            'wa_notif_izin_sakit_reviewer_enabled' => ['nullable', 'boolean'],
            'wa_notif_target' => ['required', 'in:siswa'],
            'wa_gateway_provider' => ['nullable', 'string', 'in:CUSTOM,SENDERBLAST,STANDARD,FONNTE'],
            'wa_gateway_base_url' => ['nullable', 'url', 'max:255'],
            'wa_gateway_authorization' => ['nullable', 'string', 'max:500'],
            'wa_gateway_header_key' => ['nullable', 'string', 'max:80'],
            'wa_gateway_header_value' => ['nullable', 'string', 'max:500'],
            'wa_gateway_body_type' => ['nullable', 'string', 'in:application/json,application/x-www-form-urlencoded,multipart/form-data,text/plain'],
            'wa_gateway_parameter_1' => ['nullable', 'string', 'max:80'],
            'wa_gateway_value_1' => ['nullable', 'string', 'max:255'],
            'wa_gateway_parameter_2' => ['nullable', 'string', 'max:80'],
            'wa_gateway_value_2' => ['nullable', 'string', 'max:255'],
            'wa_gateway_parameter_3' => ['nullable', 'string', 'max:80'],
            'wa_gateway_value_3' => ['nullable', 'string', 'max:255'],
            'wa_gateway_parameter_4' => ['nullable', 'string', 'max:80'],
            'wa_gateway_value_4' => ['nullable', 'string', 'max:255'],
            'wa_gateway_timeout' => ['nullable', 'integer', 'min:3', 'max:120'],
            'wa_template_hadir' => ['nullable', 'string', 'max:2000'],
            'wa_template_terlambat' => ['nullable', 'string', 'max:2000'],
            'wa_template_pulang' => ['nullable', 'string', 'max:2000'],
            'wa_template_pulang_cepat' => ['nullable', 'string', 'max:2000'],
            'wa_template_izin_sakit_diajukan' => ['nullable', 'string', 'max:2000'],
            'wa_template_izin_sakit_disetujui' => ['nullable', 'string', 'max:2000'],
            'wa_template_izin_sakit_ditolak' => ['nullable', 'string', 'max:2000'],
            'wa_template_izin_sakit_reviewer_wakel' => ['nullable', 'string', 'max:2000'],
            'wa_template_izin_sakit_reviewer_admin' => ['nullable', 'string', 'max:2000'],
            'wa_template_forgot_password_otp' => ['nullable', 'string', 'max:2000'],
            'telegram_action' => ['nullable', 'in:reapply_webhook,reset_token'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_template_hadir' => ['nullable', 'string', 'max:2000'],
            'telegram_template_terlambat' => ['nullable', 'string', 'max:2000'],
            'telegram_template_pulang' => ['nullable', 'string', 'max:2000'],
            'telegram_template_pulang_cepat' => ['nullable', 'string', 'max:2000'],
            'telegram_template_izin_sakit_diajukan' => ['nullable', 'string', 'max:2000'],
            'telegram_template_izin_sakit_disetujui' => ['nullable', 'string', 'max:2000'],
            'telegram_template_izin_sakit_ditolak' => ['nullable', 'string', 'max:2000'],
        ]);

        $provider = strtoupper(trim((string) ($validated['wa_gateway_provider'] ?? 'SENDERBLAST')));
        if ($provider === 'STANDARD') {
            $provider = 'SENDERBLAST';
        }
        if (!in_array($provider, ['CUSTOM', 'SENDERBLAST', 'FONNTE'], true)) {
            $provider = 'SENDERBLAST';
        }

        $baseUrl = $this->normalizeNullableString($validated['wa_gateway_base_url'] ?? null);
        $bodyType = $this->normalizeNullableString($validated['wa_gateway_body_type'] ?? 'application/json');
        $parameter1 = $this->normalizeNullableString($validated['wa_gateway_parameter_1'] ?? 'number');
        $value1 = $this->normalizeNullableString($validated['wa_gateway_value_1'] ?? 'TUJUAN');
        $parameter2 = $this->normalizeNullableString($validated['wa_gateway_parameter_2'] ?? 'message');
        $value2 = $this->normalizeNullableString($validated['wa_gateway_value_2'] ?? 'PESAN');
        $parameter3 = $this->normalizeNullableString($validated['wa_gateway_parameter_3'] ?? null);
        $value3 = $this->normalizeNullableString($validated['wa_gateway_value_3'] ?? null);
        $parameter4 = $this->normalizeNullableString($validated['wa_gateway_parameter_4'] ?? null);
        $value4 = $this->normalizeNullableString($validated['wa_gateway_value_4'] ?? null);
        $headerKey = $this->normalizeNullableString($validated['wa_gateway_header_key'] ?? null);
        $headerValue = $this->normalizeNullableString($validated['wa_gateway_header_value'] ?? null);

        if ($provider === 'SENDERBLAST') {
            $baseUrl = 'https://app.senderblast.com/api/v1/send-message';
            $bodyType = 'application/json';
            $parameter1 = 'number';
            $value1 = 'TUJUAN';
            $parameter2 = 'message';
            $value2 = 'PESAN';
            $parameter3 = 'sender';
            $parameter4 = 'api_key';
        } elseif ($provider === 'FONNTE') {
            $baseUrl = 'https://api.fonnte.com/send';
            $bodyType = 'application/json';
            $parameter1 = 'target';
            $value1 = 'TUJUAN';
            $parameter2 = 'message';
            $value2 = 'PESAN';
            $parameter3 = null;
            $value3 = null;
            $parameter4 = null;
            $value4 = null;
            $headerKey = null;
            $headerValue = null;
        }

        $attendanceNotificationEnabled = $request->boolean('attendance_notif_enabled');
        $attendanceNotificationChannel = strtolower(trim((string) ($validated['attendance_notif_channel'] ?? 'whatsapp')));
        if (! in_array($attendanceNotificationChannel, ['whatsapp', 'telegram', 'both'], true)) {
            $attendanceNotificationChannel = 'whatsapp';
        }

        $waAttendanceNotificationEnabled = $attendanceNotificationEnabled
            && in_array($attendanceNotificationChannel, ['whatsapp', 'both'], true);
        $telegramAttendanceNotificationEnabled = $attendanceNotificationEnabled
            && in_array($attendanceNotificationChannel, ['telegram', 'both'], true);
        $izinSakitNotificationEnabled = $request->boolean('izin_sakit_notif_enabled');
        $izinSakitNotificationChannel = strtolower(trim((string) ($validated['izin_sakit_notif_channel'] ?? 'whatsapp')));
        if (! in_array($izinSakitNotificationChannel, ['whatsapp', 'telegram', 'both'], true)) {
            $izinSakitNotificationChannel = 'whatsapp';
        }

        $waIzinSakitNotificationEnabled = $izinSakitNotificationEnabled
            && in_array($izinSakitNotificationChannel, ['whatsapp', 'both'], true);
        $telegramIzinSakitNotificationEnabled = $izinSakitNotificationEnabled
            && in_array($izinSakitNotificationChannel, ['telegram', 'both'], true);
        $reviewerNotificationEnabled = $request->boolean('wa_notif_izin_sakit_reviewer_enabled');

        $waPayload = [
            'attendance_notif_enabled' => $attendanceNotificationEnabled ? '1' : '0',
            'attendance_notif_channel' => $attendanceNotificationChannel,
            'izin_sakit_notif_enabled' => $izinSakitNotificationEnabled ? '1' : '0',
            'izin_sakit_notif_channel' => $izinSakitNotificationChannel,
            'wa_notif_enabled' => ($waAttendanceNotificationEnabled || $waIzinSakitNotificationEnabled || $reviewerNotificationEnabled) ? '1' : '0',
            'wa_notif_attendance_enabled' => $waAttendanceNotificationEnabled ? '1' : '0',
            'wa_notif_izin_sakit_enabled' => $waIzinSakitNotificationEnabled ? '1' : '0',
            'wa_notif_izin_sakit_reviewer_enabled' => $reviewerNotificationEnabled ? '1' : '0',
            'wa_notif_target' => 'siswa',
            'wa_gateway_provider' => $provider,
            'wa_gateway_base_url' => $baseUrl,
            'wa_gateway_authorization' => $this->normalizeNullableString($validated['wa_gateway_authorization'] ?? null),
            'wa_gateway_header_key' => $headerKey,
            'wa_gateway_header_value' => $headerValue,
            'wa_gateway_body_type' => $bodyType,
            'wa_gateway_parameter_1' => $parameter1,
            'wa_gateway_value_1' => $value1,
            'wa_gateway_parameter_2' => $parameter2,
            'wa_gateway_value_2' => $value2,
            'wa_gateway_parameter_3' => $parameter3,
            'wa_gateway_value_3' => $value3,
            'wa_gateway_parameter_4' => $parameter4,
            'wa_gateway_value_4' => $value4,
            'wa_gateway_timeout' => (string) ((int) ($validated['wa_gateway_timeout'] ?? 15)),
            'wa_template_hadir' => $this->normalizeNullableString($validated['wa_template_hadir'] ?? null),
            'wa_template_terlambat' => $this->normalizeNullableString($validated['wa_template_terlambat'] ?? null),
            'wa_template_pulang' => $this->normalizeNullableString($validated['wa_template_pulang'] ?? null),
            'wa_template_pulang_cepat' => $this->normalizeNullableString($validated['wa_template_pulang_cepat'] ?? null),
            'wa_template_izin_sakit_diajukan' => $this->normalizeNullableString($validated['wa_template_izin_sakit_diajukan'] ?? null),
            'wa_template_izin_sakit_disetujui' => $this->normalizeNullableString($validated['wa_template_izin_sakit_disetujui'] ?? null),
            'wa_template_izin_sakit_ditolak' => $this->normalizeNullableString($validated['wa_template_izin_sakit_ditolak'] ?? null),
            'wa_template_izin_sakit_reviewer_wakel' => $this->normalizeNullableString($validated['wa_template_izin_sakit_reviewer_wakel'] ?? null),
            'wa_template_izin_sakit_reviewer_admin' => $this->normalizeNullableString($validated['wa_template_izin_sakit_reviewer_admin'] ?? null),
            'wa_template_forgot_password_otp' => $this->normalizeNullableString($validated['wa_template_forgot_password_otp'] ?? null),
        ];

        $telegramSettings = $telegramBotService->getSettings(true);
        $existingTokenStored = trim((string) (Konfigurasi::query()->where('key', 'telegram_bot_token')->value('value') ?? ''));
        $existingToken = trim((string) ($telegramSettings['telegram_bot_token'] ?? ''));
        $existingBotId = trim((string) ($telegramSettings['telegram_bot_id'] ?? ''));
        $existingSecret = trim((string) ($telegramSettings['telegram_webhook_secret'] ?? ''));
        $existingWebhookStatus = trim((string) ($telegramSettings['telegram_webhook_status'] ?? 'disabled'));
        $existingWebhookUrl = trim((string) ($telegramSettings['telegram_webhook_url'] ?? ''));
        $incomingTelegramToken = $this->normalizeNullableString($validated['telegram_bot_token'] ?? null);
        $telegramAction = strtolower(trim((string) ($validated['telegram_action'] ?? '')));
        $shouldReapplyWebhook = $telegramAction === 'reapply_webhook';
        $shouldResetTelegramToken = $telegramAction === 'reset_token';
        $isTelegramTokenUpdated = $incomingTelegramToken !== null && $incomingTelegramToken !== $existingToken;
        $effectiveTelegramToken = $incomingTelegramToken ?? ($existingToken !== '' ? $existingToken : null);

        if ($shouldResetTelegramToken) {
            if ($attendanceNotificationChannel === 'telegram') {
                $attendanceNotificationEnabled = false;
                $attendanceNotificationChannel = 'whatsapp';
            } elseif ($attendanceNotificationChannel === 'both') {
                $attendanceNotificationChannel = 'whatsapp';
            }

            if ($izinSakitNotificationChannel === 'telegram') {
                $izinSakitNotificationEnabled = false;
                $izinSakitNotificationChannel = 'whatsapp';
            } elseif ($izinSakitNotificationChannel === 'both') {
                $izinSakitNotificationChannel = 'whatsapp';
            }

            $waAttendanceNotificationEnabled = $attendanceNotificationEnabled
                && in_array($attendanceNotificationChannel, ['whatsapp', 'both'], true);
            $telegramAttendanceNotificationEnabled = false;
            $waIzinSakitNotificationEnabled = $izinSakitNotificationEnabled
                && in_array($izinSakitNotificationChannel, ['whatsapp', 'both'], true);
            $telegramIzinSakitNotificationEnabled = false;
        }

        $waPayload['attendance_notif_enabled'] = $attendanceNotificationEnabled ? '1' : '0';
        $waPayload['attendance_notif_channel'] = $attendanceNotificationChannel;
        $waPayload['izin_sakit_notif_enabled'] = $izinSakitNotificationEnabled ? '1' : '0';
        $waPayload['izin_sakit_notif_channel'] = $izinSakitNotificationChannel;
        $waPayload['wa_notif_enabled'] = ($waAttendanceNotificationEnabled || $waIzinSakitNotificationEnabled || $reviewerNotificationEnabled) ? '1' : '0';
        $waPayload['wa_notif_attendance_enabled'] = $waAttendanceNotificationEnabled ? '1' : '0';
        $waPayload['wa_notif_izin_sakit_enabled'] = $waIzinSakitNotificationEnabled ? '1' : '0';

        if (! $shouldResetTelegramToken && ($telegramAttendanceNotificationEnabled || $telegramIzinSakitNotificationEnabled || $shouldReapplyWebhook) && $effectiveTelegramToken === null) {
            throw ValidationException::withMessages([
                'telegram_bot_token' => 'Token bot Telegram wajib diisi agar notifikasi Telegram bisa digunakan.',
            ]);
        }

        $telegramPayload = [
            'telegram_notif_attendance_enabled' => $telegramAttendanceNotificationEnabled ? '1' : '0',
            'telegram_notif_izin_sakit_enabled' => $telegramIzinSakitNotificationEnabled ? '1' : '0',
            'telegram_template_hadir' => $this->normalizeNullableString($validated['telegram_template_hadir'] ?? null),
            'telegram_template_terlambat' => $this->normalizeNullableString($validated['telegram_template_terlambat'] ?? null),
            'telegram_template_pulang' => $this->normalizeNullableString($validated['telegram_template_pulang'] ?? null),
            'telegram_template_pulang_cepat' => $this->normalizeNullableString($validated['telegram_template_pulang_cepat'] ?? null),
            'telegram_template_izin_sakit_diajukan' => $this->normalizeNullableString($validated['telegram_template_izin_sakit_diajukan'] ?? null),
            'telegram_template_izin_sakit_disetujui' => $this->normalizeNullableString($validated['telegram_template_izin_sakit_disetujui'] ?? null),
            'telegram_template_izin_sakit_ditolak' => $this->normalizeNullableString($validated['telegram_template_izin_sakit_ditolak'] ?? null),
            'telegram_bot_id' => $existingBotId !== '' ? $existingBotId : null,
            'telegram_bot_username' => $this->normalizeNullableString($telegramSettings['telegram_bot_username'] ?? null),
            'telegram_webhook_secret' => $existingSecret !== '' ? $existingSecret : null,
            'telegram_webhook_url' => $existingWebhookUrl !== '' ? $existingWebhookUrl : null,
            'telegram_webhook_status' => $existingWebhookStatus,
            'telegram_webhook_last_error' => $this->normalizeNullableString($telegramSettings['telegram_webhook_last_error'] ?? null),
        ];

        if ($existingTokenStored !== '') {
            $telegramPayload['telegram_bot_token'] = $existingTokenStored;
        }

        $oldBotIdToArchive = null;
        $resetWebhookWarning = null;
        if ($shouldResetTelegramToken) {
            $oldBotIdToArchive = $existingBotId !== '' ? $existingBotId : null;

            if ($existingToken !== '') {
                $clearWebhookResult = $telegramBotService->clearWebhook($existingToken);
                if (! ($clearWebhookResult['success'] ?? false)) {
                    $resetWebhookWarning = trim((string) ($clearWebhookResult['message'] ?? 'Unknown error'));
                    Log::warning('Telegram webhook cleanup failed during token reset', [
                        'bot_id' => $existingBotId !== '' ? $existingBotId : null,
                        'message' => $resetWebhookWarning,
                    ]);
                }
            }

            $telegramPayload = array_merge($telegramPayload, [
                'telegram_notif_attendance_enabled' => '0',
                'telegram_notif_izin_sakit_enabled' => '0',
                'telegram_bot_token' => null,
                'telegram_bot_id' => null,
                'telegram_bot_username' => null,
                'telegram_webhook_secret' => null,
                'telegram_webhook_url' => null,
                'telegram_webhook_status' => null,
                'telegram_webhook_last_error' => null,
            ]);
        } elseif ($isTelegramTokenUpdated || $shouldReapplyWebhook) {
            $botValidation = $telegramBotService->validateBotToken((string) $effectiveTelegramToken);
            if (! ($botValidation['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'telegram_bot_token' => 'Token bot Telegram tidak valid: '.trim((string) ($botValidation['message'] ?? 'Validasi gagal.')),
                ]);
            }

            $botData = is_array($botValidation['data'] ?? null) ? $botValidation['data'] : [];
            $newBotId = trim((string) ($botData['id'] ?? ''));
            $newBotUsername = ltrim(trim((string) ($botData['username'] ?? '')), '@');
            if ($newBotId === '') {
                throw ValidationException::withMessages([
                    'telegram_bot_token' => 'Telegram tidak mengembalikan identitas bot yang valid.',
                ]);
            }

            $syncCommandsResult = $telegramBotService->syncDefaultBotCommands((string) $effectiveTelegramToken);
            if (! ($syncCommandsResult['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'telegram_bot_token' => 'Daftar command bot Telegram gagal diperbarui: '.trim((string) ($syncCommandsResult['message'] ?? 'Unknown error')),
                ]);
            }

            $isDifferentBot = $existingBotId !== '' && $existingBotId !== $newBotId;
            if ($isDifferentBot) {
                if ($existingToken === '') {
                    throw ValidationException::withMessages([
                        'telegram_bot_token' => 'Bot lama tidak bisa dilepas karena token lama tidak tersedia. Simpan ulang token bot lama atau perbarui data konfigurasi terlebih dulu.',
                    ]);
                }

                $clearWebhookResult = $telegramBotService->clearWebhook($existingToken);
                if (! ($clearWebhookResult['success'] ?? false)) {
                    throw ValidationException::withMessages([
                        'telegram_bot_token' => 'Webhook bot lama gagal dilepas: '.trim((string) ($clearWebhookResult['message'] ?? 'Unknown error')),
                    ]);
                }

                $oldBotIdToArchive = $existingBotId;
            }

            $secret = $existingSecret !== '' && ! $isDifferentBot
                ? $existingSecret
                : $telegramBotService->generateWebhookSecret();

            $webhookResult = $telegramBotService->setWebhook((string) $effectiveTelegramToken, $secret);
            if (! ($webhookResult['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'telegram_bot_token' => 'Webhook Telegram gagal dipasang: '.trim((string) ($webhookResult['message'] ?? 'Unknown error')),
                ]);
            }

            $telegramPayload['telegram_bot_token'] = Crypt::encryptString((string) $effectiveTelegramToken);
            $telegramPayload['telegram_bot_id'] = $newBotId;
            $telegramPayload['telegram_bot_username'] = $newBotUsername !== '' ? $newBotUsername : null;
            $telegramPayload['telegram_webhook_secret'] = $secret;
            $telegramPayload['telegram_webhook_url'] = trim((string) ($webhookResult['webhook_url'] ?? $telegramBotService->buildWebhookUrl($secret)));
            $telegramPayload['telegram_webhook_status'] = 'active';
            $telegramPayload['telegram_webhook_last_error'] = null;
        }

        $this->saveConfigurationPayload($waPayload, 'Pengaturan notifikasi WhatsApp');
        $this->saveConfigurationPayload($telegramPayload, 'Pengaturan notifikasi Telegram');

        if ($oldBotIdToArchive !== null) {
            TelegramChatLink::query()
                ->where('telegram_bot_id', $oldBotIdToArchive)
                ->update(['is_active' => false]);
        }

        // Bersihkan key lama agar format key konsisten memakai `wa_gateway_*`.
        Konfigurasi::query()
            ->whereIn('key', [
                'wa_gateway_template',
                'wa_gateway_default_param_1',
                'wa_gateway_default_value_1',
                'wa_gateway_default_param_2',
                'wa_gateway_default_value_2',
                'wa_gateway_extra_param_1',
                'wa_gateway_extra_value_1',
                'wa_gateway_extra_param_2',
                'wa_gateway_extra_value_2',
                'parameter_1',
                'value_1',
                'parameter_2',
                'value_2',
                'parameter_3',
                'value_3',
                'parameter_4',
                'value_4',
                'wa_template_alpa',
            ])
            ->delete();

        $telegramBotService->clearSettingsCache();
        $freshSettings = $this->getSettings();
        $successMessage = 'Pengaturan notifikasi berhasil disimpan.';
        if ($shouldResetTelegramToken) {
            $successMessage = 'Konfigurasi Telegram berhasil direset.';
            if ($resetWebhookWarning !== null && $resetWebhookWarning !== '') {
                $successMessage .= ' Webhook bot lama tidak bisa dilepas, tetapi data lokal tetap dibersihkan.';
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $freshSettings,
            ]);
        }

        return back()->with('success', $successMessage);
    }

    public function testSend(Request $request, WaGatewayService $waGatewayService): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'string', 'min:5', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $waGatewayService->sendCustomMessage(
            (string) ($validated['recipient'] ?? ''),
            (string) ($validated['message'] ?? '')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', Rule::in(['siswa', 'kelas'])],
            'siswa_id' => [
                Rule::requiredIf(fn () => $request->input('target_type') === 'siswa'),
                'nullable',
                'integer',
                Rule::exists('siswa', 'id'),
            ],
            'kelas_id' => [
                Rule::requiredIf(fn () => $request->input('target_type') === 'kelas'),
                'nullable',
                'integer',
                Rule::exists('kelas', 'id'),
            ],
            'message' => ['required', 'string', 'max:2000'],
            'pause_min_sec' => ['nullable', 'integer', 'min:0', 'max:60'],
            'pause_max_sec' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        $targetType = (string) ($validated['target_type'] ?? '');
        $siswaId = (int) ($validated['siswa_id'] ?? 0);
        $kelasId = (int) ($validated['kelas_id'] ?? 0);
        $message = trim((string) ($validated['message'] ?? ''));
        $pauseMinSec = isset($validated['pause_min_sec']) ? (int) $validated['pause_min_sec'] : null;
        $pauseMaxSec = isset($validated['pause_max_sec']) ? (int) $validated['pause_max_sec'] : null;

        $pauseMinMs = (int) config('services.wa_gateway.broadcast_interval_min_ms', 5000);
        $pauseMaxMs = (int) config('services.wa_gateway.broadcast_interval_max_ms', 10000);
        if ($pauseMinSec !== null) {
            $pauseMinMs = $pauseMinSec * 1000;
        }
        if ($pauseMaxSec !== null) {
            $pauseMaxMs = $pauseMaxSec * 1000;
        }
        $pauseMinMs = max(0, min($pauseMinMs, 60000));
        $pauseMaxMs = max($pauseMinMs, min($pauseMaxMs, 60000));

        $recipients = $this->resolveBroadcastRecipients($targetType, $siswaId, $kelasId);
        if (count($recipients) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Penerima tidak ditemukan atau nomor WA belum tersedia.',
            ], 422);
        }

        $total = count($recipients);
        $senderName = trim((string) ($request->user()?->name ?? $request->user()?->username ?? ''));
        $targetLabel = $this->resolveBroadcastTargetLabel($targetType, $siswaId, $kelasId, $recipients);
        $websiteName = $this->resolveWebsiteName();
        $meta = [
            'target_type' => $targetType,
            'target_id' => $targetType === 'siswa' ? $siswaId : $kelasId,
            'queued_by_user_id' => (int) ($request->user()?->id ?? 0),
            'queued_by_name' => $senderName !== '' ? $senderName : null,
            'target_type_label' => $targetType === 'kelas' ? 'Per Kelas' : 'Per Siswa',
            'target_label' => $targetLabel,
            'website_name' => $websiteName,
            'app_name' => (string) config('app.name', 'Absensindo'),
        ];

        try {
            dispatch(function () use ($recipients, $message, $meta, $pauseMinMs, $pauseMaxMs): void {
                $waGatewayService = app(WaGatewayService::class);
                $pauseMinMs = max(0, min((int) $pauseMinMs, 60000));
                $pauseMaxMs = max($pauseMinMs, min((int) $pauseMaxMs, 60000));

                $sent = 0;
                $failed = 0;
                $totalRecipients = count($recipients);
                $requestTime = Carbon::now();
                $baseContext = [
                    'website_name' => (string) ($meta['website_name'] ?? ''),
                    'app_name' => (string) ($meta['app_name'] ?? ''),
                    'tanggal' => $requestTime->format('d-m-Y'),
                    'jam' => $requestTime->format('H:i'),
                    'waktu' => $requestTime->format('H:i:s'),
                    'tanggal_jam' => $requestTime->format('d-m-Y H:i'),
                ];

                foreach ($recipients as $index => $recipient) {
                    $phone = trim((string) ($recipient['phone'] ?? ''));
                    $label = trim((string) ($recipient['label'] ?? ''));
                    if ($phone === '') {
                        $failed++;
                        continue;
                    }

                    try {
                        $context = self::buildBroadcastRecipientContext(
                            is_array($recipient) ? $recipient : []
                        );
                        $messageParsed = self::renderBroadcastMessageTemplate(
                            $message,
                            array_merge($baseContext, $context)
                        );

                        $result = $waGatewayService->sendCustomMessage($phone, $messageParsed);
                        if ((bool) ($result['success'] ?? false)) {
                            $sent++;
                        } else {
                            $failed++;
                            Log::warning('WA broadcast background recipient failed', [
                                'phone' => $phone,
                                'label' => $label !== '' ? $label : null,
                                'error' => (string) ($result['message'] ?? 'Unknown error'),
                                'meta' => $meta,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('WA broadcast background recipient exception', [
                            'phone' => $phone,
                            'label' => $label !== '' ? $label : null,
                            'message' => $e->getMessage(),
                            'meta' => $meta,
                        ]);
                    }

                    if ($pauseMaxMs > 0 && $index < ($totalRecipients - 1)) {
                        $pauseMs = $pauseMaxMs > $pauseMinMs
                            ? random_int($pauseMinMs, $pauseMaxMs)
                            : $pauseMinMs;
                        usleep($pauseMs * 1000);
                    }
                }

                Log::info('WA broadcast background completed', [
                    'total' => $totalRecipients,
                    'sent' => $sent,
                    'failed' => $failed,
                    'pause_min_ms' => $pauseMinMs,
                    'pause_max_ms' => $pauseMaxMs,
                    'meta' => $meta,
                ]);
            })->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('WA broadcast background dispatch failed', [
                'meta' => $meta,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai proses background notifikasi.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dikirim ke ' . $total . ' siswa.',
            'data' => [
                'target_type' => $targetType,
                'total' => $total,
                'background_total' => $total,
                'mode' => 'after_response',
            ],
        ]);
    }

    protected function getSettings(): array
    {
        $defaults = [
            'attendance_notif_enabled' => '0',
            'attendance_notif_channel' => 'whatsapp',
            'izin_sakit_notif_enabled' => '0',
            'izin_sakit_notif_channel' => 'whatsapp',
            'wa_notif_enabled' => '0',
            'wa_notif_attendance_enabled' => '0',
            'wa_notif_izin_sakit_enabled' => '0',
            'wa_notif_izin_sakit_reviewer_enabled' => '0',
            'wa_notif_target' => 'siswa',
            'wa_gateway_provider' => 'SENDERBLAST',
            'wa_gateway_base_url' => '',
            'wa_gateway_authorization' => '',
            'wa_gateway_header_key' => '',
            'wa_gateway_header_value' => '',
            'wa_gateway_body_type' => 'application/json',
            'wa_gateway_parameter_1' => 'number',
            'wa_gateway_value_1' => 'TUJUAN',
            'wa_gateway_parameter_2' => 'message',
            'wa_gateway_value_2' => 'PESAN',
            'wa_gateway_parameter_3' => '',
            'wa_gateway_value_3' => '',
            'wa_gateway_parameter_4' => '',
            'wa_gateway_value_4' => '',
            'wa_gateway_timeout' => '15',
            'wa_template_hadir' => 'Halo {nama}, absensi hari ini: HADIR pada {tanggal} {jam}.',
            'wa_template_terlambat' => 'Halo {nama}, absensi hari ini: TERLAMBAT pada {tanggal} {jam}.',
            'wa_template_pulang' => 'Halo {nama}, absensi pulang hari ini tercatat pada {tanggal} {jam}.',
            'wa_template_pulang_cepat' => 'Halo {nama}, hari ini tercatat PULANG CEPAT pada {tanggal} {jam}.',
            'wa_template_izin_sakit_diajukan' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} sudah diterima dan menunggu persetujuan.',
            'wa_template_izin_sakit_disetujui' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} sudah disetujui.',
            'wa_template_izin_sakit_ditolak' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} ditolak.',
            'wa_template_izin_sakit_reviewer_wakel' => 'Halo {recipient_name}, ada pengajuan {jenis} baru dari {siswa_nama} (kelas {kelas}) untuk {rentang_tanggal}. Alasan: {alasan}. Mohon ditinjau.',
            'wa_template_izin_sakit_reviewer_admin' => 'Halo {recipient_name}, ada pengajuan {jenis} baru dari {siswa_nama} (kelas {kelas}) untuk {rentang_tanggal}. Alasan: {alasan}. Mohon ditinjau.',
            'wa_template_forgot_password_otp' => "Halo {nama},\n\nKode OTP untuk reset password akun {username} adalah *{otp_code}*.\nKode berlaku {otp_expired_minutes} menit.\nWaktu permintaan: {otp_request_time}.\n\nJangan berikan kode ini kepada siapa pun.",
        ];

        $legacyMap = [
            'wa_gateway_template' => 'wa_gateway_provider',
            'wa_gateway_default_param_1' => 'wa_gateway_parameter_1',
            'wa_gateway_default_value_1' => 'wa_gateway_value_1',
            'wa_gateway_default_param_2' => 'wa_gateway_parameter_2',
            'wa_gateway_default_value_2' => 'wa_gateway_value_2',
            'wa_gateway_extra_param_1' => 'wa_gateway_parameter_3',
            'wa_gateway_extra_value_1' => 'wa_gateway_value_3',
            'wa_gateway_extra_param_2' => 'wa_gateway_parameter_4',
            'wa_gateway_extra_value_2' => 'wa_gateway_value_4',
            'parameter_1' => 'wa_gateway_parameter_1',
            'value_1' => 'wa_gateway_value_1',
            'parameter_2' => 'wa_gateway_parameter_2',
            'value_2' => 'wa_gateway_value_2',
            'parameter_3' => 'wa_gateway_parameter_3',
            'value_3' => 'wa_gateway_value_3',
            'parameter_4' => 'wa_gateway_parameter_4',
            'value_4' => 'wa_gateway_value_4',
        ];

        $rows = Konfigurasi::query()
            ->whereIn('key', array_merge(array_keys($defaults), array_keys($legacyMap)))
            ->pluck('value', 'key')
            ->all();

        $settings = array_merge($defaults, $rows);
        $legacyNotificationEnabled = (string) ($rows['wa_notif_enabled'] ?? $defaults['wa_notif_enabled']) === '1';
        foreach ([
            'wa_notif_attendance_enabled',
            'wa_notif_izin_sakit_enabled',
            'wa_notif_izin_sakit_reviewer_enabled',
        ] as $notificationKey) {
            if (!array_key_exists($notificationKey, $rows)) {
                $settings[$notificationKey] = $legacyNotificationEnabled ? '1' : '0';
            }
        }

        foreach ($legacyMap as $legacyKey => $newKey) {
            $newValue = trim((string) ($settings[$newKey] ?? ''));
            if ($newValue !== '') {
                continue;
            }

            $legacyValue = trim((string) ($rows[$legacyKey] ?? ''));
            if ($legacyValue === '') {
                continue;
            }

            $settings[$newKey] = $legacyValue;
        }
        if (strtoupper((string) ($settings['wa_gateway_provider'] ?? '')) === 'STANDARD') {
            $settings['wa_gateway_provider'] = 'SENDERBLAST';
        }
        if (strtoupper((string) ($settings['wa_gateway_provider'] ?? '')) === 'SENDERBLAST') {
            if (trim((string) ($settings['wa_gateway_base_url'] ?? '')) === '') {
                $settings['wa_gateway_base_url'] = 'https://app.senderblast.com/api/v1/send-message';
            }
            if (trim((string) ($settings['wa_gateway_body_type'] ?? '')) === '') {
                $settings['wa_gateway_body_type'] = 'application/json';
            }
            if (trim((string) ($settings['wa_gateway_parameter_1'] ?? '')) === '') {
                $settings['wa_gateway_parameter_1'] = 'number';
            }
            if (trim((string) ($settings['wa_gateway_value_1'] ?? '')) === '') {
                $settings['wa_gateway_value_1'] = 'TUJUAN';
            }
            if (trim((string) ($settings['wa_gateway_parameter_2'] ?? '')) === '') {
                $settings['wa_gateway_parameter_2'] = 'message';
            }
            if (trim((string) ($settings['wa_gateway_value_2'] ?? '')) === '') {
                $settings['wa_gateway_value_2'] = 'PESAN';
            }
            if (trim((string) ($settings['wa_gateway_parameter_3'] ?? '')) === '') {
                $settings['wa_gateway_parameter_3'] = 'sender';
            }
            if (trim((string) ($settings['wa_gateway_parameter_4'] ?? '')) === '') {
                $settings['wa_gateway_parameter_4'] = 'api_key';
            }
        } elseif (strtoupper((string) ($settings['wa_gateway_provider'] ?? '')) === 'FONNTE') {
            $settings['wa_gateway_base_url'] = 'https://api.fonnte.com/send';
            $settings['wa_gateway_body_type'] = 'application/json';
            $settings['wa_gateway_parameter_1'] = 'target';
            $settings['wa_gateway_value_1'] = 'TUJUAN';
            $settings['wa_gateway_parameter_2'] = 'message';
            $settings['wa_gateway_value_2'] = 'PESAN';
            $settings['wa_gateway_parameter_3'] = '';
            $settings['wa_gateway_value_3'] = '';
            $settings['wa_gateway_parameter_4'] = '';
            $settings['wa_gateway_value_4'] = '';
            $settings['wa_gateway_header_key'] = '';
            $settings['wa_gateway_header_value'] = '';
        }
        $settings['wa_notif_target'] = 'siswa';
        $settings['wa_notif_attendance_enabled'] = (string) ($settings['wa_notif_attendance_enabled'] ?? '0') === '1';
        $settings['wa_notif_izin_sakit_enabled'] = (string) ($settings['wa_notif_izin_sakit_enabled'] ?? '0') === '1';
        $settings['wa_notif_izin_sakit_reviewer_enabled'] = (string) ($settings['wa_notif_izin_sakit_reviewer_enabled'] ?? '0') === '1';
        $settings['wa_notif_enabled'] = $settings['wa_notif_attendance_enabled']
            || $settings['wa_notif_izin_sakit_enabled']
            || $settings['wa_notif_izin_sakit_reviewer_enabled'];
        $settings['wa_gateway_timeout'] = (int) ($settings['wa_gateway_timeout'] ?? 15);

        $telegramSettings = app(TelegramBotService::class)->getSettings();
        $settings = array_merge($settings, $telegramSettings);

        $telegramAttendanceEnabled = (bool) ($telegramSettings['telegram_notif_attendance_enabled'] ?? false);
        $telegramIzinSakitEnabled = (bool) ($telegramSettings['telegram_notif_izin_sakit_enabled'] ?? false);
        if (array_key_exists('attendance_notif_enabled', $rows)) {
            $settings['attendance_notif_enabled'] = (string) ($rows['attendance_notif_enabled'] ?? '0') === '1';
        } else {
            $settings['attendance_notif_enabled'] = $settings['wa_notif_attendance_enabled'] || $telegramAttendanceEnabled;
        }

        if (array_key_exists('attendance_notif_channel', $rows)) {
            $savedChannel = strtolower(trim((string) ($rows['attendance_notif_channel'] ?? 'whatsapp')));
            $settings['attendance_notif_channel'] = in_array($savedChannel, ['whatsapp', 'telegram', 'both'], true)
                ? $savedChannel
                : 'whatsapp';
        } else {
            $settings['attendance_notif_channel'] = $settings['wa_notif_attendance_enabled'] && $telegramAttendanceEnabled
                ? 'both'
                : ($telegramAttendanceEnabled ? 'telegram' : 'whatsapp');
        }

        if (array_key_exists('izin_sakit_notif_enabled', $rows)) {
            $settings['izin_sakit_notif_enabled'] = (string) ($rows['izin_sakit_notif_enabled'] ?? '0') === '1';
        } else {
            $settings['izin_sakit_notif_enabled'] = $settings['wa_notif_izin_sakit_enabled'] || $telegramIzinSakitEnabled;
        }

        if (array_key_exists('izin_sakit_notif_channel', $rows)) {
            $savedIzinSakitChannel = strtolower(trim((string) ($rows['izin_sakit_notif_channel'] ?? 'whatsapp')));
            $settings['izin_sakit_notif_channel'] = in_array($savedIzinSakitChannel, ['whatsapp', 'telegram', 'both'], true)
                ? $savedIzinSakitChannel
                : 'whatsapp';
        } else {
            $settings['izin_sakit_notif_channel'] = $settings['wa_notif_izin_sakit_enabled'] && $telegramIzinSakitEnabled
                ? 'both'
                : ($telegramIzinSakitEnabled ? 'telegram' : 'whatsapp');
        }

        return $settings;
    }

    protected function normalizeNullableString($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    protected function saveConfigurationPayload(array $payload, string $description): void
    {
        foreach ($payload as $key => $value) {
            $normalizedValue = is_string($value) ? trim($value) : $value;
            if ($normalizedValue === null || $normalizedValue === '') {
                Konfigurasi::query()->where('key', $key)->delete();
                continue;
            }

            Konfigurasi::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $normalizedValue, 'keterangan' => $description]
            );
        }
    }

    protected function getNotificationRecipientOptions(): array
    {
        $phoneReadyCounts = Siswa::query()
            ->selectRaw('kelas, COUNT(*) AS total')
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
            ->groupBy('kelas')
            ->pluck('total', 'kelas')
            ->all();

        $kelas = Kelas::query()
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(function (Kelas $row) use ($phoneReadyCounts): array {
                $className = trim((string) $row->nama);
                return [
                    'id' => (int) $row->id,
                    'name' => $className,
                    'recipient_count' => (int) ($phoneReadyCounts[$className] ?? 0),
                ];
            })
            ->values()
            ->all();

        $siswa = Siswa::query()
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
            ->orderBy('nama')
            ->orderBy('nisn')
            ->get(['id', 'nama', 'nisn', 'kelas', 'no_hp'])
            ->map(function (Siswa $row): array {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->nama,
                    'nisn' => (string) ($row->nisn ?? ''),
                    'kelas' => (string) ($row->kelas ?? ''),
                    'label' => $this->formatSiswaLabel($row),
                ];
            })
            ->values()
            ->all();

        return [
            'kelas' => $kelas,
            'siswa' => $siswa,
        ];
    }

    /**
     * @return array<int, array{
     *     phone:string,
     *     label:string,
     *     context:array{
     *         nama:string,
     *         nisn:string,
     *         kelas:string,
     *         no_hp:string,
     *         jenis_kelamin:string,
     *         tanggal_lahir:string,
     *         agama:string,
     *         nama_ayah:string,
     *         nama_ibu:string,
     *         nama_orang_tua:string,
     *         alamat:string
     *     }
     * }>
     */
    protected function resolveBroadcastRecipients(string $targetType, int $siswaId, int $kelasId): array
    {
        if ($targetType === 'siswa') {
            if ($siswaId <= 0) {
                return [];
            }

            $siswa = Siswa::query()
                ->whereKey($siswaId)
                ->whereNotNull('no_hp')
                ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
                ->first([
                    'id',
                    'nama',
                    'nisn',
                    'kelas',
                    'no_hp',
                    'jenis_kelamin',
                    'tanggal_lahir',
                    'agama',
                    'nama_ayah',
                    'nama_ibu',
                    'alamat',
                ]);

            if (!$siswa) {
                return [];
            }

            $rawPhone = trim((string) ($siswa->no_hp ?? ''));
            return [[
                'phone' => $rawPhone,
                'label' => $this->formatSiswaLabel($siswa),
                'context' => $this->buildRecipientContext($siswa, $rawPhone),
            ]];
        }

        if ($targetType !== 'kelas' || $kelasId <= 0) {
            return [];
        }

        $kelasNama = trim((string) (Kelas::query()->whereKey($kelasId)->value('nama') ?? ''));
        if ($kelasNama === '') {
            return [];
        }

        $rows = Siswa::query()
            ->where('kelas', $kelasNama)
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
            ->orderBy('nama')
            ->orderBy('nisn')
            ->get([
                'id',
                'nama',
                'nisn',
                'kelas',
                'no_hp',
                'jenis_kelamin',
                'tanggal_lahir',
                'agama',
                'nama_ayah',
                'nama_ibu',
                'alamat',
            ]);

        $recipients = [];
        $seenPhones = [];
        foreach ($rows as $row) {
            $rawPhone = trim((string) ($row->no_hp ?? ''));
            if ($rawPhone === '') {
                continue;
            }

            $phoneKey = preg_replace('/\D+/', '', $rawPhone);
            $phoneKey = $phoneKey !== '' ? $phoneKey : $rawPhone;
            if (isset($seenPhones[$phoneKey])) {
                continue;
            }

            $seenPhones[$phoneKey] = true;
            $recipients[] = [
                'phone' => $rawPhone,
                'label' => $this->formatSiswaLabel($row),
                'context' => $this->buildRecipientContext($row, $rawPhone),
            ];
        }

        return $recipients;
    }

    /**
     * @return array{
     *     nama:string,
     *     nisn:string,
     *     kelas:string,
     *     no_hp:string,
     *     jenis_kelamin:string,
     *     tanggal_lahir:string,
     *     agama:string,
     *     nama_ayah:string,
     *     nama_ibu:string,
     *     nama_orang_tua:string,
     *     alamat:string
     * }
     */
    protected function buildRecipientContext(Siswa $siswa, string $rawPhone): array
    {
        $namaAyah = trim((string) ($siswa->nama_ayah ?? ''));
        $namaIbu = trim((string) ($siswa->nama_ibu ?? ''));
        $namaOrangTua = implode(' / ', array_values(array_filter([$namaAyah, $namaIbu], fn ($x) => $x !== '')));

        $tanggalLahir = '';
        try {
            if ($siswa->tanggal_lahir instanceof \DateTimeInterface) {
                $tanggalLahir = $siswa->tanggal_lahir->format('d-m-Y');
            } elseif (trim((string) ($siswa->tanggal_lahir ?? '')) !== '') {
                $parsed = Carbon::parse((string) $siswa->tanggal_lahir);
                $tanggalLahir = $parsed->format('d-m-Y');
            }
        } catch (\Throwable $e) {
            $tanggalLahir = '';
        }

        return [
            'nama' => trim((string) ($siswa->nama ?? '')),
            'nisn' => trim((string) ($siswa->nisn ?? '')),
            'kelas' => trim((string) ($siswa->kelas ?? '')),
            'no_hp' => trim((string) $rawPhone),
            'jenis_kelamin' => trim((string) ($siswa->jenis_kelamin ?? '')),
            'tanggal_lahir' => $tanggalLahir,
            'agama' => trim((string) ($siswa->agama ?? '')),
            'nama_ayah' => $namaAyah,
            'nama_ibu' => $namaIbu,
            'nama_orang_tua' => $namaOrangTua,
            'alamat' => trim((string) ($siswa->alamat ?? '')),
        ];
    }

    /**
     * @param  array<int, array{
     *     label?:string,
     *     context?:array{nama?:string,kelas?:string}
     * }>  $recipients
     */
    protected function resolveBroadcastTargetLabel(string $targetType, int $siswaId, int $kelasId, array $recipients): string
    {
        if ($targetType === 'kelas') {
            return trim((string) (Kelas::query()->whereKey($kelasId)->value('nama') ?? ''));
        }

        if ($siswaId > 0) {
            $name = trim((string) (Siswa::query()->whereKey($siswaId)->value('nama') ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $first = $recipients[0] ?? [];
        $label = trim((string) ($first['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        return trim((string) ($first['context']['nama'] ?? ''));
    }

    protected function resolveWebsiteName(): string
    {
        $websiteName = trim((string) (Konfigurasi::query()
            ->where('key', 'website_nama')
            ->value('value') ?? ''));

        if ($websiteName !== '') {
            return $websiteName;
        }

        return (string) config('app.name', 'Absensindo');
    }

    /**
     * @param  array{
     *     label?:string,
     *     context?:array{
     *         nama?:string,
     *         nisn?:string,
     *         kelas?:string,
     *         no_hp?:string,
     *         jenis_kelamin?:string,
     *         tanggal_lahir?:string,
     *         agama?:string,
     *         nama_ayah?:string,
     *         nama_ibu?:string,
     *         nama_orang_tua?:string,
     *         alamat?:string
     *     }
     * }  $recipient
     * @return array<string, string>
     */
    protected static function buildBroadcastRecipientContext(array $recipient): array
    {
        $context = is_array($recipient['context'] ?? null) ? $recipient['context'] : [];

        $nama = trim((string) ($context['nama'] ?? ''));
        $nisn = trim((string) ($context['nisn'] ?? ''));
        $kelas = trim((string) ($context['kelas'] ?? ''));
        $noHp = trim((string) ($context['no_hp'] ?? $recipient['phone'] ?? ''));
        $label = trim((string) ($recipient['label'] ?? ''));
        $jenisKelamin = trim((string) ($context['jenis_kelamin'] ?? ''));
        $tanggalLahir = trim((string) ($context['tanggal_lahir'] ?? ''));
        $agama = trim((string) ($context['agama'] ?? ''));
        $namaAyah = trim((string) ($context['nama_ayah'] ?? ''));
        $namaIbu = trim((string) ($context['nama_ibu'] ?? ''));
        $namaOrangTua = trim((string) ($context['nama_orang_tua'] ?? ''));
        $alamat = trim((string) ($context['alamat'] ?? ''));

        return [
            'nama' => $nama,
            'nisn' => $nisn,
            'kelas' => $kelas,
            'no_hp' => $noHp,
            'siswa_label' => $label,
            'jenis_kelamin' => $jenisKelamin,
            'tanggal_lahir' => $tanggalLahir,
            'agama' => $agama,
            'nama_ayah' => $namaAyah,
            'nama_ibu' => $namaIbu,
            'nama_orang_tua' => $namaOrangTua,
            'alamat' => $alamat,
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    protected static function renderBroadcastMessageTemplate(string $template, array $context): string
    {
        $removedKeys = ['siswa_id', 'tanggal_lahir_iso', 'tanggal_iso', 'timestamp'];
        $rendered = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($context, $removedKeys): string {
            $key = strtolower(trim((string) ($matches[1] ?? '')));
            if ($key === '') {
                return (string) ($matches[0] ?? '');
            }

            if (!array_key_exists($key, $context)) {
                if (in_array($key, $removedKeys, true)) {
                    return '';
                }

                return (string) ($matches[0] ?? '');
            }

            return (string) ($context[$key] ?? '');
        }, $template);

        return $rendered !== null ? $rendered : $template;
    }

    protected function formatSiswaLabel(Siswa $siswa): string
    {
        $name = trim((string) ($siswa->nama ?? ''));
        $nisn = trim((string) ($siswa->nisn ?? ''));
        $kelas = trim((string) ($siswa->kelas ?? ''));

        $segments = [];
        if ($name !== '') {
            $segments[] = $name;
        }
        if ($nisn !== '') {
            $segments[] = 'NISN: ' . $nisn;
        }
        if ($kelas !== '') {
            $segments[] = 'Kelas ' . $kelas;
        }

        return implode(' - ', $segments);
    }
}
