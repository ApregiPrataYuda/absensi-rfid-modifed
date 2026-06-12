<?php

namespace App\Http\Controllers;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class PageActionController extends Controller
{
    protected function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }

    protected function respondNoArgs(callable $handler): JsonResponse
    {
        return response()->json($handler());
    }

    protected function respondArgs(Request $request, callable $handler): JsonResponse
    {
        return response()->json($handler($this->extractArgs($request)));
    }

    protected function respondAuth(callable $handler): JsonResponse
    {
        return response()->json($handler($this->resolvePageAuthToken()));
    }

    protected function respondArgsAuth(Request $request, callable $handler): JsonResponse
    {
        return response()->json($handler($this->extractArgs($request), $this->resolvePageAuthToken()));
    }

    protected function extractArgs(Request $request): array
    {
        $args = $request->input('args', []);

        if (!is_array($args)) {
            return [$args];
        }

        return array_values($args);
    }

    protected function resolvePageAuthToken(): ?AuthToken
    {
        $user = request()->user();
        if (!$user instanceof User) {
            return null;
        }

        $clientRole = $this->resolvePageClientRole($user);

        $auth = AuthToken::query()
            ->with(['user.roles'])
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (!$auth) {
            $auth = AuthToken::query()->create([
                'token' => (string) Str::uuid(),
                'user_id' => $user->id,
                'siswa_id' => null,
                'role' => $clientRole,
                'expires_at' => now()->addDay(),
                'created_at' => now(),
            ]);
        } elseif ((string) ($auth->role ?? '') !== $clientRole) {
            $auth->forceFill([
                'role' => $clientRole,
            ])->save();
        }

        return $auth->loadMissing(['user.roles']);
    }

    protected function resolvePageClientRole(User $user): string
    {
        $roleName = strtolower(trim((string) ($user->getRoleNames()->first() ?? '')));

        return $roleName === 'super-admin' ? 'admin' : $roleName;
    }
}
