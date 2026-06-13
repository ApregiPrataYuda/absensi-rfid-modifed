@extends('layouts.page')

@section('title', 'Kartu Absensi')

@section('content')
<div id="view-kartu-absensi" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50/30">
            <div>
                <h3 class="font-bold text-sm text-gray-800">Kartu Absensi</h3>
                <p class="text-xs text-gray-500">Kelola daftar kartu fisik, tautkan ke karyawan, dan pantau scan terakhir.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <span id="kartu-absensi-auto-refresh-status" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-100">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    Menghubungkan realtime...
                </span>
                <button type="button" onclick="refreshKartuAbsensiPage(this)" class="bg-white text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-gray-50 hover:text-indigo-600 transition" title="Perbarui Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button type="button" onclick="showAddKartuAbsensiModal()" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </div>
        </div>

        @if (session('success') || $errors->any())
            <div class="px-4 pt-4">
                @if (session('success'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif
            </div>
        @endif

        <div id="kartu-absensi-create-panel" class="p-4 border-b border-gray-100 bg-white {{ $errors->any() ? '' : 'hidden' }}">
            <form method="POST" action="{{ route('kartu-absensi.store') }}" class="grid grid-cols-1 lg:grid-cols-4 gap-3 items-end">
                @csrf

                <div class="lg:col-span-1">
                    <label for="code" class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Kode Kartu</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code') }}"
                        class="w-full bg-white border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all font-mono uppercase"
                        placeholder="Contoh: 04AABBCC"
                        required
                    >
                </div>

                <div class="lg:col-span-2">
                    <label for="siswa_id" class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Tautkan ke karyawan</label>
                    <select id="siswa_id" name="siswa_id" class="w-full bg-gray-50 border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all">
                        <option value="">Belum ditautkan</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" {{ (string) old('siswa_id') === (string) $student->id ? 'selected' : '' }}>
                                {{ $student->nama }} ({{ $student->nisn }}){{ $student->kelas ? ' - '.$student->kelas : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-1">
                    <button type="submit" class="w-full bg-blue-600 text-white px-3 py-2.5 rounded-lg text-xs font-bold shadow-sm hover:bg-blue-700 transition transform active:scale-95">
                        Simpan Kartu
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4 bg-white border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex flex-col sm:flex-row items-center gap-3 text-xs w-full md:w-auto">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <span class="text-gray-500 font-bold whitespace-nowrap">Show</span>
                    <select id="kartuAbsensiLimit" onchange="handleKartuAbsensiLimit(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-auto cursor-pointer">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <select id="filterLinkKartuAbsensi" onchange="handleKartuAbsensiStatusFilter(this.value)" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2 w-full sm:w-44 font-bold shadow-sm cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="linked">Sudah Tertaut</option>
                    <option value="unlinked">Belum Tertaut</option>
                </select>
            </div>

            <div class="relative w-full md:w-72">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400 text-xs"></i>
                </div>
                <input id="searchKartuAbsensi" type="text" oninput="handleKartuAbsensiSearch(this.value)" class="bg-white border border-gray-200 text-gray-900 text-xs rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2 transition-all" placeholder="Cari kode, nama, NIK, Gedung...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="p-3 text-center w-12">No</th>
                        <th class="p-3">Kode Kartu</th>
                        <th class="p-3">Karyawan</th>
                        <th class="p-3 hidden md:table-cell">Gedung - Lantai</th>
                        <th class="p-3 hidden lg:table-cell">Scan Terakhir</th>
                        <th class="p-3 text-center w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tbody-kartu-absensi" class="divide-y divide-gray-50 bg-white text-xs text-gray-700">
                    @forelse ($cards as $card)
                        @php
                            $scanLabel = $card->last_scanned_at
                                ? $card->last_scanned_at->format('d M Y H:i') . ' ' . ($card->last_scanned_source ?: 'unknown')
                                : 'belum pernah discan';
                            $searchIndex = strtolower(trim(implode(' ', array_filter([
                                $card->code,
                                $card->siswa?->nama,
                                $card->siswa?->nisn,
                                $card->siswa?->kelas,
                                $scanLabel,
                            ]))));
                        @endphp
                        <tr
                            data-kartu-row
                            data-search="{{ $searchIndex }}"
                            data-link-status="{{ $card->siswa_id ? 'linked' : 'unlinked' }}"
                            class="hover:bg-gray-50"
                        >
                            <td class="p-3 text-center text-gray-400 font-mono" data-row-number>1</td>
                            <td class="p-3 align-top">
                                <div class="font-mono font-semibold text-gray-900 uppercase">{{ $card->code }}</div>
                                <div class="text-[10px] text-gray-400">ID #{{ $card->id }}</div>
                            </td>
                            <td class="p-3 align-top">
                                @if ($card->siswa)
                                    <div class="font-semibold text-gray-900">{{ $card->siswa->nama }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $card->siswa->nisn }}</div>
                                @else
                                    <div class="font-semibold text-amber-700">Belum ditautkan</div>
                                    <div class="text-[11px] text-amber-600">Kartu belum punya pemilik</div>
                                @endif
                            </td>
                            <td class="p-3 hidden md:table-cell align-top">
                                <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-700 text-[11px] font-semibold">
                                    {{ $card->siswa?->kelas ?: '-' }}
                                </span>
                            </td>
                            <td class="p-3 hidden lg:table-cell align-top">
                                @if ($card->last_scanned_at)
                                    <div class="font-semibold text-gray-900">{{ $card->last_scanned_at->format('d M Y') }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $card->last_scanned_at->format('H:i') }} • {{ $card->last_scanned_source ?: 'unknown' }}</div>
                                @else
                                    <span class="text-gray-400">Belum pernah discan</span>
                                @endif
                            </td>
                            <td class="p-3 align-top text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" onclick="showEditKartuAbsensiModal({{ $card->id }})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" onclick="confirmDeleteKartuAbsensi({{ $card->id }})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <form id="delete-kartu-{{ $card->id }}" method="POST" action="{{ route('kartu-absensi.destroy', $card) }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr id="kartu-absensi-empty-initial">
                            <td colspan="6" class="p-8 text-center text-gray-400">Belum ada kartu absensi terdaftar.</td>
                        </tr>
                    @endforelse
                    <tr id="kartu-absensi-empty-state" class="hidden">
                        <td colspan="6" class="p-8 text-center text-gray-400">Tidak ada data yang cocok dengan filter saat ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="footer-kartu-absensi" class="p-4 border-t border-gray-100 bg-gray-50/30 flex justify-between items-center text-xs text-gray-500">
            <span id="info-kartu-absensi">Menampilkan 0 data</span>
            <div class="flex gap-1">
                <button onclick="changeKartuAbsensiPage(-1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-prev-kartu-absensi">Prev</button>
                <button onclick="changeKartuAbsensiPage(1)" class="px-3 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 disabled:opacity-50 transition shadow-sm" id="btn-next-kartu-absensi">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.kartu-absensi')
@endpush
