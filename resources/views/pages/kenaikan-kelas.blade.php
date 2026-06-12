@extends('layouts.page')

@section('title', 'Kenaikan Kelas')

@section('content')
<div id="view-kenaikan-kelas" class="view-section active animate-fade-in">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        
        <div class="p-4 bg-indigo-50 border-b border-indigo-100 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div>
                <h3 class="font-bold text-indigo-900"><i class="fas fa-level-up-alt mr-2"></i>Proses Kenaikan Kelas</h3>
                <p class="text-xs text-indigo-600">Pilih kelas asal dan tujuan, lalu tentukan siswa yang naik/tinggal.</p>
            </div>
            <button id="btn-tutup-tahun-arsip" onclick="showModalArsip(event)" class="inline-flex items-center justify-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-emerald-700 transition shadow-sm w-full sm:w-auto whitespace-nowrap">
                <i class="fas fa-archive"></i> Tutup Tahun (Arsip)
            </button>
        </div>

        <div class="p-4">
            <div class="flex flex-col md:flex-row gap-4 mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Kelas Asal (Sekarang)</label>
                    <select id="promoKelasAsal" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 font-bold text-gray-700">
                        <option value="">-- Pilih Kelas --</option>
                        </select>
                </div>
                
                <div class="flex items-center justify-center pt-4">
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>

                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Naik Ke Kelas (Tujuan)</label>
                    <select id="promoKelasTujuan" class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 font-bold text-indigo-700">
                        <option value="">-- Pilih Tujuan --</option>
                        <option value="LULUS" class="font-bold text-green-600">LULUS (Alumni)</option>
                        </select>
                </div>

                <div class="flex items-end">
                    <button onclick="loadSiswaUntukPromosi()" class="w-full md:w-auto bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition shadow-md h-[38px]">
                        <i class="fas fa-users mr-2"></i> Load Siswa
                    </button>
                </div>
            </div>

            <div id="container-promo-siswa" class="hidden">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-bold text-sm text-gray-700">Daftar Siswa</h4>
                    <div class="text-xs space-x-2">
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-green-500 mr-1"></span> Naik/Lulus</span>
                        <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-1"></span> Tinggal Kelas</span>
                    </div>
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg max-h-[400px]">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-600 font-bold sticky top-0 z-10">
                            <tr>
                                <th class="p-3 w-10 text-center">No</th>
                                <th class="p-3">Nama Siswa</th>
                                <th class="p-3">NISN</th>
                                <th class="p-3 text-center">Status Keputusan</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-promo-siswa" class="divide-y divide-gray-100 bg-white">
                            </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800 mb-4 flex items-start gap-2">
                    <i class="fas fa-info-circle mt-0.5"></i>
                    <div>
                        <strong>Keterangan:</strong><br>
                        - Siswa dengan status <b>"Naik Kelas"</b> akan dipindahkan ke <b>Kelas Tujuan</b>.<br>
                        - Siswa dengan status <b>"Tinggal Kelas"</b> akan tetap berada di <b>Kelas Asal</b>.
                    </div>
                </div>

                <button onclick="executePromotion()" id="btn-eksekusi-promo" class="w-full bg-emerald-600 text-white py-3 rounded-xl font-bold hover:bg-emerald-700 transition shadow-lg text-sm">
                    <i class="fas fa-check-double mr-2"></i> Simpan & Proses Kenaikan Kelas
                </button>
            </div>
            
            <div id="promo-placeholder" class="text-center py-12 text-gray-400">
                <i class="fas fa-chalkboard-teacher text-4xl mb-3 opacity-30"></i>
                <p>Silakan pilih kelas asal dan tujuan untuk memulai.</p>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
@include('pages.scripts.kenaikan-kelas')
@endpush
