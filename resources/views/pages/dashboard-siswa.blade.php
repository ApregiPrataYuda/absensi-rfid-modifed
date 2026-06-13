@extends('layouts.page')

@section('title', 'Dashboard Karyawan')

@section('content')
<div id="view-siswa-dashboard" class="view-section active animate-fade-in space-y-4 md:space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight">Dashboard Karyawan</h2>
            <p class="text-sm text-gray-500 mt-1">Ringkasan kehadiran dan aktivitas Anda hari ini.</p>
        </div>
        <div class="grid grid-cols-1 sm:flex sm:items-center gap-2">
            <span class="inline-flex items-center justify-center sm:justify-start gap-2 text-xs font-semibold bg-white text-gray-600 px-3 py-2 rounded-lg border border-gray-200 shadow-sm">
                <i class="far fa-calendar-alt text-indigo-500"></i>
                <span id="dashDate">...</span>
            </span>
            <button onclick="refreshData('dashboard')" class="inline-flex items-center justify-center gap-2 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 px-4 py-2 rounded-lg shadow-sm hover:bg-indigo-100 transition">
                <i class="fas fa-sync-alt"></i>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">
        <section id="heroCard" class="order-1 xl:col-span-2 relative overflow-hidden rounded-[1.75rem] md:rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 p-5 md:p-8 text-white shadow-xl">
            <div class="absolute -top-24 -right-20 w-72 h-72 bg-white/10 blur-3xl rounded-full"></div>
            <div class="absolute -bottom-24 -left-20 w-72 h-72 bg-cyan-300/10 blur-3xl rounded-full"></div>
            <div class="relative z-10 space-y-5">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.2em] text-indigo-200 font-bold">Status Hari Ini</p>
                        <h3 class="text-[1.7rem] md:text-3xl font-bold leading-tight mt-1">Halo, <span id="dashGreeting">Karyawan</span></h3>
                        <p id="studentGreetingSubtext" class="text-sm text-indigo-100/90 mt-1">Pantau kehadiran Anda secara realtime.</p>
                    </div>
                    <div id="dashStatusBadge" class="inline-flex self-start items-center gap-2 px-4 py-2 rounded-xl bg-white/15 border border-white/20 backdrop-blur-md text-white text-xs font-bold">
                        Memuat...
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <article class="rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm p-3.5 md:p-4">
                        <div class="text-[11px] uppercase tracking-wider text-indigo-100 font-semibold">Jam Datang</div>
                        <div id="valMasuk" class="font-mono text-[1.7rem] md:text-3xl font-bold mt-1">--:--</div>
                    </article>
                    <article class="rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm p-3.5 md:p-4">
                        <div class="text-[11px] uppercase tracking-wider text-indigo-100 font-semibold">Jam Pulang</div>
                        <div id="valPulang" class="font-mono text-[1.7rem] md:text-3xl font-bold mt-1">--:--</div>
                    </article>
                    <article class="rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm p-3.5 md:p-4">
                        <div class="text-[11px] uppercase tracking-wider text-indigo-100 font-semibold">Kehadiran</div>
                        <div id="studentStatusText" class="text-lg font-bold mt-2">Belum Absen</div>
                        <div id="studentStatusHint" class="text-xs text-indigo-100/90 mt-1">Data sedang diperbarui.</div>
                    </article>
                </div>

                <div class="rounded-2xl border border-white/15 bg-white/10 backdrop-blur-sm p-3.5 md:p-4">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <p class="text-xs text-indigo-100">Kehadiran Anda hari ini</p>
                        <p id="studentAttendanceRate" class="text-sm font-bold text-white">0%</p>
                    </div>
                    <div class="w-full h-2 bg-white/20 rounded-full overflow-hidden">
                        <div id="studentAttendanceProgressBar" class="h-full w-0 bg-emerald-300 rounded-full transition-all duration-500"></div>
                    </div>
                    <p id="studentAttendanceSummary" class="text-xs text-indigo-100 mt-2">Menunggu data Gedung.</p>

                    <div id="alertBelumAbsen" class="hidden mt-3 rounded-xl border border-rose-200/20 bg-rose-300/10 p-3 text-white">
                        <h4 class="text-xs font-bold tracking-wide text-white">Peringatan Absensi</h4>
                        <p class="mt-1 text-[11px] leading-relaxed text-white/90">
                            Anda belum melakukan absensi datang hari ini. Silakan lakukan absensi sesuai jadwal.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <aside class="order-2 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <article class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 md:p-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="min-w-0">
                        <h4 id="profileNameSidebar" class="font-bold text-gray-800 text-sm truncate">Nama Karyawan</h4>
                        <p id="profileNisnSidebar" class="text-xs text-gray-500 font-mono truncate">-</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                        <p class="text-[10px] uppercase text-gray-500 font-bold">Kelas</p>
                        <p id="profileKelasSidebar" class="text-sm font-semibold text-gray-800 mt-1">-</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                        <p class="text-[10px] uppercase text-gray-500 font-bold">Status Akun</p>
                        <p class="text-sm font-semibold text-emerald-600 mt-1">Aktif</p>
                    </div>
                </div>
            </article>

            <article class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <h4 class="text-sm font-bold text-gray-800">Akses Cepat</h4>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    {{-- <a href="{{ route('mata-pelajaran-saya') }}" class="flex min-h-[72px] flex-col justify-between rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-indigo-50 hover:border-indigo-100 transition">
                        <span class="inline-flex items-center gap-2 text-[13px] font-medium text-gray-700">
                            <i class="fas fa-book text-[13px] text-indigo-500"></i>
                            Mata Pelajaran
                        </span>
                        <span class="text-[10px] font-semibold text-gray-400">Lihat jadwal</span>
                    </a> --}}

                    <a href="{{ route('presensi-saya') }}" class="flex min-h-[72px] flex-col justify-between rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-emerald-50 hover:border-emerald-100 transition">
                        <span class="inline-flex items-center gap-2 text-[13px] font-medium text-gray-700">
                            <i class="fas fa-chart-line text-[13px] text-emerald-500"></i>
                            Presensi Saya
                        </span>
                        <span class="text-[10px] font-semibold text-gray-400">Lihat riwayat</span>
                    </a>

                    @can('tabungan-siswa.self.view')
                        {{-- <a href="{{ route('tabungan-saya') }}" class="flex min-h-[72px] flex-col justify-between rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-sky-50 hover:border-sky-100 transition">
                            <span class="inline-flex items-center gap-2 text-[13px] font-medium text-gray-700">
                                <i class="fas fa-piggy-bank text-[13px] text-sky-500"></i>
                                Tabungan Saya
                            </span>
                            <span class="text-[10px] font-semibold text-gray-400">Cek saldo</span>
                        </a> --}}
                    @endcan

                    @canany(['izin-sakit.request', 'izin-sakit.approve', 'izin-sakit.manage'])
                        <a href="{{ route('izin-sakit.index') }}" class="flex min-h-[72px] flex-col justify-between rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-amber-50 hover:border-amber-100 transition">
                            <span class="inline-flex items-center gap-2 text-[13px] font-medium text-gray-700">
                                <i class="fas fa-notes-medical text-[13px] text-amber-500"></i>
                                Izin / Sakit
                            </span>
                            <span class="text-[10px] font-semibold text-gray-400">Ajukan izin</span>
                        </a>
                    @endcanany

                    <a href="{{ route('kartu-siswa') }}" class="flex min-h-[72px] flex-col justify-between rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-slate-100 transition">
                        <span class="inline-flex items-center gap-2 text-[13px] font-medium text-gray-700">
                            <i class="fas fa-id-card text-[13px] text-slate-500"></i>
                            Kartu Digital
                        </span>
                        <span class="text-[10px] font-semibold text-gray-400">Buka kartu</span>
                    </a>
                </div>
            </article>
        </aside>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <article class="bg-white rounded-xl border border-gray-100 p-3.5 md:p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold">Total Karyawan 

</p>
            <p id="studentClassTotal" class="mt-2 text-xl md:text-2xl font-bold text-gray-800">0</p>
        </article>
        <article class="bg-white rounded-xl border border-emerald-100 p-3.5 md:p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-emerald-700 font-semibold">Hadir</p>
            <p id="studentClassHadir" class="mt-2 text-xl md:text-2xl font-bold text-emerald-700">0</p>
        </article>
        <article class="bg-white rounded-xl border border-blue-100 p-3.5 md:p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-blue-700 font-semibold">Izin + Sakit</p>
            <p id="studentClassIzinSakit" class="mt-2 text-xl md:text-2xl font-bold text-blue-700">0</p>
        </article>
        <article class="bg-white rounded-xl border border-amber-100 p-3.5 md:p-4 shadow-sm">
            <p class="text-[11px] uppercase tracking-wide text-amber-700 font-semibold">Belum/Alpa (Tanpa Keterangan)</p>
            <p id="studentClassNeedAction" class="mt-2 text-xl md:text-2xl font-bold text-amber-700">0</p>
        </article>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.dashboard-siswa')
@endpush
