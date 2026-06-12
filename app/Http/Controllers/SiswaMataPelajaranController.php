<?php

namespace App\Http\Controllers;

use App\Models\JadwalPelajaran;
use App\Models\JadwalHarian;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiswaMataPelajaranController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('siswa'), 403);

        $siswa = Siswa::query()
            ->where('nisn', trim((string) $user->username))
            ->first();

        $dayOptions = $this->dayOptions();
        $hiddenHolidayDays = collect();
        $jadwalRows = collect();

        if ($siswa && trim((string) $siswa->kelas) !== '') {
            $kelasNama = trim((string) $siswa->kelas);
            $kelasId = (int) (Kelas::query()->where('nama', $kelasNama)->value('id') ?? 0);

            if ($kelasId > 0) {
                $hiddenHolidayDays = JadwalHarian::query()
                    ->where('kelas_id', $kelasId)
                    ->where('is_libur', true)
                    ->pluck('hari')
                    ->map(fn ($value) => (int) $value)
                    ->filter(fn (int $value) => $value >= 1 && $value <= 7)
                    ->unique()
                    ->values();
            }

            $jadwalQuery = JadwalPelajaran::query()
                ->with(['guru:id,name,username', 'kelas:id,nama'])
                ->whereHas('kelas', fn ($query) => $query->where('nama', $kelasNama))
                ->orderBy('hari')
                ->orderBy('jam_mulai')
                ->orderBy('id');

            if ($hiddenHolidayDays->isNotEmpty()) {
                $jadwalQuery->whereNotIn('hari', $hiddenHolidayDays->all());
            }

            $jadwalRows = $jadwalQuery
                ->get()
                ->map(function (JadwalPelajaran $row): array {
                    return [
                        'id' => (int) $row->id,
                        'hari' => (int) $row->hari,
                        'jam_mulai' => substr((string) $row->jam_mulai, 0, 5),
                        'jam_selesai' => substr((string) $row->jam_selesai, 0, 5),
                        'mata_pelajaran' => (string) $row->mata_pelajaran,
                        'guru_nama' => (string) ($row->guru?->name ?: ($row->guru?->username ?? '-')),
                        'ruang' => (string) ($row->ruang ?? ''),
                        'keterangan' => (string) ($row->keterangan ?? ''),
                    ];
                })
                ->values();
        }

        $visibleDayOptions = collect($dayOptions)->reject(function ($label, $day) use ($hiddenHolidayDays): bool {
            return $hiddenHolidayDays->contains((int) $day);
        })->all();

        if (empty($visibleDayOptions)) {
            $visibleDayOptions = $dayOptions;
        }

        $jadwalByDay = collect($visibleDayOptions)->mapWithKeys(function ($label, $day) use ($jadwalRows) {
            return [
                (int) $day => $jadwalRows->where('hari', (int) $day)->values()->all(),
            ];
        });

        $totalSesi = (int) $jadwalRows->count();
        $totalMapel = (int) $jadwalRows
            ->map(fn ($row) => mb_strtolower(trim((string) ($row['mata_pelajaran'] ?? ''))))
            ->filter()
            ->unique()
            ->count();

        return view('pages.mata-pelajaran-saya', [
            'siswa' => $siswa,
            'dayOptions' => $visibleDayOptions,
            'jadwalByDay' => $jadwalByDay,
            'totalSesi' => $totalSesi,
            'totalMapel' => $totalMapel,
        ]);
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
}
