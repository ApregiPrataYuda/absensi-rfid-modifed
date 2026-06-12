@extends('layouts.main')

@section('body')
<div class="min-h-screen bg-[#F3F4F6]">
    <main class="mx-auto max-w-4xl px-4 py-6 md:py-10">
        <div class="view-section active animate-fade-in">
            <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-indigo-100 bg-gradient-to-r from-indigo-50 to-blue-50 px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-indigo-500">Setup Wizard</p>
                    <h1 class="mt-1 text-xl font-bold text-gray-800 md:text-2xl">Instalasi Aplikasi Absensindo</h1>
                    <p class="mt-2 text-sm text-gray-500">Ikuti langkah berurutan: cek persyaratan, konfigurasi database, konfigurasi website, lalu jalankan instalasi.</p>
                </div>

                <div class="px-5 py-4">
                    <div class="grid grid-cols-2 gap-2 text-[11px] font-bold sm:grid-cols-4 md:text-xs">
                        @php
                            $step = (int) ($step ?? 1);
                        @endphp
                        <div class="rounded-lg border px-3 py-2 text-center {{ $step >= 1 ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                            1. Persyaratan
                        </div>
                        <div class="rounded-lg border px-3 py-2 text-center {{ $step >= 2 ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                            2. Database
                        </div>
                        <div class="rounded-lg border px-3 py-2 text-center {{ $step >= 3 ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                            3. Website
                        </div>
                        <div class="rounded-lg border px-3 py-2 text-center {{ $step >= 4 ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                            4. Selesai
                        </div>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
@endsection
