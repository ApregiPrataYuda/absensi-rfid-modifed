@extends('layouts.page')

@section('title', 'Dashboard Bendahara')

@section('content')
<div class="view-section active animate-fade-in space-y-4 md:space-y-6">
    <div class="rounded-3xl border border-amber-100 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-5 md:p-7 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-800">
                    <i class="fas fa-piggy-bank"></i>
                    Dashboard Bendahara
                </div>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-gray-900">Operasional Tabungan Siswa</h2>
                <p class="mt-2 text-sm text-gray-600">Kelola jenis tabungan, rekening tabungan, input transaksi dari modal riwayat, dan pantau saldo siswa dari satu alur kerja.</p>
            </div>
            <a href="{{ route('tabungan-siswa.rekening.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600">
                <i class="fas fa-arrow-right"></i>
                Buka Tabungan Siswa
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <a href="{{ route('tabungan-siswa.rekening.index') }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-amber-200 hover:bg-amber-50/40">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                    <i class="fas fa-wallet text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">Rekening & Transaksi</div>
                    <div class="text-xs text-gray-500">Input transaksi langsung dari rekening siswa.</div>
                </div>
            </div>
        </a>

        <a href="{{ route('tabungan-siswa.jenis.index') }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/40">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                    <i class="fas fa-piggy-bank text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">Jenis Tabungan</div>
                    <div class="text-xs text-gray-500">Kelola master jenis tabungan yang dipakai rekening.</div>
                </div>
            </div>
        </a>

        <a href="{{ route('data-siswa') }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-sky-200 hover:bg-sky-50/40">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                    <i class="fas fa-user-graduate text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">Data Siswa</div>
                    <div class="text-xs text-gray-500">Pastikan data siswa dan kelas selalu sinkron.</div>
                </div>
            </div>
        </a>

        <a href="{{ route('settings.profile.index') }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/40">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                    <i class="fas fa-user-cog text-lg"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">Profil Akun</div>
                    <div class="text-xs text-gray-500">Kelola profil operator bendahara.</div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
