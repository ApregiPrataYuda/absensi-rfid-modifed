<?php

use App\Http\Controllers\Api\Admin\AttendanceLogController as AdminAttendanceLogController;
use App\Http\Controllers\Api\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;


Route::post('/device/activate', [DeviceController::class, 'activate']);
Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle']);

Route::prefix('device')
    ->middleware('device')
    ->group(function (): void {
        Route::post('/cek', [DeviceController::class, 'cek']);
        Route::post('/attendance', [DeviceController::class, 'attendance'])
            ->middleware('throttle:device-attendance');
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin|super-admin'])
    ->group(function (): void {
        Route::get('/devices', [AdminDeviceController::class, 'index']);
        Route::post('/devices', [AdminDeviceController::class, 'store']);
        Route::put('/devices/{device}/activate', [AdminDeviceController::class, 'activate']);
        Route::put('/devices/{device}/deactivate', [AdminDeviceController::class, 'deactivate']);
        Route::put('/devices/{device}/revoke', [AdminDeviceController::class, 'revoke']);
        Route::put('/devices/{device}/reset', [AdminDeviceController::class, 'reset']);
        Route::get('/attendance', [AdminAttendanceLogController::class, 'index']);
    });
