@extends('layouts.page')

@section('title', 'Master Jenis Pelanggaran')

@section('content')
<div id="view-poin-pelanggaran" class="view-section active animate-fade-in space-y-5">
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div>
                <h4 class="font-bold text-sm text-gray-800">Daftar Jenis Pelanggaran</h4>
                <p class="text-xs text-gray-500 mt-1">Daftar ini menjadi referensi resmi saat pencatatan riwayat pelanggaran siswa.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="refreshPoinPelanggaranData(true)" class="bg-white text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-700 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt mr-1"></i> Perbarui
                </button>
                @can('poin-pelanggaran.manage')
                    <button onclick="showJenisPelanggaranModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </button>
                @endcan
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 w-12 text-center">No</th>
                        <th class="p-3">Nama Pelanggaran</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3 w-24 text-center">Poin</th>
                        <th class="p-3 w-24 text-center">Status</th>
                        <th class="p-3 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-jenis-pelanggaran" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-400">Memuat data jenis pelanggaran...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
@include('pages.scripts.poin-pelanggaran')
@endpush
