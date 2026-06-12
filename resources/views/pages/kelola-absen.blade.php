@extends('layouts.page')

@section('title', 'Kelola Hari Libur')

@section('content')
<div id="view-kelola-absen" class="view-section active animate-fade-in">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      
      <div class="lg:col-span-1 min-w-0">
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
              <div class="p-5 border-b border-gray-100 bg-indigo-50/50">
                  <h3 class="font-bold text-gray-800 flex items-center">
                      <i class="fas fa-clock text-indigo-600 mr-2"></i> Pengaturan Waktu
                  </h3>
                  <p class="text-xs text-gray-500 mt-1">Konfigurasi jam operasional absensi.</p>
              </div>
              <div class="p-5">
                  <form onsubmit="saveGlobalConfig(event)">
                      <div class="space-y-4">
                          <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                              <p class="text-[10px] uppercase font-bold text-gray-400 mb-2">Absen Datang</p>
                              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                  <div>
                                      <label class="block text-[11px] font-bold text-gray-700 mb-1">Mulai Buka</label>
                                      <input type="time" id="conf_masuk_mulai" name="jam_masuk_mulai" required class="w-full border-gray-300 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                  </div>
                                  <div>
                                      <label class="block text-[11px] font-bold text-gray-700 mb-1">Batas Tepat Waktu</label>
                                      <input type="time" id="conf_masuk_akhir" name="jam_masuk_akhir" required class="w-full border-gray-300 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                  </div>
                                  <div>
                                      <label class="block text-[11px] font-bold text-gray-700 mb-1">Batas Masuk Telat</label>
                                      <input type="time" id="conf_masuk_telat" name="jam_masuk_telat" required class="w-full border-gray-300 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                  </div>
                              </div>
                              <p class="text-[10px] text-orange-500 mt-1.5"><i class="fas fa-info-circle"></i> Setelah batas tepat waktu sampai batas masuk telat: status <b>Terlambat</b>. Lewat batas masuk telat tidak bisa absen masuk.</p>
                          </div>

                          <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                              <p class="text-[10px] uppercase font-bold text-gray-400 mb-2">Absen Pulang</p>
                              <div class="grid grid-cols-2 gap-3">
                                  <div>
                                      <label class="block text-[11px] font-bold text-gray-700 mb-1">Mulai Buka</label>
                                      <input type="time" id="conf_pulang_mulai" name="jam_pulang_mulai" required class="w-full border-gray-300 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                  </div>
                                  <div>
                                      <label class="block text-[11px] font-bold text-gray-700 mb-1">Tutup Absen</label>
                                      <input type="time" id="conf_pulang_akhir" name="jam_pulang_akhir" required class="w-full border-gray-300 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                                  </div>
                              </div>
                              <p class="text-[10px] text-orange-500 mt-1.5"><i class="fas fa-info-circle"></i> Pulang sebelum dibuka: <b>Pulang Cepat</b></p>
                          </div>
                      </div>
                      
                      <button type="submit" id="btnSaveConfig" class="w-full mt-5 bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-bold shadow-md hover:bg-indigo-700 transition transform active:scale-95 flex items-center justify-center gap-2">
                          <i class="fas fa-save"></i> Simpan Pengaturan
                      </button>
                  </form>
              </div>
          </div>
      </div>

      <div class="lg:col-span-2 min-w-0">
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden min-w-0">
              <div class="p-5 border-b border-gray-100 bg-gray-50/30 space-y-3">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                          <h3 class="font-bold text-gray-800">Kelola Hari Libur</h3>
                          <p class="text-xs text-gray-500 mt-1">Siswa tidak bisa absen pada tanggal ini.</p>
                      </div>
                      <button onclick="showAddLiburModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-indigo-700 transition">
                          <i class="fas fa-plus mr-1"></i> Tambah Hari Libur
                      </button>
                  </div>
                  <div class="flex flex-wrap items-center justify-between gap-2">
                      <div class="inline-flex items-center gap-1 p-1 rounded-lg bg-white border border-gray-200">
                          <button id="btn-tab-libur-list" onclick="switchLiburView('list')" class="px-3 py-1.5 rounded-md text-xs font-bold bg-indigo-600 text-white">
                              <i class="fas fa-list mr-1"></i> Daftar
                          </button>
                          <button id="btn-tab-libur-calendar" onclick="switchLiburView('calendar')" class="px-3 py-1.5 rounded-md text-xs font-bold text-gray-600 hover:bg-gray-100">
                              <i class="fas fa-calendar-alt mr-1"></i> Kalender
                          </button>
                      </div>
                      <div id="libur-list-controls" class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-2 py-1.5">
                          <label for="filterBulanLibur" class="text-[11px] font-bold text-gray-600">Bulan</label>
                          <input
                              type="month"
                              id="filterBulanLibur"
                              onchange="handleLiburMonthFilter(this.value)"
                              class="border-gray-200 rounded-md text-xs p-1.5 focus:ring-indigo-500 focus:border-indigo-500"
                          >
                      </div>
                  </div>
              </div>

              <div id="panel-libur-list">
                  <div class="overflow-x-auto">
                      <table class="w-full text-left">
                          <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                              <tr>
                                  <th class="p-4 w-10 text-center">No</th>
                                  <th class="p-4">Periode</th>
                                  <th class="p-4">Kelas</th>
                                  <th class="p-4">Keterangan</th>
                                  <th class="p-4 text-center w-20">Aksi</th>
                              </tr>
                          </thead>
                          <tbody id="tbody-libur" class="divide-y divide-gray-50 text-sm"></tbody>
                      </table>
                  </div>

                  <div id="footer-libur" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
                      <span id="info-libur">Menampilkan 0 data</span>
                      <div class="flex gap-1">
                          <button onclick="changePage('libur', -1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-prev-libur">Prev</button>
                          <button onclick="changePage('libur', 1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50" id="btn-next-libur">Next</button>
                      </div>
                  </div>
              </div>

              <div id="panel-libur-calendar" class="hidden p-4 md:p-5 overflow-y-auto" style="max-height: 68vh;">
                  <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                      <div class="inline-flex items-center rounded-lg border border-gray-200 bg-white overflow-hidden">
                          <button onclick="shiftLiburCalendarMonth(-1)" class="h-9 w-9 text-gray-600 hover:bg-gray-100 transition" title="Bulan sebelumnya">
                              <i class="fas fa-chevron-left text-xs"></i>
                          </button>
                          <div id="libur-calendar-month-label" class="min-w-[150px] text-center text-sm font-bold text-gray-700 px-3"></div>
                          <button onclick="shiftLiburCalendarMonth(1)" class="h-9 w-9 text-gray-600 hover:bg-gray-100 transition" title="Bulan berikutnya">
                              <i class="fas fa-chevron-right text-xs"></i>
                          </button>
                      </div>
                      <div class="flex items-center gap-2">
                          <label for="filterKelasKalenderLibur" class="text-xs font-bold text-gray-600">Kelas</label>
                          <select id="filterKelasKalenderLibur" onchange="handleLiburCalendarClassFilter(this.value)" class="border border-gray-200 rounded-lg text-xs p-2 focus:ring-indigo-500 focus:border-indigo-500">
                              <option value="">Semua Kelas</option>
                          </select>
                      </div>
                  </div>
                  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
                      <div class="xl:col-span-2 min-w-0">
                          <div class="grid grid-cols-7 text-[11px] font-bold uppercase text-gray-500 mb-2">
                              <div class="text-center py-1">Sen</div>
                              <div class="text-center py-1">Sel</div>
                              <div class="text-center py-1">Rab</div>
                              <div class="text-center py-1">Kam</div>
                              <div class="text-center py-1">Jum</div>
                              <div class="text-center py-1">Sab</div>
                              <div class="text-center py-1">Min</div>
                          </div>
                          <div id="libur-calendar-grid" class="grid grid-cols-7 gap-2"></div>
                          <div class="mt-4 flex flex-wrap gap-3 text-[11px] text-gray-600">
                              <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-red-200"></span>Libur Global</span>
                              <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-amber-200"></span>Libur Kelas</span>
                              <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-sky-400 bg-sky-100"></span>Hari Ini</span>
                          </div>
                      </div>
                      <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/40">
                          <h4 class="text-sm font-bold text-gray-700 mb-2">Detail Tanggal</h4>
                          <p id="libur-calendar-selected-date" class="text-xs text-gray-500 mb-2">Pilih tanggal pada kalender.</p>
                          <div id="libur-calendar-events" class="space-y-2"></div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
@endsection

@push('scripts')
@include('pages.scripts.kelola-absen')
@endpush
