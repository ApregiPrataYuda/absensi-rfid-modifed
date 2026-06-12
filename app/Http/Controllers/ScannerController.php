<?php

namespace App\Http\Controllers;

use App\Services\Modules\AttendanceRecordService;
use App\Services\Modules\StudentRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScannerController extends PageActionController
{
    public function __construct(
        protected AttendanceRecordService $attendanceRecords,
        protected StudentRecordService $studentRecords,
    ) {
    }

    public function index(): View
    {
        return view('pages.scanner');
    }

    public function lookupForScan(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->lookupSiswaForScan($args, $auth));
    }

    public function batchScan(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->batchScanAbsensi($args, $auth));
    }

    public function scanRfid(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->scanRfidAbsensi($args, $auth));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->updateAbsensiStatus($args, $auth));
    }
}
