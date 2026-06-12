@extends('layouts.page')

@section('title', 'Absensi Pelajaran')

@section('content')
<div id="view-absensi-pelajaran" class="view-section active animate-fade-in">
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 max-w-6xl mx-auto items-start">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4 text-white text-center">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-book-open text-xl"></i>
                    </div>
                    <h3 class="font-bold text-sm">Absensi Pelajaran</h3>
                    <p class="text-indigo-100 text-[10px] mt-0.5">Scan tersimpan real-time, tutup sesi otomatis Alfa</p>
                </div>

                <div class="p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg border border-indigo-100">
                            <i class="far fa-calendar-alt mr-2"></i> <span id="lessonDateLabel">-</span>
                        </span>
                        <button id="lessonRefreshBtn" type="button" class="flex-1 bg-white border border-gray-200 text-gray-600 py-2 rounded-xl font-bold text-xs hover:bg-gray-50 shadow-sm flex items-center justify-center gap-2">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>

                    <div>
                        <label class="block mb-1 text-[10px] font-bold text-gray-500 uppercase tracking-wide">Pilih Sesi (Jadwal Hari Ini)</label>
                        <select id="lessonSessionSelect" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5"></select>
                        <p class="text-[10px] text-gray-400 mt-1">Daftar siswa otomatis dimuat saat sesi dipilih.</p>
                    </div>

                    <div id="lessonSessionInfo" class="hidden rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-[11px] text-gray-700 space-y-1"></div>

                    <button id="lessonSessionActionBtn" type="button" disabled class="w-full bg-gray-200 text-gray-500 py-3 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 cursor-not-allowed">
                        <i class="fas fa-ban"></i>
                        <span>Jadwal Belum Dipilih</span>
                    </button>

                    <div class="grid grid-cols-2 gap-2">
                        <button id="lessonModeQrBtn" type="button" class="px-3 py-2 rounded-xl text-xs font-bold border border-indigo-200 bg-indigo-600 text-white shadow-sm">
                            QR Kamera
                        </button>
                        <button id="lessonModeRfidBtn" type="button" class="px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-gray-100 text-gray-600">
                            RFID USB
                        </button>
                    </div>

                    <div id="lessonQrPanel" class="space-y-3">
                        <button id="lessonQrOpenBtn" type="button" class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 shadow-md shadow-indigo-200">
                            <i class="fas fa-video text-lg"></i>
                            <span>Buka Kamera Live</span>
                        </button>
                        <p class="text-[10px] text-center text-gray-400">Membuka tab baru, izinkan jika browser memblokir.</p>

                        <div id="lessonPollingStatus" class="hidden bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-center">
                            <div class="flex items-center justify-center gap-2 text-indigo-600 text-xs font-bold">
                                <i class="fas fa-circle-notch fa-spin"></i>
                                <span>Menunggu hasil scan dari kamera...</span>
                            </div>
                        </div>

                        <div>
                            <label class="block mb-1 text-[10px] font-bold text-gray-500 uppercase tracking-wide">Input NISN / QR (Manual)</label>
                            <input id="lessonManualQrInput" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Scan/ketik kode lalu Enter" autocomplete="off">
                            <p class="text-[10px] text-gray-400 mt-1">Bisa untuk scanner USB QR (keyboard-wedge).</p>
                        </div>
                    </div>

                    <div id="lessonRfidPanel" class="hidden space-y-3">
                        <div>
                            <label class="block mb-1 text-[10px] font-bold text-gray-500 uppercase tracking-wide">Input UID RFID</label>
                            <input id="lessonRfidInput" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5" placeholder="Scan UID RFID lalu Enter" autocomplete="off">
                            <p class="text-[10px] text-gray-400 mt-1">Mode RFID USB biasanya akan mengisi seperti keyboard lalu Enter.</p>
                        </div>
                    </div>

                    <div id="lessonLastResult" class="hidden rounded-xl border px-3 py-2 text-[11px]"></div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-3 bg-white rounded-2xl shadow border border-gray-100 overflow-hidden flex flex-col" style="max-height: 82vh;">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-4 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-list-check text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm">Daftar Siswa</h3>
                        <p class="text-emerald-100 text-[10px]">Checklist manual atau scan QR/RFID</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span id="lessonStatTotal" class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full border border-white/30">0</span>
                    <span id="lessonStatRecorded" class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full border border-white/30">0</span>
                    <span id="lessonStatBelum" class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full border border-white/30">0</span>
                </div>
            </div>

            <div class="bg-amber-50 border-b border-amber-100 px-4 py-2 flex items-center justify-between gap-3 shrink-0">
                <div class="min-w-0 flex items-center gap-2">
                    <i class="fas fa-info-circle text-amber-500 text-xs shrink-0"></i>
                    <p class="text-[10px] text-amber-700 font-semibold">Saat sesi ditutup, siswa yang belum tercatat otomatis diisi Alfa.</p>
                </div>
                <button id="lessonBroadcastHadirBtn" type="button" disabled class="shrink-0 bg-gray-100 text-gray-400 px-3 py-1.5 rounded-lg font-bold text-[11px] transition flex items-center justify-center gap-2 cursor-not-allowed border border-gray-200 whitespace-nowrap">
                    <i class="fas fa-bullhorn"></i>
                    <span>Broadcast Hadir</span>
                </button>
            </div>

            <div class="overflow-y-auto flex-1" id="lessonRosterWrapper">
                <table class="w-full text-sm border-collapse">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 border-b-2 border-gray-100">
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-8">#</th>
                            <th class="px-4 py-3 text-left text-[10px] font-bold text-gray-400 uppercase">Siswa</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-28">Status</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-20">Metode</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase w-20">Jam</th>
                        </tr>
                    </thead>
                    <tbody id="lessonRosterBody">
                        <tr>
                            <td colspan="5" class="py-14 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <i class="fas fa-book-open text-5xl text-gray-200"></i>
                                    <p class="font-semibold text-sm text-gray-400">Pilih sesi pelajaran untuk memulai</p>
                                    <p class="text-xs text-gray-300">Daftar siswa akan muncul di sini</p>
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
@include('pages.scripts.absensi-pelajaran')
@endpush
