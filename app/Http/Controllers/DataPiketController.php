<?php

namespace App\Http\Controllers;

use App\Services\Modules\StaffRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataPiketController extends PageActionController
{
    public function __construct(
        protected StaffRecordService $staffRecords,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($this->shouldReturnJson($request)) {
            return $this->respondAuth(fn ($auth) => $this->staffRecords->getPiketList($auth));
        }

        return view('pages.data-guru', [
            'staffContext' => 'piket',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->staffRecords->addPiket($args, $auth));
    }

    public function update(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->staffRecords->updatePiket($args, $auth));
    }

    public function destroy(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->staffRecords->deletePiket($args, $auth));
    }

    public function import(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->staffRecords->importPiketBulk($args, $auth));
    }
}
