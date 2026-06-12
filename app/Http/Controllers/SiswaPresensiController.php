<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiswaPresensiController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('siswa'), 403);

        $siswa = Siswa::query()
            ->where('nisn', trim((string) $user->username))
            ->first();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthlyRows = collect();
        $recentRows = collect();

        if ($siswa) {
            $baseQuery = Absensi::query()
                ->where('siswa_id', (int) $siswa->id)
                ->orderByDesc('tanggal')
                ->orderByDesc('id');

            $recentRows = (clone $baseQuery)
                ->limit(30)
                ->get()
                ->map(fn (Absensi $row) => $this->formatAbsensiRow($row))
                ->values();

            $monthlyRows = (clone $baseQuery)
                ->whereDate('tanggal', '>=', $monthStart)
                ->whereDate('tanggal', '<=', $monthEnd)
                ->get()
                ->map(fn (Absensi $row) => $this->formatAbsensiRow($row))
                ->values();
        }

        $statusCounts = $monthlyRows
            ->groupBy(fn (array $row) => $row['status'])
            ->map(fn ($rows) => $rows->count())
            ->all();

        $stats = $this->buildStats($statusCounts, (int) $monthlyRows->count());

        return view('pages.presensi-saya', [
            'siswa' => $siswa,
            'periodeLabel' => now()->translatedFormat('F Y'),
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'monthlyRows' => $monthlyRows,
            'recentRows' => $recentRows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAbsensiRow(Absensi $row): array
    {
        $status = trim((string) ($row->status ?? ''));
        if ($status === '') {
            $status = 'Belum Absen';
        }

        $tanggalLabel = '-';
        if ($row->tanggal !== null) {
            try {
                $tanggalLabel = Carbon::parse($row->tanggal)->translatedFormat('d M Y');
            } catch (\Throwable $e) {
                $tanggalLabel = (string) $row->tanggal;
            }
        }

        return [
            'id' => (int) $row->id,
            'tanggal' => $row->tanggal?->toDateString(),
            'tanggal_label' => $tanggalLabel,
            'status' => $status,
            'jam_datang' => substr((string) ($row->jam_datang ?? ''), 0, 5),
            'jam_pulang' => substr((string) ($row->jam_pulang ?? ''), 0, 5),
            'keterangan' => trim((string) ($row->keterangan ?? '')),
        ];
    }

    /**
     * @param  array<string, int>  $statusCounts
     * @return array<string, mixed>
     */
    protected function buildStats(array $statusCounts, int $totalRows): array
    {
        $hadir = (int) ($statusCounts['Hadir'] ?? 0);
        $izin = (int) ($statusCounts['Izin'] ?? 0);
        $sakit = (int) ($statusCounts['Sakit'] ?? 0);
        $alfa = (int) ($statusCounts['Alfa'] ?? 0);
        $belumAbsen = (int) ($statusCounts['Belum Absen'] ?? 0);

        $nonHadir = $izin + $sakit + $alfa + $belumAbsen;
        $attendanceRate = $totalRows > 0
            ? round(($hadir / $totalRows) * 100, 1)
            : 0.0;

        return [
            'total' => $totalRows,
            'hadir' => $hadir,
            'izin' => $izin,
            'sakit' => $sakit,
            'alfa' => $alfa,
            'belum_absen' => $belumAbsen,
            'non_hadir' => $nonHadir,
            'attendance_rate' => $attendanceRate,
        ];
    }
}

