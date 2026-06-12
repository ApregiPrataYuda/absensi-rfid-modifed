@extends('install.layout')

@section('title', 'Instalasi - Persyaratan')

@section('content')
@php
    $requirementsPayload = is_array($requirements ?? null) ? $requirements : [];
    $requirementsOk = (bool) ($requirementsPayload['ok'] ?? false);
    $phpReq = (array) ($requirementsPayload['php'] ?? []);
    $phpOk = (bool) ($phpReq['ok'] ?? false);

    $missingRequirements = [];
    if (!$phpOk) {
        $missingRequirements[] = 'PHP ' . (string) ($phpReq['required'] ?? '8.2') . '+';
    }

    foreach ((array) ($requirementsPayload['extensions'] ?? []) as $ext) {
        if ((bool) ($ext['ok'] ?? false)) {
            continue;
        }
        $missingRequirements[] = (string) ($ext['label'] ?? $ext['key'] ?? 'Ekstensi');
    }

    foreach ((array) ($requirementsPayload['permissions'] ?? []) as $perm) {
        if ((bool) ($perm['ok'] ?? false)) {
            continue;
        }
        $missingRequirements[] = (string) ($perm['label'] ?? $perm['key'] ?? 'Izin');
    }

    $missingRequirements = array_values(array_unique(array_filter(array_map('trim', $missingRequirements))));
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-100 bg-gray-50/30 px-5 py-4">
        <h2 class="text-base font-bold text-gray-800">Langkah 1: Cek Persyaratan</h2>
        <p class="mt-1 text-sm text-gray-500">Pastikan server memenuhi persyaratan sebelum lanjut konfigurasi database.</p>
    </div>

    <div class="p-5 space-y-4">
        <div class="rounded-xl border {{ $requirementsOk ? 'border-emerald-200 bg-emerald-50/40' : 'border-red-200 bg-red-50/40' }} px-4 py-3">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center {{ $requirementsOk ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    <i class="fas {{ $requirementsOk ? 'fa-check' : 'fa-times' }}"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-extrabold text-gray-900">{{ $requirementsOk ? 'Persyaratan terpenuhi' : 'Persyaratan belum terpenuhi' }}</div>
                    @if ($requirementsOk)
                        <div class="mt-1 text-xs text-gray-600">Semua persyaratan sudah terpenuhi. Silakan lanjut ke konfigurasi database.</div>
                    @else
                        <div class="mt-1 text-xs text-gray-600">
                            <span class="font-bold text-red-700">Belum terpenuhi:</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($missingRequirements as $item)
                                <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-bold text-red-700">
                                    {{ $item }}
                                </span>
                            @endforeach
                            @if ($missingRequirements === [])
                                <span class="text-[11px] text-gray-600">Tidak diketahui (coba Refresh).</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">PHP</div>
                <div class="flex items-center gap-2 text-xs text-gray-700">
                    <i class="fas {{ $phpOk ? 'fa-check-circle text-emerald-600' : 'fa-times-circle text-red-600' }}"></i>
                    <span>Versi {{ (string) ($phpReq['current'] ?? '-') }} (min {{ (string) ($phpReq['required'] ?? '-') }})</span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Ekstensi</div>
                <div class="space-y-1">
                    @foreach ((array) ($requirementsPayload['extensions'] ?? []) as $ext)
                        @php
                            $ok = (bool) ($ext['ok'] ?? false);
                            $key = (string) ($ext['key'] ?? '');
                            $label = (string) ($ext['label'] ?? $key);
                            $detail = is_array($ext['detail'] ?? null) ? $ext['detail'] : null;
                        @endphp
                        <div class="flex items-start gap-2 text-xs text-gray-700">
                            <i class="fas mt-0.5 {{ $ok ? 'fa-check-circle text-emerald-600' : 'fa-times-circle text-red-600' }}"></i>
                            <div class="leading-5">
                                <div class="font-medium">{{ $label }}</div>
                                @if ($key === 'zip_or_phar' && $detail)
                                    <div class="text-[11px] text-gray-500">
                                        zip: {{ !empty($detail['zip']) ? 'ON' : 'OFF' }} • phar: {{ !empty($detail['phar']) ? 'ON' : 'OFF' }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Izin Folder</div>
                <div class="space-y-1">
                    @foreach ((array) ($requirementsPayload['permissions'] ?? []) as $perm)
                        @php
                            $ok = (bool) ($perm['ok'] ?? false);
                            $label = (string) ($perm['label'] ?? '-');
                        @endphp
                        <div class="flex items-center gap-2 text-xs text-gray-700">
                            <i class="fas {{ $ok ? 'fa-check-circle text-emerald-600' : 'fa-times-circle text-red-600' }}"></i>
                            <span class="font-medium">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('install.requirements') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </a>

            @if ($requirementsOk)
                <a href="{{ route('install.database') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-700">
                    Lanjut ke Database
                    <i class="fas fa-arrow-right"></i>
                </a>
            @else
                <button type="button" disabled class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white opacity-60 cursor-not-allowed">
                    Lanjut ke Database
                    <i class="fas fa-arrow-right"></i>
                </button>
            @endif
        </div>
    </div>
</div>
@endsection
