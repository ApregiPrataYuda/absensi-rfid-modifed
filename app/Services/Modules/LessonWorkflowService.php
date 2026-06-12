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

class LessonWorkflowService extends BaseActionService
{
    public function getPelajaranSessionsToday(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $dateRaw = trim((string) ($args[0] ?? ''));
        try {
            $tanggal = $dateRaw !== ''
                ? Carbon::parse($dateRaw)->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Tanggal tidak valid.'];
        }

        $hari = (int) Carbon::parse($tanggal)->dayOfWeekIso;

        $query = JadwalPelajaran::query()
            ->with(['kelas:id,nama', 'guru:id,name,username'])
            ->where('hari', $hari)
            ->orderBy('jam_mulai')
            ->orderBy('id');

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $query->where('guru_id', (int) $user->id);
        }

        $piketKelasIds = $this->getPelajaranPiketKelasIds($auth);
        if ($piketKelasIds !== null) {
            if (count($piketKelasIds) === 0) {
                return [
                    'success' => true,
                    'data' => [
                        'tanggal' => $tanggal,
                        'hari' => $hari,
                        'sessions' => [],
                    ],
                ];
            }

            $query->whereIn('kelas_id', $piketKelasIds);
        }

        $jadwalRows = $query->get();
        if ($jadwalRows->isEmpty()) {
            return [
                'success' => true,
                'data' => [
                    'tanggal' => $tanggal,
                    'hari' => $hari,
                    'sessions' => [],
                ],
            ];
        }

        $jadwalIds = $jadwalRows->pluck('id')->map(fn ($value) => (int) $value)->all();
        $sessionMap = SesiPelajaran::query()
            ->where('tanggal', $tanggal)
            ->whereIn('jadwal_pelajaran_id', $jadwalIds)
            ->get(['id', 'jadwal_pelajaran_id', 'status', 'opened_at', 'closed_at'])
            ->keyBy('jadwal_pelajaran_id');

        $sessions = $jadwalRows->map(function (JadwalPelajaran $row) use ($sessionMap): array {
            $sesi = $sessionMap->get((int) $row->id);

            return [
                'jadwal_id' => (int) $row->id,
                'kelas_id' => (int) $row->kelas_id,
                'kelas_nama' => (string) ($row->kelas?->nama ?? '-'),
                'guru_id' => $row->guru_id !== null ? (int) $row->guru_id : null,
                'guru_nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
                'mata_pelajaran' => (string) ($row->mata_pelajaran ?? ''),
                'ruang' => (string) ($row->ruang ?? ''),
                'jam_mulai' => substr((string) $row->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $row->jam_selesai, 0, 5),
                'sesi_id' => $sesi ? (int) $sesi->id : null,
                'sesi_status' => $sesi ? (string) ($sesi->status ?? '') : null,
                'sesi_opened_at' => $sesi && $sesi->opened_at ? Carbon::parse($sesi->opened_at)->format('H:i') : null,
                'sesi_closed_at' => $sesi && $sesi->closed_at ? Carbon::parse($sesi->closed_at)->format('H:i') : null,
            ];
        })->values()->all();

        return [
            'success' => true,
            'data' => [
                'tanggal' => $tanggal,
                'hari' => $hari,
                'sessions' => $sessions,
            ],
        ];
    }


    public function getPelajaranSessionDetail(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $jadwalId = (int) ($args[0] ?? 0);
        if ($jadwalId <= 0) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak valid.'];
        }

        $dateRaw = trim((string) ($args[1] ?? ''));
        try {
            $tanggal = $dateRaw !== ''
                ? Carbon::parse($dateRaw)->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Tanggal tidak valid.'];
        }

        $hari = (int) Carbon::parse($tanggal)->dayOfWeekIso;
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama', 'guru:id,name,username'])
            ->find($jadwalId);

        if (!$jadwal) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak ditemukan.'];
        }

        if ((int) $jadwal->hari !== $hari) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak sesuai dengan hari pada tanggal yang dipilih.'];
        }

        $kelasNama = trim((string) ($jadwal->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas pada jadwal pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $guruId = (int) ($jadwal->guru_id ?? 0);
            if ($guruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($guruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Jadwal pelajaran ini bukan untuk akun guru Anda.'];
            }
        }

        $sesi = SesiPelajaran::query()
            ->where('tanggal', $tanggal)
            ->where('jadwal_pelajaran_id', $jadwalId)
            ->first();

        return [
            'success' => true,
            'data' => $this->buildPelajaranSessionPayload($jadwal, $tanggal, $sesi, $kelasNama),
        ];
    }


    protected function buildPelajaranSessionPayload(JadwalPelajaran $jadwal, string $tanggal, ?SesiPelajaran $sesi, string $kelasNama): array
    {
        $students = Siswa::query()
            ->where('kelas', $kelasNama)
            ->orderBy('nama')
            ->get(['id', 'nisn', 'nama']);

        $records = collect();
        if ($sesi) {
            $records = AbsensiPelajaran::query()
                ->where('sesi_pelajaran_id', (int) $sesi->id)
                ->get(['id', 'siswa_id', 'status', 'method', 'recorded_at', 'recorded_by_user_id', 'note'])
                ->keyBy('siswa_id');
        }

        $counts = [
            'Hadir' => 0,
            'Terlambat' => 0,
            'Izin' => 0,
            'Sakit' => 0,
            'Alfa' => 0,
        ];

        foreach ($records as $record) {
            $status = trim((string) ($record->status ?? ''));
            if ($status !== '' && array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        $total = (int) $students->count();
        $recordedCount = (int) $records->count();
        $belum = max(0, $total - $recordedCount);

        $sessionStatus = 'not_started';
        if ($sesi) {
            $sessionStatus = strtolower(trim((string) ($sesi->status ?? 'open')));
            if ($sessionStatus === '') {
                $sessionStatus = 'open';
            }
            if ($sesi->closed_at !== null) {
                $sessionStatus = 'closed';
            }
        }

        return [
            'session' => [
                'id' => $sesi ? (int) $sesi->id : null,
                'tanggal' => $sesi?->tanggal?->toDateString() ?? $tanggal,
                'status' => $sessionStatus,
                'opened_at' => $sesi?->opened_at?->format('H:i:s'),
                'closed_at' => $sesi?->closed_at?->format('H:i:s'),
                'jadwal_pelajaran_id' => (int) $jadwal->id,
                'kelas' => [
                    'id' => (int) $jadwal->kelas_id,
                    'nama' => $kelasNama,
                ],
                'guru' => [
                    'id' => $jadwal->guru_id !== null ? (int) $jadwal->guru_id : null,
                    'nama' => (string) ($jadwal->guru?->name ?: ($jadwal->guru?->username ?? '-')),
                ],
                'mata_pelajaran' => (string) ($jadwal->mata_pelajaran ?? ''),
                'ruang' => (string) ($jadwal->ruang ?? ''),
                'jam_mulai' => substr((string) $jadwal->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $jadwal->jam_selesai, 0, 5),
            ],
            'students' => $students->map(function (Siswa $row) use ($records): array {
                $record = $records->get((int) $row->id);

                return [
                    'id' => (int) $row->id,
                    'nisn' => (string) ($row->nisn ?? ''),
                    'nama' => (string) ($row->nama ?? ''),
                    'status' => $record ? (string) ($record->status ?? '') : '',
                    'method' => $record ? (string) ($record->method ?? '') : '',
                    'recorded_at' => $record && $record->recorded_at ? Carbon::parse($record->recorded_at)->format('H:i:s') : null,
                ];
            })->values()->all(),
            'stats' => [
                'total' => $total,
                'recorded' => $recordedCount,
                'belum' => $belum,
                'by_status' => $counts,
            ],
            'late_tolerance_minutes' => 10,
        ];
    }


    public function startPelajaranSession(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $jadwalId = (int) ($args[0] ?? 0);
        if ($jadwalId <= 0) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak valid.'];
        }

        $dateRaw = trim((string) ($args[1] ?? ''));
        try {
            $tanggal = $dateRaw !== ''
                ? Carbon::parse($dateRaw)->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Tanggal tidak valid.'];
        }

        $hari = (int) Carbon::parse($tanggal)->dayOfWeekIso;
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama', 'guru:id,name,username'])
            ->find($jadwalId);

        if (!$jadwal) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak ditemukan.'];
        }

        if ((int) $jadwal->hari !== $hari) {
            return ['success' => false, 'message' => 'Jadwal pelajaran tidak sesuai dengan hari pada tanggal yang dipilih.'];
        }

        $kelasNama = trim((string) ($jadwal->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas pada jadwal pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $guruId = (int) ($jadwal->guru_id ?? 0);
            if ($guruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($guruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Jadwal pelajaran ini bukan untuk akun guru Anda.'];
            }
        }

        $userId = (int) ($auth->user_id ?? 0);
        $now = Carbon::now();

        $sesi = SesiPelajaran::query()
            ->where('tanggal', $tanggal)
            ->where('jadwal_pelajaran_id', $jadwalId)
            ->first();

        if (!$sesi) {
            $sesi = SesiPelajaran::query()->create([
                'tanggal' => $tanggal,
                'jadwal_pelajaran_id' => $jadwalId,
                'kelas_id' => (int) $jadwal->kelas_id,
                'guru_id' => $jadwal->guru_id !== null ? (int) $jadwal->guru_id : null,
                'status' => 'open',
                'opened_by_user_id' => $userId > 0 ? $userId : null,
                'opened_at' => $now,
            ]);
        } elseif (strtolower(trim((string) ($sesi->status ?? 'open'))) !== 'closed') {
            $dirty = false;

            if (!$sesi->opened_at) {
                $sesi->opened_at = $now;
                $dirty = true;
            }

            if (!$sesi->opened_by_user_id && $userId > 0) {
                $sesi->opened_by_user_id = $userId;
                $dirty = true;
            }

            if (trim((string) ($sesi->status ?? '')) === '') {
                $sesi->status = 'open';
                $dirty = true;
            }

            if ((int) $sesi->kelas_id !== (int) $jadwal->kelas_id) {
                $sesi->kelas_id = (int) $jadwal->kelas_id;
                $dirty = true;
            }

            $jadwalGuruId = $jadwal->guru_id !== null ? (int) $jadwal->guru_id : null;
            if ($jadwalGuruId !== null && (int) ($sesi->guru_id ?? 0) !== $jadwalGuruId) {
                $sesi->guru_id = $jadwalGuruId;
                $dirty = true;
            }

            if ($dirty) {
                $sesi->save();
            }
        }

        return [
            'success' => true,
            'data' => $this->buildPelajaranSessionPayload($jadwal, $tanggal, $sesi, $kelasNama),
        ];
    }


    public function scanPelajaranAbsensi(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $sessionId = (int) ($args[0] ?? 0);
        $scanType = strtolower(trim((string) ($args[1] ?? '')));
        $rawCode = trim((string) ($args[2] ?? ''));

        if ($sessionId <= 0 || $scanType === '' || $rawCode === '') {
            return ['success' => false, 'message' => 'Parameter tidak valid.'];
        }

        $sesi = SesiPelajaran::query()
            ->with([
                'jadwalPelajaran:id,guru_id,jam_mulai',
                'kelas:id,nama',
            ])
            ->find($sessionId);

        if (!$sesi) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak ditemukan.'];
        }

        $sessionStatus = strtolower(trim((string) ($sesi->status ?? 'open')));
        if ($sesi->closed_at !== null || $sessionStatus === 'closed') {
            return ['success' => false, 'message' => 'Sesi pelajaran sudah ditutup.'];
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $jadwalGuruId = (int) ($sesi->jadwalPelajaran?->guru_id ?? 0);
            if ($jadwalGuruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($jadwalGuruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Sesi ini bukan jadwal pelajaran Anda.'];
            }
        }

        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas sesi pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        $siswa = null;
        if ($scanType === 'qr') {
            $nisn = $this->normalizeScannedNisn($rawCode);
            if ($nisn === '') {
                return ['success' => false, 'message' => 'Kode QR tidak valid.'];
            }

            $siswa = Siswa::query()->where('nisn', $nisn)->first();
            if (!$siswa) {
                return ['success' => false, 'message' => 'NISN tidak ditemukan.'];
            }
        } elseif ($scanType === 'rfid') {
            $cardService = app(AttendanceCardService::class);
            $resolvedCard = $cardService->resolveFromScan($rawCode, KartuAbsensi::TYPE_RFID, 'web');
            if (!($resolvedCard['success'] ?? false)) {
                return ['success' => false, 'message' => $resolvedCard['message'] ?? 'UID RFID tidak valid.'];
            }

            $card = $resolvedCard['card'] ?? null;
            if (!$card) {
                return ['success' => false, 'message' => 'Data kartu RFID tidak ditemukan.'];
            }

            if (!$card->siswa) {
                $isNewCard = (bool) ($resolvedCard['created'] ?? false);
                return [
                    'success' => false,
                    'message' => $isNewCard
                        ? 'Kartu RFID baru terdeteksi. Tautkan kartu ke siswa terlebih dahulu.'
                        : 'Kartu RFID belum ditautkan ke siswa.',
                ];
            }

            $siswa = $card->siswa;
        } else {
            return ['success' => false, 'message' => 'Mode scan tidak valid.'];
        }

        $siswaKelas = $this->normalizeKelasValue($siswa?->kelas);
        if ($siswaKelas === null || $siswaKelas !== $this->normalizeKelasValue($kelasNama)) {
            return ['success' => false, 'message' => 'Siswa bukan anggota kelas ' . $kelasNama . '.'];
        }

        $lateToleranceMinutes = 10;
        $now = Carbon::now();
        $status = 'Hadir';

        try {
            $jadwalStart = trim((string) ($sesi->jadwalPelajaran?->jam_mulai ?? ''));
            if ($jadwalStart !== '' && $sesi->tanggal !== null) {
                $startMoment = Carbon::parse($sesi->tanggal->toDateString() . ' ' . substr($jadwalStart, 0, 8))
                    ->addMinutes($lateToleranceMinutes);
                if ($now->greaterThan($startMoment)) {
                    $status = 'Terlambat';
                }
            }
        } catch (\Throwable $e) {
            $status = 'Hadir';
        }

        $userId = (int) ($auth->user_id ?? 0);
        $record = AbsensiPelajaran::query()->updateOrCreate(
            [
                'sesi_pelajaran_id' => (int) $sesi->id,
                'siswa_id' => (int) $siswa->id,
            ],
            [
                'status' => $status,
                'recorded_at' => $now,
                'method' => $scanType,
                'recorded_by_user_id' => $userId > 0 ? $userId : null,
                'note' => null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Absensi pelajaran tersimpan.',
            'data' => [
                'sesi_id' => (int) $sesi->id,
                'siswa_id' => (int) $siswa->id,
                'nisn' => (string) ($siswa->nisn ?? ''),
                'nama' => (string) ($siswa->nama ?? ''),
                'kelas' => (string) ($siswa->kelas ?? ''),
                'status' => (string) ($record->status ?? $status),
                'method' => (string) ($record->method ?? $scanType),
                'recorded_at' => $record->recorded_at ? Carbon::parse($record->recorded_at)->format('H:i:s') : $now->format('H:i:s'),
            ],
        ];
    }


    public function setPelajaranAbsensiStatus(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $sessionId = (int) ($args[0] ?? 0);
        $siswaId = (int) ($args[1] ?? 0);
        $newStatus = trim((string) ($args[2] ?? ''));

        if ($sessionId <= 0 || $siswaId <= 0) {
            return ['success' => false, 'message' => 'Parameter tidak valid.'];
        }

        $allowedStatuses = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alfa'];

        $sesi = SesiPelajaran::query()
            ->with([
                'jadwalPelajaran:id,guru_id',
                'kelas:id,nama',
            ])
            ->find($sessionId);

        if (!$sesi) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak ditemukan.'];
        }

        $sessionStatus = strtolower(trim((string) ($sesi->status ?? 'open')));
        if ($sesi->closed_at !== null || $sessionStatus === 'closed') {
            return ['success' => false, 'message' => 'Sesi pelajaran sudah ditutup.'];
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $jadwalGuruId = (int) ($sesi->jadwalPelajaran?->guru_id ?? 0);
            if ($jadwalGuruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($jadwalGuruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Sesi ini bukan jadwal pelajaran Anda.'];
            }
        }

        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas sesi pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        $siswa = Siswa::query()->find($siswaId);
        if (!$siswa) {
            return ['success' => false, 'message' => 'Siswa tidak ditemukan.'];
        }

        $siswaKelas = $this->normalizeKelasValue($siswa->kelas);
        if ($siswaKelas === null || $siswaKelas !== $this->normalizeKelasValue($kelasNama)) {
            return ['success' => false, 'message' => 'Siswa bukan anggota kelas ' . $kelasNama . '.'];
        }

        $userId = (int) ($auth->user_id ?? 0);

        if ($newStatus === '' || $newStatus === 'Belum Absen') {
            AbsensiPelajaran::query()
                ->where('sesi_pelajaran_id', (int) $sesi->id)
                ->where('siswa_id', (int) $siswa->id)
                ->delete();

            return [
                'success' => true,
                'message' => 'Absensi pelajaran direset.',
                'data' => [
                    'siswa_id' => (int) $siswa->id,
                    'status' => '',
                    'method' => '',
                    'recorded_at' => null,
                ],
            ];
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Status tidak valid.'];
        }

        $now = Carbon::now();
        $record = AbsensiPelajaran::query()->updateOrCreate(
            [
                'sesi_pelajaran_id' => (int) $sesi->id,
                'siswa_id' => (int) $siswa->id,
            ],
            [
                'status' => $newStatus,
                'recorded_at' => $now,
                'method' => 'manual',
                'recorded_by_user_id' => $userId > 0 ? $userId : null,
            ]
        );

        return [
            'success' => true,
            'message' => 'Absensi pelajaran diperbarui.',
            'data' => [
                'siswa_id' => (int) $siswa->id,
                'status' => (string) ($record->status ?? $newStatus),
                'method' => (string) ($record->method ?? 'manual'),
                'recorded_at' => $record->recorded_at ? Carbon::parse($record->recorded_at)->format('H:i:s') : $now->format('H:i:s'),
            ],
        ];
    }


    public function broadcastPelajaranHadir(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $sessionId = (int) ($args[0] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak valid.'];
        }

        $sesi = SesiPelajaran::query()
            ->with([
                'jadwalPelajaran:id,guru_id',
                'kelas:id,nama',
            ])
            ->find($sessionId);

        if (!$sesi) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak ditemukan.'];
        }

        $sessionStatus = strtolower(trim((string) ($sesi->status ?? 'open')));
        if ($sesi->closed_at !== null || $sessionStatus === 'closed') {
            return ['success' => false, 'message' => 'Sesi pelajaran sudah ditutup.'];
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $jadwalGuruId = (int) ($sesi->jadwalPelajaran?->guru_id ?? 0);
            if ($jadwalGuruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($jadwalGuruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Sesi ini bukan jadwal pelajaran Anda.'];
            }
        }

        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas sesi pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        $students = Siswa::query()
            ->where('kelas', $kelasNama)
            ->orderBy('nama')
            ->get(['id', 'nisn', 'nama']);

        $totalStudents = (int) $students->count();
        $existingIds = AbsensiPelajaran::query()
            ->where('sesi_pelajaran_id', (int) $sesi->id)
            ->pluck('siswa_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $missingStudents = $students
            ->filter(fn (Siswa $row) => !in_array((int) $row->id, $existingIds, true))
            ->values();

        if ($missingStudents->isEmpty()) {
            $counts = AbsensiPelajaran::query()
                ->where('sesi_pelajaran_id', (int) $sesi->id)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) ($row->status ?? '') => (int) ($row->total ?? 0)])
                ->all();

            $recordedCount = array_sum($counts);

            return [
                'success' => true,
                'message' => 'Tidak ada siswa dengan status Belum Absen.',
                'data' => [
                    'updated_count' => 0,
                    'updated' => [],
                    'stats' => [
                        'total' => $totalStudents,
                        'recorded' => $recordedCount,
                        'belum' => max(0, $totalStudents - $recordedCount),
                        'by_status' => $counts,
                    ],
                ],
            ];
        }

        $now = Carbon::now();
        $userId = (int) ($auth->user_id ?? 0);
        $tanggal = $sesi->tanggal?->toDateString() ?? $now->toDateString();
        $dailyStatusMap = Absensi::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('siswa_id', $missingStudents->pluck('id')->map(fn ($value) => (int) $value)->all())
            ->get(['siswa_id', 'status'])
            ->mapWithKeys(function (Absensi $row): array {
                $status = trim((string) ($row->status ?? ''));
                return [(int) $row->siswa_id => $status];
            })
            ->all();

        $rows = $missingStudents->map(function (Siswa $row) use ($sesi, $now, $userId, $dailyStatusMap): array {
            $dailyStatus = trim((string) ($dailyStatusMap[(int) $row->id] ?? ''));

            if ($dailyStatus === '' || in_array($dailyStatus, ['Belum Absen', 'Alpa', 'Alfa'], true)) {
                $lessonStatus = 'Alfa';
                $note = 'Broadcast hadir - tidak hadir absensi harian';
            } elseif (in_array($dailyStatus, ['Izin', 'Sakit'], true)) {
                $lessonStatus = $dailyStatus;
                $note = 'Broadcast hadir - ikut status absensi harian';
            } else {
                $lessonStatus = 'Hadir';
                $note = 'Broadcast hadir';
            }

            return [
                'sesi_pelajaran_id' => (int) $sesi->id,
                'siswa_id' => (int) $row->id,
                'status' => $lessonStatus,
                'recorded_at' => $now,
                'method' => 'broadcast',
                'recorded_by_user_id' => $userId > 0 ? $userId : null,
                'note' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        if (count($rows) > 0) {
            AbsensiPelajaran::query()->insert($rows);
        }

        $counts = AbsensiPelajaran::query()
            ->where('sesi_pelajaran_id', (int) $sesi->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->status ?? '') => (int) ($row->total ?? 0)])
            ->all();

        $recordedCount = array_sum($counts);

        return [
            'success' => true,
            'message' => 'Broadcast hadir berhasil diterapkan.',
            'data' => [
                'updated_count' => count($rows),
                'updated' => $missingStudents->map(function (Siswa $row) use ($now, $dailyStatusMap): array {
                    $dailyStatus = trim((string) ($dailyStatusMap[(int) $row->id] ?? ''));

                    if ($dailyStatus === '' || in_array($dailyStatus, ['Belum Absen', 'Alpa', 'Alfa'], true)) {
                        $lessonStatus = 'Alfa';
                    } elseif (in_array($dailyStatus, ['Izin', 'Sakit'], true)) {
                        $lessonStatus = $dailyStatus;
                    } else {
                        $lessonStatus = 'Hadir';
                    }

                    return [
                        'siswa_id' => (int) $row->id,
                        'nisn' => (string) ($row->nisn ?? ''),
                        'nama' => (string) ($row->nama ?? ''),
                        'status' => $lessonStatus,
                        'method' => 'broadcast',
                        'recorded_at' => $now->format('H:i:s'),
                    ];
                })->values()->all(),
                'stats' => [
                    'total' => $totalStudents,
                    'recorded' => $recordedCount,
                    'belum' => max(0, $totalStudents - $recordedCount),
                    'by_status' => $counts,
                ],
            ],
        ];
    }


    public function closePelajaranSession(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $sessionId = (int) ($args[0] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak valid.'];
        }

        $sesi = SesiPelajaran::query()
            ->with([
                'jadwalPelajaran:id,guru_id,mata_pelajaran',
                'kelas:id,nama',
            ])
            ->find($sessionId);

        if (!$sesi) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak ditemukan.'];
        }

        if ($role === 'wakel') {
            $user = $this->getAuthUserFromToken($auth);
            if (!$user) {
                return ['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'];
            }

            $jadwalGuruId = (int) ($sesi->jadwalPelajaran?->guru_id ?? 0);
            if ($jadwalGuruId <= 0) {
                return ['success' => false, 'message' => 'Jadwal pelajaran belum ditautkan ke guru.'];
            }

            if ($jadwalGuruId !== (int) $user->id) {
                return ['success' => false, 'message' => 'Sesi ini bukan jadwal pelajaran Anda.'];
            }
        }

        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas sesi pelajaran tidak valid.'];
        }

        $piketAccess = $this->ensurePelajaranPiketKelasAccess($auth, $kelasNama);
        if ($piketAccess !== null) {
            return $piketAccess;
        }

        $now = Carbon::now();
        $userId = (int) ($auth->user_id ?? 0);
        $inserted = 0;

        DB::transaction(function () use ($sesi, $kelasNama, $now, $userId, &$inserted): void {
            $sessionStatus = strtolower(trim((string) ($sesi->status ?? 'open')));
            if ($sesi->closed_at !== null || $sessionStatus === 'closed') {
                return;
            }

            $siswaIds = Siswa::query()
                ->where('kelas', $kelasNama)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->all();

            $existingIds = AbsensiPelajaran::query()
                ->where('sesi_pelajaran_id', (int) $sesi->id)
                ->pluck('siswa_id')
                ->map(fn ($value) => (int) $value)
                ->all();

            $missingIds = array_values(array_diff($siswaIds, $existingIds));
            if (count($missingIds) > 0) {
                $tanggal = $sesi->tanggal?->toDateString() ?? Carbon::today()->toDateString();

                $approvedMap = IzinSakitRequest::query()
                    ->where('status', IzinSakitRequest::STATUS_APPROVED)
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->whereIn('siswa_id', $missingIds)
                    ->get(['siswa_id', 'jenis'])
                    ->mapWithKeys(function (IzinSakitRequest $row): array {
                        $jenis = strtolower(trim((string) ($row->jenis ?? '')));
                        $status = $jenis === IzinSakitRequest::JENIS_SAKIT ? 'Sakit' : 'Izin';
                        return [(int) $row->siswa_id => $status];
                    })
                    ->all();

                $rows = [];
                foreach ($missingIds as $siswaId) {
                    $autoStatus = $approvedMap[(int) $siswaId] ?? 'Alfa';
                    $note = array_key_exists((int) $siswaId, $approvedMap) ? 'Auto izin/sakit (approved)' : 'Auto alfa (close sesi)';

                    $rows[] = [
                        'sesi_pelajaran_id' => (int) $sesi->id,
                        'siswa_id' => (int) $siswaId,
                        'status' => $autoStatus,
                        'recorded_at' => $now,
                        'method' => 'system',
                        'recorded_by_user_id' => $userId > 0 ? $userId : null,
                        'note' => $note,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (count($rows) > 0) {
                    AbsensiPelajaran::query()->insert($rows);
                    $inserted = count($rows);
                }
            }

            $sesi->status = 'closed';
            $sesi->closed_at = $now;
            $sesi->closed_by_user_id = $userId > 0 ? $userId : null;
            $sesi->save();
        });

        $counts = AbsensiPelajaran::query()
            ->where('sesi_pelajaran_id', (int) $sesi->id)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->status ?? '') => (int) ($row->total ?? 0)])
            ->all();

        return [
            'success' => true,
            'message' => 'Sesi pelajaran ditutup.',
            'data' => [
                'sesi_id' => (int) $sesi->id,
                'inserted' => $inserted,
                'counts' => $counts,
                'status' => 'closed',
                'closed_at' => $now->format('H:i:s'),
            ],
        ];
    }


    public function getPelajaranReportData(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $payload = is_array($args[0] ?? null) ? $args[0] : [];
        $today = Carbon::today();

        try {
            $tanggalDari = trim((string) ($payload['tanggal_dari'] ?? '')) !== ''
                ? Carbon::parse($payload['tanggal_dari'])->toDateString()
                : $today->copy()->startOfMonth()->toDateString();
            $tanggalSampai = trim((string) ($payload['tanggal_sampai'] ?? '')) !== ''
                ? Carbon::parse($payload['tanggal_sampai'])->toDateString()
                : $today->toDateString();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Filter tanggal tidak valid.'];
        }

        if ($tanggalDari > $tanggalSampai) {
            [$tanggalDari, $tanggalSampai] = [$tanggalSampai, $tanggalDari];
        }

        $filters = [
            'tanggal_dari' => $tanggalDari,
            'tanggal_sampai' => $tanggalSampai,
            'kelas_id' => max(0, (int) ($payload['kelas_id'] ?? 0)),
            'guru_id' => max(0, (int) ($payload['guru_id'] ?? 0)),
            'mapel' => trim((string) ($payload['mapel'] ?? '')),
            'status_sesi' => strtolower(trim((string) ($payload['status_sesi'] ?? 'closed'))),
            'search' => trim((string) ($payload['search'] ?? '')),
        ];

        if (!in_array($filters['status_sesi'], ['all', 'open', 'closed'], true)) {
            $filters['status_sesi'] = 'closed';
        }

        $sessionQuery = SesiPelajaran::query()
            ->with([
                'kelas:id,nama',
                'guru:id,name,username',
                'jadwalPelajaran:id,mata_pelajaran,jam_mulai,jam_selesai,guru_id',
            ])
            ->whereDate('tanggal', '>=', $tanggalDari)
            ->whereDate('tanggal', '<=', $tanggalSampai);

        $roleScopeError = $this->applyPelajaranReportRoleScope($sessionQuery, $auth, $role);
        if ($roleScopeError !== null) {
            return $roleScopeError;
        }

        $accessibleSessions = $sessionQuery
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        $kelasOptions = $accessibleSessions
            ->map(fn (SesiPelajaran $row): array => [
                'id' => (int) $row->kelas_id,
                'nama' => (string) ($row->kelas?->nama ?? '-'),
            ])
            ->unique('id')
            ->sortBy('nama')
            ->values()
            ->all();

        $guruOptions = $accessibleSessions
            ->filter(fn (SesiPelajaran $row): bool => (int) ($row->guru_id ?? 0) > 0)
            ->map(fn (SesiPelajaran $row): array => [
                'id' => (int) $row->guru_id,
                'nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
            ])
            ->unique('id')
            ->sortBy('nama')
            ->values()
            ->all();

        $mapelOptions = $accessibleSessions
            ->map(fn (SesiPelajaran $row): string => trim((string) ($row->jadwalPelajaran?->mata_pelajaran ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $filteredSessions = $accessibleSessions
            ->filter(function (SesiPelajaran $row) use ($filters): bool {
                $kelasId = (int) $row->kelas_id;
                $guruId = (int) ($row->guru_id ?? 0);
                $mapel = trim((string) ($row->jadwalPelajaran?->mata_pelajaran ?? ''));
                $kelasNama = trim((string) ($row->kelas?->nama ?? ''));
                $guruNama = trim((string) ($row->guru?->name ?: ($row->guru?->username ?? '')));
                $statusSesi = ($row->closed_at !== null || strtolower(trim((string) ($row->status ?? ''))) === 'closed')
                    ? 'closed'
                    : 'open';

                if ($filters['kelas_id'] > 0 && $kelasId !== $filters['kelas_id']) {
                    return false;
                }
                if ($filters['guru_id'] > 0 && $guruId !== $filters['guru_id']) {
                    return false;
                }
                if ($filters['mapel'] !== '' && strcasecmp($mapel, $filters['mapel']) !== 0) {
                    return false;
                }
                if ($filters['status_sesi'] !== 'all' && $statusSesi !== $filters['status_sesi']) {
                    return false;
                }

                $search = strtolower($filters['search']);
                if ($search !== '') {
                    $haystack = strtolower(implode(' ', [
                        $kelasNama,
                        $guruNama,
                        $mapel,
                        (string) $row->tanggal?->format('Y-m-d'),
                    ]));
                    if (!str_contains($haystack, $search)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $sessionIds = $filteredSessions->pluck('id')->map(fn ($value): int => (int) $value)->all();
        $classNames = $filteredSessions
            ->map(fn (SesiPelajaran $row): string => trim((string) ($row->kelas?->nama ?? '')))
            ->filter()
            ->unique()
            ->values();

        $studentsByClass = collect();
        if ($classNames->isNotEmpty()) {
            $studentsByClass = Siswa::query()
                ->whereIn('kelas', $classNames->all())
                ->orderBy('nama')
                ->get(['id', 'nisn', 'nama', 'kelas'])
                ->groupBy(fn (Siswa $row): string => trim((string) ($row->kelas ?? '')));
        }

        $recordsBySession = collect();
        if (!empty($sessionIds)) {
            $recordsBySession = AbsensiPelajaran::query()
                ->whereIn('sesi_pelajaran_id', $sessionIds)
                ->get(['sesi_pelajaran_id', 'siswa_id', 'status', 'method', 'recorded_at'])
                ->groupBy('sesi_pelajaran_id');
        }

        $sessionRows = [];
        $studentRecapMap = [];
        $stats = [
            'total_sessions' => 0,
            'closed_sessions' => 0,
            'open_sessions' => 0,
            'students' => 0,
            'hadir' => 0,
            'terlambat' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alfa' => 0,
            'belum' => 0,
        ];

        foreach ($filteredSessions as $session) {
            $sessionId = (int) $session->id;
            $kelasNama = trim((string) ($session->kelas?->nama ?? ''));
            $guruNama = (string) ($session->guru?->name ?: ($session->guru?->username ?? '-'));
            $mapel = (string) ($session->jadwalPelajaran?->mata_pelajaran ?? '-');
            $jamMulai = substr((string) ($session->jadwalPelajaran?->jam_mulai ?? ''), 0, 5);
            $jamSelesai = substr((string) ($session->jadwalPelajaran?->jam_selesai ?? ''), 0, 5);
            $isClosed = $session->closed_at !== null || strtolower(trim((string) ($session->status ?? ''))) === 'closed';

            $roster = collect($studentsByClass->get($kelasNama, collect()));
            $recordByStudent = collect($recordsBySession->get($sessionId, collect()))->keyBy('siswa_id');

            $sessionCounts = [
                'Hadir' => 0,
                'Terlambat' => 0,
                'Izin' => 0,
                'Sakit' => 0,
                'Alfa' => 0,
                'Belum Absen' => 0,
            ];

            foreach ($roster as $student) {
                $record = $recordByStudent->get((int) $student->id);
                $status = trim((string) ($record?->status ?? ''));
                if ($status === '') {
                    $status = $isClosed ? 'Alfa' : 'Belum Absen';
                }

                if (!array_key_exists($status, $sessionCounts)) {
                    $status = 'Belum Absen';
                }

                $sessionCounts[$status]++;

                $studentId = (int) $student->id;
                if (!array_key_exists($studentId, $studentRecapMap)) {
                    $studentRecapMap[$studentId] = [
                        'siswa_id' => $studentId,
                        'nisn' => (string) ($student->nisn ?? ''),
                        'nama' => (string) ($student->nama ?? ''),
                        'kelas_nama' => (string) ($student->kelas ?? $kelasNama),
                        'total_sesi' => 0,
                        'hadir' => 0,
                        'terlambat' => 0,
                        'izin' => 0,
                        'sakit' => 0,
                        'alfa' => 0,
                        'belum' => 0,
                    ];
                }

                $studentRecapMap[$studentId]['total_sesi']++;
                if ($status === 'Hadir') {
                    $studentRecapMap[$studentId]['hadir']++;
                } elseif ($status === 'Terlambat') {
                    $studentRecapMap[$studentId]['terlambat']++;
                } elseif ($status === 'Izin') {
                    $studentRecapMap[$studentId]['izin']++;
                } elseif ($status === 'Sakit') {
                    $studentRecapMap[$studentId]['sakit']++;
                } elseif ($status === 'Alfa') {
                    $studentRecapMap[$studentId]['alfa']++;
                } else {
                    $studentRecapMap[$studentId]['belum']++;
                }
            }

            $totalSiswa = (int) $roster->count();
            $hadirCount = (int) ($sessionCounts['Hadir'] ?? 0);
            $terlambatCount = (int) ($sessionCounts['Terlambat'] ?? 0);
            $izinCount = (int) ($sessionCounts['Izin'] ?? 0);
            $sakitCount = (int) ($sessionCounts['Sakit'] ?? 0);
            $alfaCount = (int) ($sessionCounts['Alfa'] ?? 0);
            $belumCount = (int) ($sessionCounts['Belum Absen'] ?? 0);
            $recordedCount = $totalSiswa - $belumCount;
            $kehadiranRate = $totalSiswa > 0
                ? round((($hadirCount + $terlambatCount) / $totalSiswa) * 100, 1)
                : 0.0;

            $stats['total_sessions']++;
            $stats[$isClosed ? 'closed_sessions' : 'open_sessions']++;
            $stats['hadir'] += $hadirCount;
            $stats['terlambat'] += $terlambatCount;
            $stats['izin'] += $izinCount;
            $stats['sakit'] += $sakitCount;
            $stats['alfa'] += $alfaCount;
            $stats['belum'] += $belumCount;

            $sessionRows[] = [
                'session_id' => $sessionId,
                'tanggal' => $session->tanggal?->toDateString(),
                'tanggal_label' => $session->tanggal
                    ? $session->tanggal->translatedFormat('d M Y')
                    : '-',
                'kelas_id' => (int) $session->kelas_id,
                'kelas_nama' => $kelasNama !== '' ? $kelasNama : '-',
                'guru_id' => $session->guru_id !== null ? (int) $session->guru_id : null,
                'guru_nama' => $guruNama,
                'mata_pelajaran' => $mapel,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'status_sesi' => $isClosed ? 'closed' : 'open',
                'status_label' => $isClosed ? 'Ditutup' : 'Berjalan',
                'opened_at' => $session->opened_at?->format('H:i:s'),
                'closed_at' => $session->closed_at?->format('H:i:s'),
                'total_siswa' => $totalSiswa,
                'recorded' => $recordedCount,
                'hadir' => $hadirCount,
                'terlambat' => $terlambatCount,
                'izin' => $izinCount,
                'sakit' => $sakitCount,
                'alfa' => $alfaCount,
                'belum' => $belumCount,
                'kehadiran_rate' => $kehadiranRate,
            ];
        }

        $studentRows = collect(array_values($studentRecapMap))
            ->map(function (array $row): array {
                $present = (int) $row['hadir'] + (int) $row['terlambat'];
                $totalSesi = max(0, (int) $row['total_sesi']);
                $row['kehadiran_rate'] = $totalSesi > 0
                    ? round(($present / $totalSesi) * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sortBy(fn (array $row): string => strtolower(($row['kelas_nama'] ?? '') . '|' . ($row['nama'] ?? '')))
            ->values()
            ->all();

        $stats['students'] = count($studentRows);

        return [
            'success' => true,
            'data' => [
                'filters' => $filters,
                'options' => [
                    'kelas' => $kelasOptions,
                    'guru' => $guruOptions,
                    'mapel' => $mapelOptions,
                    'status_sesi' => [
                        ['value' => 'closed', 'label' => 'Sesi Ditutup'],
                        ['value' => 'open', 'label' => 'Sesi Berjalan'],
                        ['value' => 'all', 'label' => 'Semua Sesi'],
                    ],
                ],
                'stats' => $stats,
                'sessions' => $sessionRows,
                'students' => $studentRows,
            ],
        ];
    }


    public function getPelajaranReportSessionDetail(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $piketConfig = $this->ensurePelajaranPiketConfigured($auth);
        if ($piketConfig !== null) {
            return $piketConfig;
        }

        $sessionId = (int) ($args[0] ?? 0);
        if ($sessionId <= 0) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak valid.'];
        }

        $sesi = SesiPelajaran::query()
            ->with([
                'jadwalPelajaran:id,mata_pelajaran,jam_mulai,jam_selesai,guru_id',
                'kelas:id,nama',
                'guru:id,name,username',
            ])
            ->find($sessionId);

        if (!$sesi) {
            return ['success' => false, 'message' => 'Sesi pelajaran tidak ditemukan.'];
        }

        $accessError = $this->ensurePelajaranReportSessionAccess($sesi, $auth, $role);
        if ($accessError !== null) {
            return $accessError;
        }

        $jadwal = $sesi->jadwalPelajaran;
        if (!$jadwal) {
            return ['success' => false, 'message' => 'Data jadwal pelajaran untuk sesi ini tidak ditemukan.'];
        }

        $kelasNama = trim((string) ($sesi->kelas?->nama ?? ''));
        if ($kelasNama === '') {
            return ['success' => false, 'message' => 'Kelas sesi pelajaran tidak valid.'];
        }

        return [
            'success' => true,
            'data' => $this->buildPelajaranSessionPayload(
                $jadwal,
                $sesi->tanggal?->toDateString() ?? Carbon::today()->toDateString(),
                $sesi,
                $kelasNama
            ),
        ];
    }


    protected function dispatchWaAttendanceNotification(WaGatewayService $service, Siswa $siswa, array $context): void
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

            dispatch(function () use ($siswaId, $nisn, $context): void {
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
            $service->notifyAttendance($siswa, $context);
        } catch (\Throwable $e) {
            Log::warning('WA attendance notification failed', [
                'nisn' => $siswa->nisn ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

}