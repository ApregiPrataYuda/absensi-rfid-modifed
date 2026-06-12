<?php

namespace App\Http\Controllers;

use App\Services\Modules\LessonWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsensiPelajaranController extends PageActionController
{
    public function __construct(
        protected LessonWorkflowService $lessonWorkflows,
    ) {
    }

    public function index(): View
    {
        return view('pages.absensi-pelajaran');
    }

    public function todaySessions(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->getPelajaranSessionsToday($args, $auth));
    }

    public function sessionDetail(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->getPelajaranSessionDetail($args, $auth));
    }

    public function startSession(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->startPelajaranSession($args, $auth));
    }

    public function scan(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->scanPelajaranAbsensi($args, $auth));
    }

    public function setStatus(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->setPelajaranAbsensiStatus($args, $auth));
    }

    public function broadcast(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->broadcastPelajaranHadir($args, $auth));
    }

    public function closeSession(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->lessonWorkflows->closePelajaranSession($args, $auth));
    }
}
