<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user
            ? strtolower((string) ($user->getRoleNames()->first() ?? ''))
            : null;

        if (in_array($role, ['super-admin', 'admin', 'kepsek', 'wakasek'], true)) {
            return view('pages.dashboard-admin');
        }

        if ($role === 'bendahara') {
            return view('pages.dashboard-bendahara');
        }

        if (in_array($role, ['wakel', 'piket'], true)) {
            return view('pages.dashboard-guru');
        }

        return view('pages.dashboard-siswa');
    }
}
