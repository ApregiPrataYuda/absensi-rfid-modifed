<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JadwalPelajaranController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.jadwal-pelajaran');
    }

    public function data(Request $request): JsonResponse
    {
        $kelasId = (int) $request->query('kelas_id', 0);
        $guruId = (int) $request->query('guru_id', 0);
        $hari = (int) $request->query('hari', 0);

        $query = JadwalPelajaran::query()
            ->with([
                'kelas:id,nama',
                'guru:id,name,username',
            ])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->orderBy('id');

        if ($kelasId > 0) {
            $query->where('kelas_id', $kelasId);
        }
        if ($guruId > 0) {
            $query->where('guru_id', $guruId);
        }
        if ($hari >= 1 && $hari <= 7) {
            $query->where('hari', $hari);
        }

        $rows = $query->get()->map(fn (JadwalPelajaran $row) => $this->formatRow($row))->values();

        return response()->json([
            'data' => $rows,
            'kelas' => Kelas::query()->orderBy('nama')->get(['id', 'nama']),
            'guru' => $this->guruOptions(),
            'day_options' => $this->dayOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureNoScheduleConflict($validated);

        $jadwal = JadwalPelajaran::query()->create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jadwal pelajaran berhasil ditambahkan.',
                'data' => $this->formatRow($jadwal->load(['kelas:id,nama', 'guru:id,name,username'])),
            ]);
        }

        return redirect()
            ->route('jadwal-pelajaran.index')
            ->with('success', 'Jadwal pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse|JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureNoScheduleConflict($validated, (int) $jadwalPelajaran->id);

        $jadwalPelajaran->update($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jadwal pelajaran berhasil diperbarui.',
                'data' => $this->formatRow($jadwalPelajaran->load(['kelas:id,nama', 'guru:id,name,username'])),
            ]);
        }

        return redirect()
            ->route('jadwal-pelajaran.index')
            ->with('success', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(Request $request, JadwalPelajaran $jadwalPelajaran): RedirectResponse|JsonResponse
    {
        $jadwalPelajaran->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Jadwal pelajaran berhasil dihapus.',
            ]);
        }

        return redirect()
            ->route('jadwal-pelajaran.index')
            ->with('success', 'Jadwal pelajaran berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'guru_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

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
            'hari' => ['required', 'integer', 'between:1,7'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'mata_pelajaran' => ['required', 'string', 'max:120'],
            'ruang' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureNoScheduleConflict(array $payload, int $ignoreId = 0): void
    {
        $kelasId = (int) ($payload['kelas_id'] ?? 0);
        $hari = (int) ($payload['hari'] ?? 0);
        $jamMulai = trim((string) ($payload['jam_mulai'] ?? ''));
        $jamSelesai = trim((string) ($payload['jam_selesai'] ?? ''));

        if ($kelasId <= 0 || $hari < 1 || $hari > 7 || $jamMulai === '' || $jamSelesai === '') {
            return;
        }

        $query = JadwalPelajaran::query()
            ->where('kelas_id', $kelasId)
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai);

        if ($ignoreId > 0) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Jadwal bentrok dengan sesi lain di kelas dan hari yang sama.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function dayOptions(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
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
    protected function formatRow(JadwalPelajaran $row): array
    {
        $dayOptions = $this->dayOptions();

        return [
            'id' => (int) $row->id,
            'kelas_id' => (int) $row->kelas_id,
            'kelas_nama' => (string) ($row->kelas?->nama ?? '-'),
            'guru_id' => $row->guru_id !== null ? (int) $row->guru_id : null,
            'guru_nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
            'hari' => (int) $row->hari,
            'hari_label' => (string) ($dayOptions[(int) $row->hari] ?? ('Hari ' . $row->hari)),
            'jam_mulai' => substr((string) $row->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $row->jam_selesai, 0, 5),
            'mata_pelajaran' => (string) $row->mata_pelajaran,
            'ruang' => (string) ($row->ruang ?? ''),
            'keterangan' => (string) ($row->keterangan ?? ''),
        ];
    }
}
