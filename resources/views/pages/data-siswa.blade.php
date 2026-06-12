@extends('layouts.page')

@section('title', 'Direktori Karyawan')

@section('content')
<div id="view-data-siswa" class="view-section active animate-fade-in">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      
      <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50/30">
        <div>
            <h3 class="font-bold text-sm text-gray-800">Direktori Karyawan</h3>
            <p class="text-xs text-gray-500">Kelola data Karyawan, Gedung / Lantai, dan cetak kartu.</p>
        </div>
        
        <div class="flex items-center gap-2 flex-wrap justify-end">
            <button onclick="refreshData('siswa')" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                <i class="fas fa-sync-alt"></i>
            </button>
            
            {{-- <button onclick="downloadTemplate('siswa')" class="bg-amber-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-amber-600 transition transform active:scale-95" title="Download Template Excel">
                <i class="fas fa-download mr-1"></i> Template
            </button> --}}

            <button onclick="downloadKartuSiswaBulk()" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-indigo-700 transition transform active:scale-95">
                <i class="fas fa-id-card mr-1"></i> Cetak Kartu
            </button>

            {{-- <button onclick="triggerImportSiswa()" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-emerald-700 transition transform active:scale-95">
                <i class="fas fa-file-excel mr-1"></i> Import CSV/Excel
            </button> --}}
            <input type="file" id="fileInputSiswa" accept=".xlsx, .xls, .csv" class="hidden" onchange="handleFileImportSiswa(this)">

            <button onclick="showAddSiswaModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                <i class="fas fa-plus mr-1"></i> Tambah
            </button>
        </div>
      </div>

      <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
          
          <div class="flex flex-col sm:flex-row items-center gap-3 text-xs w-full md:w-auto">
              <div class="flex items-center gap-2 w-full sm:w-auto">
                  <span class="text-gray-500 font-bold whitespace-nowrap">Show</span>
                  <select onchange="handleTableLimit('siswa', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-auto cursor-pointer">
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                      <option value="all">Semua</option>
                  </select>
              </div>

              <select id="filterKelasSiswa" onchange="handleTableClassFilter('Semua Gedung', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-40 font-bold shadow-sm cursor-pointer">
                    <option value="">Semua Gedung</option>
              </select>
          </div>

          <div class="relative w-full md:w-64">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-search text-gray-400 text-xs"></i>
              </div>
              <input type="text" oninput="handleTableSearch('siswa', this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari Nama / NIK / No HP...">
          </div>
      </div>

      <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
              <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                  <tr>
                      <th class="p-3 text-center w-12">No</th>
                      <th class="p-3">Nama Lengkap</th>
                      <th class="p-3 hidden md:table-cell">NIK</th>
                      <th class="p-3 hidden sm:table-cell">Gedung / Lantai</th>
                      <th class="p-3 hidden lg:table-cell">Jenis Kelamin</th>
                      <th class="p-3 hidden xl:table-cell">Agama</th>
                      <th class="p-3 hidden xl:table-cell">No HP</th>
                      <th class="p-3 text-center w-40">Aksi</th>
                  </tr>
              </thead>
              <tbody id="tbody-siswa" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                  </tbody>
          </table>
      </div>

      <div id="footer-siswa" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
          <span id="info-siswa">Menampilkan 0 data</span>
          <div class="flex gap-1">
              <button onclick="changePage('siswa', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-prev-siswa">Prev</button>
              <button onclick="changePage('siswa', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-next-siswa">Next</button>
          </div>
      </div>
  </div>
@endsection

@push('scripts')
@include('pages.scripts.data-siswa')
@endpush
