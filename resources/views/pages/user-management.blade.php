@extends('layouts.page')

@section('title', 'Manajemen User')

@section('content')
<div id="view-user-management" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-sm text-gray-800">Manajemen User</h3>
                <p class="text-xs text-gray-500 mt-1">
                    @if (!empty($viewerCanManageAdminRole))
                        Kelola akun role admin, mandor. Role super-admin hanya bisa dilihat (read-only).
                    @else
                        Kelola akun role 
                    @endif
                </p>
            </div>
            @if (!empty($viewerManageableRoles))
                <button onclick="openAddAdminUserModal()" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-purple-700 transition">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            @endif
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

            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="p-3 text-center w-12">No</th>
                            <th class="p-3">Username</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3 hidden md:table-cell">Email</th>
                            <th class="p-3">Role</th>
                            <th class="p-3 text-center w-40">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                        @forelse ($users as $idx => $user)
                            @php
                                $isSuperAdmin = $user->hasRole('super-admin');
                                $roleName = $isSuperAdmin
                                    ? 'super-admin'
                                    : ($user->hasRole('bendahara')
                                        ? 'bendahara'
                                    : ($user->hasRole('kepsek')
                                        ? 'kepsek'
                                        : ($user->hasRole('wakasek') ? 'wakasek' : 'admin')));
                                $roleBadgeClass = match ($roleName) {
                                    'super-admin' => 'bg-purple-50 text-purple-700',
                                    'bendahara' => 'bg-amber-50 text-amber-700',
                                    'kepsek' => 'bg-emerald-50 text-emerald-700',
                                    'wakasek' => 'bg-cyan-50 text-cyan-700',
                                    default => 'bg-indigo-50 text-indigo-700',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 text-center text-gray-500">{{ $idx + 1 }}</td>
                                <td class="p-3 font-semibold text-gray-800">{{ $user->username }}</td>
                                <td class="p-3">{{ $user->name ?: '-' }}</td>
                                <td class="p-3 hidden md:table-cell">{{ $user->email ?: '-' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-[11px] font-bold {{ $roleBadgeClass }}">
                                        {{ strtoupper($roleName) }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    @if ($isSuperAdmin)
                                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-[11px] font-semibold">Read-only</span>
                                    @else
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                data-action="{{ route('role-permission.users.update', $user) }}"
                                                data-username="{{ $user->username }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $roleName }}"
                                                data-jenis-kelamin="{{ $user->jenis_kelamin }}"
                                                data-tanggal-lahir="{{ optional($user->tanggal_lahir)->format('Y-m-d') }}"
                                                data-agama="{{ $user->agama }}"
                                                data-no-hp="{{ $user->no_hp }}"
                                                data-alamat="{{ $user->alamat }}"
                                                onclick="openEditAdminUserModal(this)"
                                                class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition"
                                                title="Edit"
                                            >
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <form method="POST" action="{{ route('role-permission.users.destroy', $user) }}" class="js-user-delete-form" data-username="{{ $user->username }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-400">Belum ada data user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="adminUserModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/55" onclick="closeModal()"></div>
    <div class="relative w-full max-w-3xl">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden w-full">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 id="adminUserModalTitle" class="text-xl font-bold text-gray-800">Tambah User</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-6 max-h-[75vh] overflow-y-auto">
                <form id="adminUserForm" method="POST" action="" class="space-y-5">
                    @csrf
                    <input id="adminUserMethod" type="hidden" name="_method" value="PUT" disabled>
                    <input id="adminUserFormMode" type="hidden" name="form_mode" value="add">
                    <input id="adminUserEditAction" type="hidden" name="edit_action" value="">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Username</label>
                            <input id="adminUserUsername" name="username" placeholder="Username" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nama User</label>
                            <input id="adminUserName" name="name" placeholder="Nama lengkap user" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Email</label>
                            <input id="adminUserEmail" type="email" name="email" placeholder="email@domain.com (opsional)" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Role</label>
                            <select id="adminUserRole" name="role" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                                @foreach (($viewerManageableRoles ?? []) as $manageableRole)
                                    <option value="{{ $manageableRole }}">{{ strtoupper($manageableRole) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label id="adminUserPasswordLabel" class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Password</label>
                            <input id="adminUserPassword" type="password" name="password" placeholder="Password" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                            <p id="adminUserPasswordHint" class="text-[11px] text-gray-500 mt-1">Wajib diisi untuk akun baru.</p>
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">No HP</label>
                            <input id="adminUserNoHp" name="no_hp" placeholder="08xxxxxxxxxx" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Jenis Kelamin</label>
                            <select id="adminUserJenisKelamin" name="jenis_kelamin" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal Lahir</label>
                            <input id="adminUserTanggalLahir" type="date" name="tanggal_lahir" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Agama</label>
                            <select id="adminUserAgama" name="agama" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all">
                                <option value="">-- Pilih Agama --</option>
                                <option value="Islam">Islam</option>
                                <option value="Kristen">Kristen</option>
                                <option value="Katolik">Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Buddha">Buddha</option>
                                <option value="Konghucu">Konghucu</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Alamat</label>
                        <textarea id="adminUserAlamat" name="alamat" rows="3" placeholder="Alamat lengkap" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2.5 transition-all"></textarea>
                    </div>

                    <div class="flex justify-end items-center gap-2 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModal()" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl border border-gray-200 bg-white text-gray-700 font-semibold text-sm hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-200 transition">
                            <i class="fas fa-times text-xs"></i>
                            Batal
                        </button>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold text-sm shadow-md hover:from-purple-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-purple-300 transition transform active:scale-[0.98]">
                            <i class="fas fa-save text-xs"></i>
                            <span id="adminUserSubmitText">Simpan Data</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const storeAdminUserUrl = @json(route('role-permission.users.store'));
    const defaultManageableRole = @json($viewerManageableRoles[0] ?? null);
    let adminModalRefs = null;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function extractAjaxErrorMessage(payload, fallback = 'Terjadi kesalahan.') {
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

    async function sendAjaxForm(form) {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: new FormData(form),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            throw new Error(extractAjaxErrorMessage(payload, 'Gagal memproses data.'));
        }

        return payload;
    }

    function getAdminModalRefs() {
        if (adminModalRefs) return adminModalRefs;

        adminModalRefs = {
            modal: document.getElementById('adminUserModal'),
            form: document.getElementById('adminUserForm'),
            method: document.getElementById('adminUserMethod'),
            formMode: document.getElementById('adminUserFormMode'),
            editAction: document.getElementById('adminUserEditAction'),
            title: document.getElementById('adminUserModalTitle'),
            submitText: document.getElementById('adminUserSubmitText'),
            username: document.getElementById('adminUserUsername'),
            name: document.getElementById('adminUserName'),
            email: document.getElementById('adminUserEmail'),
            role: document.getElementById('adminUserRole'),
            password: document.getElementById('adminUserPassword'),
            passwordLabel: document.getElementById('adminUserPasswordLabel'),
            passwordHint: document.getElementById('adminUserPasswordHint'),
            jenisKelamin: document.getElementById('adminUserJenisKelamin'),
            tanggalLahir: document.getElementById('adminUserTanggalLahir'),
            agama: document.getElementById('adminUserAgama'),
            noHp: document.getElementById('adminUserNoHp'),
            alamat: document.getElementById('adminUserAlamat'),
            submitButton: document.querySelector('#adminUserForm button[type="submit"]'),
        };

        return adminModalRefs;
    }

    function showModal() {
        const refs = getAdminModalRefs();
        if (!refs.modal) return;

        refs.modal.classList.remove('hidden');
        refs.modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        const refs = getAdminModalRefs();
        if (!refs.modal) return;

        refs.modal.classList.add('hidden');
        refs.modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    function openAdminUserModal(config = {}) {
        const refs = getAdminModalRefs();
        if (!refs.form) return;

        const isEdit = config.mode === 'edit';
        const action = isEdit ? String(config.action || '').trim() : storeAdminUserUrl;
        if (!action) return;

        refs.form.action = action;
        refs.formMode.value = isEdit ? 'edit' : 'add';
        refs.editAction.value = isEdit ? action : '';
        refs.method.disabled = !isEdit;
        refs.method.value = 'PUT';

        refs.title.textContent = isEdit ? 'Edit User' : 'Tambah User';
        refs.submitText.textContent = isEdit ? 'Perbarui Data' : 'Simpan Data';
        refs.passwordLabel.textContent = isEdit ? 'Password Baru (Opsional)' : 'Password';
        refs.passwordHint.textContent = isEdit ? 'Kosongkan jika password tidak diubah.' : 'Wajib diisi untuk akun baru.';
        refs.password.placeholder = isEdit ? 'Kosongkan jika tidak diubah' : 'Password';
        refs.password.required = !isEdit;

        refs.username.value = config.username || '';
        refs.name.value = config.name || '';
        refs.email.value = config.email || '';
        refs.role.value = config.role || defaultManageableRole || '';
        refs.password.value = '';
        refs.jenisKelamin.value = config.jenisKelamin || '';
        refs.tanggalLahir.value = config.tanggalLahir || '';
        refs.agama.value = config.agama || '';
        refs.noHp.value = config.noHp || '';
        refs.alamat.value = config.alamat || '';

        showModal();
    }

    function openAddAdminUserModal() {
        openAdminUserModal({
            mode: 'add',
            username: '',
            name: '',
            email: '',
            role: defaultManageableRole || '',
            jenisKelamin: '',
            tanggalLahir: '',
            agama: '',
            noHp: '',
            alamat: '',
        });
    }

    function openEditAdminUserModal(button) {
        if (!button) return;
        openAdminUserModal({
            mode: 'edit',
            action: button.dataset.action || '',
            username: button.dataset.username || '',
            name: button.dataset.name || '',
            email: button.dataset.email || '',
            role: button.dataset.role || defaultManageableRole || '',
            jenisKelamin: button.dataset.jenisKelamin || '',
            tanggalLahir: button.dataset.tanggalLahir || '',
            agama: button.dataset.agama || '',
            noHp: button.dataset.noHp || '',
            alamat: button.dataset.alamat || '',
        });
    }

    async function handleAdminUserFormSubmit(event) {
        event.preventDefault();
        const refs = getAdminModalRefs();
        if (!refs.form) return;

        const submitTextBefore = refs.submitText ? refs.submitText.textContent : 'Simpan Data';
        if (refs.submitText) refs.submitText.textContent = 'Menyimpan...';
        if (refs.submitButton) refs.submitButton.disabled = true;

        try {
            const payload = await sendAjaxForm(refs.form);
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'Data berhasil disimpan.');
            }
            closeModal();
            setTimeout(() => window.location.reload(), 180);
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal menyimpan data.');
            }
        } finally {
            if (refs.submitText) refs.submitText.textContent = submitTextBefore;
            if (refs.submitButton) refs.submitButton.disabled = false;
        }
    }

    async function handleDeleteUserSubmit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) return;

        const username = String(form.dataset.username || 'user ini');
        if (!window.confirm(`Hapus user ${username}?`)) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        const icon = button ? button.querySelector('i') : null;
        if (button) button.disabled = true;
        if (icon) icon.classList.add('fa-spin');

        try {
            const payload = await sendAjaxForm(form);
            if (window.showAlert) {
                window.showAlert('success', payload.message || 'User berhasil dihapus.');
            }
            setTimeout(() => window.location.reload(), 180);
        } catch (error) {
            if (window.showAlert) {
                window.showAlert('error', error.message || 'Gagal menghapus user.');
            }
            if (button) button.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const hasErrors = @json($errors->any());
        const refs = getAdminModalRefs();
        if (refs.form) {
            refs.form.addEventListener('submit', handleAdminUserFormSubmit);
        }
        document.querySelectorAll('.js-user-delete-form').forEach((form) => {
            form.addEventListener('submit', handleDeleteUserSubmit);
        });

        if (!hasErrors) return;

        const oldMode = @json(old('form_mode'));
        if (oldMode === 'edit') {
            openAdminUserModal({
                mode: 'edit',
                action: @json(old('edit_action')),
                role: @json(old('role', $viewerManageableRoles[0] ?? null)),
                username: @json(old('username')),
                name: @json(old('name')),
                email: @json(old('email')),
                jenisKelamin: @json(old('jenis_kelamin')),
                tanggalLahir: @json(old('tanggal_lahir')),
                agama: @json(old('agama')),
                noHp: @json(old('no_hp')),
                alamat: @json(old('alamat')),
            });
            return;
        }

        if (oldMode === 'add') {
            openAdminUserModal({
                mode: 'add',
                role: @json(old('role', $viewerManageableRoles[0] ?? null)),
                username: @json(old('username')),
                name: @json(old('name')),
                email: @json(old('email')),
                jenisKelamin: @json(old('jenis_kelamin')),
                tanggalLahir: @json(old('tanggal_lahir')),
                agama: @json(old('agama')),
                noHp: @json(old('no_hp')),
                alamat: @json(old('alamat')),
            });
        }
    });

    window.showModal = showModal;
    window.closeModal = closeModal;
    window.openAddAdminUserModal = openAddAdminUserModal;
    window.openEditAdminUserModal = openEditAdminUserModal;
</script>
@endpush
