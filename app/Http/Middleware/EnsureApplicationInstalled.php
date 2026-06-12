<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = (bool) config('app.installed', true);
        $routeName = (string) ($request->route()?->getName() ?? '');
        $isInstallRoute = str_starts_with($routeName, 'install.');

        if (!$installed) {
            if ($isInstallRoute || $request->is('up')) {
                return $next($request);
            }

            return redirect()->route('install.requirements');
        }

        if ($isInstallRoute) {
            return $request->user()
                ? redirect()->route('dashboard')
                : redirect()->route('login');
        }

        return $next($request);
    }
}
