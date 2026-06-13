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

class StudentRecordService extends BaseActionService
{
    public function getSiswaList(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakel', 'siswa'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $filterKelas = $args[0] ?? null;
        $query = Siswa::query();
        if ($role === 'wakel') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun mandor belum ditautkan ke gedung.'];
            }

            $query->where('kelas', $wakelKelas);
        } elseif ($role === 'siswa') {
            $siswa = $this->getSiswaFromAuth($auth);
            if (!$siswa) {
                return ['success' => true, 'data' => []];
            }

            $query->where('id', $siswa->id);
        } elseif ($filterKelas) {
            $query->where('kelas', $filterKelas);
        }

        $query->with([
            'kartuAbsensi' => fn ($cardQuery) => $cardQuery
                ->where('type', KartuAbsensi::TYPE_RFID)
                ->orderByDesc('last_scanned_at')
                ->orderByDesc('id'),
        ]);

        $rows = $query->orderBy('nama')->get();

        $data = $rows->map(function (Siswa $siswa) {
            return [
                'nama' => $siswa->nama,
                'nisn' => $siswa->nisn,
                'jenisKelamin' => $siswa->jenis_kelamin,
                'tanggalLahir' => optional($siswa->tanggal_lahir)->format('Y-m-d'),
                'agama' => $siswa->agama,
                'namaAyah' => $siswa->nama_ayah,
                'namaIbu' => $siswa->nama_ibu,
                'noHp' => $siswa->no_hp,
                'kelas' => $siswa->kelas,
                'nomorKartu' => $siswa->kartuAbsensi->first()?->code,
                'alamat' => $siswa->alamat,
            ];
        })->values()->all();

        return ['success' => true, 'data' => $data];
    }


    public function addSiswa(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakel'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $payload = $args[0] ?? [];
        $nisn = isset($payload['nisn']) ? trim((string) $payload['nisn']) : '';
        if ($nisn === '') {
            return ['success' => false, 'message' => 'NIK wajib diisi'];
        }

        if (Siswa::query()->where('nisn', $nisn)->exists()) {
            return ['success' => false, 'message' => 'NIK sudah terdaftar'];
        }

        $kelasInput = $this->normalizeKelasValue($payload['kelas'] ?? null);
        $kelas = null;
        if ($role === 'wakel') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun mandor belum ditautkan ke kelas.'];
            }

            if ($kelasInput !== null && $kelasInput !== $wakelKelas) {
                return ['success' => false, 'message' => 'mandor hanya boleh menambah karaywan di gedung yg di kelola sendiri.'];
            }

            $kelas = $this->syncKelasValue($wakelKelas);
        } else {
            $kelas = $this->syncKelasValue($kelasInput);
        }

        try {
            DB::transaction(function () use ($payload, $nisn, $kelas): void {
                $siswa = Siswa::query()->create([
                    'nama' => $payload['nama'] ?? '',
                    'nisn' => $nisn,
                    'jenis_kelamin' => $payload['jenisKelamin'] ?? null,
                    'tanggal_lahir' => $payload['tanggalLahir'] ?? null,
                    'agama' => $payload['agama'] ?? null,
                    'nama_ayah' => $payload['namaAyah'] ?? null,
                    'nama_ibu' => $payload['namaIbu'] ?? null,
                    'no_hp' => $payload['noHp'] ?? null,
                    'kelas' => $kelas,
                    'alamat' => $payload['alamat'] ?? null,
                ]);

                if (array_key_exists('nomorKartu', $payload)) {
                    $this->syncSiswaRfidCardOrFail($siswa, $payload['nomorKartu']);
                }
            });
        } catch (\RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return ['success' => true, 'message' => 'Karyawan berhasil ditambahkan (Aman)'];
    }


    public function updateSiswa(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakel'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $oldNisn = trim((string) ($args[0] ?? ''));
        $payload = $args[1] ?? [];
        if ($oldNisn === '') {
            return ['success' => false, 'message' => 'NIK lama wajib diisi'];
        }

        $siswa = Siswa::query()->where('nisn', $oldNisn)->first();
        if (!$siswa) {
            return ['success' => false, 'message' => 'Karyawan tidak ditemukan (Cek spasi pada NIK)'];
        }

        $wakelKelas = null;
        if ($role === 'wakel') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun Mandor belum ditautkan ke Gedung.'];
            }

            if ($this->normalizeKelasValue($siswa->kelas) !== $wakelKelas) {
                return ['success' => false, 'message' => 'Mandor hanya boleh mengubah karyawan di gedung yg dia kelola sendiri.'];
            }
        }

        $oldNisnValue = trim((string) $siswa->nisn);
        $newNisn = isset($payload['nisn']) ? trim((string) $payload['nisn']) : $siswa->nisn;
        if ($newNisn !== $siswa->nisn && Siswa::query()->where('nisn', $newNisn)->exists()) {
            return ['success' => false, 'message' => 'NIK sudah terdaftar'];
        }

        $kelas = $this->normalizeKelasValue($siswa->kelas);
        if (array_key_exists('kelas', $payload)) {
            $kelas = $this->normalizeKelasValue($payload['kelas']);
        }
        if ($role === 'wakel') {
            if ($kelas !== null && $kelas !== $wakelKelas) {
                return ['success' => false, 'message' => 'Mandor tidak boleh memindahkan karyawan ke gedung - lantai lain.'];
            }

            $kelas = $wakelKelas;
        } elseif ($kelas !== null) {
            $kelas = $this->syncKelasValue($kelas);
        }

        try {
            DB::transaction(function () use ($siswa, $payload, $newNisn, $kelas, $oldNisnValue): void {
                $siswa->update([
                    'nama' => array_key_exists('nama', $payload)
                        ? trim((string) ($payload['nama'] ?? ''))
                        : $siswa->nama,
                    'nisn' => $newNisn,
                    'jenis_kelamin' => $this->payloadOptionalStringOrCurrent($payload, 'jenisKelamin', $siswa->jenis_kelamin),
                    'tanggal_lahir' => $this->payloadOptionalStringOrCurrent($payload, 'tanggalLahir', $siswa->tanggal_lahir),
                    'agama' => $this->payloadOptionalStringOrCurrent($payload, 'agama', $siswa->agama),
                    'nama_ayah' => $this->payloadOptionalStringOrCurrent($payload, 'namaAyah', $siswa->nama_ayah),
                    'nama_ibu' => $this->payloadOptionalStringOrCurrent($payload, 'namaIbu', $siswa->nama_ibu),
                    'no_hp' => $this->payloadOptionalStringOrCurrent($payload, 'noHp', $siswa->no_hp),
                    'kelas' => $kelas,
                    'alamat' => $this->payloadOptionalStringOrCurrent($payload, 'alamat', $siswa->alamat),
                ]);

                if (array_key_exists('nomorKartu', $payload)) {
                    $this->syncSiswaRfidCardOrFail($siswa, $payload['nomorKartu']);
                }

                $this->syncSiswaUserFromSiswa($oldNisnValue, $siswa);
            });
        } catch (\RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return ['success' => true, 'message' => 'Karyawan berhasil diupdate'];
    }


    protected function syncSiswaRfidCardOrFail(Siswa $siswa, mixed $rawCode): void
    {
        $code = strtoupper(trim((string) ($rawCode ?? '')));

        if ($code === '') {
            KartuAbsensi::query()
                ->where('type', KartuAbsensi::TYPE_RFID)
                ->where('siswa_id', $siswa->id)
                ->update(['siswa_id' => null]);

            return;
        }

        if (strlen($code) > 255) {
            throw new \RuntimeException('Nomor kartu maksimal 255 karakter.');
        }

        $conflictingCard = KartuAbsensi::query()
            ->where('type', KartuAbsensi::TYPE_RFID)
            ->where('code', $code)
            ->whereNotNull('siswa_id')
            ->where('siswa_id', '!=', $siswa->id)
            ->exists();

        if ($conflictingCard) {
            throw new \RuntimeException('Nomor kartu sudah ditautkan ke Karyawan lain.');
        }

        KartuAbsensi::query()
            ->where('type', KartuAbsensi::TYPE_RFID)
            ->where('siswa_id', $siswa->id)
            ->where('code', '!=', $code)
            ->update(['siswa_id' => null]);

        $card = KartuAbsensi::query()->firstOrNew([
            'type' => KartuAbsensi::TYPE_RFID,
            'code' => $code,
        ]);

        $card->siswa_id = $siswa->id;
        $card->save();
    }


    public function deleteSiswa(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakel'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $nisn = $args[0] ?? null;
        if (!$nisn) {
            return ['success' => false, 'message' => 'NIK wajib diisi'];
        }

        $siswa = Siswa::query()->where('nisn', trim((string) $nisn))->first();
        if (!$siswa) {
            return ['success' => false, 'message' => 'Data Karyawan tidak ditemukan.'];
        }

        if ($role === 'wakel') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun mandor belum ditautkan ke kelas.'];
            }

            if ($this->normalizeKelasValue($siswa->kelas) !== $wakelKelas) {
                return ['success' => false, 'message' => 'mandor hanya boleh menghapus karyawan di gedung yang di kelola sendiri.'];
            }
        }

        $nisnValue = trim((string) $siswa->nisn);
        DB::transaction(function () use ($siswa, $nisnValue) {
            $siswa->delete();
            $this->deleteSiswaUserByNisn($nisnValue);
        });

        return ['success' => true, 'message' => 'Data Karyawan berhasil dihapus.'];
    }


    public function importSiswaBulk(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin.'];
        }

        $dataArray = $args[0] ?? [];
        $existing = Siswa::query()->pluck('nisn')->map(fn ($n) => trim((string) $n))->flip();

        $rowsToAdd = [];
        $addedCount = 0;
        $skippedCount = 0;
        $kelasToSync = [];

        foreach ($dataArray as $item) {
            $nisn = isset($item['nisn']) ? trim((string) $item['nisn']) : '';
            if (empty($item['nama']) || $nisn === '') {
                $skippedCount++;
                continue;
            }

            if (isset($existing[$nisn])) {
                $skippedCount++;
                continue;
            }

            $kelas = $this->normalizeKelasValue($item['kelas'] ?? null);
            if ($kelas !== null) {
                $kelasToSync[] = $kelas;
            }

            $tanggalLahirRaw = $item['tanggalLahir'] ?? $item['tanggal_lahir'] ?? null;
            $tanggalLahir = $this->normalizeDateValue($tanggalLahirRaw);
            if (trim((string) ($tanggalLahirRaw ?? '')) !== '' && $tanggalLahir === null) {
                $skippedCount++;
                continue;
            }

            $rowsToAdd[] = [
                'nama' => $item['nama'],
                'nisn' => $nisn,
                'jenis_kelamin' => $item['jenisKelamin'] ?? null,
                'tanggal_lahir' => $tanggalLahir,
                'agama' => $item['agama'] ?? null,
                'nama_ayah' => $item['namaAyah'] ?? null,
                'nama_ibu' => $item['namaIbu'] ?? null,
                'no_hp' => $item['noHp'] ?? null,
                'kelas' => $kelas,
                'alamat' => $item['alamat'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing[$nisn] = true;
            $addedCount++;
        }

        if (!empty($rowsToAdd)) {
            $this->syncKelasValues($kelasToSync);
            Siswa::query()->insert($rowsToAdd);
        }

        return [
            'success' => true,
            'added' => $addedCount,
            'skipped' => $skippedCount,
            'message' => "Import selesai. Berhasil: {$addedCount}, Duplikat/Gagal: {$skippedCount}",
        ];
    }


    public function getSiswaByKelas(array $args, $auth): array
    {
        if (!$this->authHasAnyRole($auth, ['admin'])) {
            return [];
        }

        $kelasTarget = $args[0] ?? null;
        if (!$kelasTarget) {
            return [];
        }

        return Siswa::query()
            ->where('kelas', $kelasTarget)
            ->orderBy('nama')
            ->get(['nama', 'nisn', 'kelas'])
            ->map(fn ($row) => [
                'nama' => $row->nama,
                'nisn' => $row->nisn,
                'kelas' => $row->kelas,
            ])
            ->all();
    }


    public function lookupSiswaForScan(array $args, $auth): array
    {
        $role = $this->getRoleFromAuth($auth);
        if (!$auth || !in_array($role, ['admin', 'kepsek', 'wakasek', 'wakel', 'piket'], true)) {
            return ['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki izin scan.'];
        }

        $nisn = trim((string) ($args[0] ?? ''));
        if ($nisn === '') {
            return ['success' => false, 'message' => 'NIK tidak valid.'];
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();
        if (!$siswa) {
            return ['success' => false, 'message' => 'NIK tidak ditemukan.'];
        }

        $siswaKelas = $this->normalizeKelasValue($siswa->kelas);
        if ($role === 'wakel') {
            $wakelKelas = $this->getWakelKelasFromAuth($auth);
            if ($wakelKelas === null) {
                return ['success' => false, 'message' => 'Akun mandor belum ditautkan ke gedung.'];
            }
            if ($siswaKelas !== $wakelKelas) {
                return ['success' => false, 'message' => 'Hanya bisa scan karyawan di gedung yang Anda kelola.'];
            }
        }

        if ($role === 'piket') {
            $piketKelas = $this->getPiketKelasFromAuth($auth);
            // Jika kelas piket kosong => boleh scan semua kelas.
            if ($piketKelas !== null && $siswaKelas !== $piketKelas) {
                return ['success' => false, 'message' => 'Akun piket ini hanya bisa scan gedung ' . $piketKelas . '.'];
            }
        }

        return [
            'success' => true,
            'nisn' => $siswa->nisn,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
        ];
    }

}