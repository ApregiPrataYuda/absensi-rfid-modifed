@extends('install.layout')

@section('title', 'Instalasi - Database')

@section('content')
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/30 px-5 py-4">
        <h2 class="text-base font-bold text-gray-800">Langkah 2: Konfigurasi Database</h2>
        <p class="mt-1 text-sm text-gray-500">Isi koneksi database yang akan dipakai aplikasi.</p>
    </div>

    <form action="{{ route('install.database.store') }}" method="POST" class="space-y-4 p-5">
        @csrf
        <input type="hidden" name="db_port" value="3306">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="db_host" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">DB Host</label>
                <input id="db_host" name="db_host" type="text" required value="{{ old('db_host', $values['db_host'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="db_database" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">DB Name</label>
                <input id="db_database" name="db_database" type="text" required value="{{ old('db_database', $values['db_database'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="db_username" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">DB Username</label>
                <input id="db_username" name="db_username" type="text" required value="{{ old('db_username', $values['db_username'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="db_password" class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">DB Password</label>
                <input id="db_password" name="db_password" type="password" value="{{ old('db_password', $values['db_password'] ?? '') }}" class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.requirements') }}" class="text-sm font-semibold text-gray-500 transition hover:text-indigo-600">Kembali ke persyaratan</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-700">
                Simpan & Lanjut
            </button>
        </div>
    </form>
</div>
@endsection

