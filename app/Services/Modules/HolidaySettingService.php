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

class HolidaySettingService extends BaseActionService
{
    public function getHariLiburList(): array
    {
        $today = Carbon::today()->toDateString();
        $data = HariLibur::query()
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('tanggal')
            ->get()
            ->map(function (HariLibur $row) use ($today) {
                $tanggalMulai = $this->normalizeDateValue($row->tanggal_mulai) ?? $this->normalizeDateValue($row->tanggal);
                $tanggalSelesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $tanggalMulai;
                if ($tanggalMulai !== null && $tanggalSelesai !== null && $tanggalSelesai < $tanggalMulai) {
                    $tanggalSelesai = $tanggalMulai;
                }

                $kelas = $this->normalizeKelasValue($row->kelas);

                return [
                    'id' => $row->id,
                    'tanggal' => $tanggalMulai,
                    'tanggalMulai' => $tanggalMulai,
                    'tanggalSelesai' => $tanggalSelesai,
                    'kelas' => $kelas,
                    'kelasLabel' => $kelas ?? 'Semua Kelas',
                    'keterangan' => $row->keterangan,
                    'canDelete' => $tanggalSelesai !== null && $tanggalSelesai >= $today,
                ];
            })
            ->values()
            ->all();

        return ['success' => true, 'data' => $data];
    }


    public function addHariLibur(array $args, $auth): array
    {
        if ($denied = $this->requireAdmin($auth)) {
            return $denied;
        }

        $payload = $args[0] ?? [];
        $tanggalMulai = $this->normalizeDateValue(
            $payload['tanggalMulai']
                ?? $payload['tanggal_mulai']
                ?? $payload['tanggal']
                ?? null
        );
        $tanggalSelesai = $this->normalizeDateValue(
            $payload['tanggalSelesai']
                ?? $payload['tanggal_selesai']
                ?? $payload['tanggal']
                ?? null
        );
        $kelas = $this->normalizeKelasValue($payload['kelas'] ?? null);
        $keterangan = trim((string) ($payload['keterangan'] ?? ''));

        if ($tanggalMulai === null || $keterangan === '') {
            return ['success' => false, 'message' => 'Tanggal dan keterangan wajib diisi.'];
        }

        if ($tanggalSelesai === null) {
            $tanggalSelesai = $tanggalMulai;
        }
        if ($tanggalSelesai < $tanggalMulai) {
            return ['success' => false, 'message' => 'Tanggal selesai tidak boleh lebih kecil dari tanggal mulai.'];
        }

        if ($kelas !== null) {
            $kelas = $this->syncKelasValue($kelas);
        }

        HariLibur::query()->updateOrCreate(
            [
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'kelas' => $kelas,
            ],
            [
                'tanggal' => $tanggalMulai,
                'keterangan' => $keterangan,
            ]
        );

        return ['success' => true, 'message' => 'Hari libur berhasil disimpan.'];
    }


    public function deleteHariLibur(array $args, $auth): array
    {
        if ($denied = $this->requireAdmin($auth)) {
            return $denied;
        }

        $target = trim((string) ($args[0] ?? ''));
        if ($target === '') {
            return ['success' => false, 'message' => 'Data hari libur tidak valid.'];
        }

        $query = HariLibur::query();
        if (ctype_digit($target)) {
            $query->where('id', (int) $target);
        } else {
            $date = $this->normalizeDateValue($target);
            if ($date === null) {
                return ['success' => false, 'message' => 'Format tanggal tidak valid.'];
            }

            $query->where(function ($q) use ($date) {
                $q->where(function ($inner) use ($date) {
                    $inner->whereNotNull('tanggal_mulai')
                        ->whereDate('tanggal_mulai', '<=', $date)
                        ->whereDate('tanggal_selesai', '>=', $date);
                })->orWhereDate('tanggal', $date);
            });
        }

        $rows = $query
            ->get(['id', 'tanggal', 'tanggal_mulai', 'tanggal_selesai']);
        if ($rows->isEmpty()) {
            return ['success' => false, 'message' => 'Hari libur tidak ditemukan.'];
        }

        $today = Carbon::today()->toDateString();
        $hasPastHoliday = $rows->contains(function (HariLibur $row) use ($today) {
            $tanggalMulai = $this->normalizeDateValue($row->tanggal_mulai)
                ?? $this->normalizeDateValue($row->tanggal);
            if ($tanggalMulai === null) {
                return false;
            }

            $tanggalSelesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $tanggalMulai;
            if ($tanggalSelesai < $tanggalMulai) {
                $tanggalSelesai = $tanggalMulai;
            }

            return $tanggalSelesai < $today;
        });
        if ($hasPastHoliday) {
            return [
                'success' => false,
                'message' => 'Hari libur yang sudah lewat tidak dapat dihapus.',
            ];
        }

        $deleted = $query->delete();
        if ($deleted === 0) {
            return ['success' => false, 'message' => 'Hari libur tidak ditemukan.'];
        }

        return ['success' => true, 'message' => 'Hari libur berhasil dihapus.'];
    }


    public function importHariLiburBulk(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $dataArray = $args[0] ?? [];
        $existing = HariLibur::query()
            ->get(['tanggal', 'tanggal_mulai', 'tanggal_selesai', 'kelas'])
            ->mapWithKeys(function (HariLibur $row) {
                $mulai = $this->normalizeDateValue($row->tanggal_mulai) ?? $this->normalizeDateValue($row->tanggal);
                $selesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $mulai;
                if ($mulai === null) {
                    return [];
                }
                if ($selesai === null || $selesai < $mulai) {
                    $selesai = $mulai;
                }
                $kelas = $this->normalizeKelasValue($row->kelas) ?? '__ALL__';
                return [$mulai . '|' . $selesai . '|' . $kelas => true];
            })
            ->all();

        $rowsToAdd = [];
        $addedCount = 0;
        $skippedCount = 0;
        $kelasToSync = [];

        foreach ($dataArray as $item) {
            $tanggalMulai = $this->normalizeDateValue(
                $item['tanggalMulai']
                    ?? $item['tanggal_mulai']
                    ?? $item['tanggal']
                    ?? null
            );
            $tanggalSelesai = $this->normalizeDateValue(
                $item['tanggalSelesai']
                    ?? $item['tanggal_selesai']
                    ?? $item['tanggal']
                    ?? null
            );
            $kelas = $this->normalizeKelasValue($item['kelas'] ?? null);
            $keterangan = isset($item['keterangan']) ? trim((string) $item['keterangan']) : '';
            if ($tanggalMulai === null || $keterangan === '') {
                $skippedCount++;
                continue;
            }

            if ($tanggalSelesai === null) {
                $tanggalSelesai = $tanggalMulai;
            }
            if ($tanggalSelesai < $tanggalMulai) {
                [$tanggalMulai, $tanggalSelesai] = [$tanggalSelesai, $tanggalMulai];
            }

            $key = $tanggalMulai . '|' . $tanggalSelesai . '|' . ($kelas ?? '__ALL__');
            if (isset($existing[$key])) {
                $skippedCount++;
                continue;
            }

            if ($kelas !== null) {
                $kelasToSync[] = $kelas;
            }

            $rowsToAdd[] = [
                'tanggal' => $tanggalMulai,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'kelas' => $kelas,
                'keterangan' => $keterangan,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing[$key] = true;
            $addedCount++;
        }

        if (!empty($rowsToAdd)) {
            $this->syncKelasValues($kelasToSync);
            HariLibur::query()->insert($rowsToAdd);
        }

        return [
            'success' => true,
            'added' => $addedCount,
            'skipped' => $skippedCount,
            'message' => "Import selesai. Berhasil: {$addedCount}, Gagal: {$skippedCount}",
        ];
    }


    public function getAppConfig(): array
    {
        return ['success' => true, 'data' => $this->getGlobalJamConfig()];
    }


    public function saveAppConfig(array $args): array
    {
        $config = $args[0] ?? [];
        $keys = ['jam_masuk_mulai', 'jam_masuk_akhir', 'jam_masuk_telat', 'jam_pulang_mulai', 'jam_pulang_akhir'];

        foreach ($keys as $key) {
            if (isset($config[$key])) {
                Konfigurasi::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $config[$key]]
                );
            }
        }

        return ['success' => true, 'message' => 'Konfigurasi waktu berhasil disimpan'];
    }
}
