@extends('layouts.page')

@section('title', 'Pengaturan Umum')

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Pengaturan Umum</h3>
            <p class="text-xs text-gray-500 mt-1">Atur identitas website, kontak, dan zona waktu sistem.</p>
        </div>

        <div class="p-4">
            <div id="general-setting-error" class="{{ $errors->any() ? '' : 'hidden' }} mb-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs">
                @if ($errors->any())
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                @endif
            </div>

            <form id="general-setting-form" action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Nama Website</label>
                        <input type="text" name="website_nama" value="{{ old('website_nama', $settings['website_nama'] ?? '') }}" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Slogan</label>
                        <input type="text" name="website_slogan" value="{{ old('website_slogan', $settings['website_slogan'] ?? '') }}" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Contoh: Smart Attendance System">
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Deskripsi Singkat</label>
                    <textarea name="website_deskripsi" rows="3" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Deskripsi website...">{{ old('website_deskripsi', $settings['website_deskripsi'] ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Email Kontak</label>
                        <input type="email" name="website_email" value="{{ old('website_email', $settings['website_email'] ?? '') }}" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="admin@sekolah.sch.id">
                    </div>
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Telepon / WhatsApp</label>
                        <input type="text" name="website_telepon" value="{{ old('website_telepon', $settings['website_telepon'] ?? '') }}" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="08xxxxxxxxxx">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Zona Waktu</label>
                        <select name="website_timezone" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                            @foreach (($timezoneOptions ?? []) as $tzValue => $tzLabel)
                                <option value="{{ $tzValue }}" {{ old('website_timezone', $settings['website_timezone'] ?? 'Asia/Jakarta') === $tzValue ? 'selected' : '' }}>
                                    {{ $tzLabel }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 mt-1">Berlaku untuk tanggal/jam di dashboard dan laporan.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Logo Website</label>
                        <input id="general-setting-logo-input" type="file" name="website_logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="w-full text-xs text-gray-700">
                        <p class="text-[11px] text-gray-500 mt-1">Format: PNG/JPG/WEBP/SVG. Maks 2MB.</p>
                            <div id="logo-preview-wrapper" class="mt-3 flex items-center gap-3 {{ empty($settings['website_logo_url']) ? 'hidden' : '' }}">
                                <img id="logo-preview-image" src="{{ $settings['website_logo_url'] ?? '' }}" alt="Logo Website" class="w-12 h-12 object-cover rounded-lg border border-gray-200 bg-white">
                                <label class="inline-flex items-center gap-1 text-xs text-red-600">
                                    <input id="remove-logo-checkbox" type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    Hapus logo
                                </label>
                            </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50/50">
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Favicon</label>
                        <input id="general-setting-favicon-input" type="file" name="website_favicon" accept=".png,.ico,.svg,.webp" class="w-full text-xs text-gray-700">
                        <p class="text-[11px] text-gray-500 mt-1">Format: ICO/PNG/SVG/WEBP. Maks 1MB.</p>
                            <div id="favicon-preview-wrapper" class="mt-3 flex items-center gap-3 {{ empty($settings['website_favicon_url']) ? 'hidden' : '' }}">
                                <img id="favicon-preview-image" src="{{ $settings['website_favicon_url'] ?? '' }}" alt="Favicon" class="w-8 h-8 object-contain rounded border border-gray-200 bg-white p-1">
                                <label class="inline-flex items-center gap-1 text-xs text-red-600">
                                    <input id="remove-favicon-checkbox" type="checkbox" name="remove_favicon" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    Hapus favicon
                                </label>
                            </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button id="general-setting-submit" type="submit" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                        <i class="fas fa-save text-xs"></i>
                        <span id="general-setting-submit-text">Simpan Pengaturan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('general-setting-form');
        if (!form) return;

        const submitButton = document.getElementById('general-setting-submit');
        const submitText = document.getElementById('general-setting-submit-text');
        const errorBox = document.getElementById('general-setting-error');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function hideMessages() {
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.innerHTML = '';
            }
        }

        function showErrorMessages(messages) {
            if (!errorBox) return;
            const list = Array.isArray(messages) ? messages : [String(messages || 'Terjadi kesalahan.')];
            errorBox.innerHTML = list.map((text) => `<div>${String(text)}</div>`).join('');
            errorBox.classList.remove('hidden');
        }

        function setSubmitState(isLoading) {
            if (!submitButton || !submitText) return;
            submitButton.disabled = isLoading;
            if (isLoading) {
                submitButton.classList.add('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Menyimpan...';
            } else {
                submitButton.classList.remove('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Simpan Pengaturan';
            }
        }

        function updateBrandUI(data) {
            if (!data || typeof data !== 'object') return;

            const websiteName = String(data.website_nama || '').trim();
            const websiteSlogan = String(data.website_slogan || '').trim();
            const logoUrl = data.website_logo_url ? String(data.website_logo_url) : '';
            const faviconUrl = data.website_favicon_url ? String(data.website_favicon_url) : '';
            const websiteTimezone = String(data.website_timezone || window.APP_TIMEZONE || 'Asia/Jakarta');
            const websiteTimezoneLabel = String(data.website_timezone_label || window.APP_TIMEZONE_LABEL || websiteTimezone);

            const sidebarBrandName = document.getElementById('sidebarBrandName');
            const sidebarBrandSlogan = document.getElementById('sidebarBrandSlogan');
            const sidebarLogoImg = document.getElementById('sidebarBrandLogoImg');
            const sidebarLogoIcon = document.getElementById('sidebarBrandLogoIcon');

            if (sidebarBrandName && websiteName !== '') {
                sidebarBrandName.textContent = websiteName;
            }
            if (sidebarBrandSlogan) {
                sidebarBrandSlogan.textContent = websiteSlogan || 'School System';
            }
            if (sidebarLogoImg && sidebarLogoIcon) {
                if (logoUrl !== '') {
                    sidebarLogoImg.src = logoUrl;
                    sidebarLogoImg.classList.remove('hidden');
                    sidebarLogoIcon.classList.add('hidden');
                } else {
                    sidebarLogoImg.src = '';
                    sidebarLogoImg.classList.add('hidden');
                    sidebarLogoIcon.classList.remove('hidden');
                }
            }

            const pageTitlePrefix = document.title.split(' - ')[0] || 'Dashboard';
            document.title = `${pageTitlePrefix} - ${websiteName || 'Sistem Absensi Pintar'}`;

            window.APP_TIMEZONE = websiteTimezone;
            window.APP_TIMEZONE_LABEL = websiteTimezoneLabel;
            if (typeof window.updateHeaderCurrentDate === 'function') {
                window.updateHeaderCurrentDate();
            }

            const faviconLink = document.querySelector('link[rel="icon"]');
            if (faviconUrl !== '') {
                if (faviconLink) {
                    faviconLink.setAttribute('href', faviconUrl);
                } else {
                    const link = document.createElement('link');
                    link.setAttribute('rel', 'icon');
                    link.setAttribute('type', 'image/png');
                    link.setAttribute('href', faviconUrl);
                    document.head.appendChild(link);
                }
            } else if (faviconLink) {
                faviconLink.remove();
            }

            const logoPreviewWrapper = document.getElementById('logo-preview-wrapper');
            const logoPreviewImage = document.getElementById('logo-preview-image');
            const logoRemoveCheckbox = document.getElementById('remove-logo-checkbox');
            if (logoPreviewWrapper && logoPreviewImage) {
                if (logoUrl !== '') {
                    logoPreviewImage.src = logoUrl;
                    logoPreviewWrapper.classList.remove('hidden');
                } else {
                    logoPreviewImage.src = '';
                    logoPreviewWrapper.classList.add('hidden');
                }
            }
            if (logoRemoveCheckbox) logoRemoveCheckbox.checked = false;

            const faviconPreviewWrapper = document.getElementById('favicon-preview-wrapper');
            const faviconPreviewImage = document.getElementById('favicon-preview-image');
            const faviconRemoveCheckbox = document.getElementById('remove-favicon-checkbox');
            if (faviconPreviewWrapper && faviconPreviewImage) {
                if (faviconUrl !== '') {
                    faviconPreviewImage.src = faviconUrl;
                    faviconPreviewWrapper.classList.remove('hidden');
                } else {
                    faviconPreviewImage.src = '';
                    faviconPreviewWrapper.classList.add('hidden');
                }
            }
            if (faviconRemoveCheckbox) faviconRemoveCheckbox.checked = false;
        }

        async function handleSubmit(event) {
            event.preventDefault();
            hideMessages();
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
                    if (response.status === 422 && payload.errors && typeof payload.errors === 'object') {
                        const errors = Object.values(payload.errors).flat();
                        showErrorMessages(errors);
                    } else {
                        showErrorMessages(payload.message || 'Gagal menyimpan pengaturan umum.');
                    }
                    if (window.showAlert) {
                        window.showAlert('error', payload.message || 'Gagal menyimpan pengaturan umum.');
                    }
                    return;
                }

                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'Pengaturan umum berhasil disimpan.');
                }

                form.querySelectorAll('input[type="file"]').forEach((input) => {
                    input.value = '';
                });

                updateBrandUI(payload.data || {});
            } catch (error) {
                showErrorMessages(error.message || 'Terjadi kesalahan saat menyimpan.');
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
