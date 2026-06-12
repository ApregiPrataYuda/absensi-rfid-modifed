@extends('layouts.page')

@section('title', 'Kelola Kelas')

@section('content')
<div id="view-kelola-kelas" class="view-section active animate-fade-in">
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50/30">
        <div>
            <h3 class="font-bold text-sm text-gray-800">Kelola Kelas</h3>
            <p class="text-xs text-gray-500">Kelola daftar kelas, wali kelas, dan kapasitas siswa.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
            <button onclick="refreshData('kelas')" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button onclick="showAddKelasModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                <i class="fas fa-plus mr-1"></i> Tambah
            </button>
        </div>
      </div>

      <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
          <div class="flex flex-col sm:flex-row items-center gap-3 text-xs w-full md:w-auto">
              <div class="flex items-center gap-2 w-full sm:w-auto">
                  <span class="text-gray-500 font-bold whitespace-nowrap">Show</span>
                  <select onchange="handleTableLimit('kelas', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-auto cursor-pointer">
                      <option value="10">10</option>
                      <option value="25">25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                      <option value="all">Semua</option>
                  </select>
              </div>
              <select id="filterTingkatKelas" onchange="handleTableClassFilter('kelas', this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-40 font-bold shadow-sm cursor-pointer">
                    <option value="">Semua Tingkat</option>
              </select>
          </div>

          <div class="relative w-full md:w-64">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-search text-gray-400 text-xs"></i>
              </div>
              <input type="text" oninput="handleTableSearch('kelas', this.value)" class="bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari Nama Kelas...">
          </div>
      </div>

      <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
              <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                  <tr>
                      <th class="p-3 text-center w-12">No</th>
                      <th class="p-3">Nama Kelas</th>
                      <th class="p-3 hidden md:table-cell">Wali Kelas</th>
                      <th class="p-3 hidden sm:table-cell">Kapasitas</th>
                      <th class="p-3 hidden md:table-cell">Jumlah Siswa</th>
                      <th class="p-3 hidden lg:table-cell">Jam Absen</th>
                      <th class="p-3 text-center w-40">Aksi</th>
                  </tr>
              </thead>
              <tbody id="tbody-kelas" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                  <tr>
                      <td colspan="7" class="p-8 text-center text-gray-400">Memuat data kelas...</td>
                  </tr>
              </tbody>
          </table>
      </div>

      <div id="footer-kelas" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
          <span id="info-kelas">Menampilkan 0 data</span>
          <div class="flex gap-1">
              <button onclick="changePage('kelas', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-prev-kelas">Prev</button>
              <button onclick="changePage('kelas', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-next-kelas">Next</button>
          </div>
      </div>
  </div>
@endsection

@push('scripts')
@include('pages.scripts.kelola-kelas')
@endpush
