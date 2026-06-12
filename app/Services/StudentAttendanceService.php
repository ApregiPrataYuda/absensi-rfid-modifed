<?php

namespace App\Services;

use App\Jobs\SendTelegramAttendanceNotificationJob;
use App\Jobs\SendWaAttendanceNotificationJob;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\JadwalHarian;
use App\Models\Kelas;
use App\Models\Konfigurasi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StudentAttendanceService
{
    protected ?array $globalJamConfigCache = null;

    protected array $kelasIdByNameCache = [];

    public function process(Siswa $siswa, ?Carbon $moment = null): array
    {
        $now = ($moment ?? Carbon::now())->copy();
        $today = $now->toDateString();
        $jamNow = $now->format('H:i:s');
        $kelasLabel = $this->formatKelasLabel($siswa->kelas);

        $holidayRanges = $this->getHolidayRanges($today, $today);
        $holidayName = $this->resolveHolidayNameForDate($today, $siswa->kelas, $holidayRanges);
        if ($holidayName === null) {
            $holidayName = $this->resolveJadwalLiburNameForDate(
                $today,
                $siswa->kelas,
                $this->getJadwalLiburMapByKelas([$siswa->kelas])
            );
        }

        if ($holidayName !== null) {
            return $this->failureResult(
                $siswa,
                'Hari ini libur: '.$holidayName,
                'holiday',
                [
                    'holiday_name' => $holidayName,
                ]
            );
        }

        $jamKelas = $this->getJamConfigForKelas($siswa->kelas, $now);
        $masukMulai = $jamKelas['jam_masuk_mulai'];
        $masukAkhir = $jamKelas['jam_masuk_akhir'];
        $masukTelat = $jamKelas['jam_masuk_telat'];
        $pulangMulai = $jamKelas['jam_pulang_mulai'];

        if ($masukTelat < $masukAkhir) {
            $masukTelat = $masukAkhir;
        }

        if ($pulangMulai < $masukTelat) {
            $pulangMulai = $masukTelat;
        }

        $row = Absensi::query()
            ->whereDate('tanggal', $today)
            ->where('siswa_id', $siswa->id)
            ->first();

        $isWithinMasukWindow = $jamNow >= ($masukMulai.':00') && $jamNow <= ($masukTelat.':00');
        if ((!$row || !$row->jam_datang) && !$isWithinMasukWindow) {
            return $this->failureResult(
                $siswa,
                'Absen masuk hanya bisa pada jam '.$masukMulai.' - '.$masukTelat.'.',
                'outside_checkin_window',
                [
                    'checkin_start' => $masukMulai,
                    'checkin_end' => $masukTelat,
                ]
            );
        }

        if (!$row) {
            $keterangan = $jamNow > ($masukAkhir.':00') ? 'Terlambat' : 'Tepat Waktu';
            $row = Absensi::query()->create([
                'tanggal' => $today,
                'siswa_id' => $siswa->id,
                'nisn' => $siswa->nisn,
                'nama' => $siswa->nama,
                'kelas' => $kelasLabel,
                'jam_datang' => $jamNow,
                'jam_pulang' => null,
                'keterangan' => $keterangan,
                'status' => 'Hadir',
            ]);

            $this->dispatchAttendanceNotifications($siswa, [
                'type' => 'datang',
                'tanggal' => $today,
                'jam' => substr((string) $row->jam_datang, 0, 8),
                'status' => 'Hadir',
                'keterangan' => (string) ($row->keterangan ?? ''),
            ]);

            return $this->successResult(
                $siswa,
                'datang',
                substr((string) $row->jam_datang, 0, 5),
                'jamDatang',
                $keterangan === 'Terlambat' ? 'checkin_late' : 'checkin_on_time',
                $keterangan === 'Terlambat'
                    ? 'Absen masuk berhasil. Status: terlambat.'
                    : 'Absen masuk berhasil. Status: tepat waktu.',
                [
                    'status_label' => $keterangan,
                ]
            );
        }

        if (!$row->jam_datang) {
            $row->jam_datang = $jamNow;
            $row->status = 'Hadir';
            $row->keterangan = $jamNow > ($masukAkhir.':00') ? 'Terlambat' : 'Tepat Waktu';
            $row->save();

            $this->dispatchAttendanceNotifications($siswa, [
                'type' => 'datang',
                'tanggal' => $today,
                'jam' => substr((string) $row->jam_datang, 0, 8),
                'status' => 'Hadir',
                'keterangan' => (string) ($row->keterangan ?? ''),
            ]);

            return $this->successResult(
                $siswa,
                'datang',
                substr((string) $row->jam_datang, 0, 5),
                'jamDatang',
                $row->keterangan === 'Terlambat' ? 'checkin_late' : 'checkin_on_time',
                $row->keterangan === 'Terlambat'
                    ? 'Absen masuk berhasil. Status: terlambat.'
                    : 'Absen masuk berhasil. Status: tepat waktu.',
                [
                    'status_label' => (string) ($row->keterangan ?? ''),
                ]
            );
        }

        if (!$row->jam_pulang) {
            if ($jamNow < ($masukTelat.':00')) {
                return $this->failureResult(
                    $siswa,
                    'Anda sudah absen masuk hari ini',
                    'already_checked_in',
                    [
                        'checkin_time' => substr((string) $row->jam_datang, 0, 5),
                    ]
                );
            }

            $row->jam_pulang = $jamNow;
            if ($row->status === 'Belum Absen' || $row->status === '') {
                $row->status = 'Hadir';
            }

            $checkoutReason = 'checkout_on_time';
            $checkoutMessage = 'Absen pulang berhasil.';
            if ($jamNow <= ($pulangMulai.':00')) {
                $row->keterangan = 'Pulang Cepat';
                $checkoutReason = 'checkout_early';
                $checkoutMessage = 'Absen pulang berhasil. Status: pulang cepat.';
            } elseif (!$row->keterangan) {
                $row->keterangan = 'Tepat Waktu';
            }

            $row->save();

            $this->dispatchAttendanceNotifications($siswa, [
                'type' => 'pulang',
                'tanggal' => $today,
                'jam' => substr((string) $row->jam_pulang, 0, 8),
                'status' => stripos((string) ($row->keterangan ?? ''), 'pulang cepat') !== false ? 'Pulang Cepat' : 'Pulang',
                'keterangan' => (string) ($row->keterangan ?? ''),
            ]);

            return $this->successResult(
                $siswa,
                'pulang',
                substr((string) $row->jam_pulang, 0, 5),
                'jamPulang',
                $checkoutReason,
                $checkoutMessage,
                [
                    'status_label' => (string) ($row->keterangan ?? ''),
                ]
            );
        }

        return $this->failureResult(
            $siswa,
            'Anda sudah absen pulang hari ini',
            'already_checked_out',
            [
                'checkin_time' => substr((string) $row->jam_datang, 0, 5),
                'checkout_time' => substr((string) $row->jam_pulang, 0, 5),
            ]
        );
    }

    protected function successResult(
        Siswa $siswa,
        string $type,
        string $time,
        string $timeKey,
        string $reason,
        string $message,
        array $extra = []
    ): array
    {
        return array_merge([
            'success' => true,
            'nisn' => $siswa->nisn,
            'nama' => $siswa->nama,
            'kelas' => $this->formatKelasLabel($siswa->kelas),
            'type' => $type,
            'reason' => $reason,
            'message' => $message,
            $timeKey => $time,
        ], $extra);
    }

    protected function failureResult(Siswa $siswa, string $message, string $reason, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'nisn' => $siswa->nisn,
            'nama' => $siswa->nama,
            'kelas' => $this->formatKelasLabel($siswa->kelas),
            'message' => $message,
            'reason' => $reason,
        ], $extra);
    }

    protected function dispatchAttendanceNotifications(Siswa $siswa, array $context): void
    {
        $settings = $this->getAttendanceNotificationSettings();
        if (! $settings['enabled']) {
            return;
        }

        if ($settings['wa']) {
            $this->dispatchWaAttendanceNotification($siswa, $context);
        }

        if ($settings['telegram']) {
            $this->dispatchTelegramAttendanceNotification($siswa, $context);
        }
    }

    protected function dispatchWaAttendanceNotification(Siswa $siswa, array $context): void
    {
        $dispatchMode = strtoupper(trim((string) config('services.wa_gateway.dispatch_mode', 'QUEUE')));

        if (in_array($dispatchMode, ['QUEUE', 'AFTER'], true)) {
            $siswaId = (int) ($siswa->id ?? 0);
            $nisn = (string) ($siswa->nisn ?? '');

            if ($siswaId <= 0) {
                return;
            }

            SendWaAttendanceNotificationJob::dispatch($siswaId, $context, $nisn !== '' ? $nisn : null);

            return;
        }

        if (in_array($dispatchMode, ['REALTIME', 'AFTER_RESPONSE'], true)) {
            $siswaId = (int) ($siswa->id ?? 0);
            $nisn = (string) ($siswa->nisn ?? '');

            dispatch(function () use ($siswaId, $context, $nisn): void {
                try {
                    if ($siswaId <= 0) {
                        return;
                    }

                    $targetSiswa = Siswa::query()->find($siswaId);
                    if (!$targetSiswa) {
                        return;
                    }

                    app(WaGatewayService::class)->notifyAttendance($targetSiswa, $context);
                } catch (\Throwable $e) {
                    Log::warning('WA attendance notification failed (realtime after response)', [
                        'nisn' => $nisn !== '' ? $nisn : null,
                        'message' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();

            return;
        }

        try {
            app(WaGatewayService::class)->notifyAttendance($siswa, $context);
        } catch (\Throwable $e) {
            Log::warning('WA attendance notification failed', [
                'nisn' => $siswa->nisn ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function dispatchTelegramAttendanceNotification(Siswa $siswa, array $context): void
    {
        $dispatchMode = strtoupper(trim((string) config('services.telegram_bot.dispatch_mode', 'QUEUE')));

        if (in_array($dispatchMode, ['QUEUE', 'AFTER'], true)) {
            $siswaId = (int) ($siswa->id ?? 0);
            $nisn = (string) ($siswa->nisn ?? '');

            if ($siswaId <= 0) {
                return;
            }

            SendTelegramAttendanceNotificationJob::dispatch($siswaId, $context, $nisn !== '' ? $nisn : null);

            return;
        }

        if (in_array($dispatchMode, ['REALTIME', 'AFTER_RESPONSE'], true)) {
            $siswaId = (int) ($siswa->id ?? 0);
            $nisn = (string) ($siswa->nisn ?? '');

            dispatch(function () use ($siswaId, $context, $nisn): void {
                try {
                    if ($siswaId <= 0) {
                        return;
                    }

                    $targetSiswa = Siswa::query()->find($siswaId);
                    if (! $targetSiswa) {
                        return;
                    }

                    app(TelegramBotService::class)->notifyAttendance($targetSiswa, $context);
                } catch (\Throwable $e) {
                    Log::warning('Telegram attendance notification failed (realtime after response)', [
                        'nisn' => $nisn !== '' ? $nisn : null,
                        'message' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();

            return;
        }

        try {
            app(TelegramBotService::class)->notifyAttendance($siswa, $context);
        } catch (\Throwable $e) {
            Log::warning('Telegram attendance notification failed', [
                'nisn' => $siswa->nisn ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{enabled:bool, wa:bool, telegram:bool}
     */
    protected function getAttendanceNotificationSettings(): array
    {
        $rows = Konfigurasi::query()
            ->whereIn('key', [
                'attendance_notif_enabled',
                'attendance_notif_channel',
                'wa_notif_attendance_enabled',
                'telegram_notif_attendance_enabled',
            ])
            ->pluck('value', 'key')
            ->all();

        $enabled = array_key_exists('attendance_notif_enabled', $rows)
            ? (string) ($rows['attendance_notif_enabled'] ?? '0') === '1'
            : ((string) ($rows['wa_notif_attendance_enabled'] ?? '0') === '1'
                || (string) ($rows['telegram_notif_attendance_enabled'] ?? '0') === '1');

        $channel = strtolower(trim((string) ($rows['attendance_notif_channel'] ?? '')));
        if (! in_array($channel, ['whatsapp', 'telegram', 'both'], true)) {
            $waEnabled = (string) ($rows['wa_notif_attendance_enabled'] ?? '0') === '1';
            $telegramEnabled = (string) ($rows['telegram_notif_attendance_enabled'] ?? '0') === '1';
            $channel = $waEnabled && $telegramEnabled
                ? 'both'
                : ($telegramEnabled ? 'telegram' : 'whatsapp');
        }

        return [
            'enabled' => $enabled,
            'wa' => $enabled && in_array($channel, ['whatsapp', 'both'], true),
            'telegram' => $enabled && in_array($channel, ['telegram', 'both'], true),
        ];
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

        $idToNama = $this->resolveKelasIdsByName($kelasNames->all());
        if ($idToNama === []) {
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

    protected function getJamConfigForKelas(?string $kelas, Carbon $now): array
    {
        $config = $this->getGlobalJamConfig();
        $kelasNama = $this->normalizeKelasValue($kelas);
        if ($kelasNama === null) {
            return $config;
        }

        $idToNama = $this->resolveKelasIdsByName([$kelasNama]);
        $kelasId = array_key_first($idToNama);
        if ($kelasId === null) {
            return $config;
        }

        $hariSekarang = (int) $now->dayOfWeekIso;
        $jadwalHarian = JadwalHarian::query()
            ->select([
                'is_libur',
                'jam_masuk_mulai',
                'jam_masuk_akhir',
                'jam_masuk_telat',
                'jam_pulang_mulai',
                'jam_pulang_akhir',
            ])
            ->where('kelas_id', (int) $kelasId)
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

    protected function getGlobalJamConfig(): array
    {
        if ($this->globalJamConfigCache !== null) {
            return $this->globalJamConfigCache;
        }

        $config = [
            'jam_masuk_mulai' => '06:00',
            'jam_masuk_akhir' => '07:15',
            'jam_masuk_telat' => '07:15',
            'jam_pulang_mulai' => '15:00',
            'jam_pulang_akhir' => '17:00',
        ];

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

        $this->globalJamConfigCache = $config;

        return $this->globalJamConfigCache;
    }

    protected function resolveKelasIdsByName(array $kelasValues): array
    {
        $kelasNames = collect($kelasValues)
            ->map(fn ($value) => $this->normalizeKelasValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($kelasNames === []) {
            return [];
        }

        $missingNames = array_values(array_filter(
            $kelasNames,
            fn (string $name) => !array_key_exists($name, $this->kelasIdByNameCache)
        ));

        if ($missingNames !== []) {
            foreach ($missingNames as $name) {
                $this->kelasIdByNameCache[$name] = null;
            }

            $rows = Kelas::query()
                ->whereIn('nama', $missingNames)
                ->get(['id', 'nama']);

            foreach ($rows as $kelas) {
                $nama = $this->normalizeKelasValue($kelas->nama);
                if ($nama === null) {
                    continue;
                }

                $this->kelasIdByNameCache[$nama] = (int) $kelas->id;
            }
        }

        $idToNama = [];
        foreach ($kelasNames as $name) {
            $kelasId = $this->kelasIdByNameCache[$name] ?? null;
            if ($kelasId === null) {
                continue;
            }

            $idToNama[$kelasId] = $name;
        }

        return $idToNama;
    }

    protected function normalizeDateValue($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeKelasValue($kelas): ?string
    {
        $value = trim((string) ($kelas ?? ''));

        return $value === '' ? null : $value;
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

    protected function formatKelasLabel($kelas): string
    {
        return $this->normalizeKelasValue($kelas) ?? '-';
    }
}
