@extends('layouts.page')

@section('title', 'Mata Pelajaran Saya')

@section('content')
<div id="view-mata-pelajaran-saya" class="view-section active animate-fade-in space-y-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 md:p-5 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Mata Pelajaran Saya</h3>
            <p class="text-xs text-gray-500 mt-1">Halaman ini menampilkan jadwal mata pelajaran Anda berdasarkan kelas yang terdaftar.</p>
        </div>

        <div class="p-4 md:p-5">
            @if (!$siswa)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm">
                    Akun siswa belum tertaut ke data siswa (berdasarkan NISN/username). Hubungi admin untuk sinkronisasi data.
                </div>
            @else
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <div class="col-span-2 lg:col-span-1 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-indigo-600 font-bold">Siswa</div>
                        <div class="text-sm font-semibold text-indigo-900">{{ $siswa->nama }}</div>
                        <div class="text-xs text-indigo-700">NISN: {{ $siswa->nisn }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-emerald-600 font-bold">Kelas</div>
                        <div class="text-sm font-semibold text-emerald-900">{{ $siswa->kelas ?: '-' }}</div>
                        <div class="text-xs text-emerald-700">Total Sesi/Minggu: {{ $totalSesi }}</div>
                    </div>
                    <div class="rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-blue-600 font-bold">Ringkasan</div>
                        <div class="text-sm font-semibold text-blue-900">{{ $totalMapel }} Mata Pelajaran</div>
                        <div class="text-xs text-blue-700">Jadwal aktif semester berjalan</div>
                    </div>
                </div>

                @if ($totalSesi <= 0)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-gray-600 text-sm">
                        Jadwal pelajaran untuk kelas Anda belum tersedia.
                    </div>
                @else
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        @foreach ($jadwalByDay as $day => $items)
                            <section class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-blue-50 flex items-center justify-between gap-2">
                                    <h4 class="text-sm font-bold text-gray-800">{{ $dayOptions[(int) $day] ?? ('Hari ' . $day) }}</h4>
                                    <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full bg-indigo-600 text-white text-[11px] font-bold">
                                        {{ is_array($items) ? count($items) : 0 }} sesi
                                    </span>
                                </div>
                                <div class="p-3.5 md:p-4 space-y-3">
                                    @if (!is_array($items) || count($items) === 0)
                                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                            Tidak ada jadwal pada hari ini.
                                        </div>
                                    @else
                                        @foreach ($items as $row)
                                            <article
                                                class="mapel-session-card rounded-xl border border-gray-200 bg-gray-50/40 p-3 transition"
                                                data-hari="{{ (int) $day }}"
                                                data-jam-mulai="{{ $row['jam_mulai'] ?? '' }}"
                                                data-jam-selesai="{{ $row['jam_selesai'] ?? '' }}"
                                            >
                                                <div class="flex flex-col gap-2.5 sm:flex-row sm:items-start sm:gap-3">
                                                    <div class="mapel-time-pill self-start shrink-0 rounded-md bg-indigo-100 text-indigo-700 text-[11px] font-bold px-2 py-1 transition">
                                                        {{ $row['jam_mulai'] ?? '-' }} - {{ $row['jam_selesai'] ?? '-' }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <div class="text-sm font-semibold text-gray-800 leading-5">
                                                                {{ $row['mata_pelajaran'] ?? '-' }}
                                                            </div>
                                                            <span class="mapel-live-badge hidden inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
                                                                Sedang Berlangsung
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Guru: {{ $row['guru_nama'] ?? '-' }}
                                                        </div>
                                                        @if (!empty($row['ruang']))
                                                            <div class="text-xs text-gray-500">Ruang: {{ $row['ruang'] }}</div>
                                                        @endif
                                                        @if (!empty($row['keterangan']))
                                                            <div class="text-xs text-gray-500">Catatan: {{ $row['keterangan'] }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    @endif
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function toMinutes(value) {
            const raw = String(value || '').trim();
            const match = raw.match(/^(\d{1,2}):(\d{2})$/);
            if (!match) return null;

            const hour = Number(match[1]);
            const minute = Number(match[2]);
            if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null;
            if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return null;

            return (hour * 60) + minute;
        }

        function refreshCurrentSessionMarker() {
            const cards = document.querySelectorAll('#view-mata-pelajaran-saya .mapel-session-card');
            if (!cards.length) return;

            const now = new Date();
            const dayIso = now.getDay() === 0 ? 7 : now.getDay();
            const nowMinutes = (now.getHours() * 60) + now.getMinutes();

            cards.forEach((card) => {
                const cardDay = Number(card.getAttribute('data-hari') || 0);
                const startMinutes = toMinutes(card.getAttribute('data-jam-mulai'));
                const endMinutes = toMinutes(card.getAttribute('data-jam-selesai'));
                const isActive = cardDay === dayIso
                    && startMinutes !== null
                    && endMinutes !== null
                    && nowMinutes >= startMinutes
                    && nowMinutes <= endMinutes;

                card.classList.toggle('border-emerald-300', isActive);
                card.classList.toggle('bg-emerald-50/70', isActive);
                card.classList.toggle('ring-1', isActive);
                card.classList.toggle('ring-emerald-200', isActive);

                const timePill = card.querySelector('.mapel-time-pill');
                if (timePill) {
                    timePill.classList.toggle('bg-indigo-100', !isActive);
                    timePill.classList.toggle('text-indigo-700', !isActive);
                    timePill.classList.toggle('bg-emerald-100', isActive);
                    timePill.classList.toggle('text-emerald-700', isActive);
                }

                const badge = card.querySelector('.mapel-live-badge');
                if (badge) {
                    badge.classList.toggle('hidden', !isActive);
                }
            });
        }

        function initCurrentSessionMarker() {
            refreshCurrentSessionMarker();
            window.setInterval(refreshCurrentSessionMarker, 60000);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCurrentSessionMarker);
        } else {
            initCurrentSessionMarker();
        }
    })();
</script>
@endpush
