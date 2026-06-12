@extends('layouts.page')

@section('title', 'Rekening Tabungan')

@section('content')
<div id="view-tabungan-rekening" class="view-section active animate-fade-in space-y-5">
    <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between md:items-center gap-3">
            <div>
                <h4 class="font-bold text-sm text-gray-800">Rekening Tabungan Siswa</h4>
                <p class="text-xs text-gray-500 mt-1">Buka rekening per siswa dan per jenis tabungan, lalu lihat riwayat transaksinya per rekening.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button onclick="refreshTabunganRekeningData(true)" class="bg-white text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-700 transition">
                    <i class="fas fa-sync-alt mr-1"></i> Perbarui
                </button>
                @can('tabungan-siswa.manage')
                    <button onclick="showTabunganTransactionModal()" class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-emerald-700 transition">
                        <i class="fas fa-wallet mr-1"></i> Input Transaksi
                    </button>
                    <button onclick="showTabunganRekeningModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-1"></i> Tambah Rekening
                    </button>
                @endcan
            </div>
        </div>

        <div class="p-4 grid grid-cols-2 xl:grid-cols-4 gap-3 border-b border-gray-100 bg-gray-50/40">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-indigo-600">Total Rekening</div>
                <div id="tabunganRekeningCount" class="mt-2 text-xl font-bold text-indigo-900">0</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Rekening Aktif</div>
                <div id="tabunganRekeningActiveCount" class="mt-2 text-xl font-bold text-emerald-900">0</div>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-sky-600">Siswa Tercakup</div>
                <div id="tabunganRekeningStudentCount" class="mt-2 text-xl font-bold text-sky-900">0</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wide text-amber-600">Saldo Tersimpan</div>
                <div id="tabunganRekeningSaldoTotal" class="mt-2 text-xl font-bold text-amber-900">Rp 0</div>
            </div>
        </div>

        <div class="p-4 bg-white border-b border-gray-100">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Kelas</label>
                    <select id="filterTabunganRekeningKelas" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        <option value="">Semua Kelas</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Jenis Tabungan</label>
                    <select id="filterTabunganRekeningJenis" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        <option value="">Semua Jenis</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Status</label>
                    <select id="filterTabunganRekeningStatus" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Cari</label>
                    <input id="filterTabunganRekeningKeyword" type="text" placeholder="No rekening, siswa, NISN..." class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-transparent mb-1 select-none">Reset</label>
                    <button onclick="resetTabunganRekeningFilters()" class="w-full bg-white text-gray-700 border border-gray-200 px-3 py-2.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 transition">
                        Reset
                    </button>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-gray-500">Filter berjalan otomatis saat pilihan atau kata kunci diubah.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 w-12 text-center">No</th>
                        <th class="p-3 w-44">No Rekening</th>
                        <th class="p-3">Siswa</th>
                        <th class="p-3 w-24">Kelas</th>
                        <th class="p-3 w-44">Jenis Tabungan</th>
                        <th class="p-3 w-32 text-right">Saldo</th>
                        <th class="p-3 w-24 text-center">Riwayat</th>
                        <th class="p-3 w-24 text-center">Status</th>
                        <th class="p-3 w-32">Dibuka</th>
                        <th class="p-3 w-36 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-tabungan-rekening" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    <tr>
                        <td colspan="10" class="p-8 text-center text-gray-400">Memuat data rekening tabungan...</td>
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
