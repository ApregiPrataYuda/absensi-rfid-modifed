<?php

namespace App\Services;

use App\Models\Konfigurasi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGatewayService
{
    protected ?array $settingsCache = null;

    public function sendCustomMessage(string $recipientRaw, string $messageRaw): array
    {
        $settings = $this->getSettings();
        $baseUrl = trim((string) ($settings['wa_gateway_base_url'] ?? ''));
        if ($baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Base URL gateway belum dikonfigurasi.',
            ];
        }

        $recipient = $this->normalizePhone($recipientRaw);
        if ($recipient === null) {
            return [
                'success' => false,
                'message' => 'Nomor penerima tidak valid.',
            ];
        }

        $message = trim($messageRaw);
        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Pesan tidak boleh kosong.',
            ];
        }

        $payload = $this->buildPayload($settings, $recipient, $message);
        if (count($payload) === 0) {
            return [
                'success' => false,
                'message' => 'Payload gateway kosong. Periksa konfigurasi parameter.',
            ];
        }

        $success = $this->dispatchRequest($settings, $baseUrl, $payload);
        if (!$success) {
            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan tes. Periksa konfigurasi gateway.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Pesan tes berhasil dikirim.',
        ];
    }

    public function notifyAttendance(Siswa $siswa, array $context = []): bool
    {
        $settings = $this->getSettings();
        if (!$settings['wa_notif_attendance_enabled']) {
            return false;
        }

        $baseUrl = trim((string) ($settings['wa_gateway_base_url'] ?? ''));
        if ($baseUrl === '') {
            return false;
        }

        $recipients = $this->resolveRecipients($siswa, (string) ($settings['wa_notif_target'] ?? 'siswa'));
        if (count($recipients) === 0) {
            return false;
        }

        $message = $this->buildAttendanceMessage($settings, $siswa, $context);
        if (trim($message) === '') {
            return false;
        }

        $sent = false;
        foreach ($recipients as $recipient) {
            $payload = $this->buildPayload($settings, $recipient, $message);
            if (count($payload) === 0) {
                continue;
            }

            $sent = $this->dispatchRequest($settings, $baseUrl, $payload) || $sent;
        }

        return $sent;
    }

    public function notifyIzinSakit(Siswa $siswa, array $context = []): bool
    {
        $settings = $this->getSettings();
        if (!$settings['wa_notif_izin_sakit_enabled']) {
            Log::info('WA izin/sakit skipped: notification disabled', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'event' => (string) ($context['event'] ?? ''),
            ]);
            return false;
        }

        $baseUrl = trim((string) ($settings['wa_gateway_base_url'] ?? ''));
        if ($baseUrl === '') {
            Log::warning('WA izin/sakit skipped: gateway base URL empty', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'event' => (string) ($context['event'] ?? ''),
            ]);
            return false;
        }

        $recipients = $this->resolveRecipients($siswa, 'siswa');
        if (count($recipients) === 0) {
            Log::warning('WA izin/sakit skipped: recipient not found', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'event' => (string) ($context['event'] ?? ''),
                'siswa_no_hp' => (string) ($siswa->no_hp ?? ''),
            ]);
            return false;
        }

        $message = $this->buildIzinSakitMessage($settings, $siswa, $context);
        if (trim($message) === '') {
            Log::warning('WA izin/sakit skipped: empty message template result', [
                'siswa_id' => (int) ($siswa->id ?? 0),
                'event' => (string) ($context['event'] ?? ''),
            ]);
            return false;
        }

        $sent = false;
        foreach ($recipients as $recipient) {
            $payload = $this->buildPayload($settings, $recipient, $message);
            if (count($payload) === 0) {
                Log::warning('WA izin/sakit skipped: payload empty', [
                    'siswa_id' => (int) ($siswa->id ?? 0),
                    'recipient' => (string) $recipient,
                    'event' => (string) ($context['event'] ?? ''),
                ]);
                continue;
            }

            $success = $this->dispatchRequest($settings, $baseUrl, $payload);
            if (!$success) {
                Log::warning('WA izin/sakit send failed', [
                    'siswa_id' => (int) ($siswa->id ?? 0),
                    'recipient' => (string) $recipient,
                    'event' => (string) ($context['event'] ?? ''),
                ]);
            }

            $sent = $success || $sent;
        }

        Log::info('WA izin/sakit send result', [
            'siswa_id' => (int) ($siswa->id ?? 0),
            'event' => (string) ($context['event'] ?? ''),
            'sent' => $sent,
            'recipient_count' => count($recipients),
        ]);

        return $sent;
    }

    public function notifyIzinSakitReviewer(string $recipientRaw, array $context = []): bool
    {
        $settings = $this->getSettings();
        if (!$settings['wa_notif_izin_sakit_reviewer_enabled']) {
            Log::info('WA izin/sakit reviewer skipped: notification disabled', [
                'recipient' => $recipientRaw,
                'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            ]);
            return false;
        }

        $baseUrl = trim((string) ($settings['wa_gateway_base_url'] ?? ''));
        if ($baseUrl === '') {
            Log::warning('WA izin/sakit reviewer skipped: gateway base URL empty', [
                'recipient' => $recipientRaw,
                'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            ]);
            return false;
        }

        $recipient = $this->normalizePhone($recipientRaw);
        if ($recipient === null) {
            Log::warning('WA izin/sakit reviewer skipped: invalid recipient phone', [
                'recipient' => $recipientRaw,
                'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            ]);
            return false;
        }

        $message = $this->buildIzinSakitReviewerMessage($context);
        if (trim($message) === '') {
            Log::warning('WA izin/sakit reviewer skipped: empty message', [
                'recipient' => $recipient,
                'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            ]);
            return false;
        }

        $payload = $this->buildPayload($settings, $recipient, $message);
        if (count($payload) === 0) {
            Log::warning('WA izin/sakit reviewer skipped: empty payload', [
                'recipient' => $recipient,
                'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            ]);
            return false;
        }

        $success = $this->dispatchRequest($settings, $baseUrl, $payload);
        Log::info('WA izin/sakit reviewer send result', [
            'recipient' => $recipient,
            'receiver_type' => (string) ($context['receiver_type'] ?? ''),
            'sent' => $success,
            'siswa_nama' => (string) ($context['siswa_nama'] ?? ''),
            'kelas' => (string) ($context['kelas'] ?? ''),
        ]);

        return $success;
    }

    protected function getSettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        $defaults = [
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
        ];

        $rows = Konfigurasi::query()
            ->whereIn('key', array_keys($defaults))
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
        if (strtolower(trim((string) ($settings['wa_notif_target'] ?? ''))) !== 'siswa') {
            $settings['wa_notif_target'] = 'siswa';
        }
        $settings['wa_notif_attendance_enabled'] = (string) ($settings['wa_notif_attendance_enabled'] ?? '0') === '1';
        $settings['wa_notif_izin_sakit_enabled'] = (string) ($settings['wa_notif_izin_sakit_enabled'] ?? '0') === '1';
        $settings['wa_notif_izin_sakit_reviewer_enabled'] = (string) ($settings['wa_notif_izin_sakit_reviewer_enabled'] ?? '0') === '1';
        $settings['wa_notif_enabled'] = $settings['wa_notif_attendance_enabled']
            || $settings['wa_notif_izin_sakit_enabled']
            || $settings['wa_notif_izin_sakit_reviewer_enabled'];
        $settings['wa_gateway_timeout'] = max(3, (int) ($settings['wa_gateway_timeout'] ?? 15));
        $settings['wa_gateway_body_type'] = trim((string) ($settings['wa_gateway_body_type'] ?? 'application/json'));

        $this->settingsCache = $settings;

        return $settings;
    }

    protected function resolveRecipients(Siswa $siswa, string $target): array
    {
        $target = strtolower(trim($target));
        if (!in_array($target, ['wali', 'siswa', 'keduanya'], true)) {
            $target = 'wali';
        }

        $recipients = [];

        if ($target === 'wali' || $target === 'keduanya') {
            $waliPhone = $this->normalizePhone((string) ($siswa->no_hp ?? ''));
            if ($waliPhone !== null) {
                $recipients[] = $waliPhone;
            }
        }

        if ($target === 'siswa' || $target === 'keduanya') {
            $siswaPhone = $this->normalizePhone((string) ($siswa->no_hp ?? ''));
            if ($siswaPhone !== null) {
                $recipients[] = $siswaPhone;
            }
        }

        return array_values(array_unique($recipients));
    }

    protected function normalizePhone(string $phone): ?string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }
        return $raw;
    }

    protected function buildAttendanceMessage(array $settings, Siswa $siswa, array $context): string
    {
        $keteranganRaw = trim((string) ($context['keterangan'] ?? ''));
        $keterangan = strtolower($keteranganRaw);
        $type = strtolower(trim((string) ($context['type'] ?? '')));
        $isAlpa = str_contains($keterangan, 'alpa');
        $isPulangCepat = str_contains($keterangan, 'pulang cepat');

        $templateKey = 'wa_template_hadir';
        if ($type === 'pulang') {
            $templateKey = $isPulangCepat
                ? 'wa_template_pulang_cepat'
                : 'wa_template_pulang';
        } elseif (str_contains($keterangan, 'terlambat')) {
            $templateKey = 'wa_template_terlambat';
        }

        $template = $isAlpa
            ? 'Halo {nama}, hari ini tercatat ALPA pada {tanggal}.'
            : trim((string) ($settings[$templateKey] ?? ''));
        if ($template === '') {
            $template = trim((string) ($settings['wa_template_hadir'] ?? ''));
        }
        if ($template === '') {
            $template = 'Halo {nama}, absensi: {status} pada {tanggal} {jam}.';
        }

        $tanggalRaw = (string) ($context['tanggal'] ?? Carbon::today()->toDateString());
        $jamRaw = trim((string) ($context['jam'] ?? ''));
        $status = trim((string) ($context['status'] ?? ''));
        if ($type === 'pulang' && ($status === '' || strtolower($status) === 'hadir')) {
            $status = $isPulangCepat ? 'Pulang Cepat' : 'Pulang';
        }

        if ($status === '') {
            if ($keterangan !== '') {
                $status = ucfirst($keterangan);
            } elseif ($type === 'pulang') {
                $status = 'Pulang';
            } else {
                $status = 'Hadir';
            }
        }

        $tanggal = $tanggalRaw;
        try {
            $tanggal = Carbon::parse($tanggalRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Pakai nilai original jika gagal parse.
        }
        $jam = $jamRaw;
        if ($jamRaw !== '') {
            if (preg_match('/^\d{2}:\d{2}$/', $jamRaw) === 1) {
                $jam = $jamRaw . ':00';
            } else {
                try {
                    $jam = Carbon::parse($jamRaw)->format('H:i:s');
                } catch (\Throwable $e) {
                    // Pakai nilai original jika gagal parse.
                }
            }
        }

        $map = $this->buildStudentPlaceholderMap($siswa, [
            '{tanggal}' => $tanggal,
            '{jam}' => $jam,
            '{waktu}' => $jam,
            '{tanggal_jam}' => trim($tanggal . ($jam !== '' ? ' ' . $jam : '')),
            '{status}' => $status,
            '{keterangan}' => $keteranganRaw !== '' ? $keteranganRaw : $status,
        ]);

        return str_replace(array_keys($map), array_values($map), $template);
    }

    protected function buildIzinSakitMessage(array $settings, Siswa $siswa, array $context): string
    {
        $event = strtolower(trim((string) ($context['event'] ?? 'created')));
        $templateKey = match ($event) {
            'approved' => 'wa_template_izin_sakit_disetujui',
            'rejected' => 'wa_template_izin_sakit_ditolak',
            default => 'wa_template_izin_sakit_diajukan',
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
            // Pakai nilai original jika gagal parse.
        }
        try {
            $tanggalSelesai = Carbon::parse($tanggalSelesaiRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Pakai nilai original jika gagal parse.
        }
        $rentangTanggal = $tanggalMulai === $tanggalSelesai
            ? $tanggalMulai
            : ($tanggalMulai . ' s/d ' . $tanggalSelesai);

        $alasan = trim((string) ($context['alasan'] ?? ''));
        $catatan = trim((string) ($context['approval_note'] ?? ''));
        $disetujuiOleh = trim((string) ($context['approved_by'] ?? ''));

        $map = $this->buildStudentPlaceholderMap($siswa, [
            '{jenis}' => $jenis,
            '{status}' => $status,
            '{tanggal_mulai}' => $tanggalMulai,
            '{tanggal_selesai}' => $tanggalSelesai,
            '{rentang_tanggal}' => $rentangTanggal,
            '{alasan}' => $alasan !== '' ? $alasan : '-',
            '{catatan}' => $catatan !== '' ? $catatan : '-',
            '{disetujui_oleh}' => $disetujuiOleh !== '' ? $disetujuiOleh : '-',
        ]);

        return str_replace(array_keys($map), array_values($map), $template);
    }

    protected function buildIzinSakitReviewerMessage(array $context): string
    {
        $settings = $this->getSettings();
        $receiverType = strtolower(trim((string) ($context['receiver_type'] ?? 'admin')));
        $recipientName = trim((string) ($context['recipient_name'] ?? ''));
        $siswaNama = trim((string) ($context['siswa_nama'] ?? '-'));
        $kelas = trim((string) ($context['kelas'] ?? '-'));
        $jenisRaw = strtolower(trim((string) ($context['jenis'] ?? 'izin')));
        $jenis = $jenisRaw === 'sakit' ? 'Sakit' : 'Izin';
        $alasan = trim((string) ($context['alasan'] ?? ''));
        if ($alasan === '') {
            $alasan = '-';
        }

        $tanggalMulaiRaw = (string) ($context['tanggal_mulai'] ?? Carbon::today()->toDateString());
        $tanggalSelesaiRaw = (string) ($context['tanggal_selesai'] ?? $tanggalMulaiRaw);
        $tanggalMulai = $tanggalMulaiRaw;
        $tanggalSelesai = $tanggalSelesaiRaw;
        try {
            $tanggalMulai = Carbon::parse($tanggalMulaiRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Pakai nilai original jika gagal parse.
        }
        try {
            $tanggalSelesai = Carbon::parse($tanggalSelesaiRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Pakai nilai original jika gagal parse.
        }
        $rentangTanggal = $tanggalMulai === $tanggalSelesai
            ? $tanggalMulai
            : ($tanggalMulai . ' s/d ' . $tanggalSelesai);

        $templateKey = in_array($receiverType, ['wakel', 'guru'], true)
            ? 'wa_template_izin_sakit_reviewer_wakel'
            : 'wa_template_izin_sakit_reviewer_admin';
        $template = trim((string) ($settings[$templateKey] ?? ''));

        if ($template === '') {
            $template = 'Halo {recipient_name}, ada pengajuan {jenis} baru dari {siswa_nama} (kelas {kelas}) untuk {rentang_tanggal}. Alasan: {alasan}. Mohon ditinjau.';
        }

        $receiverLabel = match ($receiverType) {
            'wakel' => 'wali kelas',
            'guru' => 'guru',
            default => 'admin',
        };

        $map = [
            '{recipient_name}' => $recipientName !== '' ? $recipientName : '-',
            '{receiver_type}' => $receiverLabel,
            '{siswa_nama}' => $siswaNama,
            '{jenis}' => $jenis,
            '{tanggal_mulai}' => $tanggalMulai,
            '{tanggal_selesai}' => $tanggalSelesai,
            '{rentang_tanggal}' => $rentangTanggal,
            '{alasan}' => $alasan,
        ] + $this->buildStudentContextPlaceholderMap($context);

        return str_replace(array_keys($map), array_values($map), $template);
    }

    protected function buildPayload(array $settings, string $recipient, string $message): array
    {
        $payload = [];

        for ($i = 1; $i <= 4; $i++) {
            $paramKey = trim((string) ($settings['wa_gateway_parameter_' . $i] ?? ''));
            if ($paramKey === '') {
                continue;
            }

            $rawValue = (string) ($settings['wa_gateway_value_' . $i] ?? '');
            $resolvedValue = $this->resolveDynamicValue($rawValue, $recipient, $message);
            // Support nested payload with dot notation, e.g. "data.message".
            Arr::set($payload, $paramKey, $resolvedValue);
        }

        // Fallback minimum payload agar gateway tetap menerima request.
        if (count($payload) === 0) {
            $payload = [
                'number' => $recipient,
                'message' => $message,
            ];
        }

        return $payload;
    }

    protected function resolveDynamicValue(string $rawValue, string $recipient, string $message): string
    {
        $value = str_ireplace(
            ['TUJUAN', 'PESAN', '{tujuan}', '{pesan}'],
            [$recipient, $message, $recipient, $message],
            $rawValue
        );

        return (string) $value;
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

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    protected function buildStudentContextPlaceholderMap(array $context, array $extra = []): array
    {
        $nama = trim((string) ($context['nama'] ?? $context['siswa_nama'] ?? ''));
        $nisn = trim((string) ($context['nisn'] ?? ''));
        $kelas = trim((string) ($context['kelas'] ?? ''));
        $namaAyah = trim((string) ($context['nama_ayah'] ?? ''));
        $namaIbu = trim((string) ($context['nama_ibu'] ?? ''));

        $labelParts = array_values(array_filter([
            $nama,
            $nisn !== '' ? 'NISN: ' . $nisn : '',
            $kelas !== '' ? 'Kelas ' . $kelas : '',
        ], fn (string $value): bool => $value !== ''));

        return $extra + [
            '{nama}' => $nama,
            '{nisn}' => $nisn,
            '{kelas}' => $kelas,
            '{no_hp}' => trim((string) ($context['no_hp'] ?? '')),
            '{jenis_kelamin}' => trim((string) ($context['jenis_kelamin'] ?? '')),
            '{tanggal_lahir}' => trim((string) ($context['tanggal_lahir'] ?? '')),
            '{agama}' => trim((string) ($context['agama'] ?? '')),
            '{nama_ayah}' => $namaAyah,
            '{nama_ibu}' => $namaIbu,
            '{nama_orang_tua}' => trim((string) ($context['nama_orang_tua'] ?? implode(' / ', array_values(array_filter([$namaAyah, $namaIbu], fn (string $value): bool => $value !== ''))))),
            '{alamat}' => trim((string) ($context['alamat'] ?? '')),
            '{siswa_label}' => trim((string) ($context['siswa_label'] ?? implode(' - ', $labelParts))),
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

    protected function dispatchRequest(array $settings, string $baseUrl, array $payload): bool
    {
        $bodyType = strtolower(trim((string) ($settings['wa_gateway_body_type'] ?? 'application/json')));
        $timeout = max(3, (int) ($settings['wa_gateway_timeout'] ?? 15));
        $authorization = trim((string) ($settings['wa_gateway_authorization'] ?? ''));
        $customHeaderKey = trim((string) ($settings['wa_gateway_header_key'] ?? ''));
        $customHeaderValue = trim((string) ($settings['wa_gateway_header_value'] ?? ''));

        $client = Http::timeout($timeout)->acceptJson();
        $headers = [];
        if ($authorization !== '') {
            $headers['Authorization'] = $authorization;
        }
        if ($customHeaderKey !== '' && $customHeaderValue !== '') {
            $headers[$customHeaderKey] = $customHeaderValue;
        }
        if (count($headers) > 0) {
            $client = $client->withHeaders($headers);
        }

        try {
            if ($bodyType === 'application/x-www-form-urlencoded') {
                $response = $client->asForm()->post($baseUrl, $payload);
            } elseif ($bodyType === 'multipart/form-data') {
                $multipart = [];
                foreach ($payload as $name => $value) {
                    $multipart[] = [
                        'name' => (string) $name,
                        'contents' => (string) $value,
                    ];
                }
                $response = $client->asMultipart()->post($baseUrl, $multipart);
            } elseif ($bodyType === 'text/plain') {
                $response = $client
                    ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'text/plain')
                    ->post($baseUrl);
            } else {
                $response = $client->asJson()->post($baseUrl, $payload);
            }

            if ($response->successful()) {
                return true;
            }

            Log::warning('WA gateway request failed', [
                'status' => $response->status(),
                'url' => $baseUrl,
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA gateway exception', [
                'message' => $e->getMessage(),
                'url' => $baseUrl,
            ]);
        }

        return false;
    }
}
