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

class PromotionWorkflowService extends BaseActionService
{
    public function getArchiveResetPreview($auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $absensiCount = (int) Absensi::query()->count();
        $siswaCount = (int) Siswa::query()->count();
        $kelasCount = (int) Kelas::query()->count();
        $canArchive = $absensiCount > 0;

        $minTanggal = Absensi::query()->min('tanggal');
        $maxTanggal = Absensi::query()->max('tanggal');

        $rangeLabel = '-';
        $hariLiburCountInRange = 0;
        if ($minTanggal && $maxTanggal) {
            try {
                $startLabel = Carbon::parse($minTanggal)->format('d-m-Y');
                $endLabel = Carbon::parse($maxTanggal)->format('d-m-Y');
                $rangeLabel = $startLabel . ' s/d ' . $endLabel;
            } catch (\Throwable $e) {
                $rangeLabel = (string) $minTanggal . ' s/d ' . (string) $maxTanggal;
            }

            $hariLiburCountInRange = (int) $this->buildHariLiburOverlapQuery('1900-01-01', (string) $maxTanggal)->count();
        }

        return [
            'success' => true,
            'data' => [
                'absensiCount' => $absensiCount,
                'siswaCount' => $siswaCount,
                'kelasCount' => $kelasCount,
                'absensiDateRange' => $rangeLabel,
                'hariLiburCountInRange' => $hariLiburCountInRange,
                'canArchive' => $canArchive,
                'cannotArchiveMessage' => $canArchive ? null : 'Data absensi masih kosong. Tutup Tahun Ajaran tidak dapat dilakukan.',
                'defaultArchiveName' => 'Arsip_' . now()->format('Ymd_His'),
            ],
        ];
    }


    public function archiveAndResetYear(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $archiveName = trim((string) ($args[0] ?? ''));
        if ($archiveName === '') {
            $archiveName = 'Arsip_' . now()->format('Ymd_His');
        }

        $absensiRows = Absensi::query()->orderBy('tanggal')->get();
        if ($absensiRows->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Data absensi masih kosong. Tutup Tahun Ajaran tidak dapat dilakukan.',
            ];
        }

        $siswaRows = Siswa::query()->orderBy('nama')->get();
        $rangeStart = null;
        $rangeEnd = null;
        $deleteWindowStart = '1900-01-01';
        if ($absensiRows->isNotEmpty()) {
            $firstTanggal = $absensiRows->first()->tanggal ?? null;
            $lastTanggal = $absensiRows->last()->tanggal ?? null;
            if ($firstTanggal && $lastTanggal) {
                $rangeStart = Carbon::parse($firstTanggal)->toDateString();
                $rangeEnd = Carbon::parse($lastTanggal)->toDateString();
            }
        }

        $hariLiburRows = collect();
        if ($rangeEnd) {
            $hariLiburRows = $this->buildHariLiburOverlapQuery($deleteWindowStart, $rangeEnd)
                ->orderBy('tanggal_mulai')
                ->orderBy('tanggal')
                ->get();
        }

        $absensiCsv = $this->arrayToCsv($absensiRows->map(function ($row) {
            return [
                $row->tanggal->format('Y-m-d'),
                $row->nisn,
                $row->nama,
                $row->kelas,
                $row->jam_datang,
                $row->jam_pulang,
                $row->keterangan,
                $row->status,
            ];
        })->prepend(['Tanggal', 'NISN', 'Nama', 'Kelas', 'Jam Datang', 'Jam Pulang', 'Keterangan', 'Status'])->all());

        $siswaCsv = $this->arrayToCsv($siswaRows->map(function ($row) {
            return [
                $row->nama,
                $row->nisn,
                $row->jenis_kelamin,
                $row->tanggal_lahir?->format('Y-m-d'),
                $row->agama,
                $row->nama_ayah,
                $row->nama_ibu,
                $row->no_hp,
                $row->kelas,
                $row->alamat,
            ];
        })->prepend(['Nama', 'NISN', 'Jenis Kelamin', 'Tanggal Lahir', 'Agama', 'Nama Ayah', 'Nama Ibu', 'No HP', 'Kelas', 'Alamat'])->all());

        $hariLiburCsv = $this->arrayToCsv($hariLiburRows->map(function ($row) {
            $tanggalMulai = $this->normalizeDateValue($row->tanggal_mulai) ?? $this->normalizeDateValue($row->tanggal);
            $tanggalSelesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $tanggalMulai;
            if ($tanggalMulai !== null && $tanggalSelesai !== null && $tanggalSelesai < $tanggalMulai) {
                $tanggalSelesai = $tanggalMulai;
            }

            return [
                $tanggalMulai,
                $tanggalSelesai,
                $this->normalizeKelasValue($row->kelas),
                $row->keterangan,
            ];
        })->prepend(['Tanggal Mulai', 'Tanggal Selesai', 'Kelas', 'Keterangan'])->all());

        $absensiPath = 'archives/' . $archiveName . '_absensi.csv';
        $siswaPath = 'archives/' . $archiveName . '_siswa.csv';
        $hariLiburPath = 'archives/' . $archiveName . '_hari_libur.csv';

        Storage::disk('public')->put($absensiPath, $absensiCsv);
        Storage::disk('public')->put($siswaPath, $siswaCsv);
        Storage::disk('public')->put($hariLiburPath, $hariLiburCsv);

        $deletedHariLibur = 0;
        DB::transaction(function () use ($deleteWindowStart, $rangeEnd, &$deletedHariLibur) {
            Absensi::query()->delete();

            if ($rangeEnd) {
                $deletedHariLibur = (int) $this->buildHariLiburOverlapQuery($deleteWindowStart, $rangeEnd)->delete();
            }
        });

        $message = 'Berhasil! Data tersimpan: ' . $archiveName;
        if ($rangeEnd) {
            $message .= ' | Hari libur terhapus (awal data s/d ' . $rangeEnd . '): ' . $deletedHariLibur;
        }

        return [
            'success' => true,
            'url' => Storage::url($absensiPath),
            'message' => $message,
            'deletedHariLibur' => $deletedHariLibur,
            'rentangTanggalArsip' => ($rangeStart && $rangeEnd) ? ($rangeStart . ' s/d ' . $rangeEnd) : null,
        ];
    }


    public function processGradePromotion(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $mapping = $args[0] ?? [];
        $mapObj = [];
        foreach ($mapping as $item) {
            if (!empty($item['asal']) && !empty($item['tujuan'])) {
                $mapObj[$item['asal']] = $item['tujuan'];
            }
        }

        $this->syncKelasValues(array_filter(
            array_values($mapObj),
            fn ($target) => $target !== 'LULUS'
        ));

        $movedCount = 0;

        DB::transaction(function () use ($mapObj, &$movedCount) {
            $siswaList = Siswa::query()->whereIn('kelas', array_keys($mapObj))->get();
            foreach ($siswaList as $siswa) {
                $target = $mapObj[$siswa->kelas] ?? null;
                if (!$target) {
                    continue;
                }

                if ($target === 'LULUS') {
                    \App\Models\Alumni::query()->create([
                        'nama' => $siswa->nama,
                        'nisn' => $siswa->nisn,
                        'jenis_kelamin' => $siswa->jenis_kelamin,
                        'kelas_terakhir' => $siswa->kelas,
                        'tahun_lulus' => now()->year,
                        'kontak' => $siswa->no_hp,
                    ]);
                    $siswa->delete();
                } else {
                    $siswa->update(['kelas' => $target]);
                }

                $movedCount++;
            }
        });

        return ['success' => true, 'movedCount' => $movedCount];
    }


    public function processIndividualPromotion(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $kelasAsal = $args[0] ?? null;
        $kelasTujuan = $args[1] ?? null;
        $promoData = $args[2] ?? [];

        if (!$kelasAsal || !$kelasTujuan) {
            return ['success' => false, 'message' => 'Kelas asal/tujuan wajib diisi'];
        }

        if ($kelasTujuan !== 'LULUS') {
            $kelasTujuan = $this->syncKelasValue($kelasTujuan);
        }

        DB::transaction(function () use ($kelasAsal, $kelasTujuan, $promoData) {
            $nisnMap = Siswa::query()->pluck('id', 'nisn');

            foreach ($promoData as $item) {
                $nisn = $item['nisn'] ?? null;
                $status = $item['status'] ?? null;
                if (!$nisn || !$status) {
                    continue;
                }

                $siswaId = $nisnMap[$nisn] ?? null;
                if (!$siswaId) {
                    continue;
                }

                $siswa = Siswa::query()->find($siswaId);
                if (!$siswa) {
                    continue;
                }

                if ($status === 'NAIK') {
                    if ($kelasTujuan === 'LULUS') {
                        \App\Models\Alumni::query()->create([
                            'nama' => $siswa->nama,
                            'nisn' => $siswa->nisn,
                            'jenis_kelamin' => $siswa->jenis_kelamin,
                            'kelas_terakhir' => $kelasAsal,
                            'tahun_lulus' => now()->year,
                            'kontak' => $siswa->no_hp,
                        ]);
                        $siswa->delete();
                    } else {
                        $siswa->update(['kelas' => $kelasTujuan]);
                    }
                }
            }
        });

        return ['success' => true];
    }

}