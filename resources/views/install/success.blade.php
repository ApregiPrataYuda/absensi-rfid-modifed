@extends('install.layout')

@section('title', 'Instalasi Berhasil')

@section('content')
<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
    <h2 class="text-base font-bold text-emerald-800">Langkah 4: Instalasi Berhasil</h2>
    <p class="mt-1 text-sm text-emerald-700">Migrate dan seeder selesai. Aplikasi <strong>{{ $websiteName }}</strong> sudah siap dipakai.</p>
</div>

<div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/30 px-5 py-4">
        <h3 class="text-base font-bold text-gray-800">Data Login Default</h3>
        <p class="mt-1 text-sm text-gray-500">Gunakan akun berikut untuk login pertama kali. Ganti password setelah berhasil masuk.</p>
    </div>

    <div class="overflow-x-auto px-5 py-4">
        <table class="min-w-full overflow-hidden rounded-lg border border-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2">Role</th>
                    <th class="px-3 py-2">Username</th>
                    <th class="px-3 py-2">Password</th>
                    <th class="px-3 py-2">Nama</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                @foreach ($accounts as $account)
                    <tr>
                        <td class="px-3 py-2">{{ $account['role'] }}</td>
                        <td class="px-3 py-2 font-mono">{{ $account['username'] }}</td>
                        <td class="px-3 py-2 font-mono">{{ $account['password'] }}</td>
                        <td class="px-3 py-2">{{ $account['name'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="px-5 pb-5 text-right">
        <a href="{{ $loginUrl }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-700">
            Ke Halaman Login
        </a>
    </div>
</div>
@endsection
