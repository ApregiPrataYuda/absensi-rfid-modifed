<?php

namespace App\Http\Controllers;

use App\Services\Modules\AttendanceRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapBulananController extends PageActionController
{
    public function __construct(
        protected AttendanceRecordService $attendanceRecords,
    ) {
    }

    public function index(): View
    {
        return view('pages.rekap-bulanan');
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->attendanceRecords->getMonthlyReportData($args, $auth));
    }
}
