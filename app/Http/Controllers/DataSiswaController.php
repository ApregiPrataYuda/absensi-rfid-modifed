<?php

namespace App\Http\Controllers;

use App\Services\Modules\StudentRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataSiswaController extends PageActionController
{
    public function __construct(
        protected StudentRecordService $studentRecords,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($this->shouldReturnJson($request)) {
            return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->getSiswaList($args, $auth));
        }

        return view('pages.data-siswa');
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->addSiswa($args, $auth));
    }

    public function update(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->updateSiswa($args, $auth));
    }

    public function destroy(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->deleteSiswa($args, $auth));
    }

    public function import(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->importSiswaBulk($args, $auth));
    }

    public function byClass(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->getSiswaByKelas($args, $auth));
    }

    public function lookupForScan(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->studentRecords->lookupSiswaForScan($args, $auth));
    }
}
