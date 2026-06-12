<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AttendanceLog::query()
            ->with('device:id,serial_number,mac_address,status')
            ->latest('scanned_at');

        if (isset($validated['device_id'])) {
            $query->where('device_id', $validated['device_id']);
        }

        if (!empty($validated['serial_number'])) {
            $query->whereHas('device', function ($deviceQuery) use ($validated): void {
                $deviceQuery->where('serial_number', $validated['serial_number']);
            });
        }

        if (!empty($validated['date_from'])) {
            $query->where('scanned_at', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }

        if (!empty($validated['date_to'])) {
            $query->where('scanned_at', '<=', Carbon::parse($validated['date_to'])->endOfDay());
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($validated['per_page'] ?? 50),
            'rc' => 200,
        ]);
    }
}
