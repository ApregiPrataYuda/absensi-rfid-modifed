<?php

namespace App\Http\Controllers;

use App\Services\AppUpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdateSettingController extends Controller
{
    protected function applyUpdateServerDefaults(): void
    {
        $baseUrl = 'https://release.absensindo.com';
        $baseUrl = rtrim($baseUrl, '/');

        $manifestUrl = $baseUrl . '/api/apps/absensindo/manifest';
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        config([
            'updater.manifest_url' => $manifestUrl,
            'updater.public_key' => 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0NCk1JSUJJakFOQmdrcWhraUc5dzBCQVFFRkFBT0NBUThBTUlJQkNnS0NBUUVBeTlRZ3B0eUhPMzRva2Z6a2ptN0oNCnhzRS9NTHdEVGsrS2FIcVJzRG5DWG1IMUZyeHNZYjNkbFQ3VHVvT3NuWHpTbHp3R24wcVBHbXJTYXQ2ZTBhNDQNClNoRWdtcW5xc1R5dHNNaWpqbXFMM2EyN3JJSndEMHN3SmNiWjI1NW9PYWxSVzgxNDd6UFU1NmJUdDVkS2Y1OW8NCmpEY1duWUwxMlR2Q09IVnJGbzNkQ3gzMDV4WHF2dm1tMHRrcFVhZHhMU1k1cmkvVTIyc2dMU0JSb2tLTEVoc1oNCjRDOWRBMUd3M0hkdlNLTHBYN0pYeFJGaTE1ZzVnNFgxQ2p1RWJRYkVhU3NrSzNOK04yTzI2c2dPdFJFM3hSUisNCkQ2WS9taHpOSFV0VGFHMFVzM2h4dWpudSswN09BUnowbUdMb1l0bjFYQjFOMC9kWHhMNG5uUmhWVEkzTmJ0NDANCkh3SURBUUFCDQotLS0tLUVORCBQVUJMSUMgS0VZLS0tLS0NCg==',
            'updater.license_key' => 'fd06344b1e76df978fc4cfd8c10c121f5acb95720b0bf0211a047968b19fab0f',
            'updater.timeout' => 20,
            'updater.connect_timeout' => 10,
            'updater.download_timeout' => 300,
            'updater.download_retry' => 2,
            'updater.download_retry_sleep_ms' => 750,
        ]);

        if ($host !== '') {
            config([
                'updater.allowed_hosts' => [$host],
            ]);
        }
    }

    public function index(): View
    {
        $this->applyUpdateServerDefaults();

        $updatesRoot = storage_path('app/updates');
        $lastUpdate = null;
        $lastUpdatePath = $updatesRoot . DIRECTORY_SEPARATOR . 'last_update.json';

        if (is_file($lastUpdatePath)) {
            try {
                $payload = json_decode((string) file_get_contents($lastUpdatePath), true);
                if (is_array($payload)) {
                    $lastUpdate = $payload;
                }
            } catch (\Throwable $e) {
                $lastUpdate = null;
            }
        }

        return view('pages.settings-update', [
            'currentVersion' => (string) config('app.version', ''),
            'manifestUrl' => (string) config('updater.manifest_url', ''),
            'lastUpdate' => $lastUpdate,
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $progressPath = storage_path('app/updates/progress.json');

        if (!is_file($progressPath)) {
            return response()->json([
                'status' => 'idle',
            ]);
        }

        try {
            $payload = json_decode((string) file_get_contents($progressPath), true);
            if (is_array($payload)) {
                return response()->json($payload);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'status' => 'idle',
        ]);
    }

    public function check(Request $request, AppUpdaterService $updater): JsonResponse
    {
        $this->applyUpdateServerDefaults();

        return response()->json($updater->check());
    }

    public function install(Request $request, AppUpdaterService $updater): JsonResponse
    {
        $this->applyUpdateServerDefaults();

        try {
            if ($request->hasSession()) {
                $request->session()->save();
            }
            if (function_exists('session_write_close')) {
                @session_write_close();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json($updater->installLatest());
    }
}
