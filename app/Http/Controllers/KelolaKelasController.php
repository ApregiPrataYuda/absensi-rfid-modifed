<?php

namespace App\Http\Controllers;

use App\Models\JadwalHarian;
use App\Models\Kelas;
use App\Models\Konfigurasi;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KelolaKelasController extends Controller
{
    public function index()
    {
        return view('pages.kelola-kelas');
    }

    public function data(Request $request): JsonResponse
    {
        $kelas = Kelas::query()
            ->with([
                'waliKelasUser:id,name,username',
                'jadwalHarian:id,kelas_id,hari,is_libur,jam_masuk_mulai,jam_masuk_akhir,jam_masuk_telat,jam_pulang_mulai,jam_pulang_akhir,keterangan',
            ])
            ->select([
                'id',
                'nama',
                'wali_kelas',
                'kapasitas',
            ])
            ->withCount('siswa')
            ->orderBy('nama')
            ->get()
            ->map(fn (Kelas $row) => $this->formatKelasResponse($row))
            ->values();

        $guru = User::role('wakel')
            ->orderBy('name')
            ->orderBy('username')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $row) => [
                'id' => $row->id,
                'name' => $row->name ?: $row->username,
                'username' => $row->username,
            ])
            ->values();

        return response()->json([
            'data' => $kelas,
            'guru' => $guru,
            'default_jam' => $this->getGlobalJamConfig(),
        ]);
    }

    public function classOptions(): JsonResponse
    {
        $kelas = Kelas::query()
            ->orderBy('nama')
            ->pluck('nama')
            ->map(fn ($nama) => trim((string) $nama))
            ->filter()
            ->values();

        return response()->json($kelas);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('kelas', 'nama')],
            'wali_kelas' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (!User::role('wakel')->whereKey((int) $value)->exists()) {
                        $fail('Wali kelas harus akun dengan role wakel.');
                    }
                },
            ],
            'kapasitas' => ['nullable', 'integer', 'min:1'],
            'jadwal_harian' => ['nullable', 'array'],
            'jadwal_harian.*.hari' => ['required_with:jadwal_harian', 'integer', 'between:1,7', 'distinct'],
            'jadwal_harian.*.is_libur' => ['nullable', 'boolean'],
            'jadwal_harian.*.jam_masuk_mulai' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_masuk_akhir' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_masuk_telat' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_pulang_mulai' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_pulang_akhir' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $jadwalInput = is_array($validated['jadwal_harian'] ?? null) ? $validated['jadwal_harian'] : [];
        unset($validated['jadwal_harian']);

        $kelas = null;
        DB::transaction(function () use (&$kelas, $validated, $jadwalInput) {
            $kelas = Kelas::query()->create($validated);
            $jadwalToStore = !empty($jadwalInput)
                ? $jadwalInput
                : $this->buildDefaultJadwalFromConfig();
            $this->syncJadwalHarian($kelas, $jadwalToStore);
            $this->syncWaliKelasBinding($kelas, null, null);
        });

        $kelas->load([
            'waliKelasUser:id,name,username',
            'jadwalHarian:id,kelas_id,hari,is_libur,jam_masuk_mulai,jam_masuk_akhir,jam_masuk_telat,jam_pulang_mulai,jam_pulang_akhir,keterangan',
        ])->loadCount('siswa');

        return response()->json([
            'message' => 'Gedung - Lantai berhasil ditambahkan.',
            'data' => $this->formatKelasResponse($kelas),
        ]);
    }

    public function update(Request $request, Kelas $kelas): JsonResponse
    {
        $previousNamaKelas = $this->normalizeKelasValue($kelas->nama);
        $previousWaliId = $kelas->wali_kelas !== null ? (int) $kelas->wali_kelas : null;

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('kelas', 'nama')->ignore($kelas->id)],
            'wali_kelas' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (!User::role('wakel')->whereKey((int) $value)->exists()) {
                        $fail('Mandor harus akun dengan role mandor.');
                    }
                },
            ],
            'kapasitas' => ['nullable', 'integer', 'min:1'],
            'jadwal_harian' => ['nullable', 'array'],
            'jadwal_harian.*.hari' => ['required_with:jadwal_harian', 'integer', 'between:1,7', 'distinct'],
            'jadwal_harian.*.is_libur' => ['nullable', 'boolean'],
            'jadwal_harian.*.jam_masuk_mulai' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_masuk_akhir' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_masuk_telat' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_pulang_mulai' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.jam_pulang_akhir' => ['nullable', 'date_format:H:i'],
            'jadwal_harian.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $hasJadwalPayload = $request->has('jadwal_harian');
        $jadwalInput = is_array($validated['jadwal_harian'] ?? null) ? $validated['jadwal_harian'] : [];
        unset($validated['jadwal_harian']);

        DB::transaction(function () use ($kelas, $validated, $hasJadwalPayload, $jadwalInput, $previousWaliId, $previousNamaKelas) {
            $kelas->update($validated);
            $this->syncWaliKelasBinding($kelas, $previousWaliId, $previousNamaKelas);
            if ($hasJadwalPayload && !empty($jadwalInput)) {
                $this->syncJadwalHarian($kelas, $jadwalInput);
            }
        });

        $kelas->load([
            'waliKelasUser:id,name,username',
            'jadwalHarian:id,kelas_id,hari,is_libur,jam_masuk_mulai,jam_masuk_akhir,jam_masuk_telat,jam_pulang_mulai,jam_pulang_akhir,keterangan',
        ])->loadCount('siswa');

        return response()->json([
            'message' => 'Gedung - Lantai berhasil diperbarui.',
            'data' => $this->formatKelasResponse($kelas),
        ]);
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        $kelasNama = $this->normalizeKelasValue($kelas->nama);

        DB::transaction(function () use ($kelas, $kelasNama) {
            $siswaRows = Siswa::query()
                ->where('kelas', $kelas->nama)
                ->get(['id', 'nisn']);

            $nisnList = $siswaRows
                ->pluck('nisn')
                ->map(fn ($nisn) => trim((string) $nisn))
                ->filter()
                ->values()
                ->all();

            if (!empty($nisnList)) {
                User::role('siswa')
                    ->whereIn('username', $nisnList)
                    ->delete();
            }

            if ($siswaRows->isNotEmpty()) {
                Siswa::query()
                    ->whereIn('id', $siswaRows->pluck('id')->all())
                    ->delete();
            }

            if ($kelasNama !== null) {
                User::role('wakel')
                    ->where('kelas', $kelasNama)
                    ->update(['kelas' => null]);
            }

            $kelas->delete();
        });

        return response()->json([
            'message' => 'Gedung - Lantai berhasil dihapus.',
        ]);
    }

    protected function formatJamValue($value): ?string
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

    protected function formatKelasResponse(Kelas $row): array
    {
        $wali = $row->waliKelasUser;
        $jadwalHarian = $row->jadwalHarian
            ->sortBy('hari')
            ->values()
            ->map(fn (JadwalHarian $jadwal) => [
                'hari' => (int) $jadwal->hari,
                'is_libur' => (bool) $jadwal->is_libur,
                'jam_masuk_mulai' => $this->formatJamValue($jadwal->jam_masuk_mulai),
                'jam_masuk_akhir' => $this->formatJamValue($jadwal->jam_masuk_akhir),
                'jam_masuk_telat' => $this->formatJamValue($jadwal->jam_masuk_telat),
                'jam_pulang_mulai' => $this->formatJamValue($jadwal->jam_pulang_mulai),
                'jam_pulang_akhir' => $this->formatJamValue($jadwal->jam_pulang_akhir),
                'keterangan' => $jadwal->keterangan,
            ])
            ->all();

        return [
            'id' => $row->id,
            'nama' => $row->nama,
            'wali_kelas' => $row->wali_kelas,
            'wali_kelas_nama' => $wali?->name ?: $wali?->username,
            'kapasitas' => $row->kapasitas,
            'jumlah_siswa' => (int) ($row->siswa_count ?? 0),
            'jadwal_harian' => $jadwalHarian,
        ];
    }

    protected function buildDefaultJadwalFromConfig(): array
    {
        $defaults = $this->getGlobalJamConfig();

        $rows = [];
        for ($hari = 1; $hari <= 7; $hari++) {
            $isMinggu = $hari === 7;
            $rows[] = [
                'hari' => $hari,
                'is_libur' => $isMinggu,
                'jam_masuk_mulai' => $isMinggu ? null : $defaults['jam_masuk_mulai'],
                'jam_masuk_akhir' => $isMinggu ? null : $defaults['jam_masuk_akhir'],
                'jam_masuk_telat' => $isMinggu ? null : $defaults['jam_masuk_telat'],
                'jam_pulang_mulai' => $isMinggu ? null : $defaults['jam_pulang_mulai'],
                'jam_pulang_akhir' => $isMinggu ? null : $defaults['jam_pulang_akhir'],
                'keterangan' => $isMinggu ? 'Minggu' : null,
            ];
        }

        return $rows;
    }

    protected function getGlobalJamConfig(): array
    {
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
            $value = $this->formatJamValue($row->value);
            if (array_key_exists($key, $config) && $value !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    protected function syncJadwalHarian(Kelas $kelas, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $now = now();
        $prepared = collect($rows)
            ->map(function ($item) use ($kelas, $now) {
                $hari = (int) ($item['hari'] ?? 0);
                if ($hari < 1 || $hari > 7) {
                    return null;
                }

                return [
                    'kelas_id' => (int) $kelas->id,
                    'hari' => $hari,
                    'is_libur' => (bool) ($item['is_libur'] ?? false),
                    'jam_masuk_mulai' => $this->formatJamValue($item['jam_masuk_mulai'] ?? null),
                    'jam_masuk_akhir' => $this->formatJamValue($item['jam_masuk_akhir'] ?? null),
                    'jam_masuk_telat' => $this->formatJamValue($item['jam_masuk_telat'] ?? null),
                    'jam_pulang_mulai' => $this->formatJamValue($item['jam_pulang_mulai'] ?? null),
                    'jam_pulang_akhir' => $this->formatJamValue($item['jam_pulang_akhir'] ?? null),
                    'keterangan' => isset($item['keterangan']) ? trim((string) $item['keterangan']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->unique('hari')
            ->sortBy('hari')
            ->values();

        if ($prepared->isEmpty()) {
            return;
        }

        JadwalHarian::query()->upsert(
            $prepared->all(),
            ['kelas_id', 'hari'],
            ['is_libur', 'jam_masuk_mulai', 'jam_masuk_akhir', 'jam_masuk_telat', 'jam_pulang_mulai', 'jam_pulang_akhir', 'keterangan', 'updated_at']
        );

        JadwalHarian::query()
            ->where('kelas_id', $kelas->id)
            ->whereNotIn('hari', $prepared->pluck('hari')->all())
            ->delete();
    }

    protected function syncWaliKelasBinding(Kelas $kelas, ?int $previousWaliId, ?string $previousNamaKelas): void
    {
        $currentNamaKelas = $this->normalizeKelasValue($kelas->nama);
        $currentWaliId = $kelas->wali_kelas !== null ? (int) $kelas->wali_kelas : null;
        $previousNamaKelas = $this->normalizeKelasValue($previousNamaKelas);

        if ($currentWaliId !== null) {
            Kelas::query()
                ->where('wali_kelas', $currentWaliId)
                ->where('id', '!=', (int) $kelas->id)
                ->update(['wali_kelas' => null]);

            User::query()
                ->whereKey($currentWaliId)
                ->update(['kelas' => $currentNamaKelas]);
        }

        if (
            $previousNamaKelas !== null
            && $currentNamaKelas !== null
            && $previousNamaKelas !== $currentNamaKelas
        ) {
            $query = User::role('wakel')->where('kelas', $previousNamaKelas);
            if ($currentWaliId !== null) {
                $query->where('id', '!=', $currentWaliId);
            }
            $query->update(['kelas' => null]);
        }

        if ($previousWaliId !== null && $previousWaliId !== $currentWaliId) {
            $this->clearUserKelasIfMatches($previousWaliId, [$previousNamaKelas, $currentNamaKelas]);
        }
    }

    protected function clearUserKelasIfMatches(int $userId, array $kelasCandidates): void
    {
        $user = User::query()->find($userId);
        if (!$user || !$user->hasRole('wakel')) {
            return;
        }

        $matchTargets = collect($kelasCandidates)
            ->map(fn ($value) => $this->normalizeKelasValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($matchTargets === []) {
            return;
        }

        $userKelas = $this->normalizeKelasValue($user->kelas);
        if ($userKelas === null || !in_array($userKelas, $matchTargets, true)) {
            return;
        }

        $user->update(['kelas' => null]);
    }

    protected function normalizeKelasValue($kelas): ?string
    {
        $value = trim((string) ($kelas ?? ''));
        return $value === '' ? null : $value;
    }
}
