@extends('layouts.page')

@php
    $staffContext = strtolower((string) ($staffContext ?? 'guru'));
    $isPiket = $staffContext === 'piket';
    $staffTitle = $isPiket ? 'Manajemen' : 'Manajemen Foreman(Mandor)';
    $kelasTitle = $isPiket ? 'Gedung - Lantai Tugas' : 'Pengelola Gedung - Lantai';
@endphp

@section('title', $staffTitle)

@section('content')
<div id="view-data-guru" class="view-section active animate-fade-in">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      
      <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/30">
          <div>
              <h3 class="font-bold text-sm text-gray-800">{{ $staffTitle }}</h3>
          </div>
          <div class="flex items-center gap-2">
            <button onclick="refreshData('guru')" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-purple-600 transition" title="Perbarui Data">
                <i class="fas fa-sync-alt"></i>
            </button>
            
            {{-- @unless($isPiket) --}}
              {{-- <button onclick="downloadTemplate('guru')" class="bg-amber-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-amber-600 transition transform active:scale-95" title="Download Template Excel">
                  <i class="fas fa-download mr-1"></i> Template
              </button> --}}

              {{-- <button onclick="triggerImportGuru()" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-emerald-700 transition">
                  <i class="fas fa-file-excel mr-1"></i> Import CSV/Excel
              </button> --}}

              {{-- <input type="file" id="fileInputGuru" accept=".xlsx, .xls, .csv" class="hidden" onchange="handleFileImportGuru(this)">
            @endunless --}}
          
            <button onclick="showAddGuruModal()" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-purple-700 transition">
                <i class="fas fa-plus mr-1"></i> Tambah
            </button>
          </div>
      </div>

      <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
          
          <div class="flex flex-col sm:flex-row items-center gap-3 text-xs w-full md:w-auto">
              <div class="flex items-center gap-2">
                  <span class="text-gray-500 font-bold">Show</span>
                  <select onchange="handleTableLimit('guru', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2">
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="all">Semua</option>
                  </select>
              </div>

              <select id="filterKelasGuru" onchange="handleTableClassFilter('guru', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-2 w-full sm:w-40 font-bold shadow-sm">
                    <option value="">Semua Gedung</option>
              </select>
          </div>

          <div class="relative w-full md:w-64">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-search text-gray-400 text-xs"></i>
              </div>
              <input type="text" oninput="handleTableSearch('guru', this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-purple-500 focus:border-purple-500 block w-full pl-10 p-2" placeholder="Cari username, nama, email, no hp...">
          </div>
      </div>

      <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                <tr>
                    <th class="p-3 text-center w-10">No</th>
                    <th class="p-3">Username Foreman(mandor)</th>
                    <th class="p-3">Nama Foreman(mandor)</th>
                    <th class="p-3 hidden lg:table-cell">Email</th>
                    <th class="p-3 hidden lg:table-cell">No HP</th>
                    <th class="p-3">{{ $kelasTitle }}</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
              <tbody id="tbody-guru" class="divide-y divide-gray-50 bg-white text-xs">
                  </tbody>
          </table>
      </div>

      <div id="footer-guru" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
          <span id="info-guru">Menampilkan 0 data</span>
          <div class="flex gap-1">
              <button onclick="changePage('guru', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition" id="btn-prev-guru">Prev</button>
              <button onclick="changePage('guru', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition" id="btn-next-guru">Next</button>
          </div>
      </div>
  </div>
@endsection

@push('scripts')
<script>
    window.STAFF_CONTEXT = @json($staffContext);
</script>
@include('pages.scripts.data-guru')
@endpush
