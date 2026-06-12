@extends('layouts.page')

@section('title', 'Daftar Arsip')

@section('content')
<div class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100 bg-gray-50/40 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h3 class="font-bold text-gray-800">
                    <i class="fas fa-box-archive mr-2 text-indigo-600"></i> Daftar Arsip
                </h3>
                <p class="text-xs text-gray-500 mt-1">File arsip hasil Tutup Tahun Ajaran tersimpan di server.</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    id="arsip-search"
                    type="text"
                    placeholder="Cari nama file..."
                    oninput="handleArsipSearch(this.value)"
                    class="w-44 md:w-56 border border-gray-200 rounded-lg px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                >
                <select
                    id="arsip-per-page"
                    onchange="handleArsipLimit(this.value)"
                    class="border border-gray-200 rounded-lg px-2 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                >
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="arsip-total" class="inline-flex items-center px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold">
                    Total File: 0
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                    <tr>
                        <th class="p-4 w-12 text-center">No</th>
                        <th class="p-4 min-w-[220px]">Nama File</th>
                        <th class="p-4">Tipe</th>
                        <th class="p-4">Ukuran</th>
                        <th class="p-4">Terakhir Diubah</th>
                        <th class="p-4 text-center w-[170px]">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-arsip" class="divide-y divide-gray-50 text-sm">
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-500">
                            <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                                <i class="fas fa-circle-notch fa-spin"></i>
                                <span>Memuat data arsip...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
            <span id="info-arsip">Menampilkan 0 data</span>
            <div class="flex gap-1">
                <button onclick="changeArsipPage(-1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-prev-arsip">Prev</button>
                <button onclick="changeArsipPage(1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-next-arsip">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const state = {
            page: 1,
            perPage: 10,
            total: 0,
            lastPage: 1,
            from: 0,
            to: 0,
            search: '',
            rows: []
        };

        const dataUrl = @json(route('arsip.data'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderLoading(message = 'Memuat data arsip...') {
            const tbody = document.getElementById('tbody-arsip');
            if (!tbody) return;

            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="p-8 text-center">
                        <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>${escapeHtml(message)}</span>
                        </div>
                    </td>
                </tr>
            `;
        }

        function updateFooter() {
            const info = document.getElementById('info-arsip');
            const btnPrev = document.getElementById('btn-prev-arsip');
            const btnNext = document.getElementById('btn-next-arsip');
            const total = document.getElementById('arsip-total');

            if (info) {
                if (state.total === 0) {
                    info.textContent = 'Tidak ada data ditemukan.';
                } else {
                    info.textContent = `Menampilkan ${state.from} - ${state.to} dari ${state.total} data`;
                }
            }
            if (total) {
                total.textContent = `Total File: ${state.total}`;
            }
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= state.lastPage;
        }

        function renderRows() {
            const tbody = document.getElementById('tbody-arsip');
            if (!tbody) return;

            if (!state.rows || state.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-400">Belum ada file arsip.</td></tr>';
                updateFooter();
                return;
            }

            tbody.innerHTML = state.rows.map((item, idx) => `
                <tr class="hover:bg-gray-50/60">
                    <td class="p-4 text-center text-gray-500">${state.from + idx}</td>
                    <td class="p-4">
                        <div class="font-semibold text-gray-800 break-all">${escapeHtml(item.name)}</div>
                    </td>
                    <td class="p-4">
                        <span class="inline-flex items-center px-2 py-1 rounded bg-indigo-50 text-indigo-700 text-xs font-bold">
                            ${escapeHtml(item.type)}
                        </span>
                    </td>
                    <td class="p-4 text-gray-700">${escapeHtml(item.size_label)}</td>
                    <td class="p-4 text-gray-700">${escapeHtml(item.updated_at_label)}</td>
                    <td class="p-4">
                        <div class="flex items-center justify-center gap-2">
                            <a href="${escapeHtml(item.download_url)}"
                               class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button
                                type="button"
                                data-name="${escapeHtml(item.name)}"
                                data-url="${escapeHtml(item.destroy_url)}"
                                class="btn-delete-arsip inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-red-50 text-red-700 text-xs font-bold hover:bg-red-100 transition"
                            >
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            updateFooter();
        }

        async function loadArsipData() {
            renderLoading('Memuat data arsip...');

            try {
                const params = new URLSearchParams({
                    page: String(state.page),
                    per_page: String(state.perPage),
                    search: state.search,
                });

                const response = await fetch(`${dataUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Gagal memuat data arsip.');
                }

                const meta = payload.meta || {};
                state.page = Number(meta.page || 1);
                state.perPage = Number(meta.per_page || 10);
                state.total = Number(meta.total || 0);
                state.lastPage = Number(meta.last_page || 1);
                state.from = Number(meta.from || 0);
                state.to = Number(meta.to || 0);
                state.rows = Array.isArray(payload.data) ? payload.data : [];

                renderRows();
            } catch (error) {
                const tbody = document.getElementById('tbody-arsip');
                if (!tbody) return;

                tbody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-red-500">${escapeHtml(error.message || String(error))}</td></tr>`;
                state.rows = [];
                state.total = 0;
                state.from = 0;
                state.to = 0;
                state.lastPage = 1;
                updateFooter();
            }
        }

        async function deleteArsip(fileName, destroyUrl, buttonEl) {
            if (!destroyUrl) return;
            const result = await Swal.fire({
                title: 'Hapus file arsip?',
                text: `File ${fileName} akan dihapus permanen.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            });
            if (!result.isConfirmed) return;

            const icon = buttonEl ? buttonEl.querySelector('i') : null;
            if (buttonEl) buttonEl.disabled = true;
            if (icon) icon.classList.add('fa-spin');

            try {
                const response = await fetch(destroyUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message || 'Gagal menghapus file arsip.');
                }

                if (window.showAlert) {
                    window.showAlert('success', payload.message || 'File arsip berhasil dihapus.');
                }

                await loadArsipData();
            } catch (error) {
                if (window.showAlert) {
                    window.showAlert('error', error.message || 'Gagal menghapus file arsip.');
                }
                if (buttonEl) buttonEl.disabled = false;
                if (icon) icon.classList.remove('fa-spin');
            }
        }

        function changeArsipPage(direction) {
            const next = state.page + Number(direction || 0);
            if (next < 1 || next > state.lastPage) return;
            state.page = next;
            loadArsipData();
        }

        function handleArsipLimit(value) {
            state.perPage = Number(value || 10);
            state.page = 1;
            loadArsipData();
        }

        function handleArsipSearch(keyword) {
            state.search = String(keyword || '').trim();
            state.page = 1;
            loadArsipData();
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.btn-delete-arsip');
            if (!button) return;

            const fileName = String(button.dataset.name || '').trim();
            const destroyUrl = String(button.dataset.url || '').trim();
            if (!fileName || !destroyUrl) return;

            deleteArsip(fileName, destroyUrl, button);
        });

        document.addEventListener('DOMContentLoaded', function () {
            loadArsipData();
        });

        window.changeArsipPage = changeArsipPage;
        window.handleArsipLimit = handleArsipLimit;
        window.handleArsipSearch = handleArsipSearch;
    })();
</script>
@endpush
