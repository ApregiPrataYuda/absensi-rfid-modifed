@extends('layouts.page')

@section('title', 'Laporan Absensi')

@section('content')
<div id="view-rekap-absensi" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        
        <div class="p-5 border-b border-gray-100 bg-gray-50/50">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                    <h3 class="font-bold text-lg text-gray-800">Laporan Absensi</h3>
                    <p class="text-xs text-gray-500">Rekap data absensi siswa berdasarkan periode.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                    <select id="fKelasRekap" onchange="handleRekapAbsensiHeaderFilterChange()" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5 font-bold focus:ring-indigo-500 focus:border-indigo-500 shadow-sm cursor-pointer w-full md:w-auto">
                        <option value="">Semua Kelas</option>
                    </select>
                    <select id="rekapAbsensiBulan" onchange="handleRekapAbsensiHeaderFilterChange()" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5 font-bold focus:ring-indigo-500 focus:border-indigo-500 shadow-sm cursor-pointer w-full md:w-auto">
                        <option value="1">Januari</option>
                        <option value="2">Februari</option>
                        <option value="3">Maret</option>
                        <option value="4">April</option>
                        <option value="5">Mei</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">Agustus</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Desember</option>
                    </select>
                    <select id="rekapAbsensiTahun" onchange="handleRekapAbsensiHeaderFilterChange()" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5 font-bold focus:ring-indigo-500 focus:border-indigo-500 shadow-sm cursor-pointer w-full md:w-auto"></select>
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                        </div>
                        <input type="text" oninput="handleTableSearch('rekap', this.value)" class="bg-white border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2.5" placeholder="Cari Siswa / Kelas...">
                    </div>
                    <button onclick="exportToExcel()" id="btnExportExcel" class="bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-bold text-xs shadow-md hover:bg-emerald-700 transition transform active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>

            <div class="mt-4">
                <div id="rekapAbsensiDayTabs" class="flex flex-wrap items-center gap-2"></div>
            </div>

            <div class="hidden">
                <input type="date" id="fStart">
                <input type="date" id="fEnd">
            </div>
        </div>

        <div id="rekapContainer" class="hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                      <tr>
                          <th class="p-4 text-center w-10">No</th>
                          <th class="p-4">Tanggal</th>
                          <th class="p-4">Nama Siswa</th>
                          <th class="p-4 text-center">Kelas</th>
                          <th class="p-4 text-center">Jam Datang</th>
                          <th class="p-4 text-center">Jam Pulang</th>
                          
                          <th class="p-4 text-center">Keterangan Waktu</th>
                          
                          <th class="p-4 text-center">Status</th>
                      </tr>
                  </thead>
                    <tbody id="tbody-rekap" class="bg-white divide-y divide-gray-50 text-sm"></tbody>
                </table>
            </div>

            <div id="footer-rekap" class="p-4 border-t border-gray-100 bg-gray-50/30 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <span class="font-bold">Tampilkan</span>
                    <select onchange="handleTableLimit('rekap', this.value)" class="bg-white border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div class="flex items-center gap-3 justify-between md:justify-end">
                    <span id="info-rekap">Menampilkan 0 data</span>
                    <div class="flex gap-1">
                        <button onclick="changePage('rekap', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-prev-rekap">Prev</button>
                        <button onclick="changePage('rekap', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-next-rekap">Next</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="rekapEmptyState" class="text-center p-16 flex flex-col items-center justify-center">
            <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-200 mb-4 text-4xl">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-lg">Belum Ada Data</h4>
            <p class="text-gray-500 text-sm mt-1 max-w-xs mx-auto">Data absensi belum tersedia untuk periode saat ini.</p>
        </div>
        
        <div id="rekapLoading" class="hidden text-center p-16 flex flex-col items-center justify-center">
             <div class="w-16 h-16 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
             <h4 class="font-bold text-gray-800">Sedang Memproses...</h4>
             <p class="text-gray-500 text-xs mt-1">Mengambil data dari server</p>
        </div>

    </div>
@endsection

@push('scripts')
@include('pages.scripts.rekap-absensi')
@endpush
