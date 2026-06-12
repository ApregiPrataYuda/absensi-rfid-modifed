@extends('layouts.page')

@section('title', 'Riwayat Pelanggaran Karyawan')

@section('content')
<div id="view-poin-pelanggaran" class="view-section active animate-fade-in space-y-5">
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50/70">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Top Akumulasi Poin</h4>
            <p class="text-[11px] text-gray-500 mt-1">Karyawan dengan akumulasi poin pelanggaran tertinggi ditampilkan untuk prioritas tindak lanjut pembinaan.</p>
        </div>
        <div class="p-4">
            <div id="poinPelanggaranRingkasan" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <div class="p-3 rounded-lg border border-dashed border-gray-200 text-xs text-gray-500 bg-gray-50">Memuat ringkasan...</div>
            </div>
        </div>
    </section>

    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between md:items-center gap-3">
            <div>
                <h4 class="font-bold text-sm text-gray-800">Daftar Riwayat Pelanggaran</h4>
                <p class="text-xs text-gray-500 mt-1">Pilih gedung untuk menampilkan riwayat pelanggaran secara langsung.</p>
            </div>
            <div class="flex items-end gap-2 flex-wrap md:flex-nowrap">
                <div class="min-w-[220px]">
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Gedung</label>
                    <select id="filterPelanggaranKelas" class="w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        <option value="">Semua Gedung</option>
                    </select>
                </div>
                <div class="min-w-[260px]">
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Cari Karyawan / NIK</label>
                    <input id="filterPelanggaranKeyword" type="text" placeholder="Ketik nama karyawan atau NIK" class="w-full bg-white border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                </div>
                <button onclick="refreshPoinPelanggaranData(true)" class="bg-white text-gray-700 border border-gray-200 px-3 py-2.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-700 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt mr-1"></i> Perbarui
                </button>
                @can('poin-pelanggaran.manage')
                    <button onclick="showRiwayatPelanggaranModal()" class="bg-indigo-600 text-white px-3 py-2.5 rounded-lg text-xs font-bold shadow-sm hover:bg-indigo-700 transition">
                        <i class="fas fa-plus mr-1"></i> Catat
                    </button>
                @endcan
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 w-12 text-center">No</th>
                        <th class="p-3 w-28">Tanggal</th>
                        <th class="p-3">Karyawan</th>
                        <th class="p-3 w-24">Gedung</th>
                        <th class="p-3">Pelanggaran</th>
                        <th class="p-3 w-24 text-center">Poin</th>
                        <th class="p-3">Catatan</th>
                        <th class="p-3 w-36">Input Oleh</th>
                        <th class="p-3 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-riwayat-pelanggaran" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="9" class="p-8 text-center text-gray-400">Memuat riwayat pelanggaran...</td>
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
