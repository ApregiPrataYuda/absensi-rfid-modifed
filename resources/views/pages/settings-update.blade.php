@extends('layouts.page')

@section('title', 'Update Aplikasi')

@php
    $currentVersionLabel = trim((string) ($currentVersion ?? ''));
    $lastFrom = is_array($lastUpdate ?? null) ? (string) ($lastUpdate['from'] ?? '') : '';
    $lastTo = is_array($lastUpdate ?? null) ? (string) ($lastUpdate['to'] ?? '') : '';
    $lastInstalledAt = is_array($lastUpdate ?? null) ? (string) ($lastUpdate['installed_at'] ?? '') : '';

    $appUi = is_array($appUiSettings ?? null) ? $appUiSettings : [];
    $tenantTimezone = trim((string) ($appUi['website_timezone'] ?? config('app.timezone', 'Asia/Jakarta')));
    $tenantTimezoneLabelRaw = trim((string) ($appUi['website_timezone_label'] ?? $tenantTimezone));
    $tzParts = explode('(', $tenantTimezoneLabelRaw, 2);
    $tenantTimezoneLabelShort = trim((string) ($tzParts[0] ?? $tenantTimezoneLabelRaw));

    $lastInstalledAtLabel = $lastInstalledAt !== '' ? $lastInstalledAt : '-';
    if ($lastInstalledAt !== '') {
        try {
            $lastInstalledAtLabel = \Illuminate\Support\Carbon::parse($lastInstalledAt)
                ->timezone($tenantTimezone !== '' ? $tenantTimezone : 'Asia/Jakarta')
                ->format('d-m-Y H:i:s');
            if ($tenantTimezoneLabelShort !== '') {
                $lastInstalledAtLabel .= ' ' . $tenantTimezoneLabelShort;
            }
        } catch (\Throwable $e) {
            $lastInstalledAtLabel = $lastInstalledAt;
        }
    }
@endphp

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 via-indigo-500 to-sky-500"></div>
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
            <div class="absolute -left-14 -bottom-14 w-52 h-52 bg-white/10 rounded-full blur-2xl"></div>
            <div class="relative p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-extrabold text-base text-white">Update Aplikasi</h3>
                        <p class="text-xs text-indigo-100 mt-1">Cek versi terbaru dan pasang update resmi secara otomatis (1-klik).</p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/15 border border-white/20 px-3 py-1">
                        <i class="fas fa-tag text-white text-xs"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wide text-white">v{{ $currentVersionLabel !== '' ? $currentVersionLabel : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Versi Saat Ini</div>
                            <div class="mt-1 text-sm font-extrabold text-gray-900" id="current-version">{{ $currentVersionLabel !== '' ? $currentVersionLabel : '-' }}</div>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center">
                            <i class="fas fa-code-branch text-indigo-600 text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-500">Versi yang sedang aktif di aplikasi.</div>
                </div>

                <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Versi Terbaru</div>
                            <div class="mt-1 text-sm font-extrabold text-gray-900" id="latest-version-mini">-</div>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center">
                            <i class="fas fa-cloud-download-alt text-emerald-600 text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-500">Muncul setelah klik cek update.</div>
                </div>

                <div class="border border-gray-200 rounded-xl p-4 bg-white shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Status</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900" id="update-status">Belum dicek</div>
                            <div class="mt-1 text-[11px] text-gray-500" id="update-status-hint">Klik tombol cek update.</div>
                        </div>
                        <span id="badge-update" class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide border bg-gray-50 text-gray-700 border-gray-200">
                            -
                        </span>
                    </div>
                    <div class="mt-2 text-[11px] text-gray-500">Paket update resmi diverifikasi signature.</div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/40 px-4 py-3 text-xs text-gray-800">
                <div class="font-bold flex items-center gap-2">
                    <i class="fas fa-history text-gray-500"></i>
                    <span>Update Terakhir</span>
                </div>
                <div class="mt-1 text-[12px] text-gray-700">
                    @if ($lastFrom !== '' || $lastTo !== '' || $lastInstalledAt !== '')
                        Dari <span class="font-extrabold">{{ $lastFrom !== '' ? $lastFrom : '-' }}</span>
                        ke <span class="font-extrabold">{{ $lastTo !== '' ? $lastTo : '-' }}</span>
                        <span class="text-gray-500">({{ $lastInstalledAtLabel }})</span>
                    @else
                        Belum ada riwayat update.
                    @endif
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                <button id="btnCheckUpdate" type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition disabled:opacity-70 disabled:cursor-not-allowed">
                    <i class="fas fa-sync-alt"></i>
                    <span>Cek Update</span>
                </button>
                <button id="btnInstallUpdate" type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition disabled:opacity-70 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-download"></i>
                    <span>Install Update</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-600 flex items-start gap-2">
                    <i class="fas fa-wifi text-indigo-600 mt-0.5"></i>
                    <div>
                        <div class="font-bold text-gray-800">Koneksi Stabil</div>
                        <div class="text-[11px]">Gunakan internet stabil saat proses update.</div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-600 flex items-start gap-2">
                    <i class="fas fa-database text-indigo-600 mt-0.5"></i>
                    <div>
                        <div class="font-bold text-gray-800">Backup Dulu</div>
                        <div class="text-[11px]">Disarankan backup database & file sebelum update.</div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs text-gray-600 flex items-start gap-2">
                    <i class="fas fa-shield-alt text-indigo-600 mt-0.5"></i>
                    <div>
                        <div class="font-bold text-gray-800">Terverifikasi</div>
                        <div class="text-[11px]">Update diverifikasi signature untuk keamanan.</div>
                    </div>
                </div>
            </div>

            <div id="update-result" class="hidden rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div class="p-4 border-b border-gray-100 bg-gray-50/30">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Info Update</div>
                            <div class="mt-1 text-sm font-bold text-gray-900">
                                Versi terbaru: <span id="latest-version">-</span>
                            </div>
                            <div class="mt-1 text-[11px] text-gray-500">
                                Rilis: <span id="released-at">-</span> • Minimal PHP: <span id="min-php">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Catatan Rilis</div>
                    <div id="notes-html" class="prose prose-sm max-w-none text-gray-700 text-sm"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="update-progress-modal" class="fixed inset-0 z-[9999] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-gray-900/50"></div>
    <div class="relative z-10 flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden">
                <div class="border-b border-gray-100 bg-gray-50/40 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-extrabold text-gray-900" id="update-progress-title">Install Update</div>
                            <div class="mt-1 text-[11px] text-gray-500" id="update-progress-version"></div>
                        </div>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <div class="text-xs text-gray-700" id="update-progress-step">Menyiapkan...</div>
                    <div class="w-full h-3 rounded-full bg-gray-200 overflow-hidden">
                        <div id="update-progress-bar" class="h-3 rounded-full bg-indigo-600 transition-[width] duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-gray-600">
                        <div class="font-extrabold text-gray-800" id="update-progress-percent">0%</div>
                        <div class="font-medium" id="update-progress-bytes"></div>
                    </div>
                    <div class="text-[11px] text-gray-500" id="update-progress-hint">Jangan tutup halaman saat proses update berjalan.</div>
                    <div class="pt-2 flex justify-end">
                        <button id="update-progress-close" type="button" class="hidden inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-times"></i>
                            <span>Tutup</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const progressUrl = @json(route('settings.update.progress'));
    const btnCheck = document.getElementById('btnCheckUpdate');
    const btnInstall = document.getElementById('btnInstallUpdate');
    const resultBox = document.getElementById('update-result');
    const latestMiniEl = document.getElementById('latest-version-mini');
    const latestVersionEl = document.getElementById('latest-version');
    const releasedAtEl = document.getElementById('released-at');
    const minPhpEl = document.getElementById('min-php');
    const notesEl = document.getElementById('notes-html');
    const statusEl = document.getElementById('update-status');
    const statusHintEl = document.getElementById('update-status-hint');
    const badgeEl = document.getElementById('badge-update');

    const progressModal = document.getElementById('update-progress-modal');
    const progressBarEl = document.getElementById('update-progress-bar');
    const progressPercentEl = document.getElementById('update-progress-percent');
    const progressStepEl = document.getElementById('update-progress-step');
    const progressBytesEl = document.getElementById('update-progress-bytes');
    const progressCloseEl = document.getElementById('update-progress-close');
    const progressHintEl = document.getElementById('update-progress-hint');
    const progressVersionEl = document.getElementById('update-progress-version');

    let progressPolling = false;
    let progressPollTimer = null;

    const postJson = async (url, payload = {}) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload || {})
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = data?.message || 'Gagal memproses permintaan.';
            throw new Error(msg);
        }
        return data;
    };

    const getJson = async (url) => {
        const res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            }
        });

        if (!res.ok) return null;
        return await res.json().catch(() => null);
    };

    const formatBytes = (bytes) => {
        const val = Number(bytes || 0);
        if (!Number.isFinite(val) || val <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const idx = Math.min(units.length - 1, Math.floor(Math.log(val) / Math.log(1024)));
        const size = val / Math.pow(1024, idx);
        return `${size >= 10 ? size.toFixed(0) : size.toFixed(1)} ${units[idx]}`;
    };

    const openProgressModal = () => {
        if (!progressModal) return;
        progressModal.classList.remove('hidden');
        progressModal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('overflow-hidden');
        if (progressCloseEl) progressCloseEl.classList.add('hidden');
        if (progressBarEl) {
            progressBarEl.style.width = '0%';
            progressBarEl.classList.remove('bg-emerald-600', 'bg-red-600');
            progressBarEl.classList.add('bg-indigo-600');
        }
        if (progressPercentEl) progressPercentEl.textContent = '0%';
        if (progressStepEl) progressStepEl.textContent = 'Menyiapkan...';
        if (progressBytesEl) progressBytesEl.textContent = '';
        if (progressHintEl) progressHintEl.textContent = 'Jangan tutup halaman saat proses update berjalan.';
        if (progressVersionEl) progressVersionEl.textContent = '';
    };

    const closeProgressModal = () => {
        if (!progressModal) return;
        progressModal.classList.add('hidden');
        progressModal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('overflow-hidden');
    };

    const stopProgressPolling = () => {
        progressPolling = false;
        if (progressPollTimer) window.clearTimeout(progressPollTimer);
        progressPollTimer = null;
    };

    const renderProgress = (payload) => {
        const status = String(payload?.status || '');
        const step = String(payload?.step || '');
        const message = String(payload?.message || '');
        const percent = Math.max(0, Math.min(100, Number(payload?.percent ?? 0)));

        const downloadPercent = Number(payload?.download_percent ?? 0);
        const downloadTotal = Number(payload?.download_total_bytes ?? 0);
        const hasDownloadTotal = Number.isFinite(downloadTotal) && downloadTotal > 0;
        const displayPercent = step === 'download' && hasDownloadTotal && Number.isFinite(downloadPercent)
            ? Math.max(0, Math.min(100, downloadPercent))
            : percent;

        const fromVer = String(payload?.from_version || payload?.from || '');
        const toVer = String(payload?.to_version || payload?.to || '');

        if (progressStepEl) {
            progressStepEl.textContent = message !== '' ? message : (step !== '' ? step : 'Memproses...');
        }

        if (progressVersionEl) {
            if (fromVer !== '' || toVer !== '') {
                progressVersionEl.textContent = `Dari v${fromVer || '-'} ke v${toVer || '-'}`;
            } else {
                progressVersionEl.textContent = '';
            }
        }

        if (progressBarEl) progressBarEl.style.width = `${displayPercent}%`;
        if (progressPercentEl) progressPercentEl.textContent = `${Math.round(displayPercent)}%`;

        if (progressBytesEl) {
            const downloaded = Number(payload?.downloaded_bytes ?? 0);
            const total = Number(payload?.download_total_bytes ?? 0);
            if (step === 'download' && Number.isFinite(downloaded) && downloaded > 0) {
                if (Number.isFinite(total) && total > 0) {
                    progressBytesEl.textContent = `${formatBytes(downloaded)} / ${formatBytes(total)}`;
                } else {
                    progressBytesEl.textContent = `${formatBytes(downloaded)}`;
                }
            } else {
                progressBytesEl.textContent = '';
            }
        }

        if (status === 'done') {
            if (progressHintEl) progressHintEl.textContent = 'Update selesai. Klik Tutup lalu refresh halaman.';
            if (progressBarEl) {
                progressBarEl.classList.remove('bg-indigo-600', 'bg-red-600');
                progressBarEl.classList.add('bg-emerald-600');
            }
            if (progressCloseEl) progressCloseEl.classList.remove('hidden');
        }

        if (status === 'error') {
            if (progressHintEl) progressHintEl.textContent = 'Update gagal. Klik Tutup, periksa koneksi/izin file, lalu coba lagi.';
            if (progressBarEl) {
                progressBarEl.classList.remove('bg-indigo-600', 'bg-emerald-600');
                progressBarEl.classList.add('bg-red-600');
            }
            if (progressCloseEl) progressCloseEl.classList.remove('hidden');
        }
    };

    const pollProgress = async () => {
        if (!progressPolling) return;

        try {
            const payload = await getJson(progressUrl);
            if (payload) {
                renderProgress(payload);
                const st = String(payload?.status || '');
                if (st === 'done' || st === 'error') {
                    stopProgressPolling();
                    return;
                }
            }
        } catch (e) {
            // ignore
        }

        if (progressPolling) {
            progressPollTimer = window.setTimeout(pollProgress, 800);
        }
    };

    const startProgressPolling = () => {
        if (progressPolling) return;
        progressPolling = true;
        pollProgress();
    };

    const setLoading = (isLoading) => {
        if (btnCheck) btnCheck.disabled = isLoading;
        if (btnInstall) btnInstall.disabled = isLoading || btnInstall.dataset.canInstall !== '1';
        if (btnCheck) btnCheck.querySelector('i')?.classList.toggle('fa-spin', isLoading);
    };

    const setBadge = (type, text) => {
        if (!badgeEl) return;
        badgeEl.textContent = text;
        badgeEl.className = 'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-extrabold uppercase tracking-wide border';
        if (type === 'success') badgeEl.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
        else if (type === 'warning') badgeEl.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-200');
        else if (type === 'error') badgeEl.classList.add('bg-red-50', 'text-red-700', 'border-red-200');
        else badgeEl.classList.add('bg-gray-50', 'text-gray-700', 'border-gray-200');
    };

    const applyResult = (payload) => {
        const data = payload?.data || {};
        const available = !!data.update_available;

        if (statusEl) statusEl.textContent = available ? 'Update tersedia' : 'Versi terbaru';
        if (statusHintEl) statusHintEl.textContent = available ? 'Klik Install Update untuk memasang.' : 'Tidak ada update.';

        if (latestMiniEl) latestMiniEl.textContent = data.latest_version || '-';
        if (latestVersionEl) latestVersionEl.textContent = data.latest_version || '-';
        if (releasedAtEl) releasedAtEl.textContent = data.released_at || '-';
        if (minPhpEl) minPhpEl.textContent = data.min_php || '-';
        if (notesEl) notesEl.innerHTML = data.notes_html || '<div class="text-xs text-gray-500 italic">Tidak ada catatan rilis.</div>';

        if (resultBox) resultBox.classList.remove('hidden');

        if (available) {
            setBadge('warning', 'Update');
            if (btnInstall) {
                btnInstall.dataset.canInstall = '1';
                btnInstall.disabled = false;
            }
        } else {
            setBadge('success', 'OK');
            if (btnInstall) {
                btnInstall.dataset.canInstall = '0';
                btnInstall.disabled = true;
            }
        }
    };

    const showError = (message) => {
        if (statusEl) statusEl.textContent = 'Gagal';
        if (statusHintEl) statusHintEl.textContent = message;
        setBadge('error', 'Error');
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Gagal', text: message });
        } else {
            alert(message);
        }
    };

    btnCheck?.addEventListener('click', async () => {
        try {
            setLoading(true);
            if (statusEl) statusEl.textContent = 'Memeriksa...';
            if (statusHintEl) statusHintEl.textContent = 'Memeriksa versi terbaru.';
            setBadge('neutral', '...');

            const res = await postJson(@json(route('settings.update.check')));
            if (!res?.success) throw new Error(res?.message || 'Gagal cek update.');
            applyResult(res);
        } catch (e) {
            showError(e?.message || String(e));
        } finally {
            setLoading(false);
        }
    });

    btnInstall?.addEventListener('click', async () => {
        if (btnInstall.dataset.canInstall !== '1') return;

        const ok = await (typeof Swal !== 'undefined'
            ? Swal.fire({
                icon: 'warning',
                title: 'Install update sekarang?',
                text: 'Proses ini akan menimpa file aplikasi. Pastikan sudah backup.',
                showCancelButton: true,
                confirmButtonText: 'Ya, Update',
                cancelButtonText: 'Batal'
            }).then(r => r.isConfirmed)
            : Promise.resolve(confirm('Install update sekarang? Pastikan sudah backup.'))
        );

        if (!ok) return;

        try {
            setLoading(true);
            openProgressModal();
            startProgressPolling();

            const res = await postJson(@json(route('settings.update.install')));
            if (!res?.success) throw new Error(res?.message || 'Update gagal.');

            renderProgress({
                status: 'done',
                step: 'done',
                message: res?.message || 'Update selesai.',
                percent: 100,
                from_version: res?.data?.from || '',
                to_version: res?.data?.to || '',
            });
        } catch (e) {
            const msg = e?.message || String(e);
            renderProgress({ status: 'error', step: 'error', message: msg, percent: 100 });
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Update Gagal', text: msg });
            } else {
                alert(msg);
            }
        } finally {
            stopProgressPolling();
            setLoading(false);
        }
    });

    progressCloseEl?.addEventListener('click', () => closeProgressModal());

    (async () => {
        const payload = await getJson(progressUrl);
        if (payload && String(payload?.status || '') === 'running') {
            openProgressModal();
            renderProgress(payload);
            startProgressPolling();
        }
    })();
})();
</script>
@endpush
