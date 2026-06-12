<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DeviceSettingController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $devices = Device::query()
            ->withCount('attendanceLogs')
            ->latest('id')
            ->get();

        if ($this->isJsonRequest($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Data device berhasil dimuat.',
                'rc' => 200,
                'data' => [
                    'devices' => $this->serializeDevices($devices),
                    'stats' => $this->deviceStats(),
                ],
            ]);
        }

        return view('pages.settings-devices', [
            'devices' => $devices,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:devices,serial_number'],
        ]);

        $device = Device::query()->create([
            'name' => $this->requiredString($validated['name'] ?? null),
            'serial_number' => trim((string) $validated['serial_number']),
            'status' => Device::STATUS_PENDING,
        ]);

        return $this->successResponse($request, 'Device berhasil didaftarkan.', [
            'device' => $this->serializeDevice($device),
            'stats' => $this->deviceStats(),
        ]);
    }

    public function revoke(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $device->forceFill([
            'status' => Device::STATUS_REVOKED,
        ])->save();

        return $this->successResponse($request, 'Device berhasil di-revoke.', [
            'device' => $this->serializeDevice($device),
            'stats' => $this->deviceStats(),
        ]);
    }

    public function activate(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        if ($device->status === Device::STATUS_ACTIVE) {
            return $this->successResponse($request, 'Device sudah dalam status aktif.', [
                'device' => $this->serializeDevice($device),
                'stats' => $this->deviceStats(),
            ]);
        }

        if ($device->status === Device::STATUS_REVOKED) {
            return $this->errorResponse($request, 'Device yang sudah di-revoke harus di-reset terlebih dahulu.');
        }

        if ($device->status !== Device::STATUS_INACTIVE) {
            return $this->errorResponse($request, 'Hanya device nonaktif yang bisa diaktifkan kembali.');
        }

        if (!$device->mac_address || !$device->device_token) {
            return $this->errorResponse($request, 'Device belum pernah aktif penuh. Silakan reset lalu aktivasi ulang dari mesin.');
        }

        $device->forceFill([
            'status' => Device::STATUS_ACTIVE,
        ])->save();

        return $this->successResponse($request, 'Device berhasil diaktifkan kembali.', [
            'device' => $this->serializeDevice($device),
            'stats' => $this->deviceStats(),
        ]);
    }

    public function deactivate(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        if ($device->status === Device::STATUS_INACTIVE) {
            return $this->successResponse($request, 'Device sudah dalam status nonaktif.', [
                'device' => $this->serializeDevice($device),
                'stats' => $this->deviceStats(),
            ]);
        }

        if ($device->status === Device::STATUS_REVOKED) {
            return $this->errorResponse($request, 'Device yang sudah di-revoke tidak bisa dinonaktifkan.');
        }

        if ($device->status !== Device::STATUS_ACTIVE) {
            return $this->errorResponse($request, 'Hanya device aktif yang bisa dinonaktifkan.');
        }

        $device->forceFill([
            'status' => Device::STATUS_INACTIVE,
        ])->save();

        return $this->successResponse($request, 'Device berhasil dinonaktifkan.', [
            'device' => $this->serializeDevice($device),
            'stats' => $this->deviceStats(),
        ]);
    }

    public function reset(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $device->forceFill([
            'mac_address' => null,
            'device_token' => null,
            'firmware_version' => null,
            'status' => Device::STATUS_PENDING,
            'last_seen' => null,
            'activated_at' => null,
        ])->save();

        return $this->successResponse($request, 'Device berhasil di-reset ke status pending.', [
            'device' => $this->serializeDevice($device),
            'stats' => $this->deviceStats(),
        ]);
    }

    public function destroy(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $deviceId = (int) $device->id;
        $serialNumber = (string) $device->serial_number;

        $device->delete();

        return $this->successResponse($request, 'Device ' . $serialNumber . ' berhasil dihapus.', [
            'deleted_id' => $deviceId,
            'stats' => $this->deviceStats(),
        ]);
    }

    protected function isJsonRequest(Request $request): bool
    {
        if ($request->expectsJson() || $request->ajax()) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        return str_contains($accept, 'application/json');
    }

    protected function successResponse(Request $request, string $message, array $data = []): RedirectResponse|JsonResponse
    {
        if ($this->isJsonRequest($request)) {
            $payload = [
                'success' => true,
                'message' => $message,
                'rc' => 200,
            ];
            if ($data !== []) {
                $payload['data'] = $data;
            }

            return response()->json($payload);
        }

        return back()->with('success', $message);
    }

    protected function errorResponse(Request $request, string $message, int $statusCode = 422): RedirectResponse|JsonResponse
    {
        if ($this->isJsonRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'rc' => $statusCode,
            ], $statusCode);
        }

        return back()->withErrors(['device' => $message]);
    }

    protected function serializeDevice(Device $device): array
    {
        if (!array_key_exists('attendance_logs_count', $device->getAttributes())) {
            $device->loadCount('attendanceLogs');
        }

        return [
            'id' => (int) $device->id,
            'name' => $device->name ? (string) $device->name : null,
            'serial_number' => (string) $device->serial_number,
            'status' => (string) $device->status,
            'mac_address' => $device->mac_address ? (string) $device->mac_address : null,
            'firmware_version' => $device->firmware_version ? (string) $device->firmware_version : null,
            'attendance_logs_count' => (int) ($device->attendance_logs_count ?? 0),
            'created_at_label' => $device->created_at?->format('d M Y H:i') ?? '-',
            'last_seen_date' => $device->last_seen?->format('d M Y'),
            'last_seen_time' => $device->last_seen?->format('H:i:s'),
            'activated_at_date' => $device->activated_at?->format('d M Y'),
            'activated_at_time' => $device->activated_at?->format('H:i:s'),
        ];
    }

    protected function serializeDevices(Collection $devices): array
    {
        return $devices
            ->map(fn (Device $device) => $this->serializeDevice($device))
            ->values()
            ->all();
    }

    protected function deviceStats(): array
    {
        return [
            'total' => Device::query()->count(),
            'active' => Device::query()->where('status', Device::STATUS_ACTIVE)->count(),
            'inactive' => Device::query()->where('status', Device::STATUS_INACTIVE)->count(),
            'pending' => Device::query()->where('status', Device::STATUS_PENDING)->count(),
            'revoked' => Device::query()->where('status', Device::STATUS_REVOKED)->count(),
        ];
    }

    protected function requiredString($value): string
    {
        return trim((string) $value);
    }

}
