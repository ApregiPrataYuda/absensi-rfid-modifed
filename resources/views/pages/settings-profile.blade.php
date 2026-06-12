@extends('layouts.page')

@section('title', 'Pengaturan Profil')

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Pengaturan Profil</h3>
            <p class="text-xs text-gray-500 mt-1">Perbarui data akun Anda dan ubah password jika diperlukan.</p>
        </div>

        <form id="profile-setting-form" action="{{ route('settings.profile.update') }}" method="POST" enctype="multipart/form-data" class="p-4 sm:p-5">
            @csrf

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <aside class="xl:col-span-4">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h4 class="text-lg font-semibold text-slate-800">Foto Profil</h4>

                        <div class="mt-5 flex flex-col items-center">
                            <div class="relative">
                                <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-full border-4 border-white shadow bg-white overflow-hidden flex items-center justify-center">
                                    <img
                                        id="profileAvatarPreview"
                                        src="{{ $avatarUrl ?? '' }}"
                                        alt="Foto Profil"
                                        class="w-full h-full object-cover {{ !empty($avatarUrl) ? '' : 'hidden' }}"
                                    >
                                    <div id="profileAvatarFallback" class="w-full h-full flex items-center justify-center text-4xl font-bold text-indigo-600 bg-indigo-50 {{ !empty($avatarUrl) ? 'hidden' : '' }}">
                                        {{ strtoupper(substr((string) ($user->name ?? 'U'), 0, 1)) }}
                                    </div>
                                </div>

                                <label for="profile-avatar-input" class="absolute -bottom-1 -right-1 w-10 h-10 rounded-full bg-indigo-500 hover:bg-indigo-600 text-white flex items-center justify-center shadow cursor-pointer transition">
                                    <i class="fas fa-camera text-sm"></i>
                                </label>
                                <input id="profile-avatar-input" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                            </div>

                            <p class="mt-4 text-sm text-slate-500 text-center">JPG, PNG, atau WEBP<br>Maksimal 2MB</p>
                        </div>

                        <div class="mt-5 pt-4 border-t border-gray-200 space-y-3 text-sm">
                            <div class="flex items-center gap-3 text-slate-600">
                                <i class="far fa-user w-4 text-slate-400"></i>
                                <span id="profileInfoUsername">{{ '@' . ($user->username ?? 'user') }}</span>
                            </div>
                            {{-- <div class="flex items-center gap-3 text-slate-600">
                                <i class="fas fa-shield-alt w-4 text-slate-400"></i>
                                <span id="profileInfoRole" class="inline-flex items-center rounded-full px-2.5 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-semibold">{{ $roleLabel ?? 'User' }}</span>
                            </div> --}}
                            <div class="flex items-center gap-3 text-slate-600">
                                <i class="far fa-calendar w-4 text-slate-400"></i>
                                <span id="profileInfoJoined">Bergabung {{ $joinedAtLabel ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="xl:col-span-8 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nama Lengkap</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">{{ !empty($isSiswa) ? 'NISN / Username' : 'Username' }}</label>
                            <input type="text" name="username" value="{{ !empty($isSiswa) ? $user->username : old('username', $user->username) }}" required {{ !empty($isSiswa) ? 'readonly' : '' }} class="w-full border border-gray-200 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 {{ !empty($isSiswa) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-900' }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Email</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nomor HP</label>
                            <input type="text" name="no_hp" value="{{ old('no_hp', $user->no_hp) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                <option value="">Pilih Jenis Kelamin</option>
                                @php $jk = old('jenis_kelamin', $user->jenis_kelamin); @endphp
                                <option value="Laki-laki" {{ $jk === 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="Perempuan" {{ $jk === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($user->tanggal_lahir)->format('Y-m-d')) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Agama</label>
                            <select name="agama" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                                @php
                                    $agamaValue = old('agama', $user->agama);
                                    $agamaOptions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
                                @endphp
                                <option value="">Pilih Agama</option>
                                @foreach ($agamaOptions as $agama)
                                    <option value="{{ $agama }}" {{ $agamaValue === $agama ? 'selected' : '' }}>{{ $agama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Alamat</label>
                        <textarea name="alamat" rows="3" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">{{ old('alamat', $user->alamat) }}</textarea>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Ubah Password (Opsional)</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Password Saat Ini</label>
                                <input type="password" name="current_password" autocomplete="current-password" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Isi jika ubah password">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Password Baru</label>
                                <input type="password" name="new_password" autocomplete="new-password" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Minimal 6 karakter">
                            </div>
                            <div>
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Konfirmasi Password Baru</label>
                                <input type="password" name="new_password_confirmation" autocomplete="new-password" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Ulangi password baru">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button id="profile-setting-submit" type="submit" class="inline-flex w-full sm:w-auto justify-center items-center gap-2 bg-indigo-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-indigo-700 transition">
                            <i class="fas fa-save text-xs"></i>
                            <span id="profile-setting-submit-text">Simpan Profil</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('profile-setting-form');
        if (!form) return;

        const submitButton = document.getElementById('profile-setting-submit');
        const submitText = document.getElementById('profile-setting-submit-text');
        const avatarInput = document.getElementById('profile-avatar-input');
        const avatarPreview = document.getElementById('profileAvatarPreview');
        const avatarFallback = document.getElementById('profileAvatarFallback');
        const profileInfoUsername = document.getElementById('profileInfoUsername');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function setSubmitState(isLoading) {
            if (!submitButton || !submitText) return;
            submitButton.disabled = isLoading;
            if (isLoading) {
                submitButton.classList.add('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Menyimpan...';
            } else {
                submitButton.classList.remove('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Simpan Profil';
            }
        }

        function setAvatarPreview(url) {
            const cleanUrl = String(url || '').trim();
            if (avatarPreview) {
                if (cleanUrl !== '') {
                    avatarPreview.src = cleanUrl;
                    avatarPreview.classList.remove('hidden');
                } else {
                    avatarPreview.src = '';
                    avatarPreview.classList.add('hidden');
                }
            }
            if (avatarFallback) {
                avatarFallback.classList.toggle('hidden', cleanUrl !== '');
            }
        }

        function updateProfileUI(data) {
            if (!data || typeof data !== 'object') return;
            const name = String(data.name || '').trim();
            const username = String(data.username || '').trim();
            const email = String(data.email || '').trim();
            const noHp = String(data.no_hp || '').trim();
            const jenisKelamin = String(data.jenis_kelamin || '').trim();
            const tanggalLahir = String(data.tanggal_lahir || '').trim();
            const agama = String(data.agama || '').trim();
            const alamat = String(data.alamat || '').trim();
            const avatarUrl = String(data.avatar_url || '').trim();

            if (window.APP_CURRENT_USER && typeof window.APP_CURRENT_USER === 'object') {
                window.APP_CURRENT_USER.name = name;
                window.APP_CURRENT_USER.nama = name;
                window.APP_CURRENT_USER.username = username;
                window.APP_CURRENT_USER.email = email;
                window.APP_CURRENT_USER.no_hp = noHp;
                window.APP_CURRENT_USER.jenis_kelamin = jenisKelamin;
                window.APP_CURRENT_USER.tanggal_lahir = tanggalLahir;
                window.APP_CURRENT_USER.agama = agama;
                window.APP_CURRENT_USER.alamat = alamat;
                window.APP_CURRENT_USER.avatar_url = avatarUrl;
            }

            const navUserName = document.getElementById('navUserName');
            const navUserInitial = document.getElementById('navUserInitial');
            const navUserAvatarImg = document.getElementById('navUserAvatarImg');
            if (navUserName && name !== '') navUserName.textContent = name;
            if (navUserInitial && name !== '') {
                navUserInitial.textContent = name.charAt(0).toUpperCase();
                navUserInitial.classList.toggle('hidden', avatarUrl !== '');
            }
            if (navUserAvatarImg) {
                if (avatarUrl !== '') {
                    navUserAvatarImg.src = avatarUrl;
                    navUserAvatarImg.classList.remove('hidden');
                } else {
                    navUserAvatarImg.src = '';
                    navUserAvatarImg.classList.add('hidden');
                }
            }

            const headerProfileName = document.getElementById('headerProfileName');
            const headerProfileUsername = document.getElementById('headerProfileUsername');
            const headerProfileMenuName = document.getElementById('headerProfileMenuName');
            const headerProfileMenuEmail = document.getElementById('headerProfileMenuEmail');
            const headerAvatarImg = document.getElementById('headerAvatarImg');
            const headerAvatarFallback = document.getElementById('headerAvatarFallback');

            if (headerProfileName && name !== '') headerProfileName.textContent = name;
            if (headerProfileUsername && username !== '') headerProfileUsername.textContent = '@' + username;
            if (headerProfileMenuName && name !== '') headerProfileMenuName.textContent = name;
            if (headerProfileMenuEmail) {
                headerProfileMenuEmail.textContent = email !== '' ? email : ('@' + username);
            }
            if (headerAvatarImg) {
                if (avatarUrl !== '') {
                    headerAvatarImg.src = avatarUrl;
                    headerAvatarImg.classList.remove('hidden');
                } else {
                    headerAvatarImg.src = '';
                    headerAvatarImg.classList.add('hidden');
                }
            }
            if (headerAvatarFallback) {
                headerAvatarFallback.classList.toggle('hidden', avatarUrl !== '');
            }

            if (profileInfoUsername && username !== '') {
                profileInfoUsername.textContent = '@' + username;
            }

            setAvatarPreview(avatarUrl);
        }

        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                const file = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
                if (!file) return;
                const localUrl = URL.createObjectURL(file);
                setAvatarPreview(localUrl);
            });
        }

        async function handleSubmit(event) {
            event.preventDefault();
            setSubmitState(true);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    const defaultMessage = 'Gagal menyimpan profil.';
                    let message = payload.message || defaultMessage;
                    if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
                        const firstError = Object.values(payload.errors).flat()[0];
                        if (firstError) message = String(firstError);
                    }
                    if (window.showAlert) {
                        window.showAlert('error', message);
                    }
                    return;
                }

                updateProfileUI(payload.data || {});

                form.querySelectorAll('input[name="current_password"], input[name="new_password"], input[name="new_password_confirmation"]').forEach((input) => {
                    input.value = '';
                });
                if (avatarInput) avatarInput.value = '';

                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'Profil berhasil diperbarui.');
                }
            } catch (error) {
                if (window.showAlert) {
                    window.showAlert('error', error.message || 'Terjadi kesalahan saat menyimpan.');
                }
            } finally {
                setSubmitState(false);
            }
        }

        form.addEventListener('submit', handleSubmit);
    })();
</script>
@endpush
