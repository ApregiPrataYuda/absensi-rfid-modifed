<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\AdminStoreDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = Device::query()
            ->withCount('attendanceLogs')
            ->latest('id')
            ->get();

        return $this->successResponse([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    public function store(AdminStoreDeviceRequest $request): JsonResponse
    {
        $device = Device::query()->create([
            'name' => $this->requiredString($request->input('name')),
            'serial_number' => $request->string('serial_number')->toString(),
            'status' => Device::STATUS_PENDING,
        ]);

        return $this->successResponse([
            'status' => 'success',
            'data' => $device,
        ], 201);
    }

    public function revoke(Device $device): JsonResponse
    {
        $device->forceFill([
            'status' => Device::STATUS_REVOKED,
        ])->save();

        return $this->successResponse([
            'status' => 'success',
            'data' => $device->fresh(),
        ]);
    }

    public function activate(Device $device): JsonResponse
    {
        if ($device->status === Device::STATUS_ACTIVE) {
            return $this->successResponse([
                'status' => 'success',
                'data' => $device->fresh(),
            ]);
        }

        if ($device->status === Device::STATUS_REVOKED) {
            return $this->errorResponse('Device yang sudah di-revoke harus di-reset terlebih dahulu.');
        }

        if ($device->status !== Device::STATUS_INACTIVE) {
            return $this->errorResponse('Hanya device nonaktif yang bisa diaktifkan kembali.');
        }

        if (!$device->mac_address || !$device->device_token) {
            return $this->errorResponse('Device belum pernah aktif penuh. Silakan reset lalu aktivasi ulang dari mesin.');
        }

        $device->forceFill([
            'status' => Device::STATUS_ACTIVE,
        ])->save();

        return $this->successResponse([
            'status' => 'success',
            'data' => $device->fresh(),
        ]);
    }

    public function deactivate(Device $device): JsonResponse
    {
        if ($device->status === Device::STATUS_INACTIVE) {
            return $this->successResponse([
                'status' => 'success',
                'data' => $device->fresh(),
            ]);
        }

        if ($device->status === Device::STATUS_REVOKED) {
            return $this->errorResponse('Device yang sudah di-revoke tidak bisa dinonaktifkan.');
        }

        if ($device->status !== Device::STATUS_ACTIVE) {
            return $this->errorResponse('Hanya device aktif yang bisa dinonaktifkan.');
        }

        $device->forceFill([
            'status' => Device::STATUS_INACTIVE,
        ])->save();

        return $this->successResponse([
            'status' => 'success',
            'data' => $device->fresh(),
        ]);
    }

    public function reset(Device $device): JsonResponse
    {
        $device->forceFill([
            'mac_address' => null,
            'device_token' => null,
            'firmware_version' => null,
            'status' => Device::STATUS_PENDING,
            'last_seen' => null,
            'activated_at' => null,
        ])->save();

        return $this->successResponse([
            'status' => 'success',
            'data' => $device->fresh(),
        ]);
    }

    protected function successResponse(array $payload, int $statusCode = 200): JsonResponse
    {
        $payload['rc'] = $statusCode;

        return response()->json($payload, $statusCode);
    }

    protected function errorResponse(string $message, int $statusCode = 422): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'rc' => $statusCode,
        ], $statusCode);
    }

    protected function requiredString($value): string
    {
        return trim((string) $value);
    }
}
