@extends('install.layout')

@section('title', 'Instalasi - Website')

@section('content')
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/30 px-5 py-4">
        <h2 class="text-base font-bold text-gray-800">Langkah 3: Pengaturan Website</h2>
        <p class="mt-1 text-sm text-gray-500">Isi informasi website. Saat lanjut, sistem akan reset database (drop semua tabel), lalu jalankan migrate dan seeder otomatis.</p>
    </div>

    <form action="{{ route('install.website.store') }}" method="POST" class="space-y-4 p-5">
        @csrf
        <div>
            <label for="website_nama" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Website</label>
            <input id="website_nama" name="website_nama" type="text" required value="{{ old('website_nama', $values['website_nama'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="website_slogan" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Slogan</label>
            <input id="website_slogan" name="website_slogan" type="text" value="{{ old('website_slogan', $values['website_slogan'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="website_email" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Email Website</label>
            <input id="website_email" name="website_email" type="email" value="{{ old('website_email', $values['website_email'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.database') }}" class="text-sm font-semibold text-gray-500 transition hover:text-indigo-600">Kembali ke database</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-700">
                Jalankan Instalasi
            </button>
        </div>
    </form>
</div>
@endsection
