@extends('layouts.page')

@section('title', 'Rekapitulasi Tahunan')

@section('content')
<div id="view-rekap-tahunan" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <div class="p-4 border-b border-gray-100 bg-indigo-50/30">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="font-bold text-gray-800">Rekapitulasi Tahunan</h3>
                    <p class="text-xs text-gray-500">Pilih tab bulan untuk melihat rekap per bulan dalam tahun yang sama</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 justify-end w-full md:w-auto">
                    <select id="rekapKelas" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2 font-bold focus:ring-indigo-500 shadow-sm cursor-pointer w-full md:w-auto">
                        @if (!auth()->user()?->hasRole('wakel'))
                            <option value="">Semua Kelas</option>
                        @endif
                    </select>

                    <select id="rekapTahun" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2 font-bold focus:ring-indigo-500 shadow-sm cursor-pointer w-full md:w-auto">
                    </select>

                    <button onclick="exportRekapTahunanExcel()" id="btnExportRekap" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-emerald-700 transition shadow-sm flex items-center gap-2">
                        <i class="fas fa-file-excel"></i> <span class="hidden sm:inline">Export</span>
                    </button>

                    <select id="rekapBulan" class="hidden">
                        <option value="0">Januari</option>
                        <option value="1">Februari</option>
                        <option value="2">Maret</option>
                        <option value="3">April</option>
                        <option value="4">Mei</option>
                        <option value="5">Juni</option>
                        <option value="6">Juli</option>
                        <option value="7">Agustus</option>
                        <option value="8">September</option>
                        <option value="9">Oktober</option>
                        <option value="10">November</option>
                        <option value="11">Desember</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="p-4 border-b border-gray-100 bg-indigo-50/30">
            <div class="overflow-x-auto">
                <div id="rekapTahunanMonthTabs" class="flex items-center gap-2 min-w-max">
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="0">Jan</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="1">Feb</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="2">Mar</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="3">Apr</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="4">Mei</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="5">Jun</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="6">Jul</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="7">Agu</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="8">Sep</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="9">Okt</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="10">Nov</button>
                    <button type="button" class="rekap-month-tab px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 bg-white text-gray-600" data-month="11">Des</button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto relative">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-gray-100 text-gray-600 uppercase font-bold sticky top-0 z-10">
                    <tr id="thead-rekap-bulanan"></tr>
                </thead>
                <tbody id="tbody-rekap-bulanan" class="divide-y divide-gray-100 bg-white">
                    <tr><td class="p-8 text-center text-gray-500" colspan="10">Pilih tab bulan untuk menampilkan data.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50/30 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-xs text-gray-500">
            <div class="flex items-center gap-2">
                <span class="font-bold">Show</span>
                <select id="rekapBulananLimit" onchange="setRekapBulananLimit(this.value)" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">Semua</option>
                </select>
            </div>
            <div class="flex items-center justify-between md:justify-end gap-3">
                <span id="info-rekap-bulanan">Menampilkan 0 data</span>
                <div class="flex gap-1">
                    <button id="btn-prev-rekap-bulanan" onclick="changeRekapBulananPage(-1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition">Prev</button>
                    <button id="btn-next-rekap-bulanan" onclick="changeRekapBulananPage(1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.rekap-bulanan')
<script>
    function exportRekapTahunanExcel() {
        const btn = document.getElementById('btnExportRekap');
        if (!btn) return;

        const originalContent = btn.innerHTML;
        const sharedFilters = (typeof getCurrentRekapFilterSelection === 'function')
            ? getCurrentRekapFilterSelection()
            : {
                tahun: parseInt(getActiveRekapYear(), 10),
                kelas: String(document.getElementById('rekapKelas')?.value || '').trim(),
            };
        const tahun = parseInt(sharedFilters.tahun, 10);
        let kelas = String(sharedFilters.kelas || '').trim();

        if (!kelas) {
            showAlert('error', 'Pilih kelas terlebih dahulu untuk export rekap tahunan.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Loading...';

        const endpoint = (window.APP_AJAX_ACTIONS || {}).generateExcel || '';
        if (!endpoint) {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            showAlert('error', 'Endpoint export belum tersedia.');
            return;
        }

        const payload = {
            args: ['laporan_tahunan', {
                tahun: Number.isFinite(tahun) ? tahun : (new Date().getFullYear()),
                kelas: kelas,
            }],
        };
        const userToken = window.APP_CURRENT_USER?.token || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (userToken) {
            payload.token = userToken;
        }

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        })
        .then(async (response) => {
            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result?.message || 'Export gagal diproses.');
            }

            return result;
        })
        .then((result) => {
            btn.disabled = false;
            btn.innerHTML = originalContent;

            if (result?.success) {
                const link = document.createElement('a');
                link.href = result.url;
                link.setAttribute('download', '');
                document.body.appendChild(link);
                link.click();
                link.remove();
                return;
            }

            showAlert('error', result?.message || 'Export gagal diproses.');
        }).catch((err) => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            showAlert('error', 'Gagal: ' + err);
        });
    }

    (function () {
        function setMonthTabActive(monthValue) {
            const normalized = String(monthValue ?? '');
            const tabs = document.querySelectorAll('#rekapTahunanMonthTabs .rekap-month-tab');
            tabs.forEach((tab) => {
                const isActive = tab.dataset.month === normalized;
                tab.classList.toggle('bg-indigo-600', isActive);
                tab.classList.toggle('text-white', isActive);
                tab.classList.toggle('border-indigo-600', isActive);
                tab.classList.toggle('shadow-sm', isActive);
                tab.classList.toggle('bg-white', !isActive);
                tab.classList.toggle('text-gray-600', !isActive);
                tab.classList.toggle('border-gray-200', !isActive);
            });
        }

        function bindMonthTabs() {
            const monthSelect = document.getElementById('rekapBulan');
            const tabs = document.querySelectorAll('#rekapTahunanMonthTabs .rekap-month-tab');
            if (!monthSelect || tabs.length === 0) return;

            if (typeof window.loadDataRekapBulanan === 'function' && !window.__rekapTahunanLoadPatched) {
                const originalLoadDataRekapBulanan = window.loadDataRekapBulanan;
                window.loadDataRekapBulanan = function () {
                    setMonthTabActive(monthSelect.value);
                    return originalLoadDataRekapBulanan.apply(this, arguments);
                };
                window.__rekapTahunanLoadPatched = true;
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    monthSelect.value = tab.dataset.month;
                    setMonthTabActive(monthSelect.value);
                    if (typeof loadDataRekapBulanan === 'function') {
                        loadDataRekapBulanan();
                    }
                });
            });

            monthSelect.addEventListener('change', () => setMonthTabActive(monthSelect.value));
            setMonthTabActive(monthSelect.value || new Date().getMonth());
            setTimeout(() => setMonthTabActive(monthSelect.value), 120);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindMonthTabs);
            return;
        }

        bindMonthTabs();
    })();
</script>
@endpush
