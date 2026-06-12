@extends('layouts.page')

@section('title', 'Kirim Notifikasi')

@section('content')
<div class="view-section active animate-fade-in">
    @php
        $recipientKelas = collect($recipientOptions['kelas'] ?? []);
        $recipientSiswa = collect($recipientOptions['siswa'] ?? []);
    @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Kirim Notifikasi WhatsApp</h3>
            <p class="text-xs text-gray-500 mt-1">Silakan kirim notifikasi resmi kepada siswa secara individual atau berdasarkan kelas melalui halaman ini.</p>
        </div>

        <div class="p-4">
            <div id="wa-broadcast-interval-info" class="mb-4 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-[11px] text-blue-700">
                Pengiriman diproses berurutan satu per satu dengan jeda acak {{ (int) ($pauseMinSec ?? 5) }}-{{ (int) ($pauseMaxSec ?? 10) }} detik. Anda bisa ubah jeda di bawah sebelum mengirim.
            </div>
            <form id="wa-broadcast-form" action="{{ route('notifications.send.store') }}" method="POST" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Target Notifikasi</label>
                        <select id="wa-broadcast-target-type" name="target_type" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                            <option value="siswa" selected>Per Siswa</option>
                            <option value="kelas">Per Kelas</option>
                        </select>
                    </div>

                    <div id="wa-broadcast-siswa-wrapper">
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Pilih Siswa</label>
                        <input type="hidden" id="wa-broadcast-siswa-id" name="siswa_id" value="">
                        <div class="relative">
                            <input id="wa-broadcast-siswa-search" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Ketik nama/NISN/kelas siswa" autocomplete="off">
                            <div id="wa-broadcast-siswa-dropdown" class="hidden absolute top-full left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-20"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500">Ketik lalu pilih siswa dari dropdown.</p>
                    </div>

                    <div id="wa-broadcast-kelas-wrapper" class="hidden">
                        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Pilih Kelas</label>
                        <input type="hidden" id="wa-broadcast-kelas-id" name="kelas_id" value="">
                        <div class="relative">
                            <input id="wa-broadcast-kelas-search" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Ketik nama kelas" autocomplete="off">
                            <div id="wa-broadcast-kelas-dropdown" class="hidden absolute top-full left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-20"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500">Ketik lalu pilih kelas dari dropdown.</p>
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Isi Pesan</label>
                    <div class="mb-2 p-2 rounded-lg border border-gray-200 bg-gray-50 text-[11px] text-gray-600 space-y-2">
                        <div class="font-semibold text-gray-700">Variable yang didukung (klik untuk menyisipkan):</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" data-broadcast-variable="{nama}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{nama}</button>
                            <button type="button" data-broadcast-variable="{nisn}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{nisn}</button>
                            <button type="button" data-broadcast-variable="{kelas}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{kelas}</button>
                            <button type="button" data-broadcast-variable="{no_hp}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{no_hp}</button>
                            <button type="button" data-broadcast-variable="{jenis_kelamin}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{jenis_kelamin}</button>
                            <button type="button" data-broadcast-variable="{tanggal_lahir}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{tanggal_lahir}</button>
                            <button type="button" data-broadcast-variable="{agama}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{agama}</button>
                            <button type="button" data-broadcast-variable="{nama_ayah}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{nama_ayah}</button>
                            <button type="button" data-broadcast-variable="{nama_ibu}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{nama_ibu}</button>
                            <button type="button" data-broadcast-variable="{nama_orang_tua}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{nama_orang_tua}</button>
                            <button type="button" data-broadcast-variable="{alamat}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{alamat}</button>
                            <button type="button" data-broadcast-variable="{siswa_label}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{siswa_label}</button>
                            <button type="button" data-broadcast-variable="{website_name}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{website_name}</button>
                            <button type="button" data-broadcast-variable="{app_name}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{app_name}</button>
                            <button type="button" data-broadcast-variable="{tanggal}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{tanggal}</button>
                            <button type="button" data-broadcast-variable="{jam}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{jam}</button>
                            <button type="button" data-broadcast-variable="{waktu}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{waktu}</button>
                            <button type="button" data-broadcast-variable="{tanggal_jam}" class="px-2 py-1 rounded border border-gray-300 bg-white hover:bg-gray-100 text-gray-700">{tanggal_jam}</button>
                        </div>
                    </div>
                    <textarea id="wa-broadcast-message" name="message" rows="6" maxlength="2000" placeholder="Tulis pesan notifikasi..." class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"></textarea>
                </div>

                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Jeda Acak Antar Pesan (Detik)</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-[11px] font-semibold text-gray-500">Minimal</label>
                            <input id="wa-broadcast-pause-min" type="number" name="pause_min_sec" min="0" max="60" value="{{ (int) ($pauseMinSec ?? 5) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                        <div>
                            <label class="block mb-1 text-[11px] font-semibold text-gray-500">Maksimal</label>
                            <input id="wa-broadcast-pause-max" type="number" name="pause_max_sec" min="0" max="60" value="{{ (int) ($pauseMaxSec ?? 10) }}" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">Nilai akan dibatasi 0-60 detik. Jika maksimal lebih kecil dari minimal, akan otomatis disamakan.</p>
                </div>

                <div id="wa-broadcast-error" class="hidden px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs"></div>
                <div id="wa-broadcast-result" class="hidden px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs"></div>

                <div class="flex justify-end">
                    <button id="wa-broadcast-submit" type="submit" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">
                        <i id="wa-broadcast-submit-icon" class="fas fa-paper-plane text-xs"></i>
                        <i id="wa-broadcast-submit-spinner" class="fas fa-spinner fa-spin text-xs hidden"></i>
                        <span id="wa-broadcast-submit-text">Kirim Notifikasi</span>
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
        const form = document.getElementById('wa-broadcast-form');
        if (!form) return;

        const siswaOptions = @json($recipientSiswa->values()->all());
        const kelasOptions = @json($recipientKelas->values()->all());
        const targetTypeField = document.getElementById('wa-broadcast-target-type');
        const siswaWrapper = document.getElementById('wa-broadcast-siswa-wrapper');
        const kelasWrapper = document.getElementById('wa-broadcast-kelas-wrapper');
        const siswaField = document.getElementById('wa-broadcast-siswa-id');
        const kelasField = document.getElementById('wa-broadcast-kelas-id');
        const siswaSearchField = document.getElementById('wa-broadcast-siswa-search');
        const kelasSearchField = document.getElementById('wa-broadcast-kelas-search');
        const siswaDropdown = document.getElementById('wa-broadcast-siswa-dropdown');
        const kelasDropdown = document.getElementById('wa-broadcast-kelas-dropdown');
        const messageField = document.getElementById('wa-broadcast-message');
        const pauseMinField = document.getElementById('wa-broadcast-pause-min');
        const pauseMaxField = document.getElementById('wa-broadcast-pause-max');
        const errorBox = document.getElementById('wa-broadcast-error');
        const resultBox = document.getElementById('wa-broadcast-result');
        const submitButton = document.getElementById('wa-broadcast-submit');
        const submitIcon = document.getElementById('wa-broadcast-submit-icon');
        const submitSpinner = document.getElementById('wa-broadcast-submit-spinner');
        const submitText = document.getElementById('wa-broadcast-submit-text');
        const intervalInfo = document.getElementById('wa-broadcast-interval-info');
        const variableButtons = Array.from(document.querySelectorAll('[data-broadcast-variable]'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeText(value) {
            return String(value || '').trim().toLowerCase();
        }

        function siswaLabel(item) {
            const label = String(item?.label || '').trim();
            if (label !== '') return label;

            const name = String(item?.name || '').trim();
            const nisn = String(item?.nisn || '').trim();
            const kelas = String(item?.kelas || '').trim();
            return [name, nisn ? `NISN: ${nisn}` : '', kelas ? `Kelas ${kelas}` : '']
                .filter(Boolean)
                .join(' - ');
        }

        function kelasLabel(item) {
            const name = String(item?.name || '').trim();
            const count = Number(item?.recipient_count || 0);
            return `${name} (${count} nomor WA)`;
        }

        function findSiswaById(id) {
            const key = Number(id || 0);
            if (key <= 0) return null;
            return siswaOptions.find((item) => Number(item.id || 0) === key) || null;
        }

        function findKelasById(id) {
            const key = Number(id || 0);
            if (key <= 0) return null;
            return kelasOptions.find((item) => Number(item.id || 0) === key) || null;
        }

        function hideMessages() {
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.innerHTML = '';
            }
            if (resultBox) {
                resultBox.classList.add('hidden');
                resultBox.innerHTML = '';
            }
        }

        function normalizePauseSeconds(value) {
            const num = Number(value);
            if (!Number.isFinite(num)) return 0;
            return Math.max(0, Math.min(Math.round(num), 60));
        }

        function syncPauseFields() {
            if (!pauseMinField || !pauseMaxField) return;

            const minSec = normalizePauseSeconds(pauseMinField.value);
            let maxSec = normalizePauseSeconds(pauseMaxField.value);
            if (maxSec < minSec) {
                maxSec = minSec;
            }

            pauseMinField.value = String(minSec);
            pauseMaxField.value = String(maxSec);

            if (intervalInfo) {
                intervalInfo.textContent = `Pengiriman diproses berurutan satu per satu dengan jeda acak ${minSec}-${maxSec} detik. Anda bisa ubah jeda di bawah sebelum mengirim.`;
            }
        }

        function showError(messages) {
            if (!errorBox) return;
            const list = Array.isArray(messages) ? messages : [String(messages || 'Gagal mengirim notifikasi.')];
            errorBox.innerHTML = list.map((text) => `<div>${String(text)}</div>`).join('');
            errorBox.classList.remove('hidden');
        }

        function showResult(message) {
            if (!resultBox) return;
            resultBox.innerHTML = `<div>${String(message || 'Notifikasi berhasil dikirim.')}</div>`;
            resultBox.classList.remove('hidden');
        }

        function setSubmitState(isLoading) {
            if (!submitButton || !submitText) return;
            submitButton.disabled = isLoading;
            if (isLoading) {
                submitButton.classList.add('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Mengirim...';
                if (submitIcon) submitIcon.classList.add('hidden');
                if (submitSpinner) submitSpinner.classList.remove('hidden');
            } else {
                submitButton.classList.remove('opacity-80', 'cursor-not-allowed');
                submitText.textContent = 'Kirim Notifikasi';
                if (submitSpinner) submitSpinner.classList.add('hidden');
                if (submitIcon) submitIcon.classList.remove('hidden');
            }
        }

        function hideSiswaDropdown() {
            if (!siswaDropdown) return;
            siswaDropdown.innerHTML = '';
            siswaDropdown.classList.add('hidden');
        }

        function hideKelasDropdown() {
            if (!kelasDropdown) return;
            kelasDropdown.innerHTML = '';
            kelasDropdown.classList.add('hidden');
        }

        function insertVariableToMessage(variableToken) {
            if (!messageField) return;
            const token = String(variableToken || '').trim();
            if (token === '') return;

            const start = Number(messageField.selectionStart ?? messageField.value.length);
            const end = Number(messageField.selectionEnd ?? messageField.value.length);
            const original = String(messageField.value || '');
            const nextValue = original.slice(0, start) + token + original.slice(end);
            messageField.value = nextValue;

            const cursorPos = start + token.length;
            messageField.focus();
            if (typeof messageField.setSelectionRange === 'function') {
                messageField.setSelectionRange(cursorPos, cursorPos);
            }
        }

        function clearSiswaSelection() {
            if (siswaField) siswaField.value = '';
            if (siswaSearchField) {
                siswaSearchField.value = '';
                siswaSearchField.dataset.selectedId = '';
            }
            hideSiswaDropdown();
        }

        function clearKelasSelection() {
            if (kelasField) kelasField.value = '';
            if (kelasSearchField) {
                kelasSearchField.value = '';
                kelasSearchField.dataset.selectedId = '';
            }
            hideKelasDropdown();
        }

        function setSelectedSiswa(item) {
            const id = Number(item?.id || 0);
            if (id <= 0) return;
            if (siswaField) siswaField.value = String(id);
            if (siswaSearchField) {
                siswaSearchField.value = siswaLabel(item);
                siswaSearchField.dataset.selectedId = String(id);
            }
            hideSiswaDropdown();
        }

        function setSelectedKelas(item) {
            const id = Number(item?.id || 0);
            if (id <= 0) return;
            if (kelasField) kelasField.value = String(id);
            if (kelasSearchField) {
                kelasSearchField.value = kelasLabel(item);
                kelasSearchField.dataset.selectedId = String(id);
            }
            hideKelasDropdown();
        }

        function getFilteredSiswa(keyword) {
            const q = normalizeText(keyword);
            const rows = !q
                ? siswaOptions
                : siswaOptions.filter((item) => {
                    const haystack = [
                        item?.name,
                        item?.nisn,
                        item?.kelas,
                        item?.label
                    ].map(normalizeText).join(' ');
                    return haystack.includes(q);
                });

            return rows.slice(0, 30);
        }

        function getFilteredKelas(keyword) {
            const q = normalizeText(keyword);
            const rows = !q
                ? kelasOptions
                : kelasOptions.filter((item) => {
                    const haystack = [
                        item?.name,
                        String(item?.recipient_count || 0)
                    ].map(normalizeText).join(' ');
                    return haystack.includes(q);
                });

            return rows.slice(0, 30);
        }

        function renderSiswaDropdown(keyword) {
            if (!siswaDropdown) return;
            const rows = getFilteredSiswa(keyword);
            if (rows.length === 0) {
                siswaDropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Siswa tidak ditemukan.</div>';
                siswaDropdown.classList.remove('hidden');
                return;
            }

            siswaDropdown.innerHTML = rows.map((item) => `
                <button type="button" data-siswa-option-id="${Number(item.id || 0)}" class="w-full text-left px-3 py-2 hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                    <div class="text-xs font-semibold text-gray-700">${escapeHtml(String(item.name || '-'))}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(String(item.nisn || '-'))} | ${escapeHtml(String(item.kelas || '-'))}</div>
                </button>
            `).join('');
            siswaDropdown.classList.remove('hidden');

            siswaDropdown.querySelectorAll('[data-siswa-option-id]').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    const siswa = findSiswaById(button.getAttribute('data-siswa-option-id'));
                    if (!siswa) return;
                    setSelectedSiswa(siswa);
                });
            });
        }

        function renderKelasDropdown(keyword) {
            if (!kelasDropdown) return;
            const rows = getFilteredKelas(keyword);
            if (rows.length === 0) {
                kelasDropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Kelas tidak ditemukan.</div>';
                kelasDropdown.classList.remove('hidden');
                return;
            }

            kelasDropdown.innerHTML = rows.map((item) => `
                <button type="button" data-kelas-option-id="${Number(item.id || 0)}" class="w-full text-left px-3 py-2 hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                    <div class="text-xs font-semibold text-gray-700">${escapeHtml(String(item.name || '-'))}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(String(item.recipient_count || 0))} nomor WA</div>
                </button>
            `).join('');
            kelasDropdown.classList.remove('hidden');

            kelasDropdown.querySelectorAll('[data-kelas-option-id]').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    const kelas = findKelasById(button.getAttribute('data-kelas-option-id'));
                    if (!kelas) return;
                    setSelectedKelas(kelas);
                });
            });
        }

        function syncTargetField() {
            const targetType = String(targetTypeField?.value || 'siswa').trim();
            const isKelas = targetType === 'kelas';

            if (siswaWrapper) siswaWrapper.classList.toggle('hidden', isKelas);
            if (kelasWrapper) kelasWrapper.classList.toggle('hidden', !isKelas);

            if (isKelas) {
                clearSiswaSelection();
            } else {
                clearKelasSelection();
            }
        }

        async function handleSubmit(event) {
            event.preventDefault();
            hideMessages();
            syncPauseFields();
            setSubmitState(true);

            const isKelas = String(targetTypeField?.value || 'siswa').trim() === 'kelas';
            const selectedSiswaId = Number(siswaField?.value || 0);
            const selectedKelasId = Number(kelasField?.value || 0);
            if (!isKelas && selectedSiswaId <= 0) {
                showError('Pilih siswa dari dropdown terlebih dahulu.');
                setSubmitState(false);
                return;
            }
            if (isKelas && selectedKelasId <= 0) {
                showError('Pilih kelas dari dropdown terlebih dahulu.');
                setSubmitState(false);
                return;
            }

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
                const errors = Array.isArray(payload?.errors) ? payload.errors : [];

                if (!response.ok || payload.success === false) {
                    if (response.status === 422 && payload.errors && typeof payload.errors === 'object' && !Array.isArray(payload.errors)) {
                        showError(Object.values(payload.errors).flat());
                    } else if (errors.length > 0) {
                        showError(errors);
                    } else {
                        showError(payload.message || 'Gagal mengirim notifikasi.');
                    }
                    if (window.showAlert) {
                        window.showAlert('error', payload.message || 'Gagal mengirim notifikasi.');
                    }
                    return;
                }

                showResult(payload.message || 'Notifikasi berhasil dikirim.');
                if (errors.length > 0) {
                    showError(errors);
                }
                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'Notifikasi berhasil dikirim.');
                }
                if (messageField) {
                    messageField.value = '';
                }
            } catch (error) {
                showError(error.message || 'Terjadi kesalahan saat mengirim notifikasi.');
                if (window.showAlert) {
                    window.showAlert('error', error.message || 'Terjadi kesalahan saat mengirim notifikasi.');
                }
            } finally {
                setSubmitState(false);
            }
        }

        if (targetTypeField) {
            targetTypeField.addEventListener('change', () => {
                hideMessages();
                syncTargetField();
            });
        }

        if (siswaSearchField) {
            siswaSearchField.addEventListener('focus', () => {
                renderSiswaDropdown(siswaSearchField.value);
            });
            siswaSearchField.addEventListener('input', () => {
                if (siswaField) siswaField.value = '';
                siswaSearchField.dataset.selectedId = '';
                renderSiswaDropdown(siswaSearchField.value);
            });
            siswaSearchField.addEventListener('blur', () => {
                window.setTimeout(() => {
                    hideSiswaDropdown();
                }, 150);
            });
        }

        if (kelasSearchField) {
            kelasSearchField.addEventListener('focus', () => {
                renderKelasDropdown(kelasSearchField.value);
            });
            kelasSearchField.addEventListener('input', () => {
                if (kelasField) kelasField.value = '';
                kelasSearchField.dataset.selectedId = '';
                renderKelasDropdown(kelasSearchField.value);
            });
            kelasSearchField.addEventListener('blur', () => {
                window.setTimeout(() => {
                    hideKelasDropdown();
                }, 150);
            });
        }

        variableButtons.forEach((button) => {
            button.addEventListener('click', () => {
                insertVariableToMessage(button.getAttribute('data-broadcast-variable'));
            });
        });

        if (pauseMinField) {
            pauseMinField.addEventListener('input', syncPauseFields);
            pauseMinField.addEventListener('change', syncPauseFields);
        }
        if (pauseMaxField) {
            pauseMaxField.addEventListener('input', syncPauseFields);
            pauseMaxField.addEventListener('change', syncPauseFields);
        }
        syncPauseFields();

        form.addEventListener('submit', handleSubmit);
        syncTargetField();
        hideMessages();
    })();
</script>
@endpush
