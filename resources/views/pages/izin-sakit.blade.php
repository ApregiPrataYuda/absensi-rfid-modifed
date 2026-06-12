@extends('layouts.page')

@section('title', 'Izin / Sakit')

@section('content')
<div id="view-izin-sakit" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50/30">
            <div>
                <h3 class="font-bold text-sm text-gray-800">Izin / Sakit</h3>
                <p id="izin-sakit-description" class="text-xs text-gray-500">Silakan ajukan dan kelola permohonan izin/sakit siswa sesuai alur persetujuan yang berlaku.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button onclick="refreshIzinSakitData()" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button id="btn-add-izin-sakit" onclick="showAddIzinSakitModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                    <i class="fas fa-plus mr-1"></i> Pengajuan
                </button>
            </div>
        </div>

        <div class="p-4 bg-white border-b border-gray-100">
            <div id="izin-sakit-filter-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2 w-full">
                <input id="filter-izin-tanggal-dari" type="date" onchange="handleIzinSakitDateFilter()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                <input id="filter-izin-tanggal-sampai" type="date" onchange="handleIzinSakitDateFilter()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                <select id="filter-izin-jenis" onchange="handleIzinSakitJenisFilter(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                    <option value="">Semua Jenis</option>
                </select>
                <select id="filter-izin-status" onchange="handleIzinSakitStatusFilter(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                    <option value="">Semua Status</option>
                </select>
                <select onchange="handleIzinSakitLimit(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                    <option value="10">10 baris</option>
                    <option value="25">25 baris</option>
                    <option value="50">50 baris</option>
                    <option value="100">100 baris</option>
                    <option value="all">Semua</option>
                </select>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                    <input id="filter-izin-search" type="text" oninput="handleIzinSakitSearch(this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari siswa/alasan...">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 text-center w-12">No</th>
                        <th class="p-3">Tanggal</th>
                        <th id="th-izin-siswa" class="p-3">Karyawan</th>
                        <th id="th-izin-kelas" class="p-3 hidden md:table-cell">Gedung</th>
                        <th class="p-3">Jenis</th>
                        <th class="p-3 hidden lg:table-cell">Alasan</th>
                        <th class="p-3">Status</th>
                        <th id="th-izin-pengaju" class="p-3 hidden lg:table-cell">Pengaju</th>
                        <th class="p-3 text-center w-48">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-izin-sakit" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="9" class="p-8 text-center text-gray-400">Memuat data izin/sakit...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
            <span id="info-izin-sakit">Menampilkan 0 data</span>
            <div class="flex gap-1">
                <button onclick="changeIzinSakitPage(-1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-prev-izin-sakit">Prev</button>
                <button onclick="changeIzinSakitPage(1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-next-izin-sakit">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.izin-sakit')
@endpush
