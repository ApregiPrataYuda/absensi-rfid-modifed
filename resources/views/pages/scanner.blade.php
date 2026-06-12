@extends('layouts.page')

@section('title', 'Scan Absensi')

@section('content')
<div id="view-scanner" class="view-section active animate-fade-in">
                  <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 max-w-6xl mx-auto items-start">

                      <!-- KOLOM KIRI: Scanner Controls -->
                      <div class="lg:col-span-2 space-y-4">
                          <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
                              <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4 text-white text-center">
                                  <div id="scanModeHeaderIcon" class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
                                      <i class="fas fa-qrcode text-xl"></i>
                                  </div>
                                  <h3 id="scanModeHeaderTitle" class="font-bold text-sm">Scan QR Absensi</h3>
                                  <p id="scanModeHeaderSubtitle" class="text-indigo-100 text-[10px] mt-0.5">Setiap scan langsung divalidasi & disimpan</p>
                              </div>
                              <div class="p-4 space-y-3">
                                  <div id="scannerScopeInfo" class="hidden rounded-xl border px-3 py-2 text-xs font-semibold"></div>

                                  <div class="grid grid-cols-2 gap-2">
                                      <button type="button" id="scanModeQrBtn" onclick="setScannerMode('qr')" class="px-3 py-2 rounded-xl text-xs font-bold border border-indigo-200 bg-indigo-600 text-white shadow-sm">
                                          QR Code
                                      </button>
                                      <button type="button" id="scanModeRfidBtn" onclick="setScannerMode('rfid')" class="px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-gray-100 text-gray-600">
                                          RFID USB
                                      </button>
                                  </div>
                                  <p id="scanModeHint" class="text-[10px] text-gray-400">Pilih mode scan sesuai perangkat yang dipakai.</p>

                                  <div id="qrControls" class="space-y-3">
                                      <button type="button" onclick="openCameraPopup()"
                                          class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 shadow-md shadow-indigo-200">
                                          <i class="fas fa-video text-lg"></i>
                                          <span>Buka Kamera Live</span>
                                      </button>
                                      <p class="text-[10px] text-center text-gray-400">Membuka tab baru - izinkan jika browser memblokir</p>

                                      <div id="pollingStatus" class="hidden bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-center">
                                          <div class="flex items-center justify-center gap-2 text-indigo-600 text-xs font-bold">
                                              <i class="fas fa-circle-notch fa-spin"></i>
                                              <span>Menunggu hasil scan dari kamera...</span>
                                          </div>
                                      </div>

                                      <div class="flex items-center gap-3"><div class="flex-1 h-px bg-gray-200"></div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">atau</span><div class="flex-1 h-px bg-gray-200"></div></div>

                                      <label class="block w-full cursor-pointer">
                                          <div class="w-full bg-gray-100 hover:bg-gray-200 active:scale-95 text-gray-700 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2">
                                              <i class="fas fa-image"></i><span>Pilih Foto QR</span>
                                          </div>
                                          <input type="file" id="qrFileInput" accept="image/*" class="hidden" onchange="scanFromFile(this)">
                                      </label>

                                      <div class="flex items-center gap-3"><div class="flex-1 h-px bg-gray-200"></div><span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">atau</span><div class="flex-1 h-px bg-gray-200"></div></div>
                                  </div>

                                  <div>
                                      <label id="manualScanLabel" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Input NISN / Scanner USB</label>
                                      <div class="flex gap-2">
                                          <input type="text" id="manualNisn" placeholder="Scan/Ketik kode siswa..."
                                              class="flex-1 border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white outline-none transition"
                                              inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false"
                                              onkeydown="if(event.key==='Enter') submitManualNisn()">
                                          <button type="button" onclick="submitManualNisn()" class="bg-gray-900 hover:bg-black text-white px-4 py-3 rounded-xl text-sm font-bold transition shadow-sm">
                                              <i class="fas fa-check"></i>
                                          </button>
                                      </div>
                                      <p id="manualScanHint" class="text-[10px] text-gray-400 mt-1.5">Mode USB keyboard-wedge: scan kode lalu Enter.</p>
                                  </div>
                              </div>
                          </div>

                          <div id="scanResult" class="hidden animate-fade-in"></div>
                          <div id="fileResult"></div>

                          <button onclick="stopAndBack(true)"
                              class="w-full bg-white border border-gray-200 text-gray-600 py-2.5 rounded-xl font-bold text-xs hover:bg-gray-50 shadow-sm flex items-center justify-center gap-2">
                              <i class="fas fa-arrow-left"></i> Kembali
                          </button>
                      </div>

                      <!-- KOLOM KANAN: Tabel Daftar Scan -->
                      <div class="lg:col-span-3 bg-white rounded-2xl shadow border border-gray-100 overflow-hidden flex flex-col" style="max-height: 82vh;">
                          <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-4 text-white flex items-center justify-between shrink-0">
                              <div class="flex items-center gap-3">
                                  <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center"><i class="fas fa-list-check text-sm"></i></div>
                                  <div>
                                      <h3 class="font-bold text-sm">Daftar Absensi</h3>
                                      <p class="text-emerald-100 text-[10px]">Daftar scan yang berhasil diproses</p>
                                  </div>
                              </div>
                              <div class="flex items-center gap-2">
                                  <span id="scanCountBadge" class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full border border-white/30">0 Karyawan</span>
                                  <button onclick="resetScanTable()" title="Kosongkan antrian" class="w-8 h-8 bg-white/10 hover:bg-white/25 rounded-lg flex items-center justify-center transition text-white/70 hover:text-white">
                                      <i class="fas fa-trash-alt text-xs"></i>
                                  </button>
                              </div>
                          </div>
                          <!-- Info bar -->
                          <div class="bg-amber-50 border-b border-amber-100 px-4 py-2 flex items-center gap-2 shrink-0">
                              <i class="fas fa-info-circle text-amber-500 text-xs"></i>
                              <p class="text-[10px] text-amber-700 font-semibold">Setiap scan langsung tersimpan. Jika gagal, notifikasi otomatis muncul.</p>
                          </div>
                          <div class="overflow-y-auto flex-1" id="scanTableWrapper">
                              <table class="w-full text-sm border-collapse">
                                  <thead class="sticky top-0 z-10">
                                      <tr class="bg-gray-50 border-b-2 border-gray-100">
                                          <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-8">#</th>
                                          <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase">Nama Karyawan</th>
                                          <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase">Gedung / Lantai / Divisi</th>
                                          <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase">Status</th>
                                          <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase">Jam</th>
                                          <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-10"></th>
                                      </tr>
                                  </thead>
                                  <tbody id="tbody-scan-live">
                                      <tr id="scan-empty-row">
                                          <td colspan="6" class="py-16 text-center">
                                              <div class="flex flex-col items-center gap-3">
                                                  <i class="fas fa-qrcode text-5xl text-gray-200"></i>
                                                  <p class="font-semibold text-sm text-gray-400">Belum ada karyawan dalam antrian</p>
                                                  <p class="text-xs text-gray-300">Scan QR untuk menambahkan karyawan</p>
                                              </div>
                                          </td>
                                      </tr>
                                  </tbody>
                              </table>
                          </div>
                      </div>

                  </div>
              </div>
@endsection

@push('scripts')
@include('pages.scripts.scanner')
@endpush
