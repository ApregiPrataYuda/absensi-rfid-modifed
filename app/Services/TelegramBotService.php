<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\IzinSakitRequest;
use App\Models\Konfigurasi;
use App\Models\Siswa;
use App\Models\TelegramChatLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramBotService
{
    protected ?array $settingsCache = null;

    /**
     * @return array<string, mixed>
     */
    public function getSettings(bool $withSensitive = false): array
    {
        if ($this->settingsCache === null) {
            $defaults = [
                'telegram_bot_token' => '',
                'telegram_bot_id' => '',
                'telegram_bot_username' => '',
                'telegram_webhook_url' => '',
                'telegram_webhook_secret' => '',
                'telegram_webhook_status' => 'disabled',
                'telegram_webhook_last_error' => '',
                'telegram_notif_attendance_enabled' => '0',
                'telegram_notif_izin_sakit_enabled' => '0',
                'telegram_template_hadir' => 'Halo {nama}, absensi hari ini: HADIR pada {tanggal} {jam}.',
                'telegram_template_terlambat' => 'Halo {nama}, absensi hari ini: TERLAMBAT pada {tanggal} {jam}.',
                'telegram_template_pulang' => 'Halo {nama}, absensi pulang hari ini tercatat pada {tanggal} {jam}.',
                'telegram_template_pulang_cepat' => 'Halo {nama}, hari ini tercatat PULANG CEPAT pada {tanggal} {jam}.',
                'telegram_template_izin_sakit_diajukan' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} sudah diterima dan menunggu persetujuan.',
                'telegram_template_izin_sakit_disetujui' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} sudah disetujui.',
                'telegram_template_izin_sakit_ditolak' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} ditolak.',
            ];

            $rows = Konfigurasi::query()
                ->whereIn('key', array_keys($defaults))
                ->pluck('value', 'key')
                ->all();

            $settings = array_merge($defaults, $rows);
            $token = $this->decryptTokenValue($settings['telegram_bot_token'] ?? null);
            $settings['telegram_bot_token'] = $token ?? '';
            $settings['telegram_notif_attendance_enabled'] = (string) ($settings['telegram_notif_attendance_enabled'] ?? '0') === '1';
            $settings['telegram_notif_izin_sakit_enabled'] = (string) ($settings['telegram_notif_izin_sakit_enabled'] ?? '0') === '1';
            $settings['telegram_bot_token_configured'] = $token !== null && $token !== '';
            $settings['telegram_bot_token_masked'] = $this->maskToken($token);
            $settings['telegram_start_link'] = trim((string) ($settings['telegram_bot_username'] ?? '')) !== ''
                ? 'https://t.me/'.trim((string) $settings['telegram_bot_username']).'?start='
                : '';
            $settings['telegram_webhook_url_expected'] = trim((string) ($settings['telegram_webhook_secret'] ?? '')) !== ''
                ? $this->buildWebhookUrl((string) $settings['telegram_webhook_secret'])
                : '';
            $settings['telegram_linked_students_count'] = trim((string) ($settings['telegram_bot_id'] ?? '')) !== ''
                ? TelegramChatLink::query()
                    ->where('telegram_bot_id', trim((string) $settings['telegram_bot_id']))
                    ->where('is_active', true)
                    ->count()
                : 0;

            $this->settingsCache = $settings;
        }

        $settings = $this->settingsCache;
        if (! $withSensitive) {
            $settings['telegram_bot_token'] = '';
        }

        return $settings;
    }

    public function clearSettingsCache(): void
    {
        $this->settingsCache = null;
    }

    public function generateWebhookSecret(): string
    {
        return Str::random(40);
    }

    public function buildWebhookUrl(string $secret): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl.'/api/telegram/webhook/'.$secret;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateBotToken(string $token): array
    {
        return $this->request($token, 'getMe');
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(string $token): array
    {
        return $this->request($token, 'getWebhookInfo');
    }

    /**
     * @return array<string, mixed>
     */
    public function clearWebhook(string $token): array
    {
        return $this->request($token, 'setWebhook', [
            'url' => '',
            'drop_pending_updates' => 'false',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $token, string $secret): array
    {
        $url = $this->buildWebhookUrl($secret);
        $result = $this->request($token, 'setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_UNICODE),
            'drop_pending_updates' => 'true',
        ]);

        if (! ($result['success'] ?? false)) {
            return $result + ['webhook_url' => $url];
        }

        $info = $this->getWebhookInfo($token);

        return $result + [
            'webhook_url' => $url,
            'webhook_info' => $info['data'] ?? null,
        ];
    }

    /**
     * @return array<int, array{command: string, description: string}>
     */
    public function getDefaultBotCommands(): array
    {
        return [
            ['command' => 'start', 'description' => 'Hubungkan akun Telegram ke data siswa'],
            ['command' => 'cek', 'description' => 'Lihat daftar siswa yang tertaut'],
            ['command' => 'profil', 'description' => 'Lihat profil siswa tertaut dengan /profil NISN'],
            ['command' => 'absensi', 'description' => 'Lihat absensi siswa tertaut dengan /absensi NISN'],
            ['command' => 'izin', 'description' => 'Lihat izin siswa tertaut dengan /izin NISN'],
            ['command' => 'hapus', 'description' => 'Copot satu tautan dengan format /hapus NISN'],
            ['command' => 'hapussemua', 'description' => 'Copot semua tautan dari akun ini'],
            ['command' => 'menu', 'description' => 'Tampilkan panduan perintah bot'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncDefaultBotCommands(string $token): array
    {
        return $this->request($token, 'setMyCommands', [
            'commands' => json_encode($this->getDefaultBotCommands(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'scope' => json_encode([
                'type' => 'all_private_chats',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendTestMessage(string $chatId, string $message): array
    {
        $settings = $this->getSettings(true);
        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Token bot Telegram belum dikonfigurasi.',
            ];
        }

        return $this->sendMessageToChatId($chatId, $message, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMessageToChatId(int|string $chatId, string $message, ?string $token = null): array
    {
        $token = trim((string) ($token ?? ''));
        if ($token === '') {
            $settings = $this->getSettings(true);
            $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        }

        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Token bot Telegram belum tersedia.',
            ];
        }

        $message = trim($message);
        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Pesan Telegram tidak boleh kosong.',
            ];
        }

        return $this->request($token, 'sendMessage', [
            'chat_id' => (string) $chatId,
            'text' => $message,
        ]);
    }

    public function notifyAttendance(Siswa $siswa, array $context = []): bool
    {
        $settings = $this->getSettings(true);
        if (! ($settings['telegram_notif_attendance_enabled'] ?? false)) {
            return false;
        }

        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        $botId = trim((string) ($settings['telegram_bot_id'] ?? ''));
        if ($token === '' || $botId === '') {
            return false;
        }

        $links = TelegramChatLink::query()
            ->where('siswa_id', (int) $siswa->id)
            ->where('telegram_bot_id', $botId)
            ->where('is_active', true)
            ->orderByDesc('linked_at')
            ->get();

        if ($links->isEmpty()) {
            return false;
        }

        $message = $this->buildAttendanceMessage($settings, $siswa, $context);
        if (trim($message) === '') {
            return false;
        }

        $sent = false;
        foreach ($links as $link) {
            $result = $this->sendMessageToChatId((string) $link->telegram_chat_id, $message, $token);
            if ($result['success'] ?? false) {
                $sent = true;
                continue;
            }

            Log::warning('Telegram attendance notification failed', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'nisn' => (string) ($siswa->nisn ?? ''),
                'chat_id' => (string) $link->telegram_chat_id,
                'message' => (string) ($result['message'] ?? 'Unknown error'),
            ]);
        }

        return $sent;
    }

    public function notifyIzinSakit(Siswa $siswa, array $context = []): bool
    {
        $settings = $this->getSettings(true);
        if (! ($settings['telegram_notif_izin_sakit_enabled'] ?? false)) {
            return false;
        }

        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        $botId = trim((string) ($settings['telegram_bot_id'] ?? ''));
        if ($token === '' || $botId === '') {
            return false;
        }

        $links = TelegramChatLink::query()
            ->where('siswa_id', (int) $siswa->id)
            ->where('telegram_bot_id', $botId)
            ->where('is_active', true)
            ->orderByDesc('linked_at')
            ->get();

        if ($links->isEmpty()) {
            return false;
        }

        $message = $this->buildIzinSakitMessage($settings, $siswa, $context);
        if (trim($message) === '') {
            return false;
        }

        $sent = false;
        foreach ($links as $link) {
            $result = $this->sendMessageToChatId((string) $link->telegram_chat_id, $message, $token);
            if ($result['success'] ?? false) {
                $sent = true;
                continue;
            }

            Log::warning('Telegram izin/sakit notification failed', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'nisn' => (string) ($siswa->nisn ?? ''),
                'chat_id' => (string) $link->telegram_chat_id,
                'event' => (string) ($context['event'] ?? ''),
                'message' => (string) ($result['message'] ?? 'Unknown error'),
            ]);
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhookUpdate(array $payload): array
    {
        $message = data_get($payload, 'message');
        if (! is_array($message)) {
            return ['handled' => false];
        }

        $text = trim((string) data_get($message, 'text', ''));
        if ($text === '') {
            return ['handled' => false];
        }

        $chatId = $this->normalizeTelegramIdentifier(data_get($message, 'chat.id'));
        $chatType = strtolower(trim((string) data_get($message, 'chat.type', '')));
        $fromId = $this->normalizeTelegramIdentifier(data_get($message, 'from.id'));
        if ($chatId === null) {
            return ['handled' => false];
        }

        if ($chatType !== 'private') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Silakan hubungkan bot melalui chat pribadi. Setelah itu kirim /start NISN.',
            ];
        }

        $settings = $this->getSettings(true);
        $botId = trim((string) ($settings['telegram_bot_id'] ?? ''));

        if (! preg_match('/^\/([a-z_]+)(?:@\w+)?(?:\s+(.+))?$/i', $text, $matches)) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => $this->buildHelpMessage('Perintah tidak dikenali.'),
            ];
        }

        $command = strtolower(trim((string) ($matches[1] ?? '')));
        $argument = trim((string) ($matches[2] ?? ''));

        return match ($command) {
            'start', 'daftar' => $this->handleLinkCommand($chatId, $fromId, $message, $botId, $argument),
            'cek', 'tertaut', 'status' => $this->handleCheckLinkedStudentsCommand($chatId, $botId),
            'copot', 'hapus' => $this->handleUnlinkStudentCommand($chatId, $botId, $argument),
            'copotsemua', 'hapussemua' => $this->handleUnlinkAllStudentsCommand($chatId, $botId),
            'profil' => $this->handleStudentProfileCommand($chatId, $botId, $argument),
            'absensi' => $this->handleStudentAttendanceCommand($chatId, $botId, $argument),
            'izin' => $this->handleStudentIzinCommand($chatId, $botId, $argument),
            'help', 'menu', 'panduan' => [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => $this->buildHelpMessage(),
            ],
            default => [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => $this->buildHelpMessage('Perintah /'.$command.' tidak tersedia.'),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function handleLinkCommand(string $chatId, ?string $fromId, array $message, string $botId, string $nisn): array
    {
        if ($nisn === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => $this->buildHelpMessage('Format salah. Gunakan /start NISN.'),
            ];
        }

        if ($botId === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Bot Telegram belum siap. Silakan coba lagi beberapa saat.',
            ];
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();
        if (! $siswa) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'NISN tidak ditemukan. Silakan periksa kembali atau hubungi admin sekolah.',
            ];
        }

        DB::transaction(function () use ($botId, $chatId, $fromId, $message, $siswa): void {
            $link = TelegramChatLink::query()->firstOrNew([
                'telegram_bot_id' => $botId,
                'siswa_id' => (int) $siswa->id,
            ]);

            $link->fill([
                'nisn_snapshot' => (string) ($siswa->nisn ?? ''),
                'telegram_chat_id' => $chatId,
                'telegram_user_id' => $fromId,
                'telegram_username' => $this->normalizeNullableString(data_get($message, 'from.username')),
                'telegram_first_name' => $this->normalizeNullableString(data_get($message, 'from.first_name')),
                'telegram_last_name' => $this->normalizeNullableString(data_get($message, 'from.last_name')),
                'chat_type' => 'private',
                'linked_at' => Carbon::now(),
                'last_interaction_at' => Carbon::now(),
                'is_active' => true,
            ]);

            $link->save();
        });

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => 'Akun Telegram berhasil dihubungkan ke data siswa '.$siswa->nama.' ('.$siswa->nisn.'). Jika ada siswa lain dalam keluarga yang ingin ditautkan ke akun Telegram ini, kirim lagi /start NISN siswa tersebut.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleCheckLinkedStudentsCommand(string $chatId, string $botId): array
    {
        if ($botId === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Bot Telegram belum siap. Silakan coba lagi beberapa saat.',
            ];
        }

        $links = TelegramChatLink::query()
            ->with(['siswa:id,nama,nisn,kelas'])
            ->where('telegram_bot_id', $botId)
            ->where('telegram_chat_id', $chatId)
            ->where('is_active', true)
            ->orderBy('linked_at')
            ->get();

        if ($links->isEmpty()) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Belum ada siswa yang terhubung ke akun Telegram ini.'."\n\n".$this->buildHelpMessage(),
            ];
        }

        $lines = ['Siswa Tertaut'];
        foreach ($links as $index => $link) {
            $siswa = $link->siswa;
            $nama = $this->displayValue($siswa?->nama ?? null);
            $nisn = $this->displayValue($siswa?->nisn ?? $link->nisn_snapshot ?? null);
            $kelas = $this->displayValue($siswa?->kelas ?? null);
            $lines[] = ($index + 1).'. '.$nama.' | NISN '.$nisn.' | Kelas '.$kelas;
        }

        $lines[] = '';
        $lines[] = 'Gunakan /hapus NISN untuk melepas satu tautan.';

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => implode("\n", $lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleUnlinkStudentCommand(string $chatId, string $botId, string $nisn): array
    {
        if ($nisn === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Format salah. Gunakan /hapus NISN. Contoh: /hapus 2432500012',
            ];
        }

        if ($botId === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Bot Telegram belum siap. Silakan coba lagi beberapa saat.',
            ];
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();
        if (! $siswa) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'NISN tidak ditemukan. Silakan periksa kembali NISN yang ingin dicopot.',
            ];
        }

        $link = TelegramChatLink::query()
            ->where('telegram_bot_id', $botId)
            ->where('telegram_chat_id', $chatId)
            ->where('siswa_id', (int) $siswa->id)
            ->where('is_active', true)
            ->first();

        if (! $link) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Data siswa '.$siswa->nama.' ('.$siswa->nisn.') tidak sedang tertaut ke akun Telegram ini.',
            ];
        }

        $link->forceFill([
            'is_active' => false,
            'last_interaction_at' => Carbon::now(),
        ])->save();

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => 'Tautan Telegram untuk siswa '.$siswa->nama.' ('.$siswa->nisn.') berhasil dicopot. Jika ingin menghubungkan lagi, kirim /start '.$siswa->nisn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleUnlinkAllStudentsCommand(string $chatId, string $botId): array
    {
        if ($botId === '') {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Bot Telegram belum siap. Silakan coba lagi beberapa saat.',
            ];
        }

        $count = TelegramChatLink::query()
            ->where('telegram_bot_id', $botId)
            ->where('telegram_chat_id', $chatId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'last_interaction_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        if ($count < 1) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Tidak ada tautan siswa aktif pada akun Telegram ini.',
            ];
        }

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => 'Semua tautan siswa pada akun Telegram ini berhasil dicopot. Total tautan yang dilepas: '.$count.'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleStudentProfileCommand(string $chatId, string $botId, string $nisn): array
    {
        $linkedStudent = $this->findLinkedStudentByChatAndNisn($chatId, $botId, $nisn, 'profil');
        if (($linkedStudent['success'] ?? false) !== true) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => (string) ($linkedStudent['message'] ?? 'Data siswa tidak ditemukan.'),
            ];
        }

        /** @var Siswa $siswa */
        $siswa = $linkedStudent['siswa'];

        $lines = [
            'Profil Siswa',
            $this->buildStudentIdentityLine($siswa),
            '',
            'Kelas: '.$this->displayValue($siswa->kelas ?? null),
            'Jenis kelamin: '.$this->displayValue($siswa->jenis_kelamin ?? null),
            'Tanggal lahir: '.$this->formatDateValue($siswa->tanggal_lahir),
            'No. HP: '.$this->displayValue($siswa->no_hp ?? null),
            'Alamat: '.$this->displayValue($siswa->alamat ?? null),
        ];

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => implode("\n", $lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleStudentAttendanceCommand(string $chatId, string $botId, string $nisn): array
    {
        $linkedStudent = $this->findLinkedStudentByChatAndNisn($chatId, $botId, $nisn, 'absensi');
        if (($linkedStudent['success'] ?? false) !== true) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => (string) ($linkedStudent['message'] ?? 'Data siswa tidak ditemukan.'),
            ];
        }

        /** @var Siswa $siswa */
        $siswa = $linkedStudent['siswa'];
        $latestAttendance = Absensi::query()
            ->where('siswa_id', (int) $siswa->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('updated_at')
            ->first();

        if (! $latestAttendance) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Belum ada data absensi untuk '.$this->buildStudentIdentityLine($siswa).'.',
            ];
        }

        $lines = [
            'Absensi Terakhir',
            $this->buildStudentIdentityLine($siswa),
            '',
            'Tanggal: '.$this->formatDateValue($latestAttendance->tanggal),
            'Status: '.$this->displayValue($latestAttendance->status ?? null),
            'Jam datang: '.$this->displayValue($latestAttendance->jam_datang ?? null),
            'Jam pulang: '.$this->displayValue($latestAttendance->jam_pulang ?? null),
            'Keterangan: '.$this->displayValue($latestAttendance->keterangan ?? null),
        ];

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => implode("\n", $lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function handleStudentIzinCommand(string $chatId, string $botId, string $nisn): array
    {
        $linkedStudent = $this->findLinkedStudentByChatAndNisn($chatId, $botId, $nisn, 'izin');
        if (($linkedStudent['success'] ?? false) !== true) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => (string) ($linkedStudent['message'] ?? 'Data siswa tidak ditemukan.'),
            ];
        }

        /** @var Siswa $siswa */
        $siswa = $linkedStudent['siswa'];
        $latestRequest = IzinSakitRequest::query()
            ->with('approvedBy:id,name')
            ->where('siswa_id', (int) $siswa->id)
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('created_at')
            ->first();

        if (! $latestRequest) {
            return [
                'handled' => true,
                'chat_id' => $chatId,
                'reply' => 'Belum ada data izin/sakit untuk '.$this->buildStudentIdentityLine($siswa).'.',
            ];
        }

        $tanggalMulai = $this->formatDateValue($latestRequest->tanggal_mulai);
        $tanggalSelesai = $this->formatDateValue($latestRequest->tanggal_selesai);

        $rentangTanggal = $tanggalMulai === $tanggalSelesai
            ? $tanggalMulai
            : $tanggalMulai.' s/d '.$tanggalSelesai;

        $statusLabel = match (strtolower(trim((string) ($latestRequest->status ?? '')))) {
            IzinSakitRequest::STATUS_APPROVED => 'Disetujui',
            IzinSakitRequest::STATUS_REJECTED => 'Ditolak',
            default => 'Pending',
        };

        $jenisLabel = strtolower(trim((string) ($latestRequest->jenis ?? ''))) === IzinSakitRequest::JENIS_SAKIT
            ? 'Sakit'
            : 'Izin';

        $lines = [
            'Izin/Sakit Terakhir',
            $this->buildStudentIdentityLine($siswa),
            '',
            'Jenis: '.$jenisLabel,
            'Tanggal: '.$rentangTanggal,
            'Status: '.$statusLabel,
            'Alasan: '.$this->displayValue($latestRequest->alasan ?? null),
            'Catatan: '.$this->displayValue($latestRequest->approval_note ?? null),
            'Diproses oleh: '.$this->displayValue($latestRequest->approvedBy?->name ?? null),
        ];

        return [
            'handled' => true,
            'chat_id' => $chatId,
            'reply' => implode("\n", $lines),
        ];
    }

    /**
     * @return array{success: bool, siswa?: Siswa, message?: string}
     */
    protected function findLinkedStudentByChatAndNisn(string $chatId, string $botId, string $nisn, string $commandName): array
    {
        $nisn = trim($nisn);
        if ($nisn === '') {
            return [
                'success' => false,
                'message' => 'Format salah. Gunakan /'.$commandName.' NISN. Contoh: /'.$commandName.' 2432500012',
            ];
        }

        if ($botId === '') {
            return [
                'success' => false,
                'message' => 'Bot Telegram belum siap. Silakan coba lagi beberapa saat.',
            ];
        }

        $link = TelegramChatLink::query()
            ->with('siswa')
            ->where('telegram_bot_id', $botId)
            ->where('telegram_chat_id', $chatId)
            ->where('is_active', true)
            ->whereHas('siswa', fn ($query) => $query->where('nisn', $nisn))
            ->first();

        if (! $link || ! $link->siswa) {
            return [
                'success' => false,
                'message' => 'NISN '.$nisn.' tidak tertaut ke akun Telegram ini.',
            ];
        }

        return [
            'success' => true,
            'siswa' => $link->siswa,
        ];
    }

    protected function buildStudentIdentityLine(Siswa $siswa): string
    {
        return $this->displayValue($siswa->nama ?? null).' | NISN '.$this->displayValue($siswa->nisn ?? null);
    }

    protected function displayValue($value): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : '-';
    }

    protected function formatDateValue($value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '-';
        }

        try {
            return Carbon::parse($text)->format('d-m-Y');
        } catch (\Throwable $e) {
            return $text;
        }
    }

    protected function buildHelpMessage(?string $prefix = null): string
    {
        $lines = [];
        if ($prefix !== null && trim($prefix) !== '') {
            $lines[] = trim($prefix);
            $lines[] = '';
        }

        $lines[] = 'Menu Bot Telegram';
        $lines[] = '1. /start NISN';
        $lines[] = '   Hubungkan akun Telegram ke data siswa.';
        $lines[] = '2. /cek';
        $lines[] = '   Lihat siswa yang tertaut ke akun ini.';
        $lines[] = '3. /profil NISN';
        $lines[] = '   Lihat profil siswa yang tertaut ke akun ini.';
        $lines[] = '4. /absensi NISN';
        $lines[] = '   Lihat absensi terakhir siswa yang tertaut.';
        $lines[] = '5. /izin NISN';
        $lines[] = '   Lihat izin/sakit terakhir siswa yang tertaut.';
        $lines[] = '6. /hapus NISN';
        $lines[] = '   Lepas satu tautan siswa tertentu.';
        $lines[] = '7. /hapussemua';
        $lines[] = '   Lepas semua tautan dari akun ini.';
        $lines[] = '8. /menu';
        $lines[] = '   Tampilkan panduan ini lagi.';
        $lines[] = '';
        $lines[] = 'Contoh:';
        $lines[] = '/start 2432500012';
        $lines[] = '/cek';
        $lines[] = '/profil 2432500012';
        $lines[] = '/absensi 2432500012';
        $lines[] = '/izin 2432500012';
        $lines[] = '/hapus 2432500012';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function buildAttendanceMessage(array $settings, Siswa $siswa, array $context): string
    {
        $keteranganRaw = trim((string) ($context['keterangan'] ?? ''));
        $keterangan = strtolower($keteranganRaw);
        $type = strtolower(trim((string) ($context['type'] ?? '')));
        $isPulangCepat = str_contains($keterangan, 'pulang cepat');

        $templateKey = 'telegram_template_hadir';
        if ($type === 'pulang') {
            $templateKey = $isPulangCepat
                ? 'telegram_template_pulang_cepat'
                : 'telegram_template_pulang';
        } elseif (str_contains($keterangan, 'terlambat')) {
            $templateKey = 'telegram_template_terlambat';
        }

        $template = trim((string) ($settings[$templateKey] ?? ''));
        if ($template === '') {
            $template = 'Halo {nama}, absensi: {status} pada {tanggal} {jam}.';
        }

        $tanggalRaw = (string) ($context['tanggal'] ?? Carbon::today()->toDateString());
        $jamRaw = trim((string) ($context['jam'] ?? ''));
        $status = trim((string) ($context['status'] ?? ''));

        if ($status === '') {
            $status = $type === 'pulang'
                ? ($isPulangCepat ? 'Pulang Cepat' : 'Pulang')
                : (str_contains($keterangan, 'terlambat') ? 'Terlambat' : 'Hadir');
        }

        $tanggal = $tanggalRaw;
        try {
            $tanggal = Carbon::parse($tanggalRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Gunakan nilai asal.
        }

        $jam = $jamRaw;
        if ($jamRaw !== '') {
            if (preg_match('/^\d{2}:\d{2}$/', $jamRaw) === 1) {
                $jam = $jamRaw.':00';
            } else {
                try {
                    $jam = Carbon::parse($jamRaw)->format('H:i:s');
                } catch (\Throwable $e) {
                    // Gunakan nilai asal.
                }
            }
        }

        $placeholders = $this->buildStudentPlaceholderMap($siswa, [
            '{tanggal}' => $tanggal,
            '{jam}' => $jam,
            '{waktu}' => $jam,
            '{tanggal_jam}' => trim($tanggal . ($jam !== '' ? ' ' . $jam : '')),
            '{status}' => $status,
            '{keterangan}' => $keteranganRaw !== '' ? $keteranganRaw : $status,
        ]);

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function buildIzinSakitMessage(array $settings, Siswa $siswa, array $context): string
    {
        $event = strtolower(trim((string) ($context['event'] ?? 'created')));
        $templateKey = match ($event) {
            'approved' => 'telegram_template_izin_sakit_disetujui',
            'rejected' => 'telegram_template_izin_sakit_ditolak',
            default => 'telegram_template_izin_sakit_diajukan',
        };

        $defaultTemplate = match ($event) {
            'approved' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} disetujui.',
            'rejected' => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} ditolak.',
            default => 'Halo {nama}, pengajuan {jenis} untuk {rentang_tanggal} sudah diterima dan menunggu persetujuan.',
        };

        $template = trim((string) ($settings[$templateKey] ?? ''));
        if ($template === '') {
            $template = $defaultTemplate;
        }

        $jenisRaw = strtolower(trim((string) ($context['jenis'] ?? 'izin')));
        $jenis = $jenisRaw === 'sakit' ? 'Sakit' : 'Izin';
        $status = match ($event) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Pending',
        };

        $tanggalMulaiRaw = (string) ($context['tanggal_mulai'] ?? Carbon::today()->toDateString());
        $tanggalSelesaiRaw = (string) ($context['tanggal_selesai'] ?? $tanggalMulaiRaw);
        $tanggalMulai = $tanggalMulaiRaw;
        $tanggalSelesai = $tanggalSelesaiRaw;

        try {
            $tanggalMulai = Carbon::parse($tanggalMulaiRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Gunakan nilai asal.
        }

        try {
            $tanggalSelesai = Carbon::parse($tanggalSelesaiRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Gunakan nilai asal.
        }

        $rentangTanggal = $tanggalMulai === $tanggalSelesai
            ? $tanggalMulai
            : ($tanggalMulai.' s/d '.$tanggalSelesai);

        $alasan = trim((string) ($context['alasan'] ?? ''));
        $catatan = trim((string) ($context['approval_note'] ?? ''));
        $disetujuiOleh = trim((string) ($context['approved_by'] ?? ''));

        $placeholders = $this->buildStudentPlaceholderMap($siswa, [
            '{jenis}' => $jenis,
            '{status}' => $status,
            '{tanggal_mulai}' => $tanggalMulai,
            '{tanggal_selesai}' => $tanggalSelesai,
            '{rentang_tanggal}' => $rentangTanggal,
            '{alasan}' => $alasan !== '' ? $alasan : '-',
            '{catatan}' => $catatan !== '' ? $catatan : '-',
            '{disetujui_oleh}' => $disetujuiOleh !== '' ? $disetujuiOleh : '-',
        ]);

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function buildStudentPlaceholderMap(Siswa $siswa, array $extra = []): array
    {
        $nama = trim((string) ($siswa->nama ?? ''));
        $nisn = trim((string) ($siswa->nisn ?? ''));
        $kelas = trim((string) ($siswa->kelas ?? ''));
        $namaAyah = trim((string) ($siswa->nama_ayah ?? ''));
        $namaIbu = trim((string) ($siswa->nama_ibu ?? ''));

        $labelParts = array_values(array_filter([
            $nama,
            $nisn !== '' ? 'NISN: ' . $nisn : '',
            $kelas !== '' ? 'Kelas ' . $kelas : '',
        ], fn (string $value): bool => $value !== ''));

        return $extra + [
            '{nama}' => $nama,
            '{nisn}' => $nisn,
            '{kelas}' => $kelas,
            '{no_hp}' => trim((string) ($siswa->no_hp ?? '')),
            '{jenis_kelamin}' => trim((string) ($siswa->jenis_kelamin ?? '')),
            '{tanggal_lahir}' => $this->formatDatePlaceholderValue($siswa->tanggal_lahir ?? null),
            '{agama}' => trim((string) ($siswa->agama ?? '')),
            '{nama_ayah}' => $namaAyah,
            '{nama_ibu}' => $namaIbu,
            '{nama_orang_tua}' => implode(' / ', array_values(array_filter([$namaAyah, $namaIbu], fn (string $value): bool => $value !== ''))),
            '{alamat}' => trim((string) ($siswa->alamat ?? '')),
            '{siswa_label}' => implode(' - ', $labelParts),
            '{website_name}' => $this->resolveWebsiteName(),
            '{app_name}' => (string) config('app.name', 'Absensindo'),
        ];
    }

    protected function formatDatePlaceholderValue(mixed $value): string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d-m-Y');
            }

            $raw = trim((string) ($value ?? ''));
            if ($raw === '') {
                return '';
            }

            return Carbon::parse($raw)->format('d-m-Y');
        } catch (\Throwable $e) {
            return trim((string) ($value ?? ''));
        }
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
     * @return array<string, mixed>
     */
    protected function request(string $token, string $method, array $payload = []): array
    {
        $token = trim($token);
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Token bot Telegram kosong.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);

            $body = $response->json();
            if (! is_array($body)) {
                $body = [];
            }

            if ($response->successful() && ($body['ok'] ?? false) === true) {
                return [
                    'success' => true,
                    'data' => $body['result'] ?? null,
                    'message' => (string) ($body['description'] ?? ''),
                    'status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'data' => $body['result'] ?? null,
                'message' => (string) ($body['description'] ?? 'Telegram API request failed.'),
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function decryptTokenValue(?string $value): ?string
    {
        $value = $this->normalizeNullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return str_contains($value, ':') ? $value : null;
        }
    }

    protected function maskToken(?string $token): string
    {
        $token = $this->normalizeNullableString($token);
        if ($token === null) {
            return '';
        }

        if (strlen($token) <= 10) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 6).str_repeat('*', max(4, strlen($token) - 10)).substr($token, -4);
    }

    protected function normalizeTelegramIdentifier($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_string($value)) {
            $text = trim((string) $value);
            if ($text === '') {
                return null;
            }

            return preg_match('/^-?\d+$/', $text) === 1 ? $text : null;
        }

        if (is_float($value)) {
            return number_format($value, 0, '', '');
        }

        return null;
    }

    protected function normalizeNullableString($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
