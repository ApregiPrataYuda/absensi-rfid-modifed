@extends('layouts.page')

@section('title', 'Presensi Saya')

@section('content')
<div id="view-presensi-saya" class="view-section active animate-fade-in space-y-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 md:p-5 border-b border-gray-100 bg-gray-50/30">
            <h3 class="font-bold text-sm text-gray-800">Presensi Saya</h3>
            <p class="text-xs text-gray-500 mt-1">Halaman ini menampilkan statistik dan riwayat presensi pribadi Anda.</p>
        </div>

        <div class="p-4 md:p-5">
            @if (!$siswa)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm">
                    Akun siswa belum tertaut ke data siswa (berdasarkan NISN/username). Hubungi admin untuk sinkronisasi data.
                </div>
            @else
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                    <div class="col-span-2 xl:col-span-1 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-indigo-600 font-bold">Periode</div>
                        <div class="text-sm font-semibold text-indigo-900">{{ $periodeLabel }}</div>
                        <div class="text-xs text-indigo-700">Total Data: {{ $stats['total'] }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-emerald-600 font-bold">Hadir</div>
                        <div class="text-sm font-semibold text-emerald-900">{{ $stats['hadir'] }}</div>
                        <div class="text-xs text-emerald-700">Kehadiran: {{ number_format((float) $stats['attendance_rate'], 1) }}%</div>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-amber-600 font-bold">Izin + Sakit</div>
                        <div class="text-sm font-semibold text-amber-900">{{ (int) $stats['izin'] + (int) $stats['sakit'] }}</div>
                        <div class="text-xs text-amber-700">Izin: {{ $stats['izin'] }} | Sakit: {{ $stats['sakit'] }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50/70 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wide text-rose-600 font-bold">Alfa / Belum Absen</div>
                        <div class="text-sm font-semibold text-rose-900">{{ (int) $stats['alfa'] + (int) $stats['belum_absen'] }}</div>
                        <div class="text-xs text-rose-700">Alfa: {{ $stats['alfa'] }} | Belum: {{ $stats['belum_absen'] }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <section class="xl:col-span-1 rounded-2xl border border-gray-200 bg-white p-4">
                        <h4 class="text-sm font-bold text-gray-800 mb-3">Distribusi Status ({{ $periodeLabel }})</h4>
                        @if (($stats['total'] ?? 0) > 0)
                            <div class="w-full max-w-[260px] mx-auto">
                                <canvas id="presensiStatusChart" height="220"></canvas>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                @foreach (($statusCounts ?? []) as $label => $count)
                                    <div class="rounded-md bg-gray-50 border border-gray-200 px-2 py-1.5">
                                        <div class="font-semibold text-gray-700">{{ $label }}</div>
                                        <div class="text-gray-500">{{ $count }} data</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                Belum ada data presensi pada periode ini.
                            </div>
                        @endif
                    </section>

                    <section class="xl:col-span-2 rounded-2xl border border-gray-200 bg-white overflow-hidden flex flex-col">
                        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/40">
                            <h4 class="text-sm font-bold text-gray-800">Riwayat Presensi Terbaru</h4>
                        </div>
                        <div class="p-4 space-y-3 overflow-y-auto overscroll-contain xl:max-h-[62vh]">
                            @if (($recentRows ?? collect())->isEmpty())
                                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                    Belum ada riwayat presensi.
                                </div>
                            @else
                                @foreach ($recentRows as $row)
                                    @php
                                        $statusValue = trim((string) ($row['status'] ?? ''));
                                        $statusLower = strtolower($statusValue);
                                        $statusClass = 'bg-gray-100 text-gray-700';
                                        if ($statusLower === 'hadir') {
                                            $statusClass = 'bg-emerald-100 text-emerald-700';
                                        } elseif ($statusLower === 'izin') {
                                            $statusClass = 'bg-blue-100 text-blue-700';
                                        } elseif ($statusLower === 'sakit') {
                                            $statusClass = 'bg-amber-100 text-amber-700';
                                        } elseif ($statusLower === 'alfa') {
                                            $statusClass = 'bg-rose-100 text-rose-700';
                                        } elseif ($statusLower === 'belum absen') {
                                            $statusClass = 'bg-slate-100 text-slate-700';
                                        }
                                    @endphp
                                    <article class="rounded-xl border border-gray-200 bg-gray-50/40 p-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="text-sm font-semibold text-gray-800">{{ $row['tanggal_label'] ?? '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    Datang: {{ $row['jam_datang'] !== '' ? $row['jam_datang'] : '-' }} |
                                                    Pulang: {{ $row['jam_pulang'] !== '' ? $row['jam_pulang'] : '-' }}
                                                </div>
                                                @if (!empty($row['keterangan']))
                                                    <div class="text-xs text-gray-500 mt-1">Keterangan: {{ $row['keterangan'] }}</div>
                                                @endif
                                            </div>
                                            <span class="inline-flex self-start items-center px-2 py-1 rounded-full text-[11px] font-bold {{ $statusClass }}">
                                                {{ $statusValue !== '' ? $statusValue : '-' }}
                                            </span>
                                        </div>
                                    </article>
                                @endforeach
                            @endif
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const canvas = document.getElementById('presensiStatusChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const labels = @json(array_keys($statusCounts ?? []));
        const values = @json(array_values($statusCounts ?? []));
        if (!Array.isArray(labels) || !Array.isArray(values) || labels.length === 0) {
            return;
        }

        const colorMap = {
            'hadir': '#10b981',
            'izin': '#3b82f6',
            'sakit': '#f59e0b',
            'alfa': '#ef4444',
            'belum absen': '#64748b'
        };

        const colors = labels.map((label) => {
            const key = String(label || '').trim().toLowerCase();
            return colorMap[key] || '#94a3b8';
        });

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    })();
</script>
@endpush
