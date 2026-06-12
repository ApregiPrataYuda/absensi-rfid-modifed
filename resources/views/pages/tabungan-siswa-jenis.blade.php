@extends('layouts.page')

@section('title', 'Jenis Tabungan')

@section('content')
<div id="view-tabungan-jenis" class="view-section active animate-fade-in space-y-5">
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h4 class="font-bold text-sm text-gray-800">Master Jenis Tabungan</h4>
                <p class="text-xs text-gray-500 mt-1">Kelola daftar jenis tabungan yang bisa dipakai untuk membuka rekening siswa.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="refreshTabunganJenisData(true)" class="bg-white text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-700 transition">
                    <i class="fas fa-sync-alt mr-1"></i> Perbarui
                </button>
                @can('tabungan-siswa.jenis.manage')
                    <button onclick="showTabunganJenisModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                @endcan
            </div>
        </div>

        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3 border-b border-gray-100 bg-gray-50/40">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-indigo-600">Total Jenis</div>
                <div id="tabunganJenisCount" class="mt-2 text-xl font-bold text-indigo-900">0</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Jenis Aktif</div>
                <div id="tabunganJenisActiveCount" class="mt-2 text-xl font-bold text-emerald-900">0</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-amber-600">Total Rekening</div>
                <div id="tabunganJenisAccountCount" class="mt-2 text-xl font-bold text-amber-900">0</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 w-12 text-center">No</th>
                        <th class="p-3 w-32">Kode</th>
                        <th class="p-3">Nama Jenis</th>
                        <th class="p-3">Deskripsi</th>
                        <th class="p-3 w-24 text-center">Status</th>
                        <th class="p-3 w-28 text-center">Rekening</th>
                        <th class="p-3 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-tabungan-jenis" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="7" class="p-8 text-center text-gray-400">Memuat data jenis tabungan...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
@include('pages.scripts.tabungan-siswa')
@endpush
