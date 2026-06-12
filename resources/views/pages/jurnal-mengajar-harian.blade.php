@extends('layouts.page')

@section('title', 'Jurnal Mengajar Harian')

@section('content')
<div id="view-jurnal-mengajar" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50/30">
            <div>
                <h3 class="font-bold text-sm text-gray-800">Jurnal Mengajar Harian</h3>
                <p class="text-xs text-gray-500">Silakan catat jurnal kegiatan pembelajaran harian setiap kelas secara tertib.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <button onclick="refreshJurnalMengajarData()" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button onclick="showAddJurnalMengajarModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </div>
        </div>

        <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 w-full">
                <input id="filter-jurnal-tanggal-dari" type="date" onchange="handleJurnalDateFilter()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                <input id="filter-jurnal-tanggal-sampai" type="date" onchange="handleJurnalDateFilter()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                <select id="filter-jurnal-kelas" onchange="handleJurnalKelasFilter(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                    <option value="">Semua Kelas</option>
                </select>
                <select id="filter-jurnal-status" onchange="handleJurnalStatusFilter(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full">
                    <option value="">Semua Status</option>
                </select>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                    <input type="text" oninput="handleJurnalSearch(this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari mapel/topik/guru...">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 text-center w-12">No</th>
                        <th class="p-3">Tanggal</th>
                        <th class="p-3">Kelas</th>
                        <th class="p-3 hidden md:table-cell">Guru</th>
                        <th class="p-3">Mapel</th>
                        <th class="p-3">Topik</th>
                        <th class="p-3 hidden lg:table-cell">Status</th>
                        <th class="p-3 text-center w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-jurnal-mengajar" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="8" class="p-8 text-center text-gray-400">Memuat data jurnal mengajar...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
            <span id="info-jurnal-mengajar">Menampilkan 0 data</span>
            <div class="flex gap-1">
                <button onclick="changeJurnalPage(-1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-prev-jurnal">Prev</button>
                <button onclick="changeJurnalPage(1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-next-jurnal">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.jurnal-mengajar-harian')
@endpush
