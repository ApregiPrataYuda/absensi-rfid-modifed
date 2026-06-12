<?php

namespace App\Http\Controllers;

use App\Services\Modules\AttendanceRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends PageActionController
{
    public function __construct(
        protected AttendanceRecordService $attendanceRecords,
    ) {
    }

    public function index(): View
    {
        return view('pages.monitoring');
    }

    public function monitoring(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->getMonitoringRealtime($args, $auth));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->updateAbsensiStatus($args, $auth));
    }
}
