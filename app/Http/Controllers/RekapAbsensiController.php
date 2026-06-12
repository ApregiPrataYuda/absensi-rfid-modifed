<?php

namespace App\Http\Controllers;

use App\Services\Modules\AttendanceRecordService;
use App\Services\Modules\PromotionWorkflowService;
use App\Services\Modules\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapAbsensiController extends PageActionController
{
    public function __construct(
        protected AttendanceRecordService $attendanceRecords,
        protected PromotionWorkflowService $promotionWorkflows,
        protected ReportExportService $reportExports,
    ) {
    }

    public function index(): View
    {
        return view('pages.rekap-absensi');
    }

    public function attendanceList(Request $request): JsonResponse
    {
        return $this->respondArgs($request, fn (array $args) => $this->attendanceRecords->getAbsensiList($args));
    }

    public function generateExcelAction(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->reportExports->generateExcel($args, $auth));
    }

    public function templateExcel(Request $request): JsonResponse
    {
        return $this->respondArgs($request, fn (array $args) => $this->reportExports->getTemplateExcel($args));
    }

    public function archivePreview(): JsonResponse
    {
        return $this->respondAuth(fn ($auth) => $this->promotionWorkflows->getArchiveResetPreview($auth));
    }

    public function archiveReset(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->promotionWorkflows->archiveAndResetYear($args, $auth));
    }

    public function download(Request $request)
    {
        return $this->reportExports->downloadExport($request);
    }
}
