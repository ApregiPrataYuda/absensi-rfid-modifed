<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\JurnalMengajarHarian;
use App\Models\Kelas;
use App\Models\User;
use App\Services\AcademicCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JurnalMengajarHarianController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.jurnal-mengajar-harian');
    }

    public function data(Request $request): JsonResponse
    {
        $filters = [
            'tanggal_dari' => trim((string) $request->query('tanggal_dari', '')),
            'tanggal_sampai' => trim((string) $request->query('tanggal_sampai', '')),
            'kelas_id' => (int) $request->query('kelas_id', 0),
            'guru_id' => (int) $request->query('guru_id', 0),
            'status' => trim((string) $request->query('status', '')),
        ];

        $query = JurnalMengajarHarian::query()
            ->with([
                'kelas:id,nama',
                'guru:id,name,username',
                'jadwalPelajaran:id,mata_pelajaran,hari,jam_mulai,jam_selesai',
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($filters['tanggal_dari'] !== '') {
            $query->whereDate('tanggal', '>=', $filters['tanggal_dari']);
        }
        if ($filters['tanggal_sampai'] !== '') {
            $query->whereDate('tanggal', '<=', $filters['tanggal_sampai']);
        }
        if ($filters['kelas_id'] > 0) {
            $query->where('kelas_id', $filters['kelas_id']);
        }
        if ($filters['guru_id'] > 0) {
            $query->where('guru_id', $filters['guru_id']);
        }
        if (in_array($filters['status'], ['draft', 'selesai'], true)) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->get()->map(fn (JurnalMengajarHarian $row) => $this->formatRow($row))->values();

        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama', 'guru:id,name,username'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get()
            ->map(fn (JadwalPelajaran $row) => [
                'id' => (int) $row->id,
                'kelas_id' => (int) $row->kelas_id,
                'kelas_nama' => (string) ($row->kelas?->nama ?? '-'),
                'guru_id' => $row->guru_id !== null ? (int) $row->guru_id : null,
                'guru_nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
                'hari' => (int) $row->hari,
                'jam_mulai' => substr((string) $row->jam_mulai, 0, 5),
                'jam_selesai' => substr((string) $row->jam_selesai, 0, 5),
                'mata_pelajaran' => (string) $row->mata_pelajaran,
            ])
            ->values();

        return response()->json([
            'data' => $rows,
            'kelas' => Kelas::query()->orderBy('nama')->get(['id', 'nama']),
            'guru' => $this->guruOptions(),
            'jadwal' => $jadwal,
            'status_options' => [
                'draft' => 'Draft',
                'selesai' => 'Selesai',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureRelatedScheduleBelongs($validated);
        $this->assertTanggalAktifSekolah($validated);

        $jurnal = JurnalMengajarHarian::query()->create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jurnal mengajar berhasil ditambahkan.',
                'data' => $this->formatRow($jurnal->load(['kelas:id,nama', 'guru:id,name,username', 'jadwalPelajaran:id,mata_pelajaran,hari,jam_mulai,jam_selesai'])),
            ]);
        }

        return redirect()
            ->route('jurnal-mengajar.index')
            ->with('success', 'Jurnal mengajar berhasil ditambahkan.');
    }

    public function update(Request $request, JurnalMengajarHarian $jurnalMengajar): RedirectResponse|JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureRelatedScheduleBelongs($validated);
        $this->assertTanggalAktifSekolah($validated);

        $jurnalMengajar->update($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jurnal mengajar berhasil diperbarui.',
                'data' => $this->formatRow($jurnalMengajar->load(['kelas:id,nama', 'guru:id,name,username', 'jadwalPelajaran:id,mata_pelajaran,hari,jam_mulai,jam_selesai'])),
            ]);
        }

        return redirect()
            ->route('jurnal-mengajar.index')
            ->with('success', 'Jurnal mengajar berhasil diperbarui.');
    }

    public function destroy(Request $request, JurnalMengajarHarian $jurnalMengajar): RedirectResponse|JsonResponse
    {
        $jurnalMengajar->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jurnal mengajar berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('jurnal-mengajar.index')
            ->with('success', 'Jurnal mengajar berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'guru_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail): void {
                    $teacherRoleNames = ['guru', 'wakel'];
                    $hasTeacherRoleUsers = User::query()
                        ->whereHas('roles', fn ($query) => $query->whereIn('name', $teacherRoleNames))
                        ->exists();
                    if (!$hasTeacherRoleUsers) {
                        return;
                    }

                    $isValidRole = User::query()
                        ->whereKey((int) $value)
                        ->whereHas('roles', fn ($query) => $query->whereIn('name', $teacherRoleNames))
                        ->exists();
                    if (!$isValidRole) {
                        $fail('Guru harus akun dengan role guru atau wakel.');
                    }
                },
            ],
            'jadwal_pelajaran_id' => ['nullable', 'integer', Rule::exists('jadwal_pelajaran', 'id')],
            'mata_pelajaran' => ['required', 'string', 'max:120'],
            'topik_materi' => ['required', 'string', 'max:500'],
            'ringkasan_pembelajaran' => ['nullable', 'string'],
            'tugas_siswa' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'selesai'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureRelatedScheduleBelongs(array $payload): void
    {
        $jadwalId = (int) ($payload['jadwal_pelajaran_id'] ?? 0);
        if ($jadwalId <= 0) {
            return;
        }

        $jadwal = JadwalPelajaran::query()->find($jadwalId);
        if (!$jadwal) {
            throw ValidationException::withMessages([
                'jadwal_pelajaran_id' => 'Jadwal pelajaran tidak valid.',
            ]);
        }

        $kelasId = (int) ($payload['kelas_id'] ?? 0);
        $guruId = (int) ($payload['guru_id'] ?? 0);
        if ((int) $jadwal->kelas_id !== $kelasId) {
            throw ValidationException::withMessages([
                'jadwal_pelajaran_id' => 'Jadwal pelajaran tidak sesuai dengan kelas yang dipilih.',
            ]);
        }

        if ($jadwal->guru_id !== null && (int) $jadwal->guru_id !== $guruId) {
            throw ValidationException::withMessages([
                'jadwal_pelajaran_id' => 'Jadwal pelajaran tidak sesuai dengan guru yang dipilih.',
            ]);
        }

        $tanggalRaw = trim((string) ($payload['tanggal'] ?? ''));
        if ($tanggalRaw !== '') {
            $hariTanggal = (int) Carbon::parse($tanggalRaw)->dayOfWeekIso;
            if ((int) $jadwal->hari !== $hariTanggal) {
                throw ValidationException::withMessages([
                    'jadwal_pelajaran_id' => 'Jadwal pelajaran tidak sesuai dengan hari pada tanggal yang dipilih.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertTanggalAktifSekolah(array $payload): void
    {
        $kelasId = (int) ($payload['kelas_id'] ?? 0);
        $tanggalRaw = trim((string) ($payload['tanggal'] ?? ''));
        if ($kelasId <= 0 || $tanggalRaw === '') {
            return;
        }

        $kelasNama = trim((string) (Kelas::query()->whereKey($kelasId)->value('nama') ?? ''));
        if ($kelasNama === '') {
            return;
        }

        $holidayName = app(AcademicCalendarService::class)->resolveHolidayNameForDate($tanggalRaw, $kelasNama);
        if ($holidayName === null) {
            return;
        }

        $tanggalLabel = $tanggalRaw;
        try {
            $tanggalLabel = Carbon::parse($tanggalRaw)->format('d-m-Y');
        } catch (\Throwable $e) {
            // Gunakan format asal jika parsing gagal.
        }

        throw ValidationException::withMessages([
            'tanggal' => 'Tanggal ' . $tanggalLabel . ' adalah hari libur (' . $holidayName . '), jurnal tidak dapat dibuat.',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int,name:string,username:string}>
     */
    protected function guruOptions()
    {
        $teacherRoleNames = ['guru', 'wakel'];
        $hasTeacherRoleUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $teacherRoleNames))
            ->exists();

        $guruListQuery = User::query();
        if ($hasTeacherRoleUsers) {
            $guruListQuery->whereHas('roles', fn ($query) => $query->whereIn('name', $teacherRoleNames));
        }

        return $guruListQuery
            ->orderBy('name')
            ->orderBy('username')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $row) => [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?: $row->username),
                'username' => (string) $row->username,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRow(JurnalMengajarHarian $row): array
    {
        return [
            'id' => (int) $row->id,
            'tanggal' => $row->tanggal?->format('Y-m-d'),
            'kelas_id' => (int) $row->kelas_id,
            'kelas_nama' => (string) ($row->kelas?->nama ?? '-'),
            'guru_id' => (int) $row->guru_id,
            'guru_nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
            'jadwal_pelajaran_id' => $row->jadwal_pelajaran_id !== null ? (int) $row->jadwal_pelajaran_id : null,
            'mata_pelajaran' => (string) $row->mata_pelajaran,
            'topik_materi' => (string) $row->topik_materi,
            'ringkasan_pembelajaran' => (string) ($row->ringkasan_pembelajaran ?? ''),
            'tugas_siswa' => (string) ($row->tugas_siswa ?? ''),
            'catatan' => (string) ($row->catatan ?? ''),
            'status' => (string) $row->status,
        ];
    }
}
