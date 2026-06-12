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

class ReportExportService extends LessonWorkflowService
{
    protected ?array $rekapTahunanPreload = null;

    protected function canGenerateExcelType(string $role, string $type): bool
    {
        if (in_array($type, ['laporan_bulanan', 'laporan_tahunan', 'monitoring', 'laporan_absensi'], true)) {
            return in_array($role, ['admin', 'kepsek', 'wakel', 'wakasek'], true);
        }

        if ($type === 'laporan_absensi_pelajaran') {
            return in_array($role, ['admin', 'kepsek', 'wakel', 'wakasek', 'piket'], true);
        }

        return false;
    }


    public function generateExcel(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        $type = trim((string) ($args[0] ?? ''));
        if (!$auth || !$this->canGenerateExcelType($role, $type)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $filters = is_array($args[1] ?? null) ? $args[1] : [];

        if ($role === 'wakel' && $type !== 'laporan_absensi_pelajaran') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun wali kelas belum ditautkan ke kelas.'];
            }

            $filters['kelas'] = $wakelKelas;
        }

        if (!in_array($type, ['laporan_bulanan', 'laporan_tahunan', 'monitoring', 'laporan_absensi', 'laporan_absensi_pelajaran'], true)) {
            return ['success' => false, 'message' => 'Tipe export tidak dikenal'];
        }

        if ($type === 'laporan_tahunan') {
            $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
            if ($kelas === null) {
                return ['success' => false, 'message' => 'Pilih kelas terlebih dahulu untuk export rekap tahunan.'];
            }
        }

        $token = trim((string) ($auth->token ?? ''));
        if ($token === '') {
            return ['success' => false, 'message' => 'Token export tidak ditemukan.'];
        }

        $filtersPayload = $this->encodeExportFilters($filters);
        $url = route('ajax.reports.download', [
            'token' => $token,
            'type' => $type,
            'filters' => $filtersPayload,
        ]);

        return [
            'success' => true,
            'url' => $url,
        ];
    }


    public function downloadExport(Request $request)
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'Token export tidak valid.'], 401);
        }

        $auth = $this->resolveAuth($token);
        if ($auth === false || !$auth) {
            return response()->json(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'], 401);
        }

        $role = $this->getRoleFromAuth($auth);
        $type = trim((string) $request->query('type', ''));
        if (!$this->canGenerateExcelType($role, $type)) {
            return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'], 403);
        }

        $filters = $this->decodeExportFilters((string) $request->query('filters', ''));

        if ($role === 'wakel' && $type !== 'laporan_absensi_pelajaran') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return response()->json(['success' => false, 'message' => 'Akun wali kelas belum ditautkan ke kelas.'], 422);
            }

            $filters['kelas'] = $wakelKelas;
        }

        $filename = '';
        $binary = null;

        if ($type === 'laporan_bulanan') {
            $rows = $this->buildRekapBulananRows($filters);
            $bulan = (int) ($filters['bulan'] ?? (now()->month - 1));
            $bulan = max(0, min(11, $bulan));
            $tahun = (int) ($filters['tahun'] ?? now()->year);
            $bulanLabels = [
                0 => 'januari',
                1 => 'februari',
                2 => 'maret',
                3 => 'april',
                4 => 'mei',
                5 => 'juni',
                6 => 'juli',
                7 => 'agustus',
                8 => 'september',
                9 => 'oktober',
                10 => 'november',
                11 => 'desember',
            ];
            $bulanLabel = $bulanLabels[$bulan] ?? ('bulan-' . ($bulan + 1));

            $filenameBase = 'rekap_bulan_' . $bulanLabel . '_' . $tahun;
            $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
            if ($kelas !== null) {
                $filenameBase .= '_kelas_' . $this->toFilenameSlug($kelas);
            }

            $filename = $filenameBase . '_' . now()->format('Ymd_His') . '.xlsx';
            $binary = $kelas === null
                ? $this->buildRekapBulananGridXlsxBinary($rows, 3)
                : $this->buildRekapBulananXlsxBinary($rows);
        } elseif ($type === 'laporan_tahunan') {
            $tahun = (int) ($filters['tahun'] ?? now()->year);
            $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
            if ($kelas === null) {
                return response()->json(['success' => false, 'message' => 'Pilih kelas terlebih dahulu untuk export rekap tahunan.'], 422);
            }

            $filename = 'rekap_tahunan_' . $tahun . '_kelas_' . $this->toFilenameSlug($kelas) . '_' . now()->format('Ymd_His') . '.xlsx';
            $binary = $this->buildRekapTahunanXlsxBinary($tahun, $kelas);
        } elseif ($type === 'monitoring') {
            $rows = $this->buildLaporanAbsensiRows($filters);
            $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
            $dateSegment = $this->buildExportDateSegmentFromFilters($filters);
            $filenameBase = 'monitoring_harian_' . $dateSegment;
            if ($kelas !== null) {
                $filenameBase .= '_kelas_' . $this->toFilenameSlug($kelas);
            }
            $filename = $filenameBase . '_' . now()->format('His') . '.xlsx';
            $binary = $this->buildMonitoringXlsxBinary($rows, $filters);
        } elseif ($type === 'laporan_absensi') {
            $rows = $this->buildLaporanAbsensiRows($filters);
            $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
            $dateSegment = $this->buildExportDateSegmentFromFilters($filters);
            $filenameBase = 'laporan_absensi_' . $dateSegment;
            if ($kelas !== null) {
                $filenameBase .= '_kelas_' . $this->toFilenameSlug($kelas);
            }
            $filename = $filenameBase . '_' . now()->format('His') . '.xlsx';
            $binary = $this->buildMonitoringXlsxBinary($rows, $filters, 'LAPORAN KEHADIRAN SISWA');
        } elseif ($type === 'laporan_absensi_pelajaran') {
            $reportResult = $this->getPelajaranReportData([$filters], $auth);
            if (!($reportResult['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => (string) ($reportResult['message'] ?? 'Gagal menyiapkan laporan absensi pelajaran.'),
                ], 422);
            }

            $reportData = is_array($reportResult['data'] ?? null) ? $reportResult['data'] : [];
            $sessionRows = is_array($reportData['sessions'] ?? null) ? $reportData['sessions'] : [];
            $detailPayloads = [];

            foreach ($sessionRows as $sessionRow) {
                $sessionId = (int) ($sessionRow['session_id'] ?? 0);
                if ($sessionId <= 0) {
                    continue;
                }

                $detailResult = $this->getPelajaranReportSessionDetail([$sessionId], $auth);
                if (!($detailResult['success'] ?? false)) {
                    return response()->json([
                        'success' => false,
                        'message' => (string) ($detailResult['message'] ?? 'Gagal menyiapkan detail sesi pelajaran.'),
                    ], 422);
                }

                $detailPayloads[] = is_array($detailResult['data'] ?? null) ? $detailResult['data'] : [];
            }

            $dateSegment = $this->buildPelajaranExportDateSegment($filters);
            $filename = 'laporan_absensi_pelajaran_' . $dateSegment . '_' . now()->format('His') . '.xlsx';
            $binary = $this->buildPelajaranReportXlsxBinary($reportData, $detailPayloads, $filters);
        } else {
            return response()->json(['success' => false, 'message' => 'Tipe export tidak dikenal.'], 422);
        }

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }


    protected function encodeExportFilters(array $filters): string
    {
        $json = json_encode($filters, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }


    protected function decodeExportFilters(string $encoded): array
    {
        $payload = trim($encoded);
        if ($payload === '') {
            return [];
        }

        $base64 = strtr($payload, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode($base64, true);
        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }


    protected function buildLaporanAbsensiRows(array $filters): array
    {
        $start = $filters['tanggalMulai'] ?? Carbon::today()->toDateString();
        $end = $filters['tanggalAkhir'] ?? Carbon::today()->toDateString();
        $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
        $holidayRanges = $this->getHolidayRanges($start, $end);

        $siswaQuery = Siswa::query();
        if ($kelas !== null) {
            $siswaQuery->where('kelas', $kelas);
        }
        $siswaList = $siswaQuery->orderBy('nama')->get();
        $jadwalLiburMap = $this->getJadwalLiburMapByKelas($siswaList->pluck('kelas')->all());

        $absensi = Absensi::query()
            ->whereBetween('tanggal', [$start, $end])
            ->get()
            ->keyBy(fn ($row) => $row->tanggal->format('Y-m-d') . '_' . $row->nisn);

        $rows = [[
            'No', 'Tanggal', 'NISN', 'Nama Siswa', 'Kelas',
            'Jam Datang', 'Jam Pulang', 'Keterangan', 'Status'
        ]];

        $no = 1;
        $periodStart = Carbon::parse($start);
        $periodEnd = Carbon::parse($end);
        $today = Carbon::today()->toDateString();

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            foreach ($siswaList as $siswa) {
                $holidayName = $this->resolveHolidayNameForDate($dateStr, $siswa->kelas, $holidayRanges);
                if ($holidayName === null) {
                    $holidayName = $this->resolveJadwalLiburNameForDate($dateStr, $siswa->kelas, $jadwalLiburMap);
                }
                $key = $dateStr . '_' . $siswa->nisn;
                $absen = $absensi->get($key);

                $jamDatang = '-';
                $jamPulang = '-';
                $keterangan = '-';
                $status = '';

                if ($absen) {
                    $jamDatang = $absen->jam_datang ?: '-';
                    $jamPulang = $absen->jam_pulang ?: '-';
                    $keterangan = $absen->keterangan ?: '-';
                    $status = $absen->status ?: 'Hadir';
                } else {
                    if ($holidayName) {
                        $status = 'Libur';
                        $keterangan = $holidayName;
                    } elseif ($dateStr > $today) {
                        $status = '';
                        $keterangan = '';
                    } else {
                        $status = 'Belum Absen';
                        $keterangan = 'Tanpa Keterangan';
                    }
                }

                if ($status === '') {
                    continue;
                }

                if (($keterangan === '-' || $keterangan === '') && $status === 'Hadir') {
                    $keterangan = 'Tepat Waktu';
                }

                $rows[] = [
                    $no++,
                    $date->format('d-m-Y'),
                    $siswa->nisn,
                    $siswa->nama,
                    $siswa->kelas,
                    $jamDatang,
                    $jamPulang,
                    $keterangan,
                    $status,
                ];
            }
        }

        return $rows;
    }


    protected function buildRekapBulananRows(array $filters): array
    {
        $bulan = (int) ($filters['bulan'] ?? now()->month - 1);
        $tahun = (int) ($filters['tahun'] ?? now()->year);
        $kelas = $this->normalizeKelasValue($filters['kelas'] ?? null);

        $startDate = Carbon::create($tahun, $bulan + 1, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $endDate->day;
        $isUsingTahunanPreload = is_array($this->rekapTahunanPreload)
            && (int) ($this->rekapTahunanPreload['tahun'] ?? 0) === $tahun
            && (($this->rekapTahunanPreload['kelas'] ?? null) === $kelas);

        if ($isUsingTahunanPreload) {
            $holidayRanges = is_array($this->rekapTahunanPreload['holiday_ranges'] ?? null)
                ? $this->rekapTahunanPreload['holiday_ranges']
                : [];
            $siswaList = $this->rekapTahunanPreload['siswa_list'] ?? collect();
            if (!$siswaList instanceof \Illuminate\Support\Collection) {
                $siswaList = collect();
            }
            $jadwalLiburMap = is_array($this->rekapTahunanPreload['jadwal_libur_map'] ?? null)
                ? $this->rekapTahunanPreload['jadwal_libur_map']
                : [];
            $absensi = $this->rekapTahunanPreload['absensi'] ?? collect();
            if (!$absensi instanceof \Illuminate\Support\Collection) {
                $absensi = collect();
            }
        } else {
            $holidayRanges = $this->getHolidayRanges(
                $startDate->toDateString(),
                $endDate->toDateString()
            );

            $siswaQuery = Siswa::query();
            if ($kelas !== null) {
                $siswaQuery->where('kelas', $kelas)->orderBy('nama');
            } else {
                $siswaQuery->orderBy('kelas')->orderBy('nama');
            }
            $siswaList = $siswaQuery->get();
            $jadwalLiburMap = $this->getJadwalLiburMapByKelas($siswaList->pluck('kelas')->all());

            $absensi = Absensi::query()
                ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
                ->get()
                ->keyBy(fn ($row) => $row->tanggal->format('Y-m-d') . '_' . $row->nisn);
        }

        $dayColumns = $daysInMonth;
        $identityColumns = 3; // NO, NIS, NAMA SISWA
        $totalColumns = $identityColumns + $dayColumns + 4;
        $padRow = static function (array $row) use ($totalColumns): array {
            return array_pad(array_slice($row, 0, $totalColumns), $totalColumns, '');
        };

        $monthNames = [
            1 => 'JANUARI',
            2 => 'FEBRUARI',
            3 => 'MARET',
            4 => 'APRIL',
            5 => 'MEI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AGUSTUS',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DESEMBER',
        ];
        $bulanLabel = $monthNames[(int) $startDate->month] ?? strtoupper($startDate->format('F'));

        $today = Carbon::today()->toDateString();
        $dayEntries = [];
        for ($d = 1; $d <= $dayColumns; $d++) {
            $currentDate = Carbon::create($tahun, $bulan + 1, $d);
            $dayEntries[] = [
                'date' => $currentDate->toDateString(),
                'hari' => (int) $currentDate->dayOfWeekIso,
                'is_future' => $currentDate->toDateString() > $today,
            ];
        }

        $globalHolidayByDate = [];
        $kelasHolidayByDate = [];
        foreach ($holidayRanges as $range) {
            $mulai = $range['tanggal_mulai'] ?? null;
            $selesai = $range['tanggal_selesai'] ?? null;
            if ($mulai === null || $selesai === null) {
                continue;
            }

            try {
                $cursor = Carbon::parse($mulai);
                $cursorEnd = Carbon::parse($selesai);
            } catch (\Throwable $e) {
                continue;
            }

            if ($cursorEnd->lt($cursor)) {
                [$cursor, $cursorEnd] = [$cursorEnd, $cursor];
            }

            $rangeKelas = $range['kelas'] ?? null;
            $keterangan = (string) ($range['keterangan'] ?? 'Libur');
            if ($keterangan === '') {
                $keterangan = 'Libur';
            }

            for ($date = $cursor->copy(); $date->lte($cursorEnd); $date->addDay()) {
                $dateKey = $date->toDateString();
                if ($rangeKelas === null) {
                    $globalHolidayByDate[$dateKey] = $keterangan;
                    continue;
                }

                if (!isset($kelasHolidayByDate[$rangeKelas][$dateKey])) {
                    $kelasHolidayByDate[$rangeKelas][$dateKey] = $keterangan;
                }
            }
        }

        $groupedByClass = $siswaList->groupBy(function (Siswa $siswa) {
            return $this->normalizeKelasValue($siswa->kelas) ?? 'Tanpa Kelas';
        });
        if ($groupedByClass->isEmpty()) {
            $groupedByClass = collect([($kelas ?? 'Tanpa Kelas') => collect()]);
        }

        $rows = [];
        $sections = [];
        $totalGroups = $groupedByClass->count();
        $groupCursor = 0;

        foreach ($groupedByClass as $kelasName => $groupSiswa) {
            $groupCursor++;
            $kelasText = strtoupper(trim((string) $kelasName));
            if ($kelasText === '') {
                $kelasText = 'TANPA KELAS';
            }

            $titleRow = count($rows) + 1;
            $rows[] = $padRow(['ABSENSI SISWA - KELAS ' . $kelasText]);

            $subtitleRow = count($rows) + 1;
            $rows[] = $padRow(['BULAN : ' . $bulanLabel . ' ' . $tahun]);

            $groupHeaderRow = count($rows) + 1;
            $groupHeader = ['NO', 'NIS', 'NAMA SISWA', 'TANGGAL'];
            for ($d = 2; $d <= $dayColumns; $d++) {
                $groupHeader[] = '';
            }
            $groupHeader[] = 'KET';
            $groupHeader[] = '';
            $groupHeader[] = '';
            $groupHeader[] = '';
            $rows[] = $padRow($groupHeader);

            $dayHeaderRow = count($rows) + 1;
            $dayHeader = ['', '', ''];
            for ($d = 1; $d <= $dayColumns; $d++) {
                $dayHeader[] = $d;
            }
            $dayHeader[] = 'H';
            $dayHeader[] = 'S';
            $dayHeader[] = 'I';
            $dayHeader[] = 'A';
            $rows[] = $padRow($dayHeader);

            $dataStartRow = count($rows) + 1;
            $dataNo = 1;

            foreach ($groupSiswa as $siswa) {
                if (!$siswa instanceof Siswa) {
                    continue;
                }

                $stats = ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0];
                $row = [$dataNo++, $siswa->nisn, $siswa->nama];
                $siswaKelas = $this->normalizeKelasValue($siswa->kelas);

                foreach ($dayEntries as $entry) {
                    $dateStr = $entry['date'];
                    $holidayName = null;
                    if ($siswaKelas !== null) {
                        $holidayName = $kelasHolidayByDate[$siswaKelas][$dateStr] ?? null;
                    }
                    if ($holidayName === null) {
                        $holidayName = $globalHolidayByDate[$dateStr] ?? null;
                    }
                    if ($holidayName === null && $siswaKelas !== null) {
                        $holidayName = $jadwalLiburMap[$siswaKelas][$entry['hari']] ?? null;
                    }

                    if ($holidayName !== null) {
                        $row[] = 'L';
                        continue;
                    }

                    $absen = $absensi->get($dateStr . '_' . $siswa->nisn);

                    if ($entry['is_future'] && !$absen) {
                        $row[] = '';
                        continue;
                    }

                    $status = $absen?->status ?? 'Belum Absen';
                    switch ($status) {
                        case 'Hadir':
                            $row[] = 'H';
                            $stats['h']++;
                            break;
                        case 'Sakit':
                            $row[] = 'S';
                            $stats['s']++;
                            break;
                        case 'Izin':
                            $row[] = 'I';
                            $stats['i']++;
                            break;
                        default:
                            $row[] = 'A';
                            $stats['a']++;
                            break;
                    }
                }

                $row[] = $stats['h'];
                $row[] = $stats['s'];
                $row[] = $stats['i'];
                $row[] = $stats['a'];
                $rows[] = $padRow($row);
            }

            $dataEndRow = count($rows);
            if ($dataEndRow < $dataStartRow) {
                $rows[] = $padRow(['', '', 'Tidak ada data siswa']);
                $dataEndRow = count($rows);
            }

            $sections[] = [
                'title_row' => $titleRow,
                'subtitle_row' => $subtitleRow,
                'group_header_row' => $groupHeaderRow,
                'day_header_row' => $dayHeaderRow,
                'data_start_row' => $dataStartRow,
                'data_end_row' => $dataEndRow,
            ];

            if ($groupCursor < $totalGroups) {
                $rows[] = array_fill(0, $totalColumns, '');
                $rows[] = array_fill(0, $totalColumns, '');
            }
        }

        return [
            'rows' => $rows,
            'sections' => $sections,
            'day_columns' => $dayColumns,
            'total_columns' => $totalColumns,
        ];
    }


    protected function buildRekapBulananXlsxBinary(array $exportData): string
    {
        $rows = isset($exportData['rows']) && is_array($exportData['rows'])
            ? $exportData['rows']
            : $exportData;
        $sections = isset($exportData['sections']) && is_array($exportData['sections'])
            ? $exportData['sections']
            : [];
        $dayColumns = (int) ($exportData['day_columns'] ?? 31);

        if (empty($rows)) {
            $rows = [['ABSENSI SISWA']];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Bulanan');
        $sheet->fromArray($rows, null, 'A1', true);

        $totalColumns = collect($rows)->map(fn ($row) => is_array($row) ? count($row) : 0)->max() ?: 38;
        $lastColumn = Coordinate::stringFromColumnIndex($totalColumns);
        $dayStartColIndex = 4; // D
        $dayEndColIndex = $dayStartColIndex + max(1, $dayColumns) - 1;
        if ($dayEndColIndex > $totalColumns - 4) {
            $dayEndColIndex = max($dayStartColIndex, $totalColumns - 4);
        }
        $dayStartColumn = Coordinate::stringFromColumnIndex($dayStartColIndex);
        $dayEndColumn = Coordinate::stringFromColumnIndex($dayEndColIndex);
        $ketStartColIndex = $dayEndColIndex + 1;
        $ketEndColIndex = $ketStartColIndex + 3;
        $ketStartColumn = Coordinate::stringFromColumnIndex($ketStartColIndex);
        $ketEndColumn = Coordinate::stringFromColumnIndex($ketEndColIndex);

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(32);
        for ($col = $dayStartColIndex; $col <= $dayEndColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setWidth(3.5);
        }
        for ($col = $ketStartColIndex; $col <= $ketEndColIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setWidth(4);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'name' => 'Times New Roman',
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ];
        $statColumnStyles = [
            'H' => ['fill' => 'F0FDF4', 'font' => '15803D'],
            'S' => ['fill' => 'FEFCE8', 'font' => 'A16207'],
            'I' => ['fill' => 'EFF6FF', 'font' => '1D4ED8'],
            'A' => ['fill' => 'FEF2F2', 'font' => 'B91C1C'],
        ];
        $dayCodeStyles = [
            'H' => ['fill' => null, 'font' => '16A34A', 'bold' => true],
            'S' => ['fill' => 'FEFCE8', 'font' => 'CA8A04', 'bold' => true],
            'I' => ['fill' => 'EFF6FF', 'font' => '2563EB', 'bold' => true],
            'A' => ['fill' => 'FEF2F2', 'font' => 'DC2626', 'bold' => true],
            'L' => ['fill' => 'FEF2F2', 'font' => 'FCA5A5', 'bold' => true],
        ];

        foreach ($sections as $section) {
            $titleRow = (int) ($section['title_row'] ?? 0);
            $subtitleRow = (int) ($section['subtitle_row'] ?? 0);
            $groupHeaderRow = (int) ($section['group_header_row'] ?? 0);
            $dayHeaderRow = (int) ($section['day_header_row'] ?? 0);
            $dataStartRow = (int) ($section['data_start_row'] ?? 0);
            $dataEndRow = (int) ($section['data_end_row'] ?? 0);

            if ($titleRow <= 0 || $groupHeaderRow <= 0 || $dayHeaderRow <= 0) {
                continue;
            }

            $sheet->mergeCells("A{$titleRow}:{$lastColumn}{$titleRow}");
            $sheet->mergeCells("A{$subtitleRow}:{$lastColumn}{$subtitleRow}");
            $sheet->mergeCells("A{$groupHeaderRow}:A{$dayHeaderRow}");
            $sheet->mergeCells("B{$groupHeaderRow}:B{$dayHeaderRow}");
            $sheet->mergeCells("C{$groupHeaderRow}:C{$dayHeaderRow}");
            $sheet->mergeCells("{$dayStartColumn}{$groupHeaderRow}:{$dayEndColumn}{$groupHeaderRow}");
            $sheet->mergeCells("{$ketStartColumn}{$groupHeaderRow}:{$ketEndColumn}{$groupHeaderRow}");

            $sheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$subtitleRow}:{$lastColumn}{$subtitleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->getStyle("A{$titleRow}:{$lastColumn}{$titleRow}")
                ->getFont()
                ->setBold(true)
                ->setName('Times New Roman')
                ->setSize(14);
            $sheet->getStyle("A{$subtitleRow}:{$lastColumn}{$subtitleRow}")
                ->getFont()
                ->setBold(true)
                ->setName('Times New Roman')
                ->setSize(12);

            $sheet->getStyle("A{$groupHeaderRow}:{$lastColumn}{$dayHeaderRow}")
                ->applyFromArray($headerStyle);

            $tableEndRow = max($dayHeaderRow, $dataEndRow);
            if ($tableEndRow >= $dataStartRow) {
                $sheet->getStyle("A{$dataStartRow}:B{$tableEndRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$dataStartRow}:C{$tableEndRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("{$dayStartColumn}{$dataStartRow}:{$lastColumn}{$tableEndRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $sheet->getStyle("A{$groupHeaderRow}:{$lastColumn}{$tableEndRow}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$groupHeaderRow}:{$lastColumn}{$tableEndRow}")
                ->getFont()
                ->setName('Times New Roman')
                ->setSize(10);

            $tableRange = "A{$groupHeaderRow}:{$lastColumn}{$tableEndRow}";
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($tableRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

            // Warna kolom rekap H/S/I/A seperti di view.
            $statKeys = ['H', 'S', 'I', 'A'];
            foreach ($statKeys as $offset => $key) {
                $colIndex = $ketStartColIndex + $offset;
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $style = $statColumnStyles[$key];

                $sheet->getStyle("{$colLetter}{$dayHeaderRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($style['fill']);
                $sheet->getStyle("{$colLetter}{$dayHeaderRow}")
                    ->getFont()
                    ->setBold(true)
                    ->getColor()
                    ->setRGB($style['font']);

                if ($tableEndRow >= $dataStartRow) {
                    $sheet->getStyle("{$colLetter}{$dataStartRow}:{$colLetter}{$tableEndRow}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB($style['fill']);
                    $sheet->getStyle("{$colLetter}{$dataStartRow}:{$colLetter}{$tableEndRow}")
                        ->getFont()
                        ->setBold(true)
                        ->getColor()
                        ->setRGB($style['font']);
                }
            }

            // Warna kode harian H/S/I/A/L dibatch per segmen agar export lebih cepat.
            if ($tableEndRow >= $dataStartRow) {
                for ($row = $dataStartRow; $row <= $tableEndRow; $row++) {
                    $rowValues = $rows[$row - 1] ?? [];
                    $rowValues = is_array($rowValues) ? $rowValues : [];

                    $col = $dayStartColIndex;
                    while ($col <= $dayEndColIndex) {
                        $value = strtoupper(trim((string) ($rowValues[$col - 1] ?? '')));
                        if (!isset($dayCodeStyles[$value])) {
                            $col++;
                            continue;
                        }

                        $startCol = $col;
                        while ($col + 1 <= $dayEndColIndex) {
                            $nextValue = strtoupper(trim((string) ($rowValues[$col] ?? '')));
                            if ($nextValue !== $value) {
                                break;
                            }
                            $col++;
                        }
                        $endCol = $col;

                        $style = $dayCodeStyles[$value];
                        $range = Coordinate::stringFromColumnIndex($startCol) . $row
                            . ':'
                            . Coordinate::stringFromColumnIndex($endCol) . $row;

                        $styleArray = [
                            'font' => [
                                'bold' => (bool) ($style['bold'] ?? false),
                                'color' => ['rgb' => $style['font']],
                            ],
                        ];
                        if (!empty($style['fill'])) {
                            $styleArray['fill'] = [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $style['fill']],
                            ];
                        }
                        $sheet->getStyle($range)->applyFromArray($styleArray);

                        $col++;
                    }
                }
            }
        }

        $totalRows = count($rows);
        for ($rowIndex = 1; $rowIndex <= $totalRows; $rowIndex++) {
            $rowData = $rows[$rowIndex - 1] ?? [];
            if ($this->isExportRowEmpty(is_array($rowData) ? $rowData : [])) {
                $sheet->getStyle("A{$rowIndex}:{$lastColumn}{$rowIndex}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_NONE);
                $sheet->getRowDimension((int) $rowIndex)->setRowHeight(12);
                continue;
            }

            $sheet->getRowDimension((int) $rowIndex)->setRowHeight(22);
        }

        // Format biasa tanpa freeze pane.
        $sheet->freezePane('A1');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function extractRekapBulananBlocks(array $exportData): array
    {
        $rows = isset($exportData['rows']) && is_array($exportData['rows'])
            ? $exportData['rows']
            : [];
        $sections = isset($exportData['sections']) && is_array($exportData['sections'])
            ? $exportData['sections']
            : [];
        $dayColumns = (int) ($exportData['day_columns'] ?? 31);
        $totalColumns = (int) ($exportData['total_columns'] ?? ($dayColumns + 7));
        if ($totalColumns < 8) {
            $totalColumns = 8;
        }

        $blocks = [];
        foreach ($sections as $section) {
            $titleRow = max(1, (int) ($section['title_row'] ?? 1));
            $dataEndRow = max($titleRow, (int) ($section['data_end_row'] ?? $titleRow));

            $sliceStart = $titleRow - 1;
            $sliceLength = ($dataEndRow - $titleRow) + 1;
            $blockRows = array_values(array_slice($rows, $sliceStart, $sliceLength));
            if (empty($blockRows)) {
                continue;
            }

            foreach ($blockRows as $idx => $row) {
                $blockRows[$idx] = array_pad(
                    array_slice(is_array($row) ? $row : [], 0, $totalColumns),
                    $totalColumns,
                    ''
                );
            }

            $toRelative = static function (int $absoluteRow) use ($titleRow): int {
                return max(1, $absoluteRow - $titleRow + 1);
            };

            $blocks[] = [
                'rows' => $blockRows,
                'section' => [
                    'title_row' => $toRelative((int) ($section['title_row'] ?? $titleRow)),
                    'subtitle_row' => $toRelative((int) ($section['subtitle_row'] ?? ($titleRow + 1))),
                    'group_header_row' => $toRelative((int) ($section['group_header_row'] ?? ($titleRow + 2))),
                    'day_header_row' => $toRelative((int) ($section['day_header_row'] ?? ($titleRow + 3))),
                    'data_start_row' => $toRelative((int) ($section['data_start_row'] ?? ($titleRow + 4))),
                    'data_end_row' => $toRelative((int) ($section['data_end_row'] ?? ($titleRow + 4))),
                ],
                'day_columns' => $dayColumns,
                'total_columns' => $totalColumns,
            ];
        }

        return $blocks;
    }


    protected function buildRekapBulananGridXlsxBinary(array $exportData, int $columnsPerRow = 3): string
    {
        $blocks = $this->extractRekapBulananBlocks($exportData);
        if (empty($blocks)) {
            return $this->buildRekapBulananXlsxBinary($exportData);
        }

        $columnsPerRow = max(1, $columnsPerRow);
        $maxDayColumns = 31;
        $maxBlockColumns = $maxDayColumns + 7; // NO + NIS + NAMA + 31 hari + H/S/I/A
        $blockGapColumns = 3;
        $blockGapRows = 3;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Bulanan');

        for ($colSlot = 0; $colSlot < $columnsPerRow; $colSlot++) {
            $startCol = 1 + ($colSlot * ($maxBlockColumns + $blockGapColumns));
            $this->setRekapExportBlockColumnWidths($sheet, $startCol, $maxDayColumns);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'name' => 'Times New Roman',
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ];
        $statColumnStyles = [
            'H' => ['fill' => 'F0FDF4', 'font' => '15803D'],
            'S' => ['fill' => 'FEFCE8', 'font' => 'A16207'],
            'I' => ['fill' => 'EFF6FF', 'font' => '1D4ED8'],
            'A' => ['fill' => 'FEF2F2', 'font' => 'B91C1C'],
        ];
        $dayCodeStyles = [
            'H' => ['fill' => null, 'font' => '16A34A', 'bold' => true],
            'S' => ['fill' => 'FEFCE8', 'font' => 'CA8A04', 'bold' => true],
            'I' => ['fill' => 'EFF6FF', 'font' => '2563EB', 'bold' => true],
            'A' => ['fill' => 'FEF2F2', 'font' => 'DC2626', 'bold' => true],
            'L' => ['fill' => 'FEF2F2', 'font' => 'FCA5A5', 'bold' => true],
        ];

        $currentStartRow = 1;
        $blockChunks = array_chunk($blocks, $columnsPerRow);
        foreach ($blockChunks as $chunk) {
            $maxHeight = 1;
            foreach ($chunk as $idx => $block) {
                $startCol = 1 + ($idx * ($maxBlockColumns + $blockGapColumns));
                $height = $this->writeRekapBulananBlockToSheet(
                    $sheet,
                    $currentStartRow,
                    $startCol,
                    $block,
                    $maxBlockColumns,
                    $headerStyle,
                    $statColumnStyles,
                    $dayCodeStyles,
                    true
                );
                if ($height > $maxHeight) {
                    $maxHeight = $height;
                }
            }

            $currentStartRow += $maxHeight + $blockGapRows;
        }

        $sheet->freezePane('A1');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function prepareRekapTahunanPreload(int $tahun, string $kelas): void
    {
        $kelas = $this->normalizeKelasValue($kelas) ?? $kelas;
        $kelas = trim((string) $kelas);

        $startOfYear = Carbon::create($tahun, 1, 1)->toDateString();
        $endOfYear = Carbon::create($tahun, 12, 31)->toDateString();

        $siswaList = Siswa::query()
            ->where('kelas', $kelas)
            ->orderBy('nama')
            ->get();

        $jadwalLiburMap = $this->getJadwalLiburMapByKelas($siswaList->pluck('kelas')->all());
        $holidayRanges = $this->getHolidayRanges($startOfYear, $endOfYear);

        $absensi = Absensi::query()
            ->where('kelas', $kelas)
            ->whereBetween('tanggal', [$startOfYear, $endOfYear])
            ->get()
            ->keyBy(fn ($row) => $row->tanggal->format('Y-m-d') . '_' . $row->nisn);

        $this->rekapTahunanPreload = [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'siswa_list' => $siswaList,
            'jadwal_libur_map' => $jadwalLiburMap,
            'holiday_ranges' => $holidayRanges,
            'absensi' => $absensi,
        ];
    }


    protected function clearRekapTahunanPreload(): void
    {
        $this->rekapTahunanPreload = null;
    }


    protected function buildRekapTahunanMonthBlock(int $bulan, int $tahun, string $kelas): array
    {
        $monthData = $this->buildRekapBulananRows([
            'bulan' => $bulan,
            'tahun' => $tahun,
            'kelas' => $kelas,
        ]);

        $rows = isset($monthData['rows']) && is_array($monthData['rows'])
            ? $monthData['rows']
            : [];
        $sections = isset($monthData['sections']) && is_array($monthData['sections'])
            ? $monthData['sections']
            : [];
        $dayColumns = (int) ($monthData['day_columns'] ?? 31);
        $totalColumns = (int) ($monthData['total_columns'] ?? ($dayColumns + 7));
        if ($totalColumns < 8) {
            $totalColumns = 8;
        }

        $section = $sections[0] ?? null;
        if (!is_array($section)) {
            $section = [
                'title_row' => 1,
                'subtitle_row' => 2,
                'group_header_row' => 3,
                'day_header_row' => 4,
                'data_start_row' => 5,
                'data_end_row' => 5,
            ];
        }

        $titleRow = max(1, (int) ($section['title_row'] ?? 1));
        $dataEndRow = max($titleRow, (int) ($section['data_end_row'] ?? $titleRow));
        $sliceStart = $titleRow - 1;
        $sliceLength = ($dataEndRow - $titleRow) + 1;
        $blockRows = array_values(array_slice($rows, $sliceStart, $sliceLength));

        if (empty($blockRows)) {
            $monthNames = [
                1 => 'JANUARI',
                2 => 'FEBRUARI',
                3 => 'MARET',
                4 => 'APRIL',
                5 => 'MEI',
                6 => 'JUNI',
                7 => 'JULI',
                8 => 'AGUSTUS',
                9 => 'SEPTEMBER',
                10 => 'OKTOBER',
                11 => 'NOVEMBER',
                12 => 'DESEMBER',
            ];
            $monthLabel = $monthNames[$bulan + 1] ?? strtoupper((string) ($bulan + 1));
            $blockRows = [
                array_fill(0, $totalColumns, ''),
                array_fill(0, $totalColumns, ''),
                array_fill(0, $totalColumns, ''),
                array_fill(0, $totalColumns, ''),
                array_fill(0, $totalColumns, ''),
            ];
            $blockRows[0][0] = 'ABSENSI SISWA - KELAS ' . strtoupper((string) $kelas);
            $blockRows[1][0] = 'BULAN : ' . $monthLabel . ' ' . $tahun;
            $blockRows[2][0] = 'NO';
            $blockRows[2][1] = 'NIS';
            $blockRows[2][2] = 'NAMA SISWA';
            $blockRows[2][3] = 'TANGGAL';
            $blockRows[3][max(0, $totalColumns - 4)] = 'H';
            $blockRows[3][max(0, $totalColumns - 3)] = 'S';
            $blockRows[3][max(0, $totalColumns - 2)] = 'I';
            $blockRows[3][max(0, $totalColumns - 1)] = 'A';
            $blockRows[4][2] = 'Tidak ada data siswa';
        }

        foreach ($blockRows as $idx => $row) {
            $blockRows[$idx] = array_pad(array_slice(is_array($row) ? $row : [], 0, $totalColumns), $totalColumns, '');
        }

        $toRelative = static function (int $absoluteRow) use ($titleRow): int {
            return max(1, $absoluteRow - $titleRow + 1);
        };

        return [
            'rows' => $blockRows,
            'section' => [
                'title_row' => $toRelative((int) ($section['title_row'] ?? $titleRow)),
                'subtitle_row' => $toRelative((int) ($section['subtitle_row'] ?? ($titleRow + 1))),
                'group_header_row' => $toRelative((int) ($section['group_header_row'] ?? ($titleRow + 2))),
                'day_header_row' => $toRelative((int) ($section['day_header_row'] ?? ($titleRow + 3))),
                'data_start_row' => $toRelative((int) ($section['data_start_row'] ?? ($titleRow + 4))),
                'data_end_row' => $toRelative((int) ($section['data_end_row'] ?? ($titleRow + 4))),
            ],
            'day_columns' => $dayColumns,
            'total_columns' => $totalColumns,
        ];
    }


    protected function buildRekapTahunanXlsxBinary(int $tahun, string $kelas): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Tahunan');

        $maxDayColumns = 31;
        $maxBlockColumns = $maxDayColumns + 7; // NO + NIS + NAMA + 31 hari + H/S/I/A
        $blockGapColumns = 3;
        $leftStartCol = 1;
        $rightStartCol = $leftStartCol + $maxBlockColumns + $blockGapColumns;

        $this->setRekapExportBlockColumnWidths($sheet, $leftStartCol, $maxDayColumns);
        $this->setRekapExportBlockColumnWidths($sheet, $rightStartCol, $maxDayColumns);

        $headerStyle = [
            'font' => [
                'bold' => true,
                'name' => 'Times New Roman',
                'size' => 10,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2'],
            ],
        ];
        $statColumnStyles = [
            'H' => ['fill' => 'F0FDF4', 'font' => '15803D'],
            'S' => ['fill' => 'FEFCE8', 'font' => 'A16207'],
            'I' => ['fill' => 'EFF6FF', 'font' => '1D4ED8'],
            'A' => ['fill' => 'FEF2F2', 'font' => 'B91C1C'],
        ];
        $dayCodeStyles = [
            'H' => ['fill' => null, 'font' => '16A34A', 'bold' => true],
            'S' => ['fill' => 'FEFCE8', 'font' => 'CA8A04', 'bold' => true],
            'I' => ['fill' => 'EFF6FF', 'font' => '2563EB', 'bold' => true],
            'A' => ['fill' => 'FEF2F2', 'font' => 'DC2626', 'bold' => true],
            'L' => ['fill' => 'FEF2F2', 'font' => 'FCA5A5', 'bold' => true],
        ];

        $this->prepareRekapTahunanPreload($tahun, $kelas);
        try {
            $currentStartRow = 1;
            for ($pair = 0; $pair < 6; $pair++) {
                $monthLeft = $pair * 2;
                $monthRight = $monthLeft + 1;

                $leftBlock = $this->buildRekapTahunanMonthBlock($monthLeft, $tahun, $kelas);
                $rightBlock = $this->buildRekapTahunanMonthBlock($monthRight, $tahun, $kelas);

                $leftHeight = $this->writeRekapBulananBlockToSheet(
                    $sheet,
                    $currentStartRow,
                    $leftStartCol,
                    $leftBlock,
                    $maxBlockColumns,
                    $headerStyle,
                    $statColumnStyles,
                    $dayCodeStyles,
                    false
                );
                $rightHeight = $this->writeRekapBulananBlockToSheet(
                    $sheet,
                    $currentStartRow,
                    $rightStartCol,
                    $rightBlock,
                    $maxBlockColumns,
                    $headerStyle,
                    $statColumnStyles,
                    $dayCodeStyles,
                    false
                );

                $pairHeight = max($leftHeight, $rightHeight);
                if ($pairHeight <= 0) {
                    $pairHeight = 1;
                }

                $currentStartRow += $pairHeight + 3;
            }
        } finally {
            $this->clearRekapTahunanPreload();
        }

        $sheet->freezePane('A1');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function setRekapExportBlockColumnWidths($sheet, int $startColIndex, int $dayColumns): void
    {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($startColIndex))->setWidth(5);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($startColIndex + 1))->setWidth(14);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($startColIndex + 2))->setWidth(32);

        for ($offset = 0; $offset < $dayColumns; $offset++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($startColIndex + 3 + $offset))->setWidth(3.5);
        }
        for ($offset = 0; $offset < 4; $offset++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($startColIndex + 3 + $dayColumns + $offset))->setWidth(4);
        }
    }


    protected function writeRekapBulananBlockToSheet(
        $sheet,
        int $startRow,
        int $startColIndex,
        array $block,
        int $maxBlockColumns,
        array $headerStyle,
        array $statColumnStyles,
        array $dayCodeStyles,
        bool $applyDayCodeStyles = true
    ): int {
        $rows = isset($block['rows']) && is_array($block['rows']) ? $block['rows'] : [];
        if (empty($rows)) {
            return 0;
        }

        $section = isset($block['section']) && is_array($block['section']) ? $block['section'] : [];
        $dayColumns = (int) ($block['day_columns'] ?? 31);
        $totalColumns = (int) ($block['total_columns'] ?? ($dayColumns + 7));
        if ($totalColumns < 8) {
            $totalColumns = 8;
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            $normalizedRows[] = array_pad(
                array_slice(is_array($row) ? $row : [], 0, $maxBlockColumns),
                $maxBlockColumns,
                ''
            );
        }

        $sheet->fromArray(
            $normalizedRows,
            null,
            Coordinate::stringFromColumnIndex($startColIndex) . $startRow,
            true
        );

        $titleRow = $startRow + max(1, (int) ($section['title_row'] ?? 1)) - 1;
        $subtitleRow = $startRow + max(1, (int) ($section['subtitle_row'] ?? 2)) - 1;
        $groupHeaderRow = $startRow + max(1, (int) ($section['group_header_row'] ?? 3)) - 1;
        $dayHeaderRow = $startRow + max(1, (int) ($section['day_header_row'] ?? 4)) - 1;
        $dataStartRow = $startRow + max(1, (int) ($section['data_start_row'] ?? 5)) - 1;
        $dataEndRow = $startRow + max(1, (int) ($section['data_end_row'] ?? 5)) - 1;
        if ($dataEndRow < $dataStartRow) {
            $dataEndRow = $dataStartRow;
        }

        $startColumn = Coordinate::stringFromColumnIndex($startColIndex);
        $blockEndColIndex = $startColIndex + $totalColumns - 1;
        $blockEndColumn = Coordinate::stringFromColumnIndex($blockEndColIndex);
        $dayStartColIndex = $startColIndex + 3;
        $dayEndColIndex = $dayStartColIndex + max(1, $dayColumns) - 1;
        $maxDayEndColIndex = $startColIndex + $totalColumns - 5;
        if ($dayEndColIndex > $maxDayEndColIndex) {
            $dayEndColIndex = $maxDayEndColIndex;
        }
        if ($dayEndColIndex < $dayStartColIndex) {
            $dayEndColIndex = $dayStartColIndex;
        }
        $dayStartColumn = Coordinate::stringFromColumnIndex($dayStartColIndex);
        $dayEndColumn = Coordinate::stringFromColumnIndex($dayEndColIndex);
        $ketStartColIndex = $dayEndColIndex + 1;
        $ketEndColIndex = $ketStartColIndex + 3;
        $ketStartColumn = Coordinate::stringFromColumnIndex($ketStartColIndex);
        $ketEndColumn = Coordinate::stringFromColumnIndex($ketEndColIndex);

        $sheet->mergeCells("{$startColumn}{$titleRow}:{$blockEndColumn}{$titleRow}");
        $sheet->mergeCells("{$startColumn}{$subtitleRow}:{$blockEndColumn}{$subtitleRow}");
        $sheet->mergeCells($startColumn . $groupHeaderRow . ':' . $startColumn . $dayHeaderRow);
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($startColIndex + 1) . $groupHeaderRow
            . ':'
            . Coordinate::stringFromColumnIndex($startColIndex + 1) . $dayHeaderRow
        );
        $sheet->mergeCells(
            Coordinate::stringFromColumnIndex($startColIndex + 2) . $groupHeaderRow
            . ':'
            . Coordinate::stringFromColumnIndex($startColIndex + 2) . $dayHeaderRow
        );
        $sheet->mergeCells("{$dayStartColumn}{$groupHeaderRow}:{$dayEndColumn}{$groupHeaderRow}");
        $sheet->mergeCells("{$ketStartColumn}{$groupHeaderRow}:{$ketEndColumn}{$groupHeaderRow}");

        $sheet->getStyle("{$startColumn}{$titleRow}:{$blockEndColumn}{$titleRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("{$startColumn}{$subtitleRow}:{$blockEndColumn}{$subtitleRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle("{$startColumn}{$titleRow}:{$blockEndColumn}{$titleRow}")
            ->getFont()
            ->setBold(true)
            ->setName('Times New Roman')
            ->setSize(14);
        $sheet->getStyle("{$startColumn}{$subtitleRow}:{$blockEndColumn}{$subtitleRow}")
            ->getFont()
            ->setBold(true)
            ->setName('Times New Roman')
            ->setSize(12);

        $sheet->getStyle("{$startColumn}{$groupHeaderRow}:{$blockEndColumn}{$dayHeaderRow}")
            ->applyFromArray($headerStyle);

        $tableEndRow = max($dayHeaderRow, $dataEndRow);
        if ($tableEndRow >= $dataStartRow) {
            $sheet->getStyle(
                $startColumn . $dataStartRow
                . ':'
                . Coordinate::stringFromColumnIndex($startColIndex + 1) . $tableEndRow
            )
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle(
                Coordinate::stringFromColumnIndex($startColIndex + 2) . $dataStartRow
                . ':'
                . Coordinate::stringFromColumnIndex($startColIndex + 2) . $tableEndRow
            )
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("{$dayStartColumn}{$dataStartRow}:{$blockEndColumn}{$tableEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getStyle("{$startColumn}{$groupHeaderRow}:{$blockEndColumn}{$tableEndRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("{$startColumn}{$groupHeaderRow}:{$blockEndColumn}{$tableEndRow}")
            ->getFont()
            ->setName('Times New Roman')
            ->setSize(10);

        $tableRange = "{$startColumn}{$groupHeaderRow}:{$blockEndColumn}{$tableEndRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($tableRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

        $statKeys = ['H', 'S', 'I', 'A'];
        foreach ($statKeys as $offset => $key) {
            $colIndex = $ketStartColIndex + $offset;
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $style = $statColumnStyles[$key];

            $sheet->getStyle("{$colLetter}{$dayHeaderRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB($style['fill']);
            $sheet->getStyle("{$colLetter}{$dayHeaderRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB($style['font']);

            if ($tableEndRow >= $dataStartRow) {
                $sheet->getStyle("{$colLetter}{$dataStartRow}:{$colLetter}{$tableEndRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($style['fill']);
                $sheet->getStyle("{$colLetter}{$dataStartRow}:{$colLetter}{$tableEndRow}")
                    ->getFont()
                    ->setBold(true)
                    ->getColor()
                    ->setRGB($style['font']);
            }
        }

        if ($applyDayCodeStyles && $tableEndRow >= $dataStartRow) {
            for ($row = $dataStartRow; $row <= $tableEndRow; $row++) {
                $rowValues = $normalizedRows[$row - $startRow] ?? [];

                $col = $dayStartColIndex;
                while ($col <= $dayEndColIndex) {
                    $value = strtoupper(trim((string) ($rowValues[$col - $startColIndex] ?? '')));
                    if (!isset($dayCodeStyles[$value])) {
                        $col++;
                        continue;
                    }

                    $startSegment = $col;
                    while ($col + 1 <= $dayEndColIndex) {
                        $nextValue = strtoupper(trim((string) ($rowValues[$col + 1 - $startColIndex] ?? '')));
                        if ($nextValue !== $value) {
                            break;
                        }
                        $col++;
                    }

                    $style = $dayCodeStyles[$value];
                    $range = Coordinate::stringFromColumnIndex($startSegment) . $row
                        . ':'
                        . Coordinate::stringFromColumnIndex($col) . $row;

                    $styleArray = [
                        'font' => [
                            'bold' => (bool) ($style['bold'] ?? false),
                            'color' => ['rgb' => $style['font']],
                        ],
                    ];
                    if (!empty($style['fill'])) {
                        $styleArray['fill'] = [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $style['fill']],
                        ];
                    }
                    $sheet->getStyle($range)->applyFromArray($styleArray);

                    $col++;
                }
            }
        }

        $fullBlockEndColumn = Coordinate::stringFromColumnIndex($startColIndex + $maxBlockColumns - 1);
        if ($applyDayCodeStyles) {
            foreach ($normalizedRows as $idx => $rowValues) {
                $absoluteRow = $startRow + $idx;
                if ($this->isExportRowEmpty($rowValues)) {
                    $sheet->getStyle("{$startColumn}{$absoluteRow}:{$fullBlockEndColumn}{$absoluteRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_NONE);
                    $sheet->getRowDimension((int) $absoluteRow)->setRowHeight(12);
                    continue;
                }
                $sheet->getRowDimension((int) $absoluteRow)->setRowHeight(22);
            }
        }

        return count($normalizedRows);
    }


    protected function isExportRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }


    protected function arrayToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }


    protected function arrayToXlsxBinary(array $rows, string $sheetTitle = 'Export'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $safeTitle = preg_replace('/[\\\\\\/?*:\\[\\]]/', ' ', $sheetTitle) ?? 'Export';
        $safeTitle = trim($safeTitle);
        if ($safeTitle === '') {
            $safeTitle = 'Export';
        }
        $sheet->setTitle(substr($safeTitle, 0, 31));

        $maxColumns = 0;
        foreach ($rows as $rowIndex => $row) {
            $values = is_array($row) ? array_values($row) : [(string) $row];
            $maxColumns = max($maxColumns, count($values));
            foreach ($values as $colIndex => $value) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1);
                $sheet->setCellValue($cellCoordinate, $value);
            }
        }

        if (!empty($rows) && $maxColumns > 0) {
            $lastCol = Coordinate::stringFromColumnIndex($maxColumns);
            $lastRow = count($rows);

            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3F4F6'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A1:{$lastCol}{$lastRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            for ($i = 1; $i <= $maxColumns; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $sheet->freezePane('A2');
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function buildMonitoringXlsxBinary(
        array $rows,
        array $filters,
        string $mainTitle = 'MONITORING KEHADIRAN SISWA'
    ): string
    {
        if (empty($rows)) {
            $rows = [[
                'No', 'Tanggal', 'NISN', 'Nama Siswa', 'Kelas',
                'Jam Datang', 'Jam Pulang', 'Keterangan', 'Status',
            ]];
        }

        $header = is_array($rows[0] ?? null) ? array_values($rows[0]) : [];
        if (empty($header)) {
            $header = [
                'No', 'Tanggal', 'NISN', 'Nama Siswa', 'Kelas',
                'Jam Datang', 'Jam Pulang', 'Keterangan', 'Status',
            ];
        }

        $dataRows = array_values(array_slice($rows, 1));
        $maxColumns = count($header);
        foreach ($dataRows as $row) {
            $maxColumns = max($maxColumns, is_array($row) ? count($row) : 1);
        }
        if ($maxColumns < 1) {
            $maxColumns = 1;
        }

        $normalizedHeader = array_pad(array_slice($header, 0, $maxColumns), $maxColumns, '');
        $normalizedDataRows = [];
        foreach ($dataRows as $row) {
            $values = is_array($row) ? array_values($row) : [(string) $row];
            $normalizedDataRows[] = array_pad(array_slice($values, 0, $maxColumns), $maxColumns, '');
        }
        if (empty($normalizedDataRows)) {
            $normalizedDataRows[] = array_pad(['', '', '', 'Tidak ada data untuk hari ini'], $maxColumns, '');
        }

        $startDate = (string) ($filters['tanggalMulai'] ?? Carbon::today()->toDateString());
        $endDate = (string) ($filters['tanggalAkhir'] ?? $startDate);
        try {
            $dateLabel = Carbon::parse($startDate)->format('d-m-Y');
            if ($endDate !== $startDate) {
                $dateLabel .= ' s/d ' . Carbon::parse($endDate)->format('d-m-Y');
            }
        } catch (\Throwable $e) {
            $dateLabel = $startDate . ($endDate !== $startDate ? ' s/d ' . $endDate : '');
        }
        $selectedKelas = $this->normalizeKelasValue($filters['kelas'] ?? null);
        $kelasColumnIndex = array_search('Kelas', $normalizedHeader, true);
        $noColumnIndex = array_search('No', $normalizedHeader, true);
        $nameColumnIndex = array_search('Nama Siswa', $normalizedHeader, true);
        $statusColumnIndex = array_search('Status', $normalizedHeader, true);
        $keteranganColumnIndex = array_search('Keterangan', $normalizedHeader, true);

        $groupedRows = [];
        if ($selectedKelas !== null) {
            $groupedRows[$selectedKelas] = $normalizedDataRows;
        } else {
            foreach ($normalizedDataRows as $row) {
                $kelasName = 'Tanpa Kelas';
                if ($kelasColumnIndex !== false) {
                    $kelasName = $this->normalizeKelasValue($row[$kelasColumnIndex] ?? null) ?? 'Tanpa Kelas';
                }
                if (!isset($groupedRows[$kelasName])) {
                    $groupedRows[$kelasName] = [];
                }
                $groupedRows[$kelasName][] = $row;
            }

            if (!empty($groupedRows)) {
                $kelasKeys = array_keys($groupedRows);
                usort($kelasKeys, static fn ($a, $b) => strnatcasecmp((string) $a, (string) $b));
                $sorted = [];
                foreach ($kelasKeys as $kelasKey) {
                    $sorted[$kelasKey] = $groupedRows[$kelasKey];
                }
                $groupedRows = $sorted;
            } else {
                $groupedRows['Semua Kelas'] = [];
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Harian');

        $baseStartColIndex = 3; // Mulai dari kolom C agar tidak mepet kiri.
        $blockColumns = $maxColumns;
        $blockGapColumns = 3;
        $blocksPerRow = $selectedKelas !== null ? 1 : 2;
        if (count($groupedRows) <= 1) {
            $blocksPerRow = 1;
        }

        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(4);

        $entries = [];
        foreach ($groupedRows as $kelasLabel => $groupRows) {
            $entries[] = [
                'kelas' => (string) $kelasLabel,
                'rows' => is_array($groupRows) ? $groupRows : [],
            ];
        }

        $maxUsedColIndex = $baseStartColIndex + $blockColumns - 1;
        $currentTopRow = 1;
        $entryCount = count($entries);
        for ($startIndex = 0; $startIndex < $entryCount; $startIndex += $blocksPerRow) {
            $chunk = array_slice($entries, $startIndex, $blocksPerRow);
            $rowMaxBottom = $currentTopRow;

            foreach ($chunk as $position => $entry) {
                $kelasLabel = $entry['kelas'];
                $groupRows = $entry['rows'];

                $classRows = [];
                if (empty($groupRows)) {
                    $empty = array_fill(0, $maxColumns, '');
                    $messageColumnIndex = min(3, $maxColumns - 1);
                    $empty[$messageColumnIndex] = 'Tidak ada data untuk kelas ini.';
                    $classRows[] = $empty;
                } else {
                    $counter = 1;
                    foreach ($groupRows as $row) {
                        $newRow = array_pad(array_slice(is_array($row) ? $row : [], 0, $maxColumns), $maxColumns, '');
                        if ($noColumnIndex !== false) {
                            $newRow[$noColumnIndex] = $counter++;
                        }
                        $classRows[] = $newRow;
                    }
                }

                $blockStartColIndex = $baseStartColIndex + ($position * ($blockColumns + $blockGapColumns));
                $blockLastColIndex = $blockStartColIndex + $blockColumns - 1;
                $blockStartCol = Coordinate::stringFromColumnIndex($blockStartColIndex);
                $blockLastCol = Coordinate::stringFromColumnIndex($blockLastColIndex);

                $titleRow = $currentTopRow;
                $subtitleRow = $currentTopRow + 1;
                $headerRow = $currentTopRow + 3;
                $dataStartRow = $headerRow + 1;
                $dataEndRow = $dataStartRow + count($classRows) - 1;

                $sheet->setCellValue("{$blockStartCol}{$titleRow}", $mainTitle);
                $sheet->setCellValue("{$blockStartCol}{$subtitleRow}", 'Tanggal: ' . $dateLabel . ' | Kelas: ' . $kelasLabel);
                $sheet->mergeCells("{$blockStartCol}{$titleRow}:{$blockLastCol}{$titleRow}");
                $sheet->mergeCells("{$blockStartCol}{$subtitleRow}:{$blockLastCol}{$subtitleRow}");

                $sheet->fromArray([$normalizedHeader], null, "{$blockStartCol}{$headerRow}", true);
                $sheet->fromArray($classRows, null, "{$blockStartCol}{$dataStartRow}", true);

                $sheet->getStyle("{$blockStartCol}{$titleRow}:{$blockLastCol}{$titleRow}")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle("{$blockStartCol}{$subtitleRow}:{$blockLastCol}{$subtitleRow}")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("{$blockStartCol}{$headerRow}:{$blockLastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Times New Roman', 'size' => 10],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ]);

                $sheet->getStyle("{$blockStartCol}{$headerRow}:{$blockLastCol}{$dataEndRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("{$blockStartCol}{$headerRow}:{$blockLastCol}{$dataEndRow}")
                    ->getBorders()
                    ->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM);

                $sheet->getStyle("{$blockStartCol}{$dataStartRow}:{$blockLastCol}{$dataEndRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("{$blockStartCol}{$dataStartRow}:{$blockLastCol}{$dataEndRow}")
                    ->getFont()
                    ->setName('Times New Roman')
                    ->setSize(10);

                if ($nameColumnIndex !== false) {
                    $nameCol = Coordinate::stringFromColumnIndex($blockStartColIndex + $nameColumnIndex);
                    $sheet->getStyle("{$nameCol}{$dataStartRow}:{$nameCol}{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                if ($keteranganColumnIndex !== false) {
                    $ketCol = Coordinate::stringFromColumnIndex($blockStartColIndex + $keteranganColumnIndex);
                    $sheet->getStyle("{$ketCol}{$dataStartRow}:{$ketCol}{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                if ($statusColumnIndex !== false) {
                    $statusCol = Coordinate::stringFromColumnIndex($blockStartColIndex + $statusColumnIndex);
                    $sheet->getStyle("{$statusCol}{$dataStartRow}:{$statusCol}{$dataEndRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getRowDimension($titleRow)->setRowHeight(24);
                $sheet->getRowDimension($subtitleRow)->setRowHeight(20);
                $sheet->getRowDimension($currentTopRow + 2)->setRowHeight(10);
                $sheet->getRowDimension($headerRow)->setRowHeight(22);
                for ($rowNumber = $dataStartRow; $rowNumber <= $dataEndRow; $rowNumber++) {
                    $sheet->getRowDimension($rowNumber)->setRowHeight(20);
                }

                $rowMaxBottom = max($rowMaxBottom, $dataEndRow);
                $maxUsedColIndex = max($maxUsedColIndex, $blockLastColIndex);
            }

            $currentTopRow = $rowMaxBottom + 3;
        }

        for ($i = $baseStartColIndex; $i <= $maxUsedColIndex; $i++) {
            $offset = ($i - $baseStartColIndex) % ($blockColumns + $blockGapColumns);
            if ($blocksPerRow > 1 && $offset >= $blockColumns) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(4);
                continue;
            }
            $col = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function buildPelajaranExportDateSegment(array $filters): string
    {
        $start = $this->normalizeDateValue($filters['tanggal_dari'] ?? null);
        $end = $this->normalizeDateValue($filters['tanggal_sampai'] ?? null);

        if ($start === null && $end === null) {
            return now()->format('Ymd');
        }
        if ($start === null) {
            $start = $end;
        }
        if ($end === null) {
            $end = $start;
        }
        if ($start === null || $end === null) {
            return now()->format('Ymd');
        }
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $startSegment = str_replace('-', '', $start);
        $endSegment = str_replace('-', '', $end);

        return $startSegment === $endSegment
            ? $startSegment
            : $startSegment . '_' . $endSegment;
    }


    protected function buildPelajaranReportXlsxBinary(array $reportData, array $detailPayloads, array $filters): string
    {
        $sessionStatusStyles = [
            'Ditutup' => ['fill' => 'FEE2E2', 'font' => 'B91C1C'],
            'Berjalan' => ['fill' => 'DCFCE7', 'font' => '166534'],
            'Belum Mulai' => ['fill' => 'FEF3C7', 'font' => '92400E'],
        ];
        $studentStatusStyles = [
            'Hadir' => ['fill' => 'DCFCE7', 'font' => '166534'],
            'Terlambat' => ['fill' => 'FEF3C7', 'font' => '92400E'],
            'Izin' => ['fill' => 'DBEAFE', 'font' => '1D4ED8'],
            'Sakit' => ['fill' => 'EDE9FE', 'font' => '6D28D9'],
            'Alfa' => ['fill' => 'FEE2E2', 'font' => 'B91C1C'],
            'Belum Absen' => ['fill' => 'F3F4F6', 'font' => '4B5563'],
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('ABSENSINDO')
            ->setTitle('Laporan Absensi Pelajaran');
        $spreadsheet->getDefaultStyle()
            ->getFont()
            ->setName('Times New Roman')
            ->setSize(10);

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Ringkasan');
        $this->buildPelajaranReportSummarySheet($summarySheet, $reportData, $filters);

        $sessionRows = collect(is_array($reportData['sessions'] ?? null) ? $reportData['sessions'] : [])
            ->values()
            ->map(function (array $row, int $index): array {
                return [
                    $index + 1,
                    (string) ($row['tanggal'] ?? '-'),
                    (string) ($row['kelas_nama'] ?? '-'),
                    (string) ($row['mata_pelajaran'] ?? '-'),
                    (string) ($row['guru_nama'] ?? '-'),
                    (string) ((isset($row['jam_mulai'], $row['jam_selesai']) && $row['jam_mulai'] !== '' && $row['jam_selesai'] !== '')
                        ? ($row['jam_mulai'] . '-' . $row['jam_selesai'])
                        : ($row['jam_mulai'] ?? $row['jam_selesai'] ?? '-')),
                    (string) ($row['status_label'] ?? '-'),
                    (int) ($row['total_siswa'] ?? 0),
                    (int) ($row['hadir'] ?? 0),
                    (int) ($row['terlambat'] ?? 0),
                    (int) ($row['izin'] ?? 0),
                    (int) ($row['sakit'] ?? 0),
                    (int) ($row['alfa'] ?? 0),
                    (int) ($row['belum'] ?? 0),
                    (float) ($row['kehadiran_rate'] ?? 0),
                    (string) ($row['opened_at'] ?? '-'),
                    (string) ($row['closed_at'] ?? '-'),
                ];
            })
            ->all();

        $sessionSheet = $spreadsheet->createSheet();
        $this->buildPelajaranReportTableSheet(
            $sessionSheet,
            'Sesi',
            'Ringkasan Sesi Absensi Pelajaran',
            $this->buildPelajaranReportSubtitle($filters),
            ['No', 'Tanggal', 'Kelas', 'Mapel', 'Guru', 'Jam', 'Status Sesi', 'Total Siswa', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alfa', 'Belum Absen', '% Hadir', 'Dimulai', 'Ditutup'],
            $sessionRows,
            [
                'widths' => [6, 14, 12, 22, 22, 14, 14, 12, 10, 12, 10, 10, 10, 12, 12, 10, 10],
                'center_columns' => [1, 2, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17],
                'percentage_columns' => [15],
                'status_columns' => [7 => $sessionStatusStyles],
                'empty_message' => 'Tidak ada data sesi pada filter ini.',
            ]
        );

        $detailRows = [];
        $detailCounter = 1;
        foreach ($detailPayloads as $payload) {
            $session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
            $students = is_array($payload['students'] ?? null) ? $payload['students'] : [];
            $jamRange = trim((string) (($session['jam_mulai'] ?? '') !== '' || ($session['jam_selesai'] ?? '') !== ''
                ? (($session['jam_mulai'] ?? '') . '-' . ($session['jam_selesai'] ?? ''))
                : '-'));
            $sessionStatus = 'Belum Mulai';
            $sessionStatusRaw = strtolower(trim((string) ($session['status'] ?? '')));
            if ($sessionStatusRaw === 'closed') {
                $sessionStatus = 'Ditutup';
            } elseif ($sessionStatusRaw === 'open') {
                $sessionStatus = 'Berjalan';
            }

            foreach ($students as $student) {
                $student = is_array($student) ? $student : [];
                $detailRows[] = [
                    $detailCounter++,
                    (string) ($session['tanggal'] ?? '-'),
                    (string) (($session['kelas']['nama'] ?? '-') ?: '-'),
                    (string) ($session['mata_pelajaran'] ?? '-'),
                    (string) (($session['guru']['nama'] ?? '-') ?: '-'),
                    $jamRange !== '-' ? $jamRange : '-',
                    $sessionStatus,
                    (string) ($session['opened_at'] ?? '-'),
                    (string) ($session['closed_at'] ?? '-'),
                    (string) ($student['nisn'] ?? '-'),
                    (string) ($student['nama'] ?? '-'),
                    (string) (($student['status'] ?? '') !== '' ? $student['status'] : 'Belum Absen'),
                    (string) (($student['method'] ?? '') !== '' ? $student['method'] : '-'),
                    (string) (($student['recorded_at'] ?? '') !== '' ? $student['recorded_at'] : '-'),
                ];
            }
        }

        $detailSheet = $spreadsheet->createSheet();
        $this->buildPelajaranReportTableSheet(
            $detailSheet,
            'Detail Sesi',
            'Detail Siswa per Sesi Pelajaran',
            $this->buildPelajaranReportSubtitle($filters),
            ['No', 'Tanggal', 'Kelas', 'Mapel', 'Guru', 'Jam', 'Status Sesi', 'Dimulai', 'Ditutup', 'NISN', 'Nama', 'Status', 'Metode', 'Jam Catat'],
            $detailRows,
            [
                'widths' => [6, 14, 12, 20, 20, 14, 14, 10, 10, 14, 26, 14, 12, 12],
                'center_columns' => [1, 2, 6, 7, 8, 9, 10, 12, 13, 14],
                'status_columns' => [7 => $sessionStatusStyles, 12 => $studentStatusStyles],
                'empty_message' => 'Tidak ada detail sesi untuk diexport.',
            ]
        );

        $studentRows = collect(is_array($reportData['students'] ?? null) ? $reportData['students'] : [])
            ->values()
            ->map(function (array $row, int $index): array {
                return [
                    $index + 1,
                    (string) ($row['nisn'] ?? '-'),
                    (string) ($row['nama'] ?? '-'),
                    (string) ($row['kelas_nama'] ?? '-'),
                    (int) ($row['total_sesi'] ?? 0),
                    (int) ($row['hadir'] ?? 0),
                    (int) ($row['terlambat'] ?? 0),
                    (int) ($row['izin'] ?? 0),
                    (int) ($row['sakit'] ?? 0),
                    (int) ($row['alfa'] ?? 0),
                    (int) ($row['belum'] ?? 0),
                    (float) ($row['kehadiran_rate'] ?? 0),
                ];
            })
            ->all();

        $studentSheet = $spreadsheet->createSheet();
        $this->buildPelajaranReportTableSheet(
            $studentSheet,
            'Rekap Siswa',
            'Rekap Kehadiran Siswa per Mata Pelajaran',
            $this->buildPelajaranReportSubtitle($filters),
            ['No', 'NISN', 'Nama', 'Kelas', 'Total Sesi', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alfa', 'Belum Absen', '% Hadir'],
            $studentRows,
            [
                'widths' => [6, 14, 26, 12, 12, 10, 12, 10, 10, 10, 12, 12],
                'center_columns' => [1, 5, 6, 7, 8, 9, 10, 11, 12],
                'percentage_columns' => [12],
                'empty_message' => 'Tidak ada rekap siswa pada filter ini.',
            ]
        );

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }


    protected function buildPelajaranReportSubtitle(array $filters): string
    {
        $start = $this->normalizeDateValue($filters['tanggal_dari'] ?? null) ?? Carbon::today()->startOfMonth()->toDateString();
        $end = $this->normalizeDateValue($filters['tanggal_sampai'] ?? null) ?? Carbon::today()->toDateString();
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $statusMap = [
            'closed' => 'Sesi Ditutup',
            'open' => 'Sesi Berjalan',
            'all' => 'Semua Sesi',
        ];

        try {
            $periodLabel = Carbon::parse($start)->format('d-m-Y') . ' s/d ' . Carbon::parse($end)->format('d-m-Y');
        } catch (\Throwable $e) {
            $periodLabel = $start . ' s/d ' . $end;
        }

        $statusLabel = $statusMap[strtolower(trim((string) ($filters['status_sesi'] ?? 'closed')))] ?? 'Sesi Ditutup';

        return 'Periode: ' . $periodLabel . ' | Status: ' . $statusLabel;
    }


    protected function buildPelajaranReportSummarySheet($sheet, array $reportData, array $filters): void
    {
        $sheet->setTitle('Ringkasan');
        $sheet->getSheetView()->setZoomScale(95);

        $options = is_array($reportData['options'] ?? null) ? $reportData['options'] : [];
        $kelasOptions = collect(is_array($options['kelas'] ?? null) ? $options['kelas'] : []);
        $guruOptions = collect(is_array($options['guru'] ?? null) ? $options['guru'] : []);

        $kelasId = (int) ($filters['kelas_id'] ?? 0);
        $guruId = (int) ($filters['guru_id'] ?? 0);

        $selectedKelas = $kelasId > 0 ? $kelasOptions->firstWhere('id', $kelasId) : null;
        $selectedGuru = $guruId > 0 ? $guruOptions->firstWhere('id', $guruId) : null;
        $kelasLabel = $kelasId > 0
            ? (string) (is_array($selectedKelas) ? ($selectedKelas['nama'] ?? ('ID ' . $kelasId)) : ('ID ' . $kelasId))
            : 'Semua Kelas';
        $guruLabel = $guruId > 0
            ? (string) (is_array($selectedGuru) ? ($selectedGuru['nama'] ?? ('ID ' . $guruId)) : ('ID ' . $guruId))
            : 'Semua Guru';
        $mapelLabel = trim((string) ($filters['mapel'] ?? '')) !== '' ? (string) $filters['mapel'] : 'Semua Mapel';
        $searchLabel = trim((string) ($filters['search'] ?? '')) !== '' ? (string) $filters['search'] : '-';

        $statusMap = [
            'closed' => 'Sesi Ditutup',
            'open' => 'Sesi Berjalan',
            'all' => 'Semua Sesi',
        ];
        $statusLabel = $statusMap[strtolower(trim((string) ($filters['status_sesi'] ?? 'closed')))] ?? 'Sesi Ditutup';

        $start = $this->normalizeDateValue($filters['tanggal_dari'] ?? null) ?? Carbon::today()->startOfMonth()->toDateString();
        $end = $this->normalizeDateValue($filters['tanggal_sampai'] ?? null) ?? Carbon::today()->toDateString();
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        try {
            $periodLabel = Carbon::parse($start)->format('d-m-Y') . ' s/d ' . Carbon::parse($end)->format('d-m-Y');
        } catch (\Throwable $e) {
            $periodLabel = $start . ' s/d ' . $end;
        }

        $stats = is_array($reportData['stats'] ?? null) ? $reportData['stats'] : [];
        $filterRows = [
            ['Periode', $periodLabel],
            ['Kelas', $kelasLabel],
            ['Guru', $guruLabel],
            ['Mapel', $mapelLabel],
            ['Status Sesi', $statusLabel],
            ['Pencarian', $searchLabel],
        ];
        $statRows = [
            ['Total Sesi', (int) ($stats['total_sessions'] ?? 0)],
            ['Sesi Ditutup', (int) ($stats['closed_sessions'] ?? 0)],
            ['Sesi Berjalan', (int) ($stats['open_sessions'] ?? 0)],
            ['Rekap Siswa', (int) ($stats['students'] ?? 0)],
            ['Hadir', (int) ($stats['hadir'] ?? 0)],
            ['Terlambat', (int) ($stats['terlambat'] ?? 0)],
            ['Izin', (int) ($stats['izin'] ?? 0)],
            ['Sakit', (int) ($stats['sakit'] ?? 0)],
            ['Alfa', (int) ($stats['alfa'] ?? 0)],
            ['Belum Absen', (int) ($stats['belum'] ?? 0)],
        ];

        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A1', 'LAPORAN ABSENSI PELAJARAN');
        $sheet->setCellValue('A2', 'Ringkasan Filter dan Statistik');
        $sheet->setCellValue('A4', 'Filter');
        $sheet->setCellValue('B4', 'Nilai');
        $sheet->setCellValue('D4', 'Statistik');
        $sheet->setCellValue('E4', 'Nilai');

        $sheet->fromArray($filterRows, null, 'A5', true);
        $sheet->fromArray($statRows, null, 'D5', true);
        $sheet->mergeCells('A16:F16');
        $sheet->setCellValue('A16', 'Export dibuat pada ' . now()->format('d-m-Y H:i:s'));

        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4338CA'],
            ],
        ]);
        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '3730A3']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0E7FF'],
            ],
        ]);
        $sheet->getStyle('A4:B4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $sheet->getStyle('D4:E4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
        ]);
        $sheet->getStyle('A5:B10')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('D5:E14')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A16:F16')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ]);
        $sheet->getStyle('E5:E14')
            ->getFont()
            ->setBold(true);

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(34);
        $sheet->getColumnDimension('C')->setWidth(4);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(4);

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(22);
        for ($row = 4; $row <= 16; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    }


    protected function buildPelajaranReportTableSheet($sheet, string $sheetName, string $title, string $subtitle, array $headers, array $rows, array $options = []): void
    {
        $safeTitle = preg_replace('/[\\\\\\/?*:\\[\\]]/', ' ', $sheetName) ?? 'Export';
        $safeTitle = trim($safeTitle) !== '' ? trim($safeTitle) : 'Export';
        $sheet->setTitle(substr($safeTitle, 0, 31));
        $sheet->getSheetView()->setZoomScale(90);

        $columnCount = max(1, count($headers));
        $normalizedHeaders = array_pad(array_slice(array_values($headers), 0, $columnCount), $columnCount, '');
        $normalizedRows = [];
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : [(string) $row];
            $normalizedRows[] = array_pad(array_slice($values, 0, $columnCount), $columnCount, '');
        }

        if (empty($normalizedRows)) {
            $emptyRow = array_fill(0, $columnCount, '');
            $emptyRow[min(1, $columnCount - 1)] = (string) ($options['empty_message'] ?? 'Tidak ada data.');
            $normalizedRows[] = $emptyRow;
        }

        $lastCol = Coordinate::stringFromColumnIndex($columnCount);
        $headerRow = 5;
        $dataStartRow = $headerRow + 1;
        $dataEndRow = $dataStartRow + count($normalizedRows) - 1;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A1', 'LAPORAN ABSENSI PELAJARAN');
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', $subtitle);
        $sheet->fromArray([$normalizedHeaders], null, "A{$headerRow}", true);
        $sheet->fromArray($normalizedRows, null, "A{$dataStartRow}", true);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
        ]);
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '3730A3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
        ]);
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dataEndRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dataEndRow}")
            ->getBorders()
            ->getOutline()
            ->setBorderStyle(Border::BORDER_MEDIUM);

        for ($rowIndex = $dataStartRow; $rowIndex <= $dataEndRow; $rowIndex++) {
            if ($rowIndex % 2 === 0) {
                $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F8FAFC');
            }
        }

        $centerColumns = array_values(array_unique(array_map('intval', $options['center_columns'] ?? [])));
        foreach ($centerColumns as $columnIndex) {
            if ($columnIndex < 1 || $columnIndex > $columnCount) {
                continue;
            }
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getStyle("{$columnLetter}{$dataStartRow}:{$columnLetter}{$dataEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (($options['percentage_columns'] ?? []) as $columnIndex) {
            $columnIndex = (int) $columnIndex;
            if ($columnIndex < 1 || $columnIndex > $columnCount) {
                continue;
            }
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getStyle("{$columnLetter}{$dataStartRow}:{$columnLetter}{$dataEndRow}")
                ->getNumberFormat()
                ->setFormatCode('0.0"%"');
            $sheet->getStyle("{$columnLetter}{$dataStartRow}:{$columnLetter}{$dataEndRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (($options['status_columns'] ?? []) as $columnIndex => $styleMap) {
            $this->applyPelajaranExportStatusStyles($sheet, (int) $columnIndex, $dataStartRow, $dataEndRow, is_array($styleMap) ? $styleMap : []);
        }

        $widths = array_values($options['widths'] ?? []);
        for ($index = 1; $index <= $columnCount; $index++) {
            $columnLetter = Coordinate::stringFromColumnIndex($index);
            $width = $widths[$index - 1] ?? null;
            if (is_numeric($width) && (float) $width > 0) {
                $sheet->getColumnDimension($columnLetter)->setWidth((float) $width);
            } else {
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);
        for ($rowIndex = $dataStartRow; $rowIndex <= $dataEndRow; $rowIndex++) {
            $sheet->getRowDimension($rowIndex)->setRowHeight(20);
        }

        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");
        $sheet->freezePane("A{$dataStartRow}");
    }


    protected function applyPelajaranExportStatusStyles($sheet, int $columnIndex, int $startRow, int $endRow, array $styleMap): void
    {
        if ($columnIndex <= 0 || empty($styleMap) || $endRow < $startRow) {
            return;
        }

        $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
        for ($row = $startRow; $row <= $endRow; $row++) {
            $value = trim((string) $sheet->getCell("{$columnLetter}{$row}")->getValue());
            if ($value === '' || !isset($styleMap[$value])) {
                continue;
            }

            $style = is_array($styleMap[$value]) ? $styleMap[$value] : [];
            $fill = (string) ($style['fill'] ?? '');
            $font = (string) ($style['font'] ?? '');
            if ($fill === '' && $font === '') {
                continue;
            }

            $sheet->getStyle("{$columnLetter}{$row}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => $font !== '' ? $font : '111827'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fill !== '' ? $fill : 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }
    }


    protected function toFilenameSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'tanpa-kelas';
    }


    protected function buildExportDateSegmentFromFilters(array $filters): string
    {
        $start = $this->normalizeDateValue($filters['tanggalMulai'] ?? null);
        $end = $this->normalizeDateValue($filters['tanggalAkhir'] ?? null);

        if ($start === null && $end === null) {
            return now()->format('Ymd');
        }
        if ($start === null) {
            $start = $end;
        }
        if ($end === null) {
            $end = $start;
        }
        if ($start === null || $end === null) {
            return now()->format('Ymd');
        }
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $startSegment = str_replace('-', '', $start);
        $endSegment = str_replace('-', '', $end);

        if ($startSegment === $endSegment) {
            return $startSegment;
        }

        return $startSegment . '_' . $endSegment;
    }


    public function getTemplateExcel(array $args): array
    {
        $type = trim((string) ($args[0] ?? ''));
        if ($type === '') {
            return ['success' => false, 'message' => 'Tipe template tidak valid.'];
        }

        $rows = [];
        if ($type === 'siswa') {
            $rows[] = ['Nama', 'NISN', 'Jenis Kelamin', 'Tanggal Lahir', 'Agama', 'Nama Ayah', 'Nama Ibu', 'No HP', 'Kelas', 'Alamat'];
            $rows[] = ['Contoh Siswa', '1234567890', 'Laki-laki', '2010-01-01', 'Islam', 'Ayah', 'Ibu', '081234567890', '7A', 'Alamat'];
        } elseif ($type === 'guru') {
            $rows[] = ['Username', 'Password', 'Nama', 'Email', 'Kelas', 'Jenis Kelamin', 'Tanggal Lahir', 'Agama', 'No HP', 'Alamat'];
            $rows[] = ['guru_ipa', '123456', 'Guru IPA', 'guru.ipa@sekolah.sch.id', '7A', 'Laki-laki', '1990-01-01', 'Islam', '081234567890', 'Jl. Pendidikan No. 1'];
        } elseif ($type === 'libur') {
            $rows[] = ['Tanggal Mulai', 'Tanggal Selesai', 'Kelas (Opsional)', 'Keterangan'];
            $rows[] = ['2026-01-01', '2026-01-01', '', 'Tahun Baru'];
            $rows[] = ['2026-04-01', '2026-04-03', '7A', 'Class Meeting Kelas 7A'];
        } else {
            return ['success' => false, 'message' => 'Template tidak tersedia untuk tipe ini.'];
        }

        $csv = $this->arrayToCsv($rows);
        $path = 'templates/template_' . $type . '.csv';
        Storage::disk('public')->put($path, $csv);

        return [
            'success' => true,
            'url' => Storage::url($path),
        ];
    }

}