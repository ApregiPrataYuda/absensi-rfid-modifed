<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\JadwalHarian;
use App\Models\Kelas;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AcademicCalendarService
{
    public function resolveHolidayNameForDate(string $date, ?string $kelas = null): ?string
    {
        $normalizedDate = $this->normalizeDateValue($date);
        if ($normalizedDate === null) {
            return null;
        }

        $normalizedKelas = $this->normalizeKelasValue($kelas);
        $holidayRanges = $this->getHolidayRanges($normalizedDate, $normalizedDate);

        $holidayName = $this->resolveHolidayNameFromRanges($normalizedDate, $normalizedKelas, $holidayRanges);
        if ($holidayName !== null) {
            return $holidayName;
        }

        return $this->resolveJadwalLiburNameForDate($normalizedDate, $normalizedKelas);
    }

    /**
     * @return array<string, string>
     */
    public function getHolidayDateMap(string $startDate, string $endDate, ?string $kelas = null): array
    {
        $start = $this->normalizeDateValue($startDate);
        $end = $this->normalizeDateValue($endDate);
        if ($start === null || $end === null) {
            return [];
        }

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        $normalizedKelas = $this->normalizeKelasValue($kelas);
        $holidayRanges = $this->getHolidayRanges($start, $end);
        $result = [];

        foreach (CarbonPeriod::create($start, $end) as $tanggal) {
            $date = $tanggal->toDateString();
            $holidayName = $this->resolveHolidayNameFromRanges($date, $normalizedKelas, $holidayRanges);
            if ($holidayName === null) {
                $holidayName = $this->resolveJadwalLiburNameForDate($date, $normalizedKelas);
            }
            if ($holidayName !== null) {
                $result[$date] = $holidayName;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{tanggal_mulai:string,tanggal_selesai:string,kelas:?string,keterangan:string}>
     */
    protected function getHolidayRanges(string $startDate, string $endDate): array
    {
        return HariLibur::query()
            ->whereRaw('DATE(COALESCE(tanggal_mulai, tanggal)) <= ?', [$endDate])
            ->whereRaw('DATE(COALESCE(tanggal_selesai, tanggal_mulai, tanggal)) >= ?', [$startDate])
            ->orderBy('tanggal_mulai')
            ->get([
                'tanggal',
                'tanggal_mulai',
                'tanggal_selesai',
                'kelas',
                'keterangan',
            ])
            ->map(function (HariLibur $row): ?array {
                $mulai = $this->normalizeDateValue($row->tanggal_mulai) ?? $this->normalizeDateValue($row->tanggal);
                $selesai = $this->normalizeDateValue($row->tanggal_selesai) ?? $mulai;
                if ($mulai === null) {
                    return null;
                }
                if ($selesai === null || $selesai < $mulai) {
                    $selesai = $mulai;
                }

                $keterangan = trim((string) ($row->keterangan ?? ''));
                if ($keterangan === '') {
                    $keterangan = 'Libur';
                }

                return [
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                    'kelas' => $this->normalizeKelasValue($row->kelas),
                    'keterangan' => $keterangan,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{tanggal_mulai:string,tanggal_selesai:string,kelas:?string,keterangan:string}>  $holidayRanges
     */
    protected function resolveHolidayNameFromRanges(string $date, ?string $kelas, array $holidayRanges): ?string
    {
        $globalHoliday = null;

        foreach ($holidayRanges as $range) {
            if ($date < $range['tanggal_mulai'] || $date > $range['tanggal_selesai']) {
                continue;
            }

            $rangeKelas = $range['kelas'] ?? null;
            if ($rangeKelas !== null) {
                if ($kelas !== null && $rangeKelas === $kelas) {
                    return $range['keterangan'];
                }
                continue;
            }

            if ($globalHoliday === null) {
                $globalHoliday = $range['keterangan'];
            }
        }

        return $globalHoliday;
    }

    protected function resolveJadwalLiburNameForDate(string $date, ?string $kelas): ?string
    {
        if ($kelas === null || $kelas === '') {
            return null;
        }

        $kelasId = (int) (Kelas::query()->where('nama', $kelas)->value('id') ?? 0);
        if ($kelasId <= 0) {
            return null;
        }

        $hari = (int) Carbon::parse($date)->dayOfWeekIso;
        $row = JadwalHarian::query()
            ->where('kelas_id', $kelasId)
            ->where('hari', $hari)
            ->where('is_libur', true)
            ->first(['keterangan']);

        if (!$row) {
            return null;
        }

        $keterangan = trim((string) ($row->keterangan ?? ''));

        return $keterangan !== '' ? $keterangan : 'Libur (Jadwal)';
    }

    protected function normalizeDateValue($value): ?string
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        try {
            return Carbon::parse($stringValue)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeKelasValue($value): ?string
    {
        $kelas = trim((string) $value);

        return $kelas === '' ? null : $kelas;
    }
}

