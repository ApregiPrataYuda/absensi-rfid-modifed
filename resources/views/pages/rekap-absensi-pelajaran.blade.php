@extends('layouts.page')

@section('title', 'Laporan Absensi Pelajaran')

@section('content')
<div id="view-rekap-absensi-pelajaran" class="view-section active animate-fade-in space-y-4">
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-100 bg-gray-50/60">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4">
                <div>
                    <h3 class="font-bold text-lg text-gray-800">Laporan Absensi Pelajaran</h3>
                    <p class="text-xs text-gray-500 mt-1">Rekap sesi pelajaran dan akumulasi kehadiran siswa per mata pelajaran.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button id="lessonReportRefreshBtn" type="button" class="bg-white text-gray-600 border border-gray-200 px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition flex items-center gap-2">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                    <button id="lessonReportExportBtn" type="button" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-xs font-bold shadow-sm hover:bg-emerald-700 transition flex items-center gap-2">
                        <i class="fas fa-file-excel"></i>
                        <span>Export Excel</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                <div>
                    <label for="lessonReportDateFrom" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Tanggal Dari</label>
                    <input id="lessonReportDateFrom" type="date" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                </div>
                <div>
                    <label for="lessonReportDateTo" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Tanggal Sampai</label>
                    <input id="lessonReportDateTo" type="date" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                </div>
                <div>
                    <label for="lessonReportClassFilter" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Kelas</label>
                    <select id="lessonReportClassFilter" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <option value="0">Semua Kelas</option>
                    </select>
                </div>
                <div>
                    <label for="lessonReportTeacherFilter" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Guru</label>
                    <select id="lessonReportTeacherFilter" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <option value="0">Semua Guru</option>
                    </select>
                </div>
                <div>
                    <label for="lessonReportMapelFilter" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Mapel</label>
                    <select id="lessonReportMapelFilter" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <option value="">Semua Mapel</option>
                    </select>
                </div>
                <div>
                    <label for="lessonReportSessionStatusFilter" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Status Sesi</label>
                    <select id="lessonReportSessionStatusFilter" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 p-2.5">
                        <option value="closed">Sesi Ditutup</option>
                        <option value="open">Sesi Berjalan</option>
                        <option value="all">Semua Sesi</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 xl:grid-cols-[1fr_auto] gap-3 items-end">
                <div>
                    <label for="lessonReportSearchInput" class="block text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1">Cari Sesi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                        </div>
                        <input id="lessonReportSearchInput" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pl-10 p-2.5" placeholder="Kelas, guru, mapel, atau tanggal">
                    </div>
                </div>
                <div class="text-[11px] text-gray-500 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2.5">
                    Laporan menghitung sesi yang sudah dibuat. Rekap siswa akan paling akurat pada sesi yang sudah ditutup.
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3" id="lessonReportStatsGrid">
        @foreach ([
            ['id' => 'lessonReportStatSessions', 'label' => 'Total Sesi', 'icon' => 'fa-calendar-check', 'tone' => 'indigo'],
            ['id' => 'lessonReportStatStudents', 'label' => 'Siswa', 'icon' => 'fa-user-graduate', 'tone' => 'cyan'],
            ['id' => 'lessonReportStatHadir', 'label' => 'Hadir', 'icon' => 'fa-circle-check', 'tone' => 'emerald'],
            ['id' => 'lessonReportStatTerlambat', 'label' => 'Terlambat', 'icon' => 'fa-clock', 'tone' => 'amber'],
            ['id' => 'lessonReportStatIzin', 'label' => 'Izin', 'icon' => 'fa-envelope-open-text', 'tone' => 'blue'],
            ['id' => 'lessonReportStatSakit', 'label' => 'Sakit', 'icon' => 'fa-notes-medical', 'tone' => 'violet'],
            ['id' => 'lessonReportStatAlfa', 'label' => 'Alfa', 'icon' => 'fa-circle-xmark', 'tone' => 'rose'],
            ['id' => 'lessonReportStatBelum', 'label' => 'Belum', 'icon' => 'fa-hourglass-half', 'tone' => 'slate'],
        ] as $card)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-wide font-bold text-gray-400">{{ $card['label'] }}</p>
                        <div id="{{ $card['id'] }}" class="mt-2 text-2xl font-bold text-gray-800">0</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center
                        @if($card['tone'] === 'indigo') bg-indigo-50 text-indigo-600
                        @elseif($card['tone'] === 'cyan') bg-cyan-50 text-cyan-600
                        @elseif($card['tone'] === 'emerald') bg-emerald-50 text-emerald-600
                        @elseif($card['tone'] === 'amber') bg-amber-50 text-amber-600
                        @elseif($card['tone'] === 'blue') bg-blue-50 text-blue-600
                        @elseif($card['tone'] === 'violet') bg-violet-50 text-violet-600
                        @elseif($card['tone'] === 'rose') bg-rose-50 text-rose-600
                        @else bg-slate-50 text-slate-600
                        @endif">
                        <i class="fas {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="grid grid-cols-2 gap-1.5 bg-gray-100 p-1 rounded-lg w-full lg:w-[320px]">
                <button id="lessonReportTabSessions" type="button" class="w-full px-3 py-1.5 rounded-md text-xs font-semibold whitespace-nowrap leading-none bg-white text-indigo-600 shadow-sm">Ringkasan Sesi</button>
                <button id="lessonReportTabStudents" type="button" class="w-full px-3 py-1.5 rounded-md text-xs font-semibold whitespace-nowrap leading-none text-gray-500">Rekap Siswa</button>
            </div>
            <div class="flex flex-col lg:flex-row gap-3 lg:items-center w-full">
                <div class="text-xs text-gray-500 lg:ml-auto lg:text-right">
                    <span id="lessonReportSummaryText">Menampilkan 0 sesi dan 0 siswa.</span>
                </div>
                <div id="lessonReportStudentSearchWrap" class="hidden relative w-full lg:w-64">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                    </div>
                    <input id="lessonReportStudentSearchInput" type="text" class="w-full bg-white border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pl-8 pr-3 py-2 leading-none" placeholder="Cari siswa atau NISN">
                </div>
            </div>
        </div>

        <div id="lessonReportLoadingState" class="hidden p-8 text-center">
            <div class="w-7 h-7 border-2 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
            <p class="mt-2.5 text-[11px] font-semibold text-gray-600">Memuat laporan absensi pelajaran...</p>
        </div>

        <div id="lessonReportEmptyState" class="hidden p-14 text-center">
            <div class="w-20 h-20 rounded-full bg-indigo-50 text-indigo-200 flex items-center justify-center mx-auto text-4xl">
                <i class="fas fa-book-open"></i>
            </div>
            <h4 class="mt-4 text-lg font-bold text-gray-800">Belum Ada Data Sesi</h4>
            <p class="mt-1 text-sm text-gray-500">Ubah filter atau mulai sesi absensi pelajaran terlebih dahulu.</p>
        </div>

        <div id="lessonReportSessionsPanel" class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-center w-10">No</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Sesi</th>
                        <th class="px-4 py-3">Guru</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Total</th>
                        <th class="px-4 py-3 text-center">H</th>
                        <th class="px-4 py-3 text-center">T</th>
                        <th class="px-4 py-3 text-center">I</th>
                        <th class="px-4 py-3 text-center">S</th>
                        <th class="px-4 py-3 text-center">A</th>
                        <th class="px-4 py-3 text-center">Belum</th>
                        <th class="px-4 py-3 text-center">%</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="lessonReportSessionsBody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="14" class="p-10 text-center text-gray-400">Belum ada data untuk ditampilkan.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="lessonReportStudentsPanel" class="hidden overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold">
                    <tr>
                        <th class="px-4 py-3 text-center w-10">No</th>
                        <th class="px-4 py-3">NISN</th>
                        <th class="px-4 py-3">Nama Siswa</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3 text-center">Total Sesi</th>
                        <th class="px-4 py-3 text-center">H</th>
                        <th class="px-4 py-3 text-center">T</th>
                        <th class="px-4 py-3 text-center">I</th>
                        <th class="px-4 py-3 text-center">S</th>
                        <th class="px-4 py-3 text-center">A</th>
                        <th class="px-4 py-3 text-center">Belum</th>
                        <th class="px-4 py-3 text-center">% Hadir</th>
                    </tr>
                </thead>
                <tbody id="lessonReportStudentsBody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="12" class="p-10 text-center text-gray-400">Belum ada data siswa untuk ditampilkan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="lessonReportDetailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/55"></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                <div>
                    <h4 class="font-bold text-lg text-gray-800">Detail Sesi Pelajaran</h4>
                    <p id="lessonReportDetailSubhead" class="text-sm text-gray-500 mt-1">-</p>
                </div>
                <button id="lessonReportDetailCloseBtn" type="button" class="w-10 h-10 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="lessonReportDetailLoading" class="hidden p-7 text-center">
                <div class="w-6 h-6 border-2 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto"></div>
                <p class="mt-2.5 text-[11px] font-semibold text-gray-600">Memuat detail sesi...</p>
            </div>
            <div id="lessonReportDetailContent" class="hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60 grid grid-cols-2 lg:grid-cols-6 gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Kelas</div>
                        <div id="lessonReportDetailClass" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Mapel</div>
                        <div id="lessonReportDetailMapel" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Guru</div>
                        <div id="lessonReportDetailTeacher" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Jam</div>
                        <div id="lessonReportDetailTime" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Dimulai</div>
                        <div id="lessonReportDetailOpened" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2">
                        <div class="text-[10px] uppercase font-bold text-gray-400">Ditutup</div>
                        <div id="lessonReportDetailClosed" class="mt-1 text-sm font-bold text-gray-800">-</div>
                    </div>
                </div>
                <div class="overflow-x-auto max-h-[60vh]">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-center w-10">No</th>
                                <th class="px-4 py-3">NISN</th>
                                <th class="px-4 py-3">Nama Siswa</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Metode</th>
                                <th class="px-4 py-3 text-center">Jam</th>
                            </tr>
                        </thead>
                        <tbody id="lessonReportDetailBody" class="divide-y divide-gray-100 bg-white">
                            <tr>
                                <td colspan="6" class="p-10 text-center text-gray-400">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.rekap-absensi-pelajaran')
@endpush
