@extends('layouts.page')

@section('title', 'Monitoring Realtime')

@section('content')
<div id="view-monitoring" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center bg-gray-50/30 gap-4">
            <div>
                <h3 class="font-bold text-sm text-gray-800 mb-1">Monitoring Kehadiran</h3>
                <p class="text-xs text-gray-500 font-medium">Data Realtime: <span id="monitoringDate" class="text-indigo-600 font-bold">...</span></p>
            </div>
            
            <div class="flex flex-col md:flex-row items-center gap-2 w-full md:w-auto">
                <button onclick="exportMonitoringExcel()" id="btnExportMonitoring" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-emerald-700 transition transform active:scale-95 flex items-center gap-2 w-full md:w-auto justify-center">
                    <i class="fas fa-file-excel"></i> <span class="hidden sm:inline">Export Excel</span>
                </button>

                <button onclick="refreshData('monitoring')" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition w-full md:w-auto" title="Perbarui Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <div class="flex flex-col sm:flex-row items-center gap-2 text-xs w-full md:w-auto">
                
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="text-gray-500 font-bold hidden sm:inline">Show</span>
                    <select onchange="handleTableLimit('monitoring', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-auto cursor-pointer">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">Semua</option>
                    </select>
                </div>

                <select id="monitoringKelas" onchange="handleTableClassFilter('monitoring', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 font-bold w-full sm:w-auto shadow-sm cursor-pointer">
                    <option value="">Semua Gedung / Divisi</option>
                </select>

                <select onchange="handleTableStatusFilter('monitoring', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 font-bold w-full sm:w-auto shadow-sm cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="Hadir">Hadir (Hijau)</option>
                    <option value="Sakit">Sakit (Kuning)</option>
                    <option value="Izin">Izin (Biru)</option>
                    <option value="Alpa">Alpa (Merah)</option>
                    <option value="Belum Absen">Belum Absen (Abu)</option>
                </select>
            </div>

            <div class="relative w-full md:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400 text-xs"></i>
                </div>
                <input type="text" oninput="handleTableSearch('monitoring', this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari Nama, Gedung, Kelas...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
              <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                  <tr>
                      <th class="p-4 text-center w-10">No</th>
                      <th class="p-4">Nama Karyawan</th>
                      <th class="p-4 text-center">Gedung - Lantai</th>
                      <th class="p-4 text-center">Jam Datang</th>
                      <th class="p-4 text-center">Jam Pulang</th>
                      <th class="p-4 text-center">Keterangan Waktu</th>
                      <th class="p-4 text-center">Status Kehadiran</th>
                  </tr>
              </thead>
              <tbody id="tbody-monitoring" class="divide-y divide-gray-50 bg-white text-sm">
                  </tbody>
            </table>
        </div>

        <div id="footer-monitoring" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
            <span id="info-monitoring">Menampilkan 0 data</span>
            <div class="flex gap-1">
                <button onclick="changePage('monitoring', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition" id="btn-prev-monitoring">Prev</button>
                <button onclick="changePage('monitoring', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition" id="btn-next-monitoring">Next</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@include('pages.scripts.monitoring')
@endpush
