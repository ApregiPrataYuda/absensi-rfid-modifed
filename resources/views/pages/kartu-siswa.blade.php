@extends('layouts.page')

@section('title', 'Kartu Pelajar Digital')

@section('content')
<div id="view-kartu-siswa" class="view-section active animate-fade-in">
    <div id="kartuSiswaContainer"></div>
</div>
@endsection

@push('scripts')
@include('pages.scripts.kartu-siswa')
@endpush

@php
    $nik = request()->query('nisn');
    $nama = request()->query('nama');
    $kelas = request()->query('kelas');
@endphp

@if($nik)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof loadQRCodeSiswa === 'function') {
                    loadQRCodeSiswa(@json($nik), @json($nama), @json($kelas));
                }
            });
        </script>
    @endpush
@endif
