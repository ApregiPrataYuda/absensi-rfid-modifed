<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        $allowedRoles = array_map(
            static fn ($role) => strtolower(trim((string) $role)),
            $roles
        );
        $hasRole = $user->hasAnyRole($allowedRoles);

        if (!$hasRole) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses Ditolak: Anda tidak memiliki izin.',
                ], 403);
            }

            abort(403, 'Akses Ditolak: Anda tidak memiliki izin.');
        }

        return $next($request);
    }
}
