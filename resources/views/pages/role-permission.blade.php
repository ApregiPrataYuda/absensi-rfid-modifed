@extends('layouts.page')

@section('title', 'Manajemen Role & Permission')

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Manajemen Role & Permission</h3>
            <p class="text-xs text-gray-500 mt-1">Akses halaman ini dikontrol oleh permission <code>settings.roles.manage</code>.</p>
        </div>

        <div class="p-4 space-y-4">
            @if (session('success'))
                <div class="px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/40">
                <div class="flex gap-2 overflow-x-auto">
                    @foreach ($roles as $index => $role)
                        <button
                            type="button"
                            id="role-tab-{{ $role->id }}"
                            data-panel="role-panel-{{ $role->id }}"
                            role="tab"
                            aria-controls="role-panel-{{ $role->id }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            class="js-role-tab shrink-0 px-3 py-1.5 rounded-lg text-xs font-bold transition {{ $index === 0 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-100' }}"
                        >
                            {{ strtoupper($role->name) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-4">
                @foreach ($roles as $index => $role)
                    @php
                        $roleName = strtolower((string) $role->name);
                        $isProtectedRole = in_array($roleName, $protectedRoles, true);
                        $ownedPermissions = $role->permissions->pluck('name')->all();
                    @endphp
                    <div
                        id="role-panel-{{ $role->id }}"
                        role="tabpanel"
                        aria-labelledby="role-tab-{{ $role->id }}"
                        class="js-role-panel p-4 rounded-xl border border-gray-200 {{ $index === 0 ? '' : 'hidden' }}"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <div>
                                <h4 class="text-sm font-bold text-gray-800">{{ $role->name }}</h4>
                                <p class="text-xs text-gray-500">Pilih permission yang diizinkan untuk role ini.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($isProtectedRole)
                                    <span class="px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-semibold">Role Sistem</span>
                                @else
                                    <form method="POST" action="{{ route('role-permission.destroy-role', $role) }}" class="js-role-delete-form" data-role-name="{{ $role->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded border border-red-200 bg-red-50 text-red-700 text-xs font-bold hover:bg-red-100 transition">
                                            Hapus Role
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('role-permission.sync', $role) }}" class="js-role-sync-form">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 mb-3">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 px-2 py-2 rounded border border-gray-100 hover:bg-gray-50 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->name }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            {{ in_array($permission->name, $ownedPermissions, true) ? 'checked' : '' }}
                                        >
                                        <span class="text-xs text-gray-700">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition">
                                    Simpan Permission
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function roleGetCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function roleExtractError(payload, fallback = 'Terjadi kesalahan.') {
        if (!payload || typeof payload !== 'object') {
            return fallback;
        }

        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }

        const errors = payload.errors;
        if (errors && typeof errors === 'object') {
            const firstKey = Object.keys(errors)[0];
            const firstValue = firstKey ? errors[firstKey] : null;
            if (Array.isArray(firstValue) && firstValue.length > 0) {
                return String(firstValue[0]);
            }
            if (typeof firstValue === 'string' && firstValue.trim() !== '') {
                return firstValue;
            }
        }

        return fallback;
    }

    async function roleSendAjaxForm(form) {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': roleGetCsrfToken(),
            },
            body: new FormData(form),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(roleExtractError(payload, 'Gagal memproses request.'));
        }

        return payload;
    }

    function roleSetSubmitting(button, text) {
        if (!button) return () => {};
        const prevHtml = button.innerHTML;
        button.disabled = true;
        button.textContent = text;
        return () => {
            button.disabled = false;
            button.innerHTML = prevHtml;
        };
    }

    function initRoleTabs() {
        const tabs = Array.from(document.querySelectorAll('.js-role-tab'));
        const panels = Array.from(document.querySelectorAll('.js-role-panel'));
        if (tabs.length === 0 || panels.length === 0) return;

        const activateTab = (targetTab, updateHash = true) => {
            const panelId = String(targetTab.dataset.panel || '');
            if (!panelId) return;

            tabs.forEach((tab) => {
                const active = tab === targetTab;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.classList.toggle('bg-indigo-600', active);
                tab.classList.toggle('text-white', active);
                tab.classList.toggle('bg-white', !active);
                tab.classList.toggle('text-gray-600', !active);
                tab.classList.toggle('border', !active);
                tab.classList.toggle('border-gray-200', !active);
                tab.classList.toggle('hover:bg-gray-100', !active);
            });

            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.id !== panelId);
            });

            if (updateHash && window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', '#' + panelId);
            }
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => activateTab(tab));
        });

        const currentHash = String(window.location.hash || '').replace('#', '');
        const initialTab = tabs.find((tab) => tab.dataset.panel === currentHash) || tabs[0];
        activateTab(initialTab, false);
    }

    async function handleRoleDeleteSubmit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) return;

        const roleName = String(form.dataset.roleName || 'role ini');
        if (!window.confirm(`Hapus role ${roleName}?`)) return;

        const button = form.querySelector('button[type="submit"]');
        const done = roleSetSubmitting(button, 'Menghapus...');

        try {
            const payload = await roleSendAjaxForm(form);
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Role berhasil dihapus.');
            }
            setTimeout(() => window.location.reload(), 180);
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal menghapus role.');
            }
            done();
        }
    }

    async function handleRoleSyncSubmit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) return;

        const button = form.querySelector('button[type="submit"]');
        const done = roleSetSubmitting(button, 'Menyimpan...');
        try {
            const payload = await roleSendAjaxForm(form);
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Permission berhasil diperbarui.');
            }
            done();
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal menyimpan permission.');
            }
            done();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.js-role-delete-form').forEach((form) => {
            form.addEventListener('submit', handleRoleDeleteSubmit);
        });

        document.querySelectorAll('.js-role-sync-form').forEach((form) => {
            form.addEventListener('submit', handleRoleSyncSubmit);
        });

        initRoleTabs();
    });
</script>
@endpush
