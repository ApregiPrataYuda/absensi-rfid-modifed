<?php

namespace App\Services;

use App\Models\KartuAbsensi;
use App\Models\Siswa;

class AttendanceCardService
{
    public function resolveFromScan(string $rawCode, string $type, string $source = 'web'): array
    {
        $normalizedCode = $this->normalizeCode($rawCode);
        if ($normalizedCode === '') {
            return [
                'success' => false,
                'message' => 'Kode kartu tidak valid.',
            ];
        }

        $normalizedType = $this->normalizeType($type);
        $normalizedSource = $this->normalizeSource($source);
        $card = KartuAbsensi::query()
            ->with('siswa')
            ->where('type', $normalizedType)
            ->where('code', $normalizedCode)
            ->first();

        $created = false;
        $autoLinked = false;
        $matchingStudent = null;

        if (!$card) {
            $matchingStudent = $this->findMatchingStudent($normalizedType, $normalizedCode);

            $card = KartuAbsensi::query()->create([
                'type' => $normalizedType,
                'code' => $normalizedCode,
                'siswa_id' => $matchingStudent?->id,
                'last_scanned_at' => now(),
                'last_scanned_source' => $normalizedSource,
            ]);
            $card->setRelation('siswa', $matchingStudent);

            $created = true;
        } else {
            if ($card->siswa_id === null) {
                $matchingStudent = $this->findMatchingStudent($normalizedType, $normalizedCode);
                if ($matchingStudent) {
                    $card->siswa_id = $matchingStudent->id;
                    $autoLinked = true;
                    $card->setRelation('siswa', $matchingStudent);
                }
            }

            $card->last_scanned_at = now();
            $card->last_scanned_source = $normalizedSource;
            $card->save();
        }

        return [
            'success' => true,
            'created' => $created,
            'auto_linked' => $autoLinked,
            'card' => $card,
        ];
    }

    public function normalizeCode(string $rawCode): string
    {
        $code = trim($rawCode);

        return $code === '' ? '' : strtoupper($code);
    }

    protected function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, [KartuAbsensi::TYPE_QR, KartuAbsensi::TYPE_RFID], true)
            ? $type
            : KartuAbsensi::TYPE_QR;
    }

    protected function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));

        return $source === '' ? 'web' : $source;
    }

    protected function findMatchingStudent(string $type, string $normalizedCode): ?Siswa
    {
        if ($type !== KartuAbsensi::TYPE_QR) {
            return null;
        }

        return Siswa::query()
            ->where('nisn', $normalizedCode)
            ->first();
    }
}
