<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    protected array $protectedRoles = [
        'super-admin',
        'admin',
        'bendahara',
        'kepsek',
        'wakasek',
        'wakel',
        'piket',
        'siswa',
    ];

    protected array $manageableUserRoles = [
        'admin',
        'bendahara',
        'kepsek',
        'wakasek',
    ];

    public function usersIndex(): View
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $viewer = auth()->user();
        $viewerManageableRoles = $this->getManageableRolesForViewer($viewer);
        $allowedRoles = $viewerManageableRoles;
        if ($this->isSuperAdmin($viewer)) {
            array_unshift($allowedRoles, 'super-admin');
        }
        $allowedRoles = array_values(array_unique($allowedRoles));
        $users = User::query()
            ->select([
                'id',
                'username',
                'name',
                'email',
                'jenis_kelamin',
                'tanggal_lahir',
                'agama',
                'no_hp',
                'alamat',
                'created_at',
            ])
            ->with(['roles:id,name,guard_name'])
            ->whereHas('roles', function ($query) use ($guard, $allowedRoles) {
                $query
                    ->where('guard_name', $guard)
                    ->whereIn('name', $allowedRoles);
            })
            ->orderBy('username')
            ->get()
            ->sortBy(function (User $user) {
                $roleRank = 9;
                if ($user->hasRole('super-admin')) {
                    $roleRank = 0;
                } elseif ($user->hasRole('admin')) {
                    $roleRank = 1;
                } elseif ($user->hasRole('bendahara')) {
                    $roleRank = 2;
                } elseif ($user->hasRole('kepsek')) {
                    $roleRank = 3;
                } elseif ($user->hasRole('wakasek')) {
                    $roleRank = 4;
                }

                return sprintf('%d_%s', $roleRank, strtolower((string) $user->username));
            })
            ->values();

        return view('pages.user-management', [
            'users' => $users,
            'viewerManageableRoles' => $viewerManageableRoles,
            'viewerCanManageAdminRole' => in_array('admin', $viewerManageableRoles, true),
        ]);
    }

    public function index(): View
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        $roleOrder = $this->protectedRoles;
        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $this->protectedRoles)
            ->with(['permissions:id,name,guard_name'])
            ->get(['id', 'name', 'guard_name'])
            ->sortBy(function (Role $role) use ($roleOrder) {
                $name = strtolower(trim((string) $role->name));
                $index = array_search($name, $roleOrder, true);
                $rank = $index === false ? 99 : (int) $index;

                return sprintf('%02d_%s', $rank, $name);
            })
            ->values();

        return view('pages.role-permission', [
            'roles' => $roles,
            'permissions' => $permissions,
            'protectedRoles' => $this->protectedRoles,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse|JsonResponse
    {
        return $this->errorResponse($request, 'Tambah role dinonaktifkan. Gunakan role sistem yang tersedia.', [
            'role' => 'Tambah role dinonaktifkan.',
        ], 403);
    }

    public function syncPermissions(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        if ($role->guard_name !== $guard) {
            abort(404);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', $guard)),
            ],
        ]);

        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $allowedPermissions = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissions)
            ->get();

        $role->syncPermissions($allowedPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->successResponse($request, 'Permission role "' . $role->name . '" berhasil diperbarui.');
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $guard = (string) config('auth.defaults.guard', 'web');
        if ($role->guard_name !== $guard) {
            abort(404);
        }

        if (in_array(strtolower($role->name), $this->protectedRoles, true)) {
            return $this->errorResponse($request, 'Role bawaan sistem tidak bisa dihapus.', [
                'role' => 'Role bawaan sistem tidak bisa dihapus.',
            ]);
        }

        $role->delete();

        return $this->successResponse($request, 'Role berhasil dihapus.');
    }

    public function storeAdminUser(Request $request): RedirectResponse|JsonResponse
    {
        $viewer = $request->user();
        $allowedRoles = $this->getManageableRolesForViewer($viewer);
        if (count($allowedRoles) === 0) {
            return $this->errorResponse($request, 'Anda tidak memiliki akses untuk mengelola user.', [
                'role' => 'Anda tidak memiliki akses untuk mengelola user.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:' . implode(',', $allowedRoles)],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:30', 'unique:users,no_hp'],
            'alamat' => ['nullable', 'string', 'max:1000'],
        ], [
            'no_hp.unique' => 'No HP sudah digunakan.',
        ]);

        $managedRole = strtolower(trim((string) $validated['role']));
        $username = trim((string) $validated['username']);
        $name = trim((string) ($validated['name'] ?? ''));
        $email = trim((string) ($validated['email'] ?? ''));

        $user = User::query()->create([
            'username' => $username,
            'name' => $name !== '' ? $name : $username,
            'email' => $email !== '' ? strtolower($email) : null,
            'password' => Hash::make((string) $validated['password']),
            'kelas' => null,
            'jenis_kelamin' => $this->nullableString($validated['jenis_kelamin'] ?? null),
            'tanggal_lahir' => $this->nullableString($validated['tanggal_lahir'] ?? null),
            'agama' => $this->nullableString($validated['agama'] ?? null),
            'no_hp' => $this->nullableString($validated['no_hp'] ?? null),
            'alamat' => $this->nullableString($validated['alamat'] ?? null),
        ]);
        $user->syncRoles([$managedRole]);

        return $this->successResponse($request, 'User ' . $managedRole . ' berhasil ditambahkan.');
    }

    public function updateAdminUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $viewer = $request->user();
        if ($user->hasRole('super-admin')) {
            return $this->errorResponse($request, 'Akun ini tidak bisa diubah dari halaman ini.', [
                'user' => 'Akun ini tidak bisa diubah dari halaman ini.',
            ], 403);
        }

        $targetManagedRole = $this->resolveManagedRole($user);
        if ($targetManagedRole === null) {
            return $this->errorResponse($request, 'Akun ini tidak bisa diubah dari halaman ini.', [
                'user' => 'Akun ini tidak bisa diubah dari halaman ini.',
            ], 403);
        }

        if ($targetManagedRole === 'admin' && !$this->isSuperAdmin($viewer)) {
            return $this->errorResponse($request, 'Akun admin hanya bisa dikelola super-admin.', [
                'user' => 'Akun admin hanya bisa dikelola super-admin.',
            ], 403);
        }

        $allowedRoles = $this->getManageableRolesForViewer($viewer);
        if (count($allowedRoles) === 0) {
            return $this->errorResponse($request, 'Anda tidak memiliki akses untuk mengelola user.', [
                'role' => 'Anda tidak memiliki akses untuk mengelola user.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:' . implode(',', $allowedRoles)],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username,' . $user->id],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:120', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'jenis_kelamin' => ['nullable', 'in:Laki-laki,Perempuan'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:30', 'unique:users,no_hp,' . $user->id],
            'alamat' => ['nullable', 'string', 'max:1000'],
        ], [
            'no_hp.unique' => 'No HP sudah digunakan.',
        ]);

        $managedRole = strtolower(trim((string) $validated['role']));
        $username = trim((string) $validated['username']);
        $name = trim((string) ($validated['name'] ?? ''));
        $email = trim((string) ($validated['email'] ?? ''));

        $payload = [
            'username' => $username,
            'name' => $name !== '' ? $name : $username,
            'email' => $email !== '' ? strtolower($email) : null,
            'kelas' => null,
            'jenis_kelamin' => $this->nullableString($validated['jenis_kelamin'] ?? null),
            'tanggal_lahir' => $this->nullableString($validated['tanggal_lahir'] ?? null),
            'agama' => $this->nullableString($validated['agama'] ?? null),
            'no_hp' => $this->nullableString($validated['no_hp'] ?? null),
            'alamat' => $this->nullableString($validated['alamat'] ?? null),
        ];
        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make((string) $validated['password']);
        }

        $user->update($payload);
        $user->syncRoles([$managedRole]);

        return $this->successResponse($request, 'User ' . $managedRole . ' berhasil diperbarui.');
    }

    public function destroyAdminUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $viewer = $request->user();
        $targetManagedRole = $this->resolveManagedRole($user);
        if ($user->hasRole('super-admin') || $targetManagedRole === null) {
            return $this->errorResponse($request, 'Akun ini tidak bisa dihapus dari halaman ini.', [
                'user' => 'Akun ini tidak bisa dihapus dari halaman ini.',
            ], 403);
        }

        if ($targetManagedRole === 'admin' && !$this->isSuperAdmin($viewer)) {
            return $this->errorResponse($request, 'Akun admin hanya bisa dikelola super-admin.', [
                'user' => 'Akun admin hanya bisa dikelola super-admin.',
            ], 403);
        }

        $user->delete();

        return $this->successResponse($request, 'User berhasil dihapus.');
    }

    protected function nullableString($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    protected function resolveManagedRole(User $user): ?string
    {
        foreach ($this->manageableUserRoles as $roleName) {
            if ($user->hasRole($roleName)) {
                return $roleName;
            }
        }

        return null;
    }

    protected function getManageableRolesForViewer(?User $viewer): array
    {
        $roles = $this->manageableUserRoles;
        if (!$this->isSuperAdmin($viewer)) {
            $roles = array_values(array_filter(
                $roles,
                static fn (string $roleName) => $roleName !== 'admin'
            ));
        }

        return array_values(array_unique($roles));
    }

    protected function isSuperAdmin(?User $user): bool
    {
        return $user ? $user->hasRole('super-admin') : false;
    }

    protected function isJsonRequest(Request $request): bool
    {
        if ($request->expectsJson() || $request->ajax()) {
            return true;
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        return str_contains($accept, 'application/json');
    }

    protected function successResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($this->isJsonRequest($request)) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    protected function errorResponse(Request $request, string $message, array $errors = [], int $status = 422): RedirectResponse|JsonResponse
    {
        if ($this->isJsonRequest($request)) {
            $payload = [
                'success' => false,
                'message' => $message,
            ];
            if (!empty($errors)) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, $status);
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        return back()->withErrors(['error' => $message])->withInput();
    }
}
