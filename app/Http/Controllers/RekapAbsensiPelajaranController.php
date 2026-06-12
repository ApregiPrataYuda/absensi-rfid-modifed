<?php

namespace App\Http\Controllers;

use App\Services\Modules\LessonWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapAbsensiPelajaranController extends PageActionController
{
    public function __construct(
        protected LessonWorkflowService $lessonWorkflows,
    ) {
    }

    public function index(): View
    {
        return view('pages.rekap-absensi-pelajaran');
    }

    public function lessonReport(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->getPelajaranReportData($args, $auth));
    }

    public function lessonReportDetail(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->getPelajaranReportSessionDetail($args, $auth));
    }
}
