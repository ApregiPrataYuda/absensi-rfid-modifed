@extends('layouts.page')

@section('title', 'Tabungan Saya')

@section('content')
@php
    $formatRupiah = static fn ($amount) => 'Rp ' . number_format((int) $amount, 0, ',', '.');
    $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $currentMonthLabel = ($monthNames[(int) now()->format('n')] ?? now()->format('m')) . ' ' . now()->format('Y');
@endphp

<div id="view-tabungan-saya" class="view-section active animate-fade-in space-y-4">
    @if (!$siswa)
        <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                    <i class="fas fa-link-slash text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-amber-900">Akun siswa belum tertaut</h3>
                    <p class="mt-1 text-sm text-amber-800">
                        Akun siswa belum tertaut ke data siswa berdasarkan NISN atau username. Hubungi admin untuk sinkronisasi data tabungan.
                    </p>
                </div>
            </div>
        </div>
    @else
        <section class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 text-white shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.14),transparent_28%),radial-gradient(circle_at_bottom_left,rgba(255,255,255,0.06),transparent_24%)]"></div>
            <div class="relative grid gap-5 p-5 md:p-7 xl:grid-cols-[1.2fr,0.8fr]">
                <div class="space-y-5">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-slate-100 backdrop-blur-sm">
                        <i class="fas fa-wallet text-[10px]"></i>
                        Ringkasan Tabungan Pribadi
                    </div>

                    <div>
                        <h3 class="text-2xl font-bold tracking-tight md:text-3xl">Tabungan Saya</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-200/85">
                            Lihat posisi saldo, jenis tabungan aktif, cetak rekening koran, dan pantau transaksi terbaru dari satu halaman yang lebih ringkas.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-200/60">Nama Siswa</div>
                            <div class="mt-2 text-sm font-semibold text-white">{{ $siswa->nama }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-200/60">Kelas</div>
                            <div class="mt-2 text-sm font-semibold text-white">{{ $siswa->kelas ?: '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                            <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-200/60">Periode Ringkasan</div>
                            <div class="mt-2 text-sm font-semibold text-white">{{ $currentMonthLabel }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[24px] border border-white/10 bg-white/10 p-5 backdrop-blur-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-200/65">Total Saldo Tersedia</div>
                            <div class="mt-3 text-3xl font-bold tracking-tight text-white md:text-4xl">{{ $formatRupiah($stats['saldo_total'] ?? 0) }}</div>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-white">
                            <i class="fas fa-piggy-bank text-lg"></i>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white/10 px-4 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-200/60">Jenis Tabungan</div>
                            <div class="mt-2 text-xl font-bold text-white">{{ number_format((int) ($stats['account_count'] ?? 0)) }}</div>
                        </div>
                        <div class="rounded-2xl bg-white/10 px-4 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-200/60">Riwayat Tampil</div>
                            <div class="mt-2 text-xl font-bold text-white">{{ number_format((int) $recentTransactions->count()) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="h-full min-h-[126px] rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Jenis Tabungan Aktif</div>
                        <div class="mt-3 text-2xl font-bold text-slate-900">{{ number_format((int) ($stats['account_count'] ?? 0)) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Jumlah rekening tabungan yang aktif untuk akun Anda.</div>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                        <i class="fas fa-layer-group text-sm"></i>
                    </div>
                </div>
            </div>

            <div class="h-full min-h-[126px] rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Setoran Bulan Ini</div>
                        <div class="mt-3 text-2xl font-bold text-emerald-900">{{ $formatRupiah($stats['setoran_bulan_ini'] ?? 0) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Akumulasi setoran dan penyesuaian masuk selama bulan berjalan.</div>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                        <i class="fas fa-arrow-down text-sm"></i>
                    </div>
                </div>
            </div>

            <div class="h-full min-h-[126px] rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Penarikan Bulan Ini</div>
                        <div class="mt-3 text-2xl font-bold text-rose-900">{{ $formatRupiah($stats['penarikan_bulan_ini'] ?? 0) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Akumulasi penarikan dan penyesuaian keluar selama bulan berjalan.</div>
                    </div>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                        <i class="fas fa-arrow-up text-sm"></i>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 items-stretch gap-4 xl:grid-cols-[0.95fr,1.35fr]">
            <section class="flex min-h-0 flex-col rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden xl:h-[640px]">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Rekening Tabungan</h4>
                            <p class="mt-1 text-xs text-slate-500">Cetak rekening koran dan pantau posisi saldo setiap jenis tabungan.</p>
                        </div>
                        <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600">
                            {{ number_format((int) $accounts->count()) }} rekening
                        </span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 space-y-2.5">
                    @forelse ($accounts as $account)
                        @php
                            $lastTransaction = $account->transactions->first();
                        @endphp
                        <article class="group rounded-2xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:shadow-sm">
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="min-w-0">
                                    <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        {{ $account->jenisTabungan?->kode ?: 'TAB' }}
                                    </div>
                                    <h5 class="mt-2.5 text-sm font-bold text-slate-900">{{ $account->jenisTabungan?->nama ?: '-' }}</h5>
                                    <div class="mt-1 font-mono text-[11px] text-slate-500">{{ $account->nomor_rekening ?: '-' }}</div>
                                </div>
                                <div class="rounded-xl bg-slate-100 px-2.5 py-1.5 text-right">
                                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">Saldo</div>
                                    <div class="mt-1 text-[13px] font-bold text-slate-900">{{ $formatRupiah($account->saldo_cached) }}</div>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-2.5 py-2.5">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Dibuka</div>
                                    <div class="mt-1 text-xs font-semibold text-slate-700">{{ $account->opened_at?->format('d M Y') ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Transaksi Terakhir</div>
                                    <div class="mt-1 text-xs font-semibold text-slate-700">
                                        {{ $lastTransaction?->transacted_at?->format('d M Y H:i') ?: 'Belum ada transaksi' }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    data-tabungan-statement-button="1"
                                    data-statement-url="{{ route('tabungan-siswa.rekening.statement', $account) }}"
                                    data-opened-year="{{ $account->opened_at?->format('Y') ?: now()->format('Y') }}"
                                    data-default-year="{{ $lastTransaction?->transacted_at?->format('Y') ?: now()->format('Y') }}"
                                    data-default-month="{{ $lastTransaction?->transacted_at?->format('n') ?: now()->format('n') }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <i class="fas fa-file-pdf text-[10px]"></i>
                                    Cetak Rekening Koran
                                </button>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Belum ada rekening tabungan yang tercatat untuk Anda.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="flex min-h-0 flex-col rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden xl:h-[640px]">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">Riwayat Transaksi Terbaru</h4>
                            <p class="mt-1 text-xs text-slate-500">Ringkasan mutasi terakhir pada seluruh rekening tabungan pribadi Anda.</p>
                        </div>
                        <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600">
                            {{ number_format((int) $recentTransactions->count()) }} transaksi
                        </span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 space-y-2.5">
                    @forelse ($recentTransactions as $transaction)
                        @php
                            $typeLabel = $transactionTypeOptions[$transaction->jenis_transaksi] ?? ucfirst(str_replace('_', ' ', (string) $transaction->jenis_transaksi));
                            $typeClass = match ($transaction->jenis_transaksi) {
                                \App\Models\TabunganSiswaTransaction::TYPE_SETORAN => 'bg-emerald-100 text-emerald-700',
                                \App\Models\TabunganSiswaTransaction::TYPE_PENARIKAN => 'bg-rose-100 text-rose-700',
                                \App\Models\TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK => 'bg-sky-100 text-sky-700',
                                default => 'bg-amber-100 text-amber-700',
                            };
                            $accentClass = match ($transaction->jenis_transaksi) {
                                \App\Models\TabunganSiswaTransaction::TYPE_SETORAN => 'bg-emerald-500',
                                \App\Models\TabunganSiswaTransaction::TYPE_PENARIKAN => 'bg-rose-500',
                                \App\Models\TabunganSiswaTransaction::TYPE_PENYESUAIAN_MASUK => 'bg-sky-500',
                                default => 'bg-amber-500',
                            };
                            $operatorName = trim((string) ($transaction->performedBy?->name ?: ($transaction->performedBy?->username ?? '-')));
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex gap-3">
                                <div class="hidden w-1 rounded-full {{ $accentClass }} sm:block"></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-2.5 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h5 class="text-sm font-bold text-slate-900">{{ $transaction->account?->jenisTabungan?->nama ?: '-' }}</h5>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ $typeClass }}">
                                                    {{ $typeLabel }}
                                                </span>
                                            </div>
                                            <div class="mt-2 grid gap-1.5 text-[11px] text-slate-500 md:grid-cols-2">
                                                <div>
                                                    <span class="font-semibold text-slate-600">Tanggal:</span>
                                                    {{ $transaction->transacted_at?->format('d M Y H:i') ?: '-' }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-600">No Bukti:</span>
                                                    {{ $transaction->nomor_bukti }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-600">Operator:</span>
                                                    {{ $operatorName }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-600">Saldo Sesudah:</span>
                                                    {{ $formatRupiah($transaction->saldo_sesudah) }}
                                                </div>
                                            </div>
                                            @if (!empty($transaction->keterangan))
                                                <div class="mt-2.5 rounded-xl border border-slate-100 bg-slate-50 px-2.5 py-2 text-[11px] leading-5 text-slate-600">
                                                    {{ $transaction->keterangan }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-between gap-3 lg:block lg:text-right">
                                            <div class="text-base font-bold {{ $transaction->signedAmount() >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                {{ $transaction->signedAmount() >= 0 ? '+' : '-' }}{{ $formatRupiah(abs($transaction->signedAmount())) }}
                                            </div>
                                            <a href="{{ route('tabungan-siswa.transaksi.print', $transaction) }}" target="_blank" rel="noopener noreferrer" class="mt-0 inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 lg:mt-2.5" title="Cetak bukti">
                                                <i class="fas fa-print text-[12px]"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            Belum ada riwayat transaksi tabungan.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function monthName(month) {
            const names = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            return names[Math.max(0, Math.min(11, Number(month || 1) - 1))] || 'Januari';
        }

        function currentPeriod() {
            const now = new Date();

            return {
                month: now.getMonth() + 1,
                year: now.getFullYear()
            };
        }

        function maxMonthForYear(year) {
            const current = currentPeriod();
            return Number(year) === current.year ? current.month : 12;
        }

        function clampMonth(month, year) {
            const normalizedMonth = Number(month || 1);
            const maxMonth = maxMonthForYear(year);

            if (normalizedMonth < 1) {
                return 1;
            }

            return Math.min(normalizedMonth, maxMonth);
        }

        function isFuturePeriod(month, year) {
            const current = currentPeriod();
            const normalizedMonth = Number(month || 0);
            const normalizedYear = Number(year || 0);

            return normalizedYear > current.year
                || (normalizedYear === current.year && normalizedMonth > current.month);
        }

        function yearOptions(openedYear, selectedYear) {
            const current = currentPeriod();
            const startYear = Math.min(Math.max(Number(openedYear || current.year), 2000), current.year);
            const safeSelectedYear = Math.min(Math.max(Number(selectedYear || current.year), startYear), current.year);
            const options = [];

            for (let year = current.year; year >= startYear; year -= 1) {
                const selected = year === safeSelectedYear ? 'selected' : '';
                options.push(`<option value="${year}" ${selected}>${year}</option>`);
            }

            return options.join('');
        }

        function monthOptions(selectedMonth, selectedYear) {
            const safeMonth = clampMonth(selectedMonth, selectedYear);
            const maxMonth = maxMonthForYear(selectedYear);
            const options = [];

            for (let month = 1; month <= maxMonth; month += 1) {
                const selected = month === safeMonth ? 'selected' : '';
                options.push(`<option value="${month}" ${selected}>${monthName(month)}</option>`);
            }

            return options.join('');
        }

        async function openStatementPicker(button) {
            const statementUrl = String(button?.dataset?.statementUrl || '').trim();
            if (!statementUrl) {
                return;
            }

            const current = currentPeriod();
            const openedYear = Number(button.dataset.openedYear || current.year);
            const defaultYear = Math.min(Math.max(Number(button.dataset.defaultYear || current.year), openedYear), current.year);
            const defaultMonth = clampMonth(Number(button.dataset.defaultMonth || current.month), defaultYear);

            if (window.Swal) {
                const result = await Swal.fire({
                    title: 'Pilih Periode Rekening Koran',
                    html: `
                        <div class="grid grid-cols-1 gap-3 text-left mt-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Bulan</label>
                                <select id="studentStatementMonthInput" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg p-2.5">
                                    ${monthOptions(defaultMonth, defaultYear)}
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Tahun</label>
                                <select id="studentStatementYearInput" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg p-2.5">
                                    ${yearOptions(openedYear, defaultYear)}
                                </select>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Cetak PDF',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626',
                    focusConfirm: false,
                    didOpen: () => {
                        const monthInput = document.getElementById('studentStatementMonthInput');
                        const yearInput = document.getElementById('studentStatementYearInput');

                        if (!monthInput || !yearInput) {
                            return;
                        }

                        const syncMonthOptions = () => {
                            const selectedYear = Number(yearInput.value || defaultYear);
                            const selectedMonth = clampMonth(Number(monthInput.value || defaultMonth), selectedYear);

                            monthInput.innerHTML = monthOptions(selectedMonth, selectedYear);
                            monthInput.value = String(selectedMonth);
                        };

                        yearInput.addEventListener('change', syncMonthOptions);
                        syncMonthOptions();
                    },
                    preConfirm: () => {
                        const month = Number(document.getElementById('studentStatementMonthInput')?.value || 0);
                        const year = Number(document.getElementById('studentStatementYearInput')?.value || 0);

                        if (month < 1 || month > 12 || year < 2000) {
                            Swal.showValidationMessage('Pilih bulan dan tahun yang valid.');
                            return false;
                        }

                        if (isFuturePeriod(month, year)) {
                            Swal.showValidationMessage('Bulan dan tahun yang belum lewat tidak bisa dipilih.');
                            return false;
                        }

                        return { month, year };
                    }
                });

                if (!result.isConfirmed || !result.value) {
                    return;
                }

                const { month, year } = result.value;
                window.open(`${statementUrl}?month=${encodeURIComponent(String(month))}&year=${encodeURIComponent(String(year))}`, '_blank', 'noopener');
                return;
            }

            const monthInput = window.prompt('Masukkan bulan rekening koran (1-12):', String(defaultMonth));
            if (monthInput === null) {
                return;
            }

            const yearInput = window.prompt('Masukkan tahun rekening koran:', String(defaultYear));
            if (yearInput === null) {
                return;
            }

            const month = Number(monthInput);
            const year = Number(yearInput);

            if (month < 1 || month > 12 || year < 2000 || isFuturePeriod(month, year)) {
                if (typeof window.showAlert === 'function') {
                    window.showAlert('error', 'Periode rekening koran tidak valid.');
                    return;
                }
                window.alert('Periode rekening koran tidak valid.');
                return;
            }

            window.open(`${statementUrl}?month=${encodeURIComponent(String(month))}&year=${encodeURIComponent(String(year))}`, '_blank', 'noopener');
        }

        document.querySelectorAll('[data-tabungan-statement-button]').forEach((button) => {
            button.addEventListener('click', () => {
                openStatementPicker(button);
            });
        });
    })();
</script>
@endpush
