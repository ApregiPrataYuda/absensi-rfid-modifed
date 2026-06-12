<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\DeviceActivateRequest;
use App\Http\Requests\Api\Device\DeviceAttendanceRequest;
use App\Http\Requests\Api\Device\DeviceHeartbeatRequest;
use App\Http\Requests\Api\Device\DeviceCekRequest;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\KartuAbsensi;
use App\Support\DeviceApiResponder;
use App\Services\AttendanceCardService;
use App\Services\StudentAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function activate(DeviceActivateRequest $request): JsonResponse
    {
        $device = Device::query()
            ->where('serial_number', $request->string('serial_number')->toString())
            ->first();

        if (!$device) {
            return $this->errorResponse($request, 'Serial number tidak valid.', 401, [
                'reason' => 'invalid_serial_number',
            ]);
        }

        if ($device->status === Device::STATUS_REVOKED) {
            return $this->errorResponse($request, 'Device sudah dicabut.', 403, [
                'reason' => 'device_revoked',
            ]);
        }

        if ($device->status === Device::STATUS_INACTIVE) {
            return $this->errorResponse($request, 'Device sedang nonaktif.', 403, [
                'reason' => 'device_inactive',
                'require_activation' => false,
            ]);
        }

        $incomingMacAddress = $this->normalizeMacAddress($request->string('mac_address')->toString());
        $device->firmware_version = $this->nullableString($request->input('firmware_version'));

        if ($device->mac_address === null) {
            if ($response = $this->ensureMacAddressAvailable($request, $device, $incomingMacAddress)) {
                return $response;
            }

            $device->mac_address = $incomingMacAddress;
            $device->device_token = $this->generateDeviceToken();
            $device->status = Device::STATUS_ACTIVE;
            $device->activated_at = now();
        } elseif ($this->normalizeMacAddress((string) $device->mac_address) !== $incomingMacAddress) {
            return $this->errorResponse($request, 'MAC address tidak cocok dengan device ini.', 401, [
                'reason' => 'mac_address_mismatch',
            ]);
        } else {
            if (!$device->device_token) {
                $device->device_token = $this->generateDeviceToken();
            }

            $device->status = Device::STATUS_ACTIVE;
            $device->activated_at ??= now();
        }

        $device->last_seen = now();
        $device->save();

        return $this->successResponse($request, 'Aktivasi berhasil', [
            'reason' => 'device_activated',
            'serial_number' => (string) $device->serial_number,
            'device_name' => $device->name ? (string) $device->name : null,
            'token' => (string) $device->device_token,
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ]);
    }

    public function cek(DeviceCekRequest $request): JsonResponse
    {
        $device = $this->deviceFromRequest($request);
        $incomingSerialNumber = trim($request->string('serial_number')->toString());
        $incomingMacAddress = $this->normalizeMacAddress($request->string('mac_address')->toString());

        if ((string) $device->serial_number !== $incomingSerialNumber) {
            return $this->errorResponse($request, 'Serial number tidak cocok dengan device ini.', 401, [
                'reason' => 'serial_number_mismatch',
            ]);
        }

        if ($device->mac_address === null) {
            if ($response = $this->ensureMacAddressAvailable($request, $device, $incomingMacAddress)) {
                return $response;
            }

            $device->mac_address = $incomingMacAddress;
        } elseif ($this->normalizeMacAddress((string) $device->mac_address) !== $incomingMacAddress) {
            return $this->errorResponse($request, 'MAC address tidak cocok dengan device ini.', 401, [
                'reason' => 'mac_address_mismatch',
            ]);
        }

        $device->forceFill([
            'firmware_version' => $this->nullableString($request->input('firmware_version')),
            'last_seen' => now(),
        ])->save();

        return $this->successResponse($request, 'Device terdaftar', [
            'reason' => 'device_verified',
            'registered' => true,
            'serial_number' => (string) $device->serial_number,
            'device_name' => $device->name ? (string) $device->name : null,
            'status' => (string) $device->status,
            'require_activation' => false,
        ]);
    }

    public function attendance(DeviceAttendanceRequest $request): JsonResponse
    {
        $device = $this->deviceFromRequest($request);
        $scannedAt = $request->date('scanned_at');
        $uid = trim($request->string('uid')->toString());

        AttendanceLog::query()->create([
            'device_id' => $device->id,
            'uid' => $uid,
            'scanned_at' => $scannedAt,
        ]);

        $device->forceFill([
            'last_seen' => now(),
        ])->save();

        $cardService = app(AttendanceCardService::class);
        $attendanceService = app(StudentAttendanceService::class);
        $resolvedCard = $cardService->resolveFromScan($uid, KartuAbsensi::TYPE_RFID, 'device');

        if (!($resolvedCard['success'] ?? false)) {
            return $this->errorResponse($request, $resolvedCard['message'] ?? 'UID tidak valid.', 422, [
                'reason' => 'invalid_card_code',
            ]);
        }

        $card = $resolvedCard['card'];
        if (!$card->siswa) {
            $isNewCard = (bool) ($resolvedCard['created'] ?? false);

            return $this->errorResponse(
                $request,
                $isNewCard
                    ? 'Kartu baru terdeteksi. Tautkan kartu ke siswa terlebih dahulu.'
                    : 'Kartu belum ditautkan ke siswa.',
                409,
                [
                    'reason' => $isNewCard ? 'new_card_detected' : 'card_not_linked',
                    'card_registered' => $isNewCard,
                    'card_code' => $card->code,
                    'card_type' => $card->type,
                ]
            );
        }

        // Gunakan waktu scan dari device agar queue offline tetap dihitung pada waktu asli scan.
        $attendance = $attendanceService->process($card->siswa, $scannedAt);
        if (!($attendance['success'] ?? false)) {
            return $this->errorResponse(
                $request,
                $attendance['message'] ?? 'Absensi ditolak.',
                422,
                $this->buildAttendanceResponseData($attendance, $card->code, $scannedAt?->format('Y-m-d H:i:s'))
            );
        }

        return $this->successResponse(
            $request,
            $attendance['message'] ?? 'Absensi berhasil diterima',
            $this->buildAttendanceResponseData($attendance, $card->code, $scannedAt?->format('Y-m-d H:i:s'))
        );
    }

    public function heartbeat(DeviceHeartbeatRequest $request): JsonResponse
    {
        $device = $this->deviceFromRequest($request);

        $device->forceFill([
            'last_seen' => now(),
        ])->save();

        return $this->successResponse($request, 'Device online', [
            'reason' => 'heartbeat_received',
            'server_time' => now()->format('Y-m-d H:i:s'),
            'sync_interval' => 60,
        ]);
    }

    protected function deviceFromRequest(Request $request): Device
    {
        $device = $request->attributes->get('device');

        if (!$device instanceof Device) {
            abort($this->errorResponse($request, 'Unauthorized', 401, [
                'reason' => 'invalid_token',
            ]));
        }

        return $device;
    }

    protected function generateDeviceToken(): string
    {
        do {
            $token = Str::random(80);
        } while (Device::query()->where('device_token', $token)->exists());

        return $token;
    }

    protected function normalizeMacAddress(string $macAddress): string
    {
        return strtolower(trim($macAddress));
    }

    protected function ensureMacAddressAvailable(Request $request, Device $device, string $incomingMacAddress): ?JsonResponse
    {
        $alreadyAssigned = Device::query()
            ->where('mac_address', $incomingMacAddress)
            ->whereKeyNot($device->id)
            ->exists();

        if ($alreadyAssigned) {
            return $this->errorResponse($request, 'MAC address sudah digunakan device lain.', 409, [
                'reason' => 'mac_address_taken',
            ]);
        }

        return null;
    }

    protected function buildAttendanceResponseData(array $attendance, string $uid, ?string $fallbackTime): array
    {
        $data = [
            'reason' => $attendance['reason'] ?? 'attendance_rejected',
            'uid' => $uid,
            'student_nisn' => $attendance['nisn'] ?? null,
            'student_name' => $attendance['nama'] ?? null,
            'student_class' => $attendance['kelas'] ?? null,
            'server_time' => now()->format('Y-m-d H:i:s'),
        ];

        if (array_key_exists('type', $attendance)) {
            $data['type'] = $attendance['type'];
        }

        if (array_key_exists('status_label', $attendance)) {
            $data['status_label'] = $attendance['status_label'];
        }

        $time = $attendance['jamDatang'] ?? $attendance['jamPulang'] ?? $fallbackTime;
        if ($time !== null) {
            $data['time'] = $time;
        }

        foreach ([
            'holiday_name',
            'checkin_start',
            'checkin_end',
            'checkin_time',
            'checkout_time',
        ] as $key) {
            if (array_key_exists($key, $attendance)) {
                $data[$key] = $attendance[$key];
            }
        }

        return $data;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    protected function successResponse(Request $request, string $message, array|object|null $data = null, int $statusCode = 200): JsonResponse
    {
        return DeviceApiResponder::success($request, $message, $data, $statusCode);
    }

    protected function errorResponse(Request $request, string $message, int $statusCode, array|object|null $data = null): JsonResponse
    {
        return DeviceApiResponder::error($request, $message, $statusCode, $data);
    }
}
