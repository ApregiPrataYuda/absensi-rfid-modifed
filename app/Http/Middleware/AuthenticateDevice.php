<?php

namespace App\Http\Middleware;

use App\Models\Device;
use App\Support\DeviceApiResponder;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());
        if ($token === '') {
            return $this->unauthorizedResponse($request, 'missing_token');
        }

        $device = Device::query()
            ->where('device_token', $token)
            ->first();

        if (!$device) {
            return $this->unauthorizedResponse($request, 'invalid_token');
        }

        if ($device->status !== Device::STATUS_ACTIVE) {
            if ($device->status === Device::STATUS_REVOKED) {
                return $this->unauthorizedResponse($request, 'device_revoked');
            }

            if ($device->status === Device::STATUS_INACTIVE) {
                return $this->unauthorizedResponse($request, 'device_inactive');
            }

            return $this->unauthorizedResponse($request, 'device_not_active');
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }

    protected function unauthorizedResponse(Request $request, string $reason): JsonResponse
    {
        return DeviceApiResponder::error($request, 'Unauthorized', 401, [
            'reason' => $reason,
        ]);
    }
}
