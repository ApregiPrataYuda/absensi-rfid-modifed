<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\IzinSakitRequest as IzinSakitRequestModel;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Konfigurasi;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AcademicCalendarService;
use App\Services\TelegramBotService;
use App\Services\WaGatewayService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IzinSakitRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.izin-sakit');
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $canApprove = $this->canApprove($user);
        $canManage = $this->canManage($user);
        $canRequest = $this->canRequest($user);

        $query = IzinSakitRequestModel::query()
            ->with([
                'siswa:id,nama,nisn,kelas',
                'requestedBy:id,name,username',
                'approvedBy:id,name,username',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $status = trim((string) $request->query('status', ''));
        if (in_array($status, array_keys($this->statusOptions()), true)) {
            $query->where('status', $status);
        }

        $jenis = trim((string) $request->query('jenis', ''));
        if (in_array($jenis, array_keys($this->jenisOptions()), true)) {
            $query->where('jenis', $jenis);
        }

        $tanggalDari = trim((string) $request->query('tanggal_dari', ''));
        if ($tanggalDari !== '') {
            $query->whereDate('tanggal_mulai', '>=', $tanggalDari);
        }

        $tanggalSampai = trim((string) $request->query('tanggal_sampai', ''));
        if ($tanggalSampai !== '') {
            $query->whereDate('tanggal_selesai', '<=', $tanggalSampai);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('alasan', 'like', '%' . $search . '%')
                    ->orWhereHas('siswa', function ($siswaQuery) use ($search): void {
                        $siswaQuery->where('nama', 'like', '%' . $search . '%')
                            ->orWhere('nisn', 'like', '%' . $search . '%')
                            ->orWhere('kelas', 'like', '%' . $search . '%');
                    });
            });
        }

        $userId = (int) ($user?->id ?? 0);
        if (!$canManage) {
            if ($user && $user->hasRole('wakel')) {
                $kelasWakel = trim((string) ($user->kelas ?? ''));
                if ($kelasWakel !== '') {
                    $query->whereHas('siswa', fn ($siswaQuery) => $siswaQuery->where('kelas', $kelasWakel));
                } elseif ($userId > 0) {
                    $query->where('requested_by_user_id', $userId);
                }
            } elseif (!$canApprove && $userId > 0) {
                $query->where('requested_by_user_id', $userId);
            }
        }

        $rows = $query->get()->map(function (IzinSakitRequestModel $row) use ($userId) {
            return $this->formatRow($row, $userId);
        })->values();

        $requestableSiswa = $canRequest || $canManage ? $this->requestableSiswa($user, $canManage) : collect();
        $isStudentMode = (bool) ($user && $user->hasRole('siswa'));
        if ($isStudentMode && $user) {
            $selfSiswa = Siswa::query()
                ->where('nisn', trim((string) $user->username))
                ->first();
            if ($selfSiswa) {
                $requestableSiswa = collect([[
                    'id' => (int) $selfSiswa->id,
                    'nama' => (string) $selfSiswa->nama,
                    'nisn' => (string) $selfSiswa->nisn,
                    'kelas' => (string) ($selfSiswa->kelas ?? ''),
                ]]);
            }
        }

        return response()->json([
            'data' => $rows,
            'status_options' => $this->statusOptions(),
            'jenis_options' => $this->jenisOptions(),
            'siswa' => $requestableSiswa->values(),
            'can_request' => $canRequest || $canManage,
            'can_approve' => $canApprove || $canManage,
            'can_manage' => $canManage,
            'is_student' => $isStudentMode,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$this->canRequest($user) && !$this->canManage($user)) {
            return $this->errorResponse($request, 'Anda tidak memiliki izin membuat pengajuan.');
        }

        $validated = $this->validateStorePayload($request);

        // Hard safety: akun siswa selalu dipaksa ke data siswa miliknya sendiri.
        if ($user && $user->hasRole('siswa')) {
            $siswaMilikUser = Siswa::query()
                ->where('nisn', trim((string) $user->username))
                ->first();

            if (!$siswaMilikUser) {
                return $this->errorResponse($request, 'Akun siswa belum terhubung ke data siswa (NISN tidak cocok).');
            }

            $validated['siswa_id'] = (int) $siswaMilikUser->id;
        }

        $siswa = Siswa::query()->find((int) $validated['siswa_id']);
        if (!$siswa) {
            return $this->errorResponse($request, 'Data siswa tidak ditemukan.');
        }

        $this->assertCanRequestForSiswa($user, $siswa);

        $tanggalMulai = (string) $validated['tanggal_mulai'];
        $tanggalSelesai = (string) $validated['tanggal_selesai'];
        $kelasSiswa = trim((string) ($siswa->kelas ?? ''));
        $holidayDateMap = app(AcademicCalendarService::class)->getHolidayDateMap(
            $tanggalMulai,
            $tanggalSelesai,
            $kelasSiswa
        );
        $totalDays = iterator_count(CarbonPeriod::create($tanggalMulai, $tanggalSelesai));
        if ($totalDays > 0 && count($holidayDateMap) >= $totalDays) {
            return $this->errorResponse(
                $request,
                'Rentang tanggal yang dipilih seluruhnya hari libur. Tidak perlu mengajukan izin/sakit.'
            );
        }

        $overlap = $this->findOverlappingOpenRequest(
            (int) $validated['siswa_id'],
            $tanggalMulai,
            $tanggalSelesai
        );
        if ($overlap) {
            $statusLabel = (string) $overlap->status === IzinSakitRequestModel::STATUS_APPROVED ? 'approved' : 'pending';
            $existingMulai = $overlap->tanggal_mulai?->toDateString() ?? '-';
            $existingSelesai = $overlap->tanggal_selesai?->toDateString() ?? '-';
            $existingRange = $existingMulai === $existingSelesai
                ? $existingMulai
                : ($existingMulai . ' s/d ' . $existingSelesai);

            return $this->errorResponse(
                $request,
                'Sudah ada pengajuan izin/sakit berstatus ' . $statusLabel . ' pada rentang ' . $existingRange . '.'
            );
        }

        $izinSakit = IzinSakitRequestModel::query()->create([
            'siswa_id' => (int) $validated['siswa_id'],
            'jenis' => (string) $validated['jenis'],
            'tanggal_mulai' => (string) $validated['tanggal_mulai'],
            'tanggal_selesai' => (string) $validated['tanggal_selesai'],
            'alasan' => (string) $validated['alasan'],
            'status' => IzinSakitRequestModel::STATUS_PENDING,
            'requested_by_user_id' => (int) ($user?->id ?? 0) ?: null,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'approval_note' => null,
        ]);

        $izinSakit->load(['siswa:id,nama,nisn,kelas', 'requestedBy:id,name,username', 'approvedBy:id,name,username']);
        $this->sendIzinSakitNotification($izinSakit, 'created');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Pengajuan izin/sakit berhasil dibuat.',
                'data' => $this->formatRow($izinSakit, (int) ($user?->id ?? 0)),
            ]);
        }

        return redirect()
            ->route('izin-sakit.index')
            ->with('success', 'Pengajuan izin/sakit berhasil dibuat.');
    }

    public function approve(Request $request, IzinSakitRequestModel $izinSakitRequest): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$this->canApprove($user) && !$this->canManage($user)) {
            return $this->errorResponse($request, 'Anda tidak memiliki izin menyetujui pengajuan.');
        }

        $this->assertCanApproveRequest($user, $izinSakitRequest);

        if ((string) $izinSakitRequest->status !== IzinSakitRequestModel::STATUS_PENDING) {
            return $this->errorResponse($request, 'Hanya pengajuan berstatus pending yang bisa disetujui.');
        }

        $validated = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($izinSakitRequest, $user, $validated): void {
            $izinSakitRequest->status = IzinSakitRequestModel::STATUS_APPROVED;
            $izinSakitRequest->approved_by_user_id = (int) ($user?->id ?? 0) ?: null;
            $izinSakitRequest->approved_at = now();
            $izinSakitRequest->approval_note = trim((string) ($validated['approval_note'] ?? '')) ?: null;
            $izinSakitRequest->save();

            $this->applyAbsensiForApproval($izinSakitRequest);
        });

        $izinSakitRequest->load(['siswa:id,nama,nisn,kelas', 'requestedBy:id,name,username', 'approvedBy:id,name,username']);
        $this->sendIzinSakitNotification($izinSakitRequest, 'approved');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Pengajuan berhasil disetujui dan absensi diperbarui.',
                'data' => $this->formatRow($izinSakitRequest, (int) ($user?->id ?? 0)),
            ]);
        }

        return redirect()
            ->route('izin-sakit.index')
            ->with('success', 'Pengajuan berhasil disetujui dan absensi diperbarui.');
    }

    public function reject(Request $request, IzinSakitRequestModel $izinSakitRequest): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (!$this->canApprove($user) && !$this->canManage($user)) {
            return $this->errorResponse($request, 'Anda tidak memiliki izin menolak pengajuan.');
        }

        $this->assertCanApproveRequest($user, $izinSakitRequest);

        if ((string) $izinSakitRequest->status !== IzinSakitRequestModel::STATUS_PENDING) {
            return $this->errorResponse($request, 'Hanya pengajuan berstatus pending yang bisa ditolak.');
        }

        $validated = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $izinSakitRequest->status = IzinSakitRequestModel::STATUS_REJECTED;
        $izinSakitRequest->approved_by_user_id = (int) ($user?->id ?? 0) ?: null;
        $izinSakitRequest->approved_at = now();
        $izinSakitRequest->approval_note = trim((string) ($validated['approval_note'] ?? '')) ?: null;
        $izinSakitRequest->save();

        $izinSakitRequest->load(['siswa:id,nama,nisn,kelas', 'requestedBy:id,name,username', 'approvedBy:id,name,username']);
        $this->sendIzinSakitNotification($izinSakitRequest, 'rejected');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Pengajuan berhasil ditolak.',
                'data' => $this->formatRow($izinSakitRequest, (int) ($user?->id ?? 0)),
            ]);
        }

        return redirect()
            ->route('izin-sakit.index')
            ->with('success', 'Pengajuan berhasil ditolak.');
    }

    public function destroy(Request $request, IzinSakitRequestModel $izinSakitRequest): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        $isOwnerPending = (int) ($izinSakitRequest->requested_by_user_id ?? 0) === $userId
            && (string) $izinSakitRequest->status === IzinSakitRequestModel::STATUS_PENDING;

        if (!$this->canManage($user) && !$isOwnerPending) {
            return $this->errorResponse($request, 'Anda tidak memiliki izin menghapus pengajuan ini.');
        }

        $izinSakitRequest->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Pengajuan berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('izin-sakit.index')
            ->with('success', 'Pengajuan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateStorePayload(Request $request): array
    {
        return $request->validate([
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')],
            'jenis' => ['required', Rule::in(array_keys($this->jenisOptions()))],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'max:2000'],
        ]);
    }

    protected function findOverlappingOpenRequest(int $siswaId, string $tanggalMulai, string $tanggalSelesai): ?IzinSakitRequestModel
    {
        return IzinSakitRequestModel::query()
            ->where('siswa_id', $siswaId)
            ->whereIn('status', [
                IzinSakitRequestModel::STATUS_PENDING,
                IzinSakitRequestModel::STATUS_APPROVED,
            ])
            ->whereDate('tanggal_mulai', '<=', $tanggalSelesai)
            ->whereDate('tanggal_selesai', '>=', $tanggalMulai)
            ->orderByDesc('tanggal_mulai')
            ->first();
    }

    protected function applyAbsensiForApproval(IzinSakitRequestModel $request): void
    {
        $siswa = $request->siswa()->first();
        if (!$siswa) {
            return;
        }

        $jenis = strtolower(trim((string) $request->jenis));
        $statusLabel = $jenis === IzinSakitRequestModel::JENIS_SAKIT ? 'Sakit' : 'Izin';
        $baseKeterangan = $statusLabel . ' (Approval)';
        $alasanRingkas = trim((string) ($request->alasan ?? ''));
        if ($alasanRingkas !== '') {
            $baseKeterangan .= ': ' . mb_substr($alasanRingkas, 0, 150);
        }

        $mulai = $request->tanggal_mulai?->toDateString();
        $selesai = $request->tanggal_selesai?->toDateString();
        if ($mulai === null || $selesai === null) {
            return;
        }

        $kelasSiswa = trim((string) ($siswa->kelas ?? ''));
        $holidayDateMap = app(AcademicCalendarService::class)->getHolidayDateMap($mulai, $selesai, $kelasSiswa);

        foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
            $tanggalStr = $tanggal->toDateString();
            if (isset($holidayDateMap[$tanggalStr])) {
                continue;
            }

            Absensi::query()->updateOrCreate(
                [
                    'tanggal' => $tanggalStr,
                    'siswa_id' => (int) $siswa->id,
                ],
                [
                    'nisn' => (string) $siswa->nisn,
                    'nama' => (string) $siswa->nama,
                    'kelas' => (string) $siswa->kelas,
                    'jam_datang' => null,
                    'jam_pulang' => null,
                    'status' => $statusLabel,
                    'keterangan' => $baseKeterangan,
                ]
            );
        }
    }

    protected function canRequest(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin', 'kepsek', 'wakel', 'siswa'])
            && $user->hasAnyPermission(['izin-sakit.request', 'izin-sakit.manage']);
    }

    protected function canApprove(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('siswa')) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin', 'kepsek', 'wakel'])
            && $user->hasAnyPermission(['izin-sakit.approve', 'izin-sakit.manage']);
    }

    protected function canManage(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('siswa') || $user->hasRole('wakel')) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'admin', 'kepsek'])
            && $user->hasPermissionTo('izin-sakit.manage');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,nama:string,nisn:string,kelas:string}>
     */
    protected function requestableSiswa(?User $user, bool $canManage)
    {
        if (!$user) {
            return collect();
        }

        $query = Siswa::query()
            ->orderBy('kelas')
            ->orderBy('nama');

        if ($canManage) {
            return $query->get(['id', 'nama', 'nisn', 'kelas'])
                ->map(fn (Siswa $row) => [
                    'id' => (int) $row->id,
                    'nama' => (string) $row->nama,
                    'nisn' => (string) $row->nisn,
                    'kelas' => (string) ($row->kelas ?? ''),
                ])
                ->values();
        }

        if ($user->hasRole('siswa')) {
            $query->where('nisn', trim((string) $user->username));
        } elseif ($user->hasRole('wakel')) {
            $kelas = trim((string) ($user->kelas ?? ''));
            if ($kelas === '') {
                return collect();
            }
            $query->where('kelas', $kelas);
        } else {
            return collect();
        }

        return $query->get(['id', 'nama', 'nisn', 'kelas'])
            ->map(fn (Siswa $row) => [
                'id' => (int) $row->id,
                'nama' => (string) $row->nama,
                'nisn' => (string) $row->nisn,
                'kelas' => (string) ($row->kelas ?? ''),
            ])
            ->values();
    }

    protected function assertCanRequestForSiswa(?User $user, Siswa $siswa): void
    {
        if (!$user) {
            throw ValidationException::withMessages([
                'siswa_id' => 'Akun tidak valid.',
            ]);
        }

        if ($this->canManage($user)) {
            return;
        }

        if ($user->hasRole('siswa')) {
            $nisnUser = trim((string) $user->username);
            if ($nisnUser === '' || $nisnUser !== (string) $siswa->nisn) {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Anda hanya dapat mengajukan untuk akun siswa sendiri.',
                ]);
            }
        }

        if ($user->hasRole('wakel')) {
            $kelasWakel = trim((string) ($user->kelas ?? ''));
            if ($kelasWakel === '') {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Akun wali kelas belum ditautkan ke kelas.',
                ]);
            }

            if ($kelasWakel !== (string) $siswa->kelas) {
                throw ValidationException::withMessages([
                    'siswa_id' => 'Anda hanya dapat mengajukan untuk siswa di kelas yang Anda ampu.',
                ]);
            }
        }
    }

    protected function assertCanApproveRequest(?User $user, IzinSakitRequestModel $request): void
    {
        if (!$user || $this->canManage($user)) {
            return;
        }

        if ($user->hasRole('wakel')) {
            $kelasWakel = trim((string) ($user->kelas ?? ''));
            if ($kelasWakel === '') {
                throw ValidationException::withMessages([
                    'general' => 'Akun wali kelas belum ditautkan ke kelas.',
                ]);
            }

            $kelasSiswa = trim((string) ($request->siswa?->kelas ?? ''));
            if ($kelasSiswa !== $kelasWakel) {
                throw ValidationException::withMessages([
                    'general' => 'Anda hanya dapat approval pengajuan untuk kelas yang Anda ampu.',
                ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return [
            IzinSakitRequestModel::STATUS_PENDING => 'Pending',
            IzinSakitRequestModel::STATUS_APPROVED => 'Approved',
            IzinSakitRequestModel::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function jenisOptions(): array
    {
        return [
            IzinSakitRequestModel::JENIS_IZIN => 'Izin',
            IzinSakitRequestModel::JENIS_SAKIT => 'Sakit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRow(IzinSakitRequestModel $row, int $userId = 0): array
    {
        return [
            'id' => (int) $row->id,
            'siswa_id' => (int) $row->siswa_id,
            'siswa_nama' => (string) ($row->siswa?->nama ?? '-'),
            'siswa_nisn' => (string) ($row->siswa?->nisn ?? '-'),
            'kelas' => (string) ($row->siswa?->kelas ?? '-'),
            'jenis' => (string) $row->jenis,
            'tanggal_mulai' => $row->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $row->tanggal_selesai?->toDateString(),
            'alasan' => (string) ($row->alasan ?? ''),
            'status' => (string) $row->status,
            'requested_by_user_id' => $row->requested_by_user_id !== null ? (int) $row->requested_by_user_id : null,
            'requested_by_name' => (string) ($row->requestedBy?->name ?: ($row->requestedBy?->username ?? '-')),
            'approved_by_user_id' => $row->approved_by_user_id !== null ? (int) $row->approved_by_user_id : null,
            'approved_by_name' => (string) ($row->approvedBy?->name ?: ($row->approvedBy?->username ?? '-')),
            'approved_at' => $row->approved_at?->toDateTimeString(),
            'approval_note' => (string) ($row->approval_note ?? ''),
            'requested_by_me' => $userId > 0 && (int) ($row->requested_by_user_id ?? 0) === $userId,
        ];
    }

    protected function sendIzinSakitNotification(IzinSakitRequestModel $izinSakitRequest, string $event): void
    {
        try {
            // Selalu ambil ulang relasi siswa penuh agar field no_hp tersedia untuk notifikasi WA.
            $siswa = $izinSakitRequest->siswa()->first();
            if (!$siswa) {
                return;
            }

            $approvedByName = trim((string) ($izinSakitRequest->approvedBy?->name ?: ($izinSakitRequest->approvedBy?->username ?? '')));
            $context = [
                'event' => $event,
                'jenis' => (string) ($izinSakitRequest->jenis ?? ''),
                'tanggal_mulai' => $izinSakitRequest->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $izinSakitRequest->tanggal_selesai?->toDateString(),
                'alasan' => (string) ($izinSakitRequest->alasan ?? ''),
                'approval_note' => (string) ($izinSakitRequest->approval_note ?? ''),
                'approved_by' => $approvedByName,
            ];

            $notificationSettings = $this->getIzinSakitNotificationSettings();
            $sent = false;

            if ($notificationSettings['enabled']) {
                if ($notificationSettings['wa']) {
                    $sent = app(WaGatewayService::class)->notifyIzinSakit($siswa, $context) || $sent;
                }

                if ($notificationSettings['telegram']) {
                    $sent = app(TelegramBotService::class)->notifyIzinSakit($siswa, $context) || $sent;
                }
            }

            if ($notificationSettings['enabled'] && ! $sent) {
                Log::warning('Izin/sakit student notification not sent from controller', [
                    'izin_sakit_request_id' => (int) ($izinSakitRequest->id ?? 0),
                    'siswa_id' => (int) ($izinSakitRequest->siswa_id ?? 0),
                    'event' => $event,
                    'channels' => $notificationSettings,
                ]);
            }

            if ($event === 'created') {
                $this->sendWaIzinSakitReviewerFallback($izinSakitRequest, $siswa);
            }
        } catch (\Throwable $e) {
            Log::warning('Izin/sakit student notification failed', [
                'izin_sakit_request_id' => (int) ($izinSakitRequest->id ?? 0),
                'siswa_id' => (int) ($izinSakitRequest->siswa_id ?? 0),
                'event' => $event,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{enabled:bool, wa:bool, telegram:bool}
     */
    protected function getIzinSakitNotificationSettings(): array
    {
        $rows = Konfigurasi::query()
            ->whereIn('key', [
                'izin_sakit_notif_enabled',
                'izin_sakit_notif_channel',
                'wa_notif_izin_sakit_enabled',
                'telegram_notif_izin_sakit_enabled',
            ])
            ->pluck('value', 'key')
            ->all();

        $enabled = array_key_exists('izin_sakit_notif_enabled', $rows)
            ? (string) ($rows['izin_sakit_notif_enabled'] ?? '0') === '1'
            : ((string) ($rows['wa_notif_izin_sakit_enabled'] ?? '0') === '1'
                || (string) ($rows['telegram_notif_izin_sakit_enabled'] ?? '0') === '1');

        $channel = strtolower(trim((string) ($rows['izin_sakit_notif_channel'] ?? '')));
        if (! in_array($channel, ['whatsapp', 'telegram', 'both'], true)) {
            $waEnabled = (string) ($rows['wa_notif_izin_sakit_enabled'] ?? '0') === '1';
            $telegramEnabled = (string) ($rows['telegram_notif_izin_sakit_enabled'] ?? '0') === '1';
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

    protected function sendWaIzinSakitReviewerFallback(IzinSakitRequestModel $izinSakitRequest, Siswa $siswa): void
    {
        try {
            $waGatewayService = app(WaGatewayService::class);
            $baseContext = $this->buildSiswaNotificationContext($siswa) + [
                'jenis' => (string) ($izinSakitRequest->jenis ?? ''),
                'tanggal_mulai' => $izinSakitRequest->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $izinSakitRequest->tanggal_selesai?->toDateString(),
                'alasan' => (string) ($izinSakitRequest->alasan ?? ''),
                'siswa_nama' => trim((string) ($siswa->nama ?? '')),
            ];

            $kelas = trim((string) ($siswa->kelas ?? ''));
            $wakelUsers = collect();
            $reviewerType = 'wakel';

            $waliKelasUser = $this->resolveWaliKelasUserByClass($kelas);
            if ($waliKelasUser !== null) {
                $wakelUsers->push($waliKelasUser);
            }

            if ($wakelUsers->isEmpty() && $kelas !== '') {
                $wakelUsers = $this->resolveGuruUsersByScheduleClass($kelas);
                if ($wakelUsers->isNotEmpty()) {
                    $reviewerType = 'guru';
                }
            }

            if ($wakelUsers->isEmpty() && $kelas !== '') {
                $wakelUsers = $this->collectRoleUsersWithPhone('wakel', function ($query) use ($kelas): void {
                    $query->where('kelas', $kelas);
                });
                if ($wakelUsers->isNotEmpty()) {
                    $reviewerType = 'wakel';
                }
            }

            if ($wakelUsers->isEmpty() && $kelas !== '') {
                $wakelUsers = $this->collectRoleUsersWithPhone('guru', function ($query) use ($kelas): void {
                    $query->where('kelas', $kelas);
                });
                if ($wakelUsers->isNotEmpty()) {
                    $reviewerType = 'guru';
                }
            }

            Log::info('WA izin/sakit reviewer candidates resolved', [
                'izin_sakit_request_id' => (int) ($izinSakitRequest->id ?? 0),
                'siswa_id' => (int) ($siswa->id ?? 0),
                'kelas' => $kelas !== '' ? $kelas : null,
                'receiver_type' => $reviewerType,
                'candidate_count' => (int) $wakelUsers->count(),
            ]);

            $sentToWakel = $this->dispatchIzinSakitReviewerToUsers(
                $waGatewayService,
                $wakelUsers,
                $reviewerType,
                $baseContext
            );

            if ($sentToWakel) {
                return;
            }

            $adminUsers = $this->collectRoleUsersWithPhone('admin');
            if ($adminUsers->isEmpty()) {
                $adminUsers = $this->collectRoleUsersWithPhone('super-admin');
            }

            $sentToAdmin = $this->dispatchIzinSakitReviewerToUsers(
                $waGatewayService,
                $adminUsers,
                'admin',
                $baseContext
            );

            if (!$sentToAdmin) {
                Log::warning('WA izin/sakit reviewer fallback failed', [
                    'izin_sakit_request_id' => (int) ($izinSakitRequest->id ?? 0),
                    'siswa_id' => (int) ($siswa->id ?? 0),
                    'kelas' => $kelas !== '' ? $kelas : null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WA izin/sakit reviewer notification failed', [
                'izin_sakit_request_id' => (int) ($izinSakitRequest->id ?? 0),
                'siswa_id' => (int) ($siswa->id ?? 0),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function buildSiswaNotificationContext(Siswa $siswa): array
    {
        $namaAyah = trim((string) ($siswa->nama_ayah ?? ''));
        $namaIbu = trim((string) ($siswa->nama_ibu ?? ''));
        $namaOrangTua = implode(' / ', array_values(array_filter([$namaAyah, $namaIbu], fn (string $value): bool => $value !== '')));

        $tanggalLahir = '';
        try {
            if ($siswa->tanggal_lahir instanceof \DateTimeInterface) {
                $tanggalLahir = $siswa->tanggal_lahir->format('d-m-Y');
            } elseif (trim((string) ($siswa->tanggal_lahir ?? '')) !== '') {
                $tanggalLahir = Carbon::parse((string) $siswa->tanggal_lahir)->format('d-m-Y');
            }
        } catch (\Throwable $e) {
            $tanggalLahir = trim((string) ($siswa->tanggal_lahir ?? ''));
        }

        return [
            'nama' => trim((string) ($siswa->nama ?? '')),
            'nisn' => trim((string) ($siswa->nisn ?? '')),
            'kelas' => trim((string) ($siswa->kelas ?? '')),
            'no_hp' => trim((string) ($siswa->no_hp ?? '')),
            'jenis_kelamin' => trim((string) ($siswa->jenis_kelamin ?? '')),
            'tanggal_lahir' => $tanggalLahir,
            'agama' => trim((string) ($siswa->agama ?? '')),
            'nama_ayah' => $namaAyah,
            'nama_ibu' => $namaIbu,
            'nama_orang_tua' => $namaOrangTua,
            'alamat' => trim((string) ($siswa->alamat ?? '')),
            'siswa_label' => $this->formatSiswaNotificationLabel($siswa),
        ];
    }

    protected function formatSiswaNotificationLabel(Siswa $siswa): string
    {
        $name = trim((string) ($siswa->nama ?? ''));
        $nisn = trim((string) ($siswa->nisn ?? ''));
        $kelas = trim((string) ($siswa->kelas ?? ''));

        return implode(' - ', array_values(array_filter([
            $name,
            $nisn !== '' ? 'NISN: ' . $nisn : '',
            $kelas !== '' ? 'Kelas ' . $kelas : '',
        ], fn (string $value): bool => $value !== '')));
    }

    protected function resolveWaliKelasUserByClass(string $kelas): ?User
    {
        $kelas = trim($kelas);
        if ($kelas === '') {
            return null;
        }

        $waliKelasUserId = (int) (Kelas::query()
            ->where('nama', $kelas)
            ->value('wali_kelas') ?? 0);

        if ($waliKelasUserId <= 0) {
            return null;
        }

        return User::query()
            ->whereKey($waliKelasUserId)
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
            ->first(['id', 'name', 'username', 'no_hp']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    protected function resolveGuruUsersByScheduleClass(string $kelas)
    {
        $kelas = trim($kelas);
        if ($kelas === '') {
            return collect();
        }

        $kelasId = (int) (Kelas::query()
            ->where('nama', $kelas)
            ->value('id') ?? 0);
        if ($kelasId <= 0) {
            return collect();
        }

        $guruIds = JadwalPelajaran::query()
            ->where('kelas_id', $kelasId)
            ->whereNotNull('guru_id')
            ->pluck('guru_id')
            ->filter(static fn ($id) => (int) $id > 0)
            ->unique()
            ->values();

        if ($guruIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $guruIds->all())
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''")
            ->get(['id', 'name', 'username', 'no_hp']);
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder):void|null  $extraFilter
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    protected function collectRoleUsersWithPhone(string $roleName, ?callable $extraFilter = null)
    {
        $query = User::query()
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $roleName))
            ->whereNotNull('no_hp')
            ->whereRaw("TRIM(COALESCE(no_hp, '')) <> ''");

        if ($extraFilter !== null) {
            $extraFilter($query);
        }

        return $query->get(['id', 'name', 'username', 'no_hp']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\User>  $users
     * @param  array<string, mixed>  $baseContext
     */
    protected function dispatchIzinSakitReviewerToUsers(
        WaGatewayService $waGatewayService,
        $users,
        string $receiverType,
        array $baseContext
    ): bool {
        if ($users->isEmpty()) {
            return false;
        }

        $sent = false;
        $seenPhones = [];

        foreach ($users as $user) {
            $rawPhone = trim((string) ($user->no_hp ?? ''));
            if ($rawPhone === '') {
                continue;
            }

            if (isset($seenPhones[$rawPhone])) {
                continue;
            }
            $seenPhones[$rawPhone] = true;

            $context = array_merge($baseContext, [
                'receiver_type' => $receiverType,
                'recipient_name' => (string) ($user->name ?: ($user->username ?? '')),
            ]);

            $isSent = $waGatewayService->notifyIzinSakitReviewer($rawPhone, $context);
            $sent = $isSent || $sent;
        }

        return $sent;
    }

    protected function errorResponse(Request $request, string $message, int $statusCode = 422): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
            ], $statusCode);
        }

        return redirect()
            ->route('izin-sakit.index')
            ->withErrors(['general' => $message]);
    }
}
