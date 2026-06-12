<?php

use App\Support\DeviceApiResponder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Cloudflare / tunnel proxy headers so Laravel keeps HTTPS scheme.
        $middleware->trustProxies(at: '*');
        $middleware->preventRequestsDuringMaintenance(except: [
            'settings/update/progress',
        ]);
        $middleware->statefulApi();
        $middleware->web(append: [
            \App\Http\Middleware\EnsureApplicationInstalled::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'device' => \App\Http\Middleware\AuthenticateDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            if (DeviceApiResponder::isDeviceRequest($request)) {
                return DeviceApiResponder::error($request, 'Unauthorized', 401, [
                    'reason' => 'unauthenticated',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unauthenticated.',
                'rc' => 401,
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            if (DeviceApiResponder::isDeviceRequest($request)) {
                return DeviceApiResponder::error($request, 'Unauthorized', 403, [
                    'reason' => 'forbidden',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                'rc' => 403,
            ], 403);
        });

        $exceptions->render(function (SpatieUnauthorizedException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            if (DeviceApiResponder::isDeviceRequest($request)) {
                return DeviceApiResponder::error($request, 'Unauthorized', 403, [
                    'reason' => 'forbidden',
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                'rc' => 403,
            ], 403);
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            $statusCode = $e->status;

            if (DeviceApiResponder::isDeviceRequest($request)) {
                return DeviceApiResponder::error($request, 'Validation error', $statusCode, [
                    'errors' => $e->errors(),
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'rc' => $statusCode,
            ], $statusCode);
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if (!$request->expectsJson()) {
                return null;
            }

            $statusCode = $e->getStatusCode();
            $message = $e->getMessage() !== ''
                ? $e->getMessage()
                : (SymfonyResponse::$statusTexts[$statusCode] ?? 'HTTP error.');

            if (DeviceApiResponder::isDeviceRequest($request)) {
                return DeviceApiResponder::error($request, $message, $statusCode, null, $e->getHeaders());
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'rc' => $statusCode,
            ], $statusCode, $e->getHeaders());
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (!$request->expectsJson() || !DeviceApiResponder::isDeviceRequest($request)) {
                return null;
            }

            return DeviceApiResponder::error($request, 'Internal server error', 500);
        });
    })->create();
