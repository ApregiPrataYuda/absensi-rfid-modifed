@extends('layouts.page')

@section('title', 'Backup & Restore Database')

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Backup & Restore Database</h3>
            <p class="text-xs text-gray-500 mt-1">Unduh backup database atau restore dari file SQL.</p>
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

            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-xs text-slate-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="font-semibold">Koneksi: <span class="font-mono">{{ $connection ?? '-' }}</span></div>
                    <div class="font-semibold">Database: <span class="font-mono">{{ $database ?? '-' }}</span></div>
                </div>
                <div class="mt-1 text-[11px] text-slate-600">Driver: <span class="font-mono">{{ $driver ?? '-' }}</span></div>
            </div>

            @if (($driver ?? '') !== 'mysql')
                <div class="px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                    Fitur ini saat ini hanya mendukung database MySQL.
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-xl p-4 bg-white">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-gray-800">Backup Database</h4>
                            <p class="text-xs text-gray-500 mt-1">Membuat file <code>.sql</code> dari semua tabel dan data. Simpan file di tempat yang aman.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a id="backup-download-link" href="{{ route('settings.backup.download') }}" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-indigo-700 transition {{ (($driver ?? '') !== 'mysql') ? 'pointer-events-none opacity-60' : '' }}">
                            <i class="fas fa-download text-[11px]"></i>
                            Download Backup
                        </a>
                        <iframe id="backup-download-frame" class="hidden"></iframe>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl p-4 bg-white">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600">
                            <i class="fas fa-rotate-left"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-gray-800">Restore Database</h4>
                            <p class="text-xs text-gray-500 mt-1">Upload file <code>.sql</code> untuk mengembalikan database. Proses ini akan menimpa data yang ada.</p>
                        </div>
                    </div>

                    <div id="restore-inline-error" class="hidden mt-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs"></div>

                    <form id="restore-form" action="{{ route('settings.backup.restore') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf

                        <div>
                            <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">File Backup (.sql)</label>
                            <input type="file" name="backup_file" accept=".sql,.txt" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                            <p class="text-[11px] text-gray-500 mt-1">Maks 50MB. Format yang direkomendasikan: <code>.sql</code>.</p>
                        </div>

                        <label class="flex items-start gap-2 text-xs text-gray-700">
                            <input type="checkbox" name="confirm_restore" value="1" class="mt-0.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500" required>
                            <span>Saya mengerti proses restore akan menimpa database saat ini dan tidak bisa dibatalkan.</span>
                        </label>

                        <div class="flex justify-end">
                            <button id="restore-submit-btn" type="submit" {{ (($driver ?? '') !== 'mysql') ? 'disabled' : '' }} class="inline-flex items-center gap-2 bg-rose-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-rose-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
                                <i class="fas fa-triangle-exclamation text-[11px]"></i>
                                Restore Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const loadingOverlay = document.getElementById('loadingOverlay');
        let isLoadingModalOpen = false;

        function showLoading() {
            if (typeof Swal !== 'undefined') {
                isLoadingModalOpen = true;
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang restore database, mohon tunggu.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });
                return;
            }

            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
            }
        }

        function hideLoading() {
            if (typeof Swal !== 'undefined' && isLoadingModalOpen) {
                Swal.close();
                isLoadingModalOpen = false;
            }

            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
        }

        function showInlineError(messages) {
            const box = document.getElementById('restore-inline-error');
            if (!box) return;

            const escapeHtml = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const list = Array.isArray(messages) ? messages : [String(messages || '')];
            const clean = list.map((m) => String(m || '').trim()).filter(Boolean);
            if (clean.length === 0) {
                box.classList.add('hidden');
                box.innerHTML = '';
                return;
            }

            box.innerHTML = clean.map((m) => `<div>${escapeHtml(m)}</div>`).join('');
            box.classList.remove('hidden');
        }

        function hideInlineError() {
            showInlineError([]);
        }

        const downloadLink = document.getElementById('backup-download-link');
        const downloadFrame = document.getElementById('backup-download-frame');
        if (downloadLink) {
            downloadLink.addEventListener('click', function (event) {
                const url = String(downloadLink.getAttribute('href') || '');
                if (!url) return;

                hideInlineError();

                if (downloadFrame) {
                    event.preventDefault();
                    downloadFrame.src = url;
                }

                if (window.showAlert) {
                    window.showAlert('info', 'Download backup dimulai.');
                }
            });
        }

        const form = document.getElementById('restore-form');
        if (!form) return;

        const submitButton = document.getElementById('restore-submit-btn');

        function setSubmitState(isLoading) {
            if (submitButton) {
                submitButton.disabled = !!isLoading;
                submitButton.classList.toggle('opacity-70', !!isLoading);
                submitButton.classList.toggle('cursor-not-allowed', !!isLoading);
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (submitButton?.disabled) {
                return;
            }

            (async () => {
                const ok = await (typeof Swal !== 'undefined'
                    ? Swal.fire({
                        icon: 'warning',
                        title: 'Restore database sekarang?',
                        text: 'Proses ini akan menimpa database saat ini dan tidak bisa dibatalkan.',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Restore',
                        cancelButtonText: 'Batal'
                    }).then(r => r.isConfirmed)
                    : Promise.resolve(confirm('Restore akan menimpa database saat ini. Lanjutkan?'))
                );

                if (!ok) return;

                hideInlineError();
                setSubmitState(true);
                showLoading();

                let shouldReload = false;

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
                            showInlineError(errors);
                            if (window.showAlert) window.showAlert('error', 'Restore gagal. Periksa input.');
                            return;
                        }

                        const message = payload.message || 'Restore database gagal.';
                        showInlineError(message);
                        if (window.showAlert) window.showAlert('error', message);
                        return;
                    }

                    if (window.showAlert) {
                        window.showAlert('success', payload.message || 'Restore database berhasil.');
                    }

                    form.reset();
                    shouldReload = true;
                } catch (error) {
                    const message = error?.message || 'Terjadi kesalahan saat restore.';
                    showInlineError(message);
                    if (window.showAlert) window.showAlert('error', message);
                } finally {
                    hideLoading();
                    setSubmitState(false);
                    if (shouldReload) {
                        setTimeout(() => window.location.reload(), 800);
                    }
                }
            })();
        });
    })();
</script>
@endpush
