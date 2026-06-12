<?php

namespace App\Http\Controllers;

use App\Services\Modules\HolidaySettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KelolaAbsenController extends PageActionController
{
    public function __construct(
        protected HolidaySettingService $holidaySettings,
    ) {
    }

    public function index(): View
    {
        return view('pages.kelola-absen');
    }

    public function appConfig(): JsonResponse
    {
        return $this->respondNoArgs(fn () => $this->holidaySettings->getAppConfig());
    }

    public function updateAppConfig(Request $request): JsonResponse
    {
        return $this->respondArgs($request, fn (array $args) => $this->holidaySettings->saveAppConfig($args));
    }

    public function holidayList(): JsonResponse
    {
        return $this->respondNoArgs(fn () => $this->holidaySettings->getHariLiburList());
    }

    public function addHoliday(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->holidaySettings->addHariLibur($args, $auth));
    }

    public function deleteHoliday(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->holidaySettings->deleteHariLibur($args, $auth));
    }

    public function importHoliday(Request $request): JsonResponse
    {
        return $this->respondArgsAuth($request, fn (array $args, $auth) => $this->holidaySettings->importHariLiburBulk($args, $auth));
    }
}
