<script>
    (function () {
        const state = {
            currentPage: '',
            types: [],
            accounts: [],
            transactions: [],
            historyTransactions: [],
            historyAccount: null,
            historySummary: {},
            students: [],
            classes: [],
            accountOptions: [],
            transactionTypeOptions: {
                setoran: 'Setoran',
                penarikan: 'Penarikan',
                penyesuaian_masuk: 'Penyesuaian Masuk',
                penyesuaian_keluar: 'Penyesuaian Keluar'
            },
            canManageTypes: false,
            canManageAccounts: false,
            canManageTransactions: false,
            filters: {
                rekening: {
                    kelas: '',
                    jenis_tabungan_id: '',
                    status: '',
                    q: ''
                },
                transaksi: {
                    kelas: '',
                    siswa_id: '',
                    jenis_tabungan_id: '',
                    jenis_transaksi: '',
                    tanggal_dari: '',
                    tanggal_sampai: '',
                    q: ''
                },
                history: {
                    year: '',
                    month: '',
                    nomor_bukti: ''
                }
            }
        };

        const csrfToken = '{{ csrf_token() }}';
        let rekeningFilterTimer = null;
        let historyFilterTimer = null;

        function getRoute(name, fallback = '') {
            return String(window.APP_ROUTES?.[name] || fallback || '').trim();
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showAlert(type, message) {
            if (typeof window.showAlert === 'function') {
                window.showAlert(type, message);
                return;
            }
            if (type === 'error') {
                console.error(message);
                return;
            }
            console.log(message);
        }

        function formatRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        }

        function formatNumber(value) {
            return Number(value || 0).toLocaleString('id-ID');
        }

        function digitsOnly(value) {
            return String(value ?? '').replace(/\D+/g, '');
        }

        function formatThousandsInputValue(value) {
            const digits = digitsOnly(value);
            if (!digits) {
                return '';
            }

            return Number(digits).toLocaleString('id-ID');
        }

        function parseFormattedInteger(value) {
            const digits = digitsOnly(value);
            return digits ? Number(digits) : 0;
        }

        function monthName(month) {
            const names = [
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            return names[Math.max(0, Math.min(11, Number(month || 1) - 1))] || 'Januari';
        }

        function currentStatementPeriod() {
            const now = new Date();

            return {
                month: now.getMonth() + 1,
                year: now.getFullYear()
            };
        }

        function maxStatementMonthForYear(year) {
            const currentPeriod = currentStatementPeriod();
            return Number(year) === currentPeriod.year ? currentPeriod.month : 12;
        }

        function clampStatementMonth(month, year) {
            const normalizedMonth = Number(month || 1);
            const maxMonth = maxStatementMonthForYear(year);

            if (normalizedMonth < 1) {
                return 1;
            }

            return Math.min(normalizedMonth, maxMonth);
        }

        function isFutureStatementPeriod(month, year) {
            const currentPeriod = currentStatementPeriod();
            const normalizedMonth = Number(month || 0);
            const normalizedYear = Number(year || 0);

            return normalizedYear > currentPeriod.year
                || (normalizedYear === currentPeriod.year && normalizedMonth > currentPeriod.month);
        }

        function localIsoPart(date = new Date()) {
            const instance = date instanceof Date ? date : new Date(date);
            const local = new Date(instance.getTime() - (instance.getTimezoneOffset() * 60000));

            return local.toISOString();
        }

        function todayLocalValue() {
            return localIsoPart().slice(0, 10);
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = String(value ?? '');
            }
        }

        function buildUrl(base, params = {}) {
            const url = new URL(base, window.location.origin);
            Object.entries(params || {}).forEach(([key, value]) => {
                if (value !== null && value !== undefined && String(value).trim() !== '') {
                    url.searchParams.set(key, String(value));
                }
            });

            return url.pathname + (url.search ? url.search : '');
        }

        async function apiRequest(url, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {})
            };

            if (method !== 'GET') {
                headers['Content-Type'] = headers['Content-Type'] || 'application/json';
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                ...options,
                headers
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = payload?.errors ? Object.values(payload.errors)?.[0]?.[0] : null;
                throw new Error(firstError || payload.message || 'Permintaan gagal diproses.');
            }

            return payload;
        }

        function setLoadingRow(targetId, colspan, text) {
            const tbody = document.getElementById(targetId);
            if (!tbody) return;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="p-8 text-center text-gray-400">${escapeHtml(text)}</td></tr>`;
        }

        function getModalShell(create = false) {
            const container = document.getElementById('modalContainer');
            if (!container) return null;

            let shell = container.querySelector('[data-modal-shell]');
            if (!shell && create) {
                container.innerHTML = `
                    <div data-modal-shell class="fixed inset-0 z-50 hidden items-center justify-center p-4">
                        <div class="absolute inset-0 bg-gray-900/45 transition-opacity" onclick="closeModal()"></div>
                        <div data-modal-host class="relative w-full max-w-5xl"></div>
                    </div>
                `;
                shell = container.querySelector('[data-modal-shell]');
            }

            return shell;
        }

        function showModal(content, maxWidthClass = 'max-w-4xl') {
            const shell = getModalShell(true);
            if (!shell) return;

            const host = shell.querySelector('[data-modal-host]');
            if (!host) return;

            host.className = `relative w-full ${maxWidthClass}`;
            host.innerHTML = content;
            shell.classList.remove('hidden');
            shell.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            const shell = getModalShell(false);
            if (!shell) return;

            const host = shell.querySelector('[data-modal-host]');
            if (host) {
                host.innerHTML = '';
            }

            shell.classList.add('hidden');
            shell.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function bindFormattedNumberInputs(scope = document) {
            if (!scope || typeof scope.querySelectorAll !== 'function') {
                return;
            }

            scope.querySelectorAll('[data-number-format="thousands"]').forEach((input) => {
                if (input.dataset.boundThousands === '1') {
                    input.value = formatThousandsInputValue(input.value);
                    return;
                }

                const applyFormatting = () => {
                    input.value = formatThousandsInputValue(input.value);
                };

                input.addEventListener('input', applyFormatting);
                input.addEventListener('blur', applyFormatting);
                input.addEventListener('paste', () => {
                    window.setTimeout(applyFormatting, 0);
                });

                input.dataset.boundThousands = '1';
                applyFormatting();
            });
        }

        async function confirmAction(message, confirmText = 'Lanjutkan') {
            if (window.Swal) {
                const res = await Swal.fire({
                    icon: 'warning',
                    title: 'Konfirmasi',
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#2563eb'
                });

                return Boolean(res.isConfirmed);
            }

            return window.confirm(message);
        }

        async function askDeleteReason() {
            if (window.Swal) {
                const res = await Swal.fire({
                    icon: 'warning',
                    title: 'Alasan hapus transaksi',
                    input: 'text',
                    inputPlaceholder: 'Tulis alasan penghapusan...',
                    inputValidator: (value) => {
                        if (!String(value || '').trim()) {
                            return 'Alasan hapus wajib diisi.';
                        }
                        return null;
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626'
                });

                if (!res.isConfirmed) {
                    return null;
                }

                return String(res.value || '').trim();
            }

            const reason = window.prompt('Alasan menghapus transaksi ini?');
            if (reason === null) {
                return null;
            }

            const trimmed = String(reason || '').trim();
            if (!trimmed) {
                showAlert('error', 'Alasan hapus wajib diisi.');
                return null;
            }

            return trimmed;
        }

        function detectCurrentPage() {
            if (document.getElementById('view-tabungan-jenis')) {
                return 'jenis';
            }
            if (document.getElementById('view-tabungan-rekening')) {
                return 'rekening';
            }

            return '';
        }

        function getTypeById(id) {
            return (state.types || []).find((row) => Number(row.id) === Number(id)) || null;
        }

        function getAccountById(id) {
            return (state.accounts || []).find((row) => Number(row.id) === Number(id)) || null;
        }

        function getTransactionById(id) {
            return (state.transactions || []).find((row) => Number(row.id) === Number(id))
                || (state.historyTransactions || []).find((row) => Number(row.id) === Number(id))
                || null;
        }

        function currentAccountOptions() {
            if (Array.isArray(state.accountOptions) && state.accountOptions.length > 0) {
                return state.accountOptions;
            }

            return (state.accounts || []).map((row) => ({
                id: Number(row.id || 0),
                nomor_rekening: String(row.nomor_rekening || ''),
                siswa_id: Number(row.siswa_id || 0),
                siswa_nama: String(row.siswa_nama || '-'),
                siswa_nisn: String(row.siswa_nisn || '-'),
                kelas: String(row.kelas || '-'),
                jenis_tabungan_id: Number(row.jenis_tabungan_id || 0),
                jenis_tabungan: String(row.jenis_tabungan || '-'),
                is_active: Boolean(row.is_active),
                label: `${row.nomor_rekening || '-'} - ${row.siswa_nama || '-'} - ${row.jenis_tabungan || '-'}`
            }));
        }

        function statementYearOptions(selectedYear) {
            const years = new Set();
            const currentYear = currentStatementPeriod().year;
            years.add(currentYear);

            if (state.historyAccount?.opened_at) {
                const openedYear = Number(String(state.historyAccount.opened_at).slice(0, 4));
                if (openedYear > 0) {
                    const start = Math.min(openedYear, currentYear);
                    const end = Math.max(openedYear, currentYear);
                    for (let year = start; year <= end; year += 1) {
                        if (year <= currentYear) {
                            years.add(year);
                        }
                    }
                }
            }

            (state.historyTransactions || []).forEach((row) => {
                const year = Number(String(row.transacted_at || '').slice(0, 4));
                if (year > 0 && year <= currentYear) {
                    years.add(year);
                }
            });

            const sorted = Array.from(years).sort((a, b) => b - a);
            return sorted.map((year) => {
                const selected = Number(selectedYear || currentYear) === Number(year) ? 'selected' : '';
                return `<option value="${year}" ${selected}>${year}</option>`;
            }).join('');
        }

        function statementMonthOptions(selectedMonth, selectedYear) {
            const normalizedYear = Number(selectedYear || currentStatementPeriod().year);
            const safeMonth = clampStatementMonth(selectedMonth, normalizedYear);
            const maxMonth = maxStatementMonthForYear(normalizedYear);

            return Array.from({ length: maxMonth }, (_, index) => {
                const month = index + 1;
                const selected = month === safeMonth ? 'selected' : '';
                return `<option value="${month}" ${selected}>${monthName(month)}</option>`;
            }).join('');
        }

        async function showStatementPicker(accountId) {
            const defaultDateSource = state.historyTransactions?.length > 0
                ? String(state.historyTransactions[0]?.transacted_at || '')
                : '';
            const currentPeriod = currentStatementPeriod();
            const defaultYear = Number(defaultDateSource.slice(0, 4) || currentPeriod.year);
            const defaultMonth = clampStatementMonth(
                Number(defaultDateSource.slice(5, 7) || currentPeriod.month),
                defaultYear
            );
            const statementUrl = getRoute('tabunganSiswaRekeningStatement').replace('__ID__', encodeURIComponent(String(accountId)));

            if (window.Swal) {
                const result = await Swal.fire({
                    title: 'Pilih Periode Rekening Koran',
                    html: `
                        <div class="grid grid-cols-1 gap-3 text-left mt-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Bulan</label>
                                <select id="statementMonthInput" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg p-2.5">
                                    ${statementMonthOptions(defaultMonth, defaultYear)}
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Tahun</label>
                                <select id="statementYearInput" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg p-2.5">
                                    ${statementYearOptions(defaultYear)}
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
                        const monthInput = document.getElementById('statementMonthInput');
                        const yearInput = document.getElementById('statementYearInput');

                        if (!monthInput || !yearInput) {
                            return;
                        }

                        const syncStatementMonthOptions = () => {
                            const selectedYear = Number(yearInput.value || currentPeriod.year);
                            const selectedMonth = clampStatementMonth(
                                Number(monthInput.value || defaultMonth),
                                selectedYear
                            );

                            monthInput.innerHTML = statementMonthOptions(selectedMonth, selectedYear);
                            monthInput.value = String(selectedMonth);
                        };

                        yearInput.addEventListener('change', syncStatementMonthOptions);
                        syncStatementMonthOptions();
                    },
                    preConfirm: () => {
                        const month = Number(document.getElementById('statementMonthInput')?.value || 0);
                        const year = Number(document.getElementById('statementYearInput')?.value || 0);

                        if (month < 1 || month > 12 || year < 2000) {
                            Swal.showValidationMessage('Pilih bulan dan tahun yang valid.');
                            return false;
                        }

                        if (isFutureStatementPeriod(month, year)) {
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
                const url = `${statementUrl}?month=${encodeURIComponent(String(month))}&year=${encodeURIComponent(String(year))}`;
                window.open(url, '_blank', 'noopener');
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
            if (month < 1 || month > 12 || year < 2000) {
                showAlert('error', 'Periode rekening koran tidak valid.');
                return;
            }

            if (isFutureStatementPeriod(month, year)) {
                showAlert('error', 'Bulan dan tahun yang belum lewat tidak bisa dipilih.');
                return;
            }

            window.open(`${statementUrl}?month=${encodeURIComponent(String(month))}&year=${encodeURIComponent(String(year))}`, '_blank', 'noopener');
        }

        function populateSelectOptions(elementId, rows, placeholder, currentValue, buildOption) {
            const select = document.getElementById(elementId);
            if (!select) return;

            const options = [`<option value="">${escapeHtml(placeholder)}</option>`].concat(
                (rows || []).map((row) => buildOption(row))
            );
            select.innerHTML = options.join('');
            select.value = currentValue || '';
        }

        function renderJenisSummary(summary = {}) {
            setText('tabunganJenisCount', formatNumber(summary.type_count || 0));
            setText('tabunganJenisActiveCount', formatNumber(summary.active_count || 0));
            setText('tabunganJenisAccountCount', formatNumber(summary.account_count || 0));
        }

        function renderJenisTable() {
            const tbody = document.getElementById('tbody-tabungan-jenis');
            if (!tbody) return;

            if (!Array.isArray(state.types) || state.types.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-400">Belum ada jenis tabungan.</td></tr>';
                return;
            }

            tbody.innerHTML = state.types.map((row, index) => {
                const statusBadge = row.is_active
                    ? '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">Aktif</span>'
                    : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-[10px] font-bold">Nonaktif</span>';
                const actions = state.canManageTypes
                    ? `
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="showTabunganJenisModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="deleteTabunganJenis(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `
                    : '<span class="text-gray-400 text-[11px]">Read only</span>';

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${index + 1}</td>
                        <td class="p-3 font-semibold text-gray-800">${escapeHtml(row.kode || '-')}</td>
                        <td class="p-3 font-semibold text-gray-800">${escapeHtml(row.nama || '-')}</td>
                        <td class="p-3 text-gray-600">${escapeHtml(row.deskripsi || '-')}</td>
                        <td class="p-3 text-center">${statusBadge}</td>
                        <td class="p-3 text-center">${formatNumber(row.accounts_count || 0)}</td>
                        <td class="p-3 text-center">${actions}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderRekeningSummary(summary = {}) {
            setText('tabunganRekeningCount', formatNumber(summary.account_count || 0));
            setText('tabunganRekeningActiveCount', formatNumber(summary.active_count || 0));
            setText('tabunganRekeningStudentCount', formatNumber(summary.student_count || 0));
            setText('tabunganRekeningSaldoTotal', formatRupiah(summary.saldo_total || 0));
        }

        function renderRekeningFilters() {
            populateSelectOptions(
                'filterTabunganRekeningKelas',
                state.classes,
                'Semua Kelas',
                state.filters.rekening.kelas,
                (row) => `<option value="${escapeHtml(row.nama)}">${escapeHtml(row.nama)}</option>`
            );
            populateSelectOptions(
                'filterTabunganRekeningJenis',
                state.types,
                'Semua Jenis',
                state.filters.rekening.jenis_tabungan_id,
                (row) => `<option value="${Number(row.id)}">${escapeHtml(row.nama)} (${escapeHtml(row.kode)})</option>`
            );

            const statusSelect = document.getElementById('filterTabunganRekeningStatus');
            if (statusSelect) {
                statusSelect.value = state.filters.rekening.status || '';
            }

            const keywordInput = document.getElementById('filterTabunganRekeningKeyword');
            if (keywordInput) {
                keywordInput.value = state.filters.rekening.q || '';
            }
        }

        function renderRekeningTable() {
            const tbody = document.getElementById('tbody-tabungan-rekening');
            if (!tbody) return;

            if (!Array.isArray(state.accounts) || state.accounts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="p-8 text-center text-gray-400">Belum ada rekening tabungan.</td></tr>';
                return;
            }

            tbody.innerHTML = state.accounts.map((row, index) => {
                const statusBadge = row.is_active
                    ? '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">Aktif</span>'
                    : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-[10px] font-bold">Nonaktif</span>';
                const actions = [];

                actions.push(`
                    <button onclick="showTabunganRekeningHistoryModal(${Number(row.id)})" class="p-2 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-100 transition" title="Riwayat transaksi">
                        <i class="fas fa-clock-rotate-left"></i>
                    </button>
                `);

                if (state.canManageAccounts) {
                    actions.push(`
                        <button onclick="showTabunganRekeningModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit rekening">
                            <i class="fas fa-pen"></i>
                        </button>
                    `);
                    if (row.can_delete) {
                        actions.push(`
                            <button onclick="deleteTabunganRekening(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus rekening">
                                <i class="fas fa-trash"></i>
                            </button>
                        `);
                    } else {
                        actions.push(`
                            <span class="inline-flex items-center justify-center p-2 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed" title="Rekening dengan saldo atau histori transaksi tidak bisa dihapus">
                                <i class="fas fa-trash"></i>
                            </span>
                        `);
                    }
                }

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${index + 1}</td>
                        <td class="p-3">
                            <div class="font-mono text-[11px] font-semibold text-gray-700">${escapeHtml(row.nomor_rekening || '-')}</div>
                        </td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.siswa_nama || '-')}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.siswa_nisn || '-')}</div>
                        </td>
                        <td class="p-3 text-gray-700">${escapeHtml(row.kelas || '-')}</td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.jenis_tabungan || '-')}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.jenis_tabungan_kode || '-')}</div>
                        </td>
                        <td class="p-3 text-right font-bold text-emerald-700">${formatRupiah(row.saldo_cached || 0)}</td>
                        <td class="p-3 text-center">
                            <div class="font-semibold text-gray-800">${formatNumber(row.transactions_count || 0)}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.latest_transaction_at || 'Belum ada')}</div>
                        </td>
                        <td class="p-3 text-center">${statusBadge}</td>
                        <td class="p-3 text-gray-600">${escapeHtml(row.opened_at_label || '-')}</td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">${actions.join('')}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderTransaksiSummary(summary = {}) {
            setText('tabunganTransaksiCount', formatNumber(summary.transaction_count || 0));
            setText('tabunganTransaksiSetoranTotal', formatRupiah(summary.setoran_total || 0));
            setText('tabunganTransaksiPenarikanTotal', formatRupiah(summary.penarikan_total || 0));
            setText('tabunganTransaksiMutasiBersih', formatRupiah(summary.mutasi_bersih || 0));
        }

        function renderTransaksiFilters() {
            populateSelectOptions(
                'filterTabunganTransaksiKelas',
                state.classes,
                'Semua Kelas',
                state.filters.transaksi.kelas,
                (row) => `<option value="${escapeHtml(row.nama)}">${escapeHtml(row.nama)}</option>`
            );
            populateSelectOptions(
                'filterTabunganTransaksiSiswa',
                state.students,
                'Semua Siswa',
                state.filters.transaksi.siswa_id,
                (row) => `<option value="${Number(row.id)}">${escapeHtml(row.label)}</option>`
            );
            populateSelectOptions(
                'filterTabunganTransaksiJenis',
                state.types,
                'Semua Jenis',
                state.filters.transaksi.jenis_tabungan_id,
                (row) => `<option value="${Number(row.id)}">${escapeHtml(row.nama)} (${escapeHtml(row.kode)})</option>`
            );
            populateSelectOptions(
                'filterTabunganTransaksiTipe',
                Object.entries(state.transactionTypeOptions || {}).map(([key, label]) => ({ key, label })),
                'Semua Transaksi',
                state.filters.transaksi.jenis_transaksi,
                (row) => `<option value="${escapeHtml(row.key)}">${escapeHtml(row.label)}</option>`
            );

            const startInput = document.getElementById('filterTabunganTransaksiTanggalDari');
            const endInput = document.getElementById('filterTabunganTransaksiTanggalSampai');
            const keywordInput = document.getElementById('filterTabunganTransaksiKeyword');

            if (startInput) startInput.value = state.filters.transaksi.tanggal_dari || '';
            if (endInput) endInput.value = state.filters.transaksi.tanggal_sampai || '';
            if (keywordInput) keywordInput.value = state.filters.transaksi.q || '';
        }

        function transactionTypeBadge(row) {
            const type = String(row.jenis_transaksi || '');
            if (type === 'setoran') return 'bg-emerald-100 text-emerald-700';
            if (type === 'penarikan') return 'bg-rose-100 text-rose-700';
            if (type === 'penyesuaian_masuk') return 'bg-sky-100 text-sky-700';
            return 'bg-amber-100 text-amber-700';
        }

        function renderTransactionRows(rows, options = {}) {
            const historyAccountId = Number(options?.historyAccountId || 0);
            const hideAccountNumber = Boolean(options?.hideAccountNumber);
            const hideStudent = Boolean(options?.hideStudent);
            const hideSavingsType = Boolean(options?.hideSavingsType);

            return (rows || []).map((row, index) => {
                const actions = [
                    `
                        <button onclick="window.open('${escapeHtml(row.print_url || getRoute('tabunganSiswaTransaksiPrint').replace('__ID__', encodeURIComponent(String(row.id))))}', '_blank', 'noopener')" class="p-2 bg-sky-50 text-sky-600 rounded-lg hover:bg-sky-100 transition" title="Cetak bukti">
                            <i class="fas fa-print"></i>
                        </button>
                    `
                ];

                if (state.canManageTransactions) {
                    const modalArg = historyAccountId ? `, { returnHistoryAccountId: ${Number(historyAccountId)} }` : '';
                    const deleteArg = historyAccountId ? `, ${Number(historyAccountId)}` : '';
                    actions.push(`
                        <button onclick="showTabunganTransactionModal(${Number(row.id)}${modalArg})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit transaksi">
                            <i class="fas fa-pen"></i>
                        </button>
                    `);
                    actions.push(`
                        <button onclick="deleteTabunganTransaction(${Number(row.id)}${deleteArg})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus transaksi">
                            <i class="fas fa-trash"></i>
                        </button>
                    `);
                }

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${index + 1}</td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.transacted_at_label || '-')}</div>
                        </td>
                        <td class="p-3 font-mono text-[11px] text-gray-600">${escapeHtml(row.nomor_bukti || '-')}</td>
                        ${hideAccountNumber ? '' : `
                            <td class="p-3">
                                <div class="font-mono text-[11px] font-semibold text-gray-700">${escapeHtml(row.nomor_rekening || '-')}</div>
                            </td>
                        `}
                        ${hideStudent ? '' : `
                            <td class="p-3">
                                <div class="font-semibold text-gray-800">${escapeHtml(row.siswa_nama || '-')}</div>
                                <div class="text-[11px] text-gray-500">${escapeHtml(row.kelas || '-')} | ${escapeHtml(row.siswa_nisn || '-')}</div>
                            </td>
                        `}
                        ${hideSavingsType ? '' : `
                            <td class="p-3">
                                <div class="font-semibold text-gray-800">${escapeHtml(row.jenis_tabungan || '-')}</div>
                                <div class="text-[11px] text-gray-500">${escapeHtml(row.jenis_tabungan_kode || '-')}</div>
                            </td>
                        `}
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-[10px] font-bold ${transactionTypeBadge(row)}">${escapeHtml(row.jenis_transaksi_label || '-')}</span>
                            ${row.keterangan ? `<div class="text-[11px] text-gray-500 mt-1">${escapeHtml(row.keterangan)}</div>` : ''}
                        </td>
                        <td class="p-3 text-right font-bold ${Number(row.signed_nominal || 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'}">
                            ${Number(row.signed_nominal || 0) >= 0 ? '+' : '-'}${formatRupiah(Math.abs(Number(row.signed_nominal || 0)))}
                        </td>
                        <td class="p-3 text-right font-semibold text-gray-800">${formatRupiah(row.saldo_sesudah || 0)}</td>
                        <td class="p-3 text-gray-600">
                            <div>${escapeHtml(row.performed_by || '-')}</div>
                            ${row.updated_by ? `<div class="text-[11px] text-gray-400 mt-1">Edit: ${escapeHtml(row.updated_by)}</div>` : ''}
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">${actions.join('')}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function renderTransaksiTable() {
            const tbody = document.getElementById('tbody-tabungan-transaksi');
            if (!tbody) return;

            if (!Array.isArray(state.transactions) || state.transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="p-8 text-center text-gray-400">Belum ada transaksi tabungan.</td></tr>';
                return;
            }

            tbody.innerHTML = renderTransactionRows(state.transactions);
        }

        function defaultHistoryFilters() {
            return {
                year: '',
                month: '',
                nomor_bukti: ''
            };
        }

        function historyYearOptions(selectedYear = '') {
            const years = new Set();

            (state.historyTransactions || []).forEach((row) => {
                const year = Number(String(row.transacted_at || '').slice(0, 4));
                if (year > 0) {
                    years.add(year);
                }
            });

            if (years.size === 0) {
                const openedYear = Number(String(state.historyAccount?.opened_at || '').slice(0, 4));
                if (openedYear > 0) {
                    years.add(openedYear);
                }
            }

            const sorted = Array.from(years).sort((a, b) => b - a);
            const options = ['<option value="">Semua Tahun</option>'].concat(
                sorted.map((year) => {
                    const selected = String(selectedYear || '') === String(year) ? 'selected' : '';
                    return `<option value="${year}" ${selected}>${year}</option>`;
                })
            );

            return options.join('');
        }

        function historyMonthOptions(selectedMonth = '') {
            const options = ['<option value="">Semua Bulan</option>'];

            for (let month = 1; month <= 12; month += 1) {
                const selected = String(Number(selectedMonth || 0)) === String(month) ? 'selected' : '';
                options.push(`<option value="${month}" ${selected}>${monthName(month)}</option>`);
            }

            return options.join('');
        }

        function filteredHistoryTransactions() {
            const yearFilter = String(state.filters.history.year || '').trim();
            const monthFilter = String(state.filters.history.month || '').trim();
            const buktiFilter = String(state.filters.history.nomor_bukti || '').trim().toLowerCase();

            return (state.historyTransactions || []).filter((row) => {
                const transactedAt = String(row.transacted_at || '');
                const rowYear = String(transactedAt.slice(0, 4));
                const rowMonth = String(Number(transactedAt.slice(5, 7) || 0));
                const nomorBukti = String(row.nomor_bukti || '').toLowerCase();

                if (yearFilter !== '' && rowYear !== yearFilter) {
                    return false;
                }

                if (monthFilter !== '' && rowMonth !== String(Number(monthFilter))) {
                    return false;
                }

                if (buktiFilter !== '' && !nomorBukti.includes(buktiFilter)) {
                    return false;
                }

                return true;
            });
        }

        function renderHistoryFilters() {
            const yearSelect = document.getElementById('filterTabunganHistoryYear');
            const monthSelect = document.getElementById('filterTabunganHistoryMonth');
            const buktiInput = document.getElementById('filterTabunganHistoryNomorBukti');

            if (yearSelect) {
                yearSelect.innerHTML = historyYearOptions(state.filters.history.year);
                yearSelect.value = String(state.filters.history.year || '');
            }

            if (monthSelect) {
                monthSelect.innerHTML = historyMonthOptions(state.filters.history.month);
                monthSelect.value = String(state.filters.history.month || '');
            }

            if (buktiInput) {
                buktiInput.value = String(state.filters.history.nomor_bukti || '');
            }
        }

        function renderHistorySummaryCards(filteredRows) {
            const filteredCount = Array.isArray(filteredRows) ? filteredRows.length : 0;
            const totalCount = Number(state.historySummary?.transaction_count || 0);
            setText('tabunganHistoryTransactionCount', formatNumber(filteredCount));
            setText(
                'tabunganHistoryTransactionCountNote',
                filteredCount === totalCount
                    ? 'Total transaksi rekening'
                    : `Dari ${formatNumber(totalCount)} total transaksi`
            );
        }

        function renderHistoryTable() {
            const tbody = document.getElementById('tbody-tabungan-history');
            if (!tbody || !state.historyAccount) {
                return;
            }

            const filteredRows = filteredHistoryTransactions();
            renderHistorySummaryCards(filteredRows);

            if (filteredRows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">Tidak ada transaksi yang sesuai dengan filter.</td></tr>';
                return;
            }

            tbody.innerHTML = renderTransactionRows(filteredRows, {
                historyAccountId: Number(state.historyAccount.id || 0),
                hideAccountNumber: true,
                hideStudent: true,
                hideSavingsType: true
            });
        }

        function applyHistoryFiltersFromUI() {
            state.filters.history.year = String(document.getElementById('filterTabunganHistoryYear')?.value || '').trim();
            state.filters.history.month = String(document.getElementById('filterTabunganHistoryMonth')?.value || '').trim();
            state.filters.history.nomor_bukti = String(document.getElementById('filterTabunganHistoryNomorBukti')?.value || '').trim();
        }

        function resetHistoryFilters() {
            state.filters.history = defaultHistoryFilters();
            renderHistoryFilters();
            renderHistoryTable();
        }

        function scheduleHistoryFilterRender(delay = 0) {
            if (historyFilterTimer !== null) {
                window.clearTimeout(historyFilterTimer);
            }

            historyFilterTimer = window.setTimeout(() => {
                applyHistoryFiltersFromUI();
                renderHistoryTable();
            }, Math.max(0, Number(delay) || 0));
        }

        function bindHistoryFilterListeners() {
            const yearSelect = document.getElementById('filterTabunganHistoryYear');
            const monthSelect = document.getElementById('filterTabunganHistoryMonth');
            const buktiInput = document.getElementById('filterTabunganHistoryNomorBukti');
            const resetButton = document.getElementById('btnResetTabunganHistoryFilters');

            if (yearSelect) {
                yearSelect.addEventListener('change', () => {
                    scheduleHistoryFilterRender(0);
                });
            }

            if (monthSelect) {
                monthSelect.addEventListener('change', () => {
                    scheduleHistoryFilterRender(0);
                });
            }

            if (buktiInput) {
                buktiInput.addEventListener('input', () => {
                    scheduleHistoryFilterRender(250);
                });
            }

            if (resetButton) {
                resetButton.addEventListener('click', () => {
                    resetHistoryFilters();
                });
            }
        }

        function rekeningDataUrl() {
            return buildUrl(getRoute('tabunganSiswaRekeningData'), state.filters.rekening);
        }

        function transaksiDataUrl() {
            return buildUrl(getRoute('tabunganSiswaTransaksiData'), state.filters.transaksi);
        }

        async function loadJenisData(showToast = false) {
            setLoadingRow('tbody-tabungan-jenis', 7, 'Memuat data jenis tabungan...');

            try {
                const res = await apiRequest(getRoute('tabunganSiswaJenisData'));
                state.types = Array.isArray(res.data) ? res.data : [];
                state.canManageTypes = Boolean(res.can_manage_types);
                renderJenisSummary(res.summary || {});
                renderJenisTable();

                if (showToast) {
                    showAlert('success', 'Data jenis tabungan diperbarui.');
                }
            } catch (error) {
                setLoadingRow('tbody-tabungan-jenis', 7, error.message || 'Gagal memuat data.');
                showAlert('error', error.message || 'Gagal memuat data.');
            }
        }

        async function loadRekeningData(showToast = false) {
            setLoadingRow('tbody-tabungan-rekening', 10, 'Memuat data rekening tabungan...');

            try {
                const res = await apiRequest(rekeningDataUrl());
                state.accounts = Array.isArray(res.data) ? res.data : [];
                state.students = Array.isArray(res.students) ? res.students : [];
                state.classes = Array.isArray(res.classes) ? res.classes : [];
                state.types = Array.isArray(res.types) ? res.types : [];
                state.accountOptions = Array.isArray(res.accounts) ? res.accounts : [];
                state.canManageAccounts = Boolean(res.can_manage_accounts);
                state.canManageTransactions = Boolean(res.can_manage_transactions);
                renderRekeningSummary(res.summary || {});
                renderRekeningFilters();
                renderRekeningTable();

                if (showToast) {
                    showAlert('success', 'Data rekening tabungan diperbarui.');
                }
            } catch (error) {
                setLoadingRow('tbody-tabungan-rekening', 10, error.message || 'Gagal memuat data.');
                showAlert('error', error.message || 'Gagal memuat data.');
            }
        }

        async function loadTransaksiData(showToast = false) {
            setLoadingRow('tbody-tabungan-transaksi', 11, 'Memuat data transaksi tabungan...');

            try {
                const res = await apiRequest(transaksiDataUrl());
                state.transactions = Array.isArray(res.data) ? res.data : [];
                state.students = Array.isArray(res.students) ? res.students : [];
                state.classes = Array.isArray(res.classes) ? res.classes : [];
                state.types = Array.isArray(res.types) ? res.types : [];
                state.accountOptions = Array.isArray(res.accounts) ? res.accounts : [];
                state.transactionTypeOptions = res.transaction_type_options || state.transactionTypeOptions;
                state.canManageTransactions = Boolean(res.can_manage_transactions);
                renderTransaksiSummary(res.summary || {});
                renderTransaksiFilters();
                renderTransaksiTable();

                if (showToast) {
                    showAlert('success', 'Data transaksi tabungan diperbarui.');
                }
            } catch (error) {
                setLoadingRow('tbody-tabungan-transaksi', 11, error.message || 'Gagal memuat data.');
                showAlert('error', error.message || 'Gagal memuat data.');
            }
        }

        function renderJenisModal(item = null) {
            const isEdit = !!item;

            showModal(`
                <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="text-sm font-bold text-gray-800">${isEdit ? 'Edit Jenis Tabungan' : 'Tambah Jenis Tabungan'}</h3>
                    </div>
                    <form id="formTabunganJenis" class="p-5 space-y-4">
                        <input type="hidden" name="id" value="${isEdit ? Number(item.id) : ''}">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Kode</label>
                            <input name="kode" type="text" maxlength="50" required value="${escapeHtml(item?.kode || '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Nama Jenis</label>
                            <input name="nama" type="text" maxlength="150" required value="${escapeHtml(item?.nama || '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Deskripsi</label>
                            <textarea name="deskripsi" rows="4" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">${escapeHtml(item?.deskripsi || '')}</textarea>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-gray-700 font-semibold">
                            <input name="is_active" type="checkbox" ${item ? (item.is_active ? 'checked' : '') : 'checked'} class="rounded border-gray-300">
                            Jenis tabungan aktif
                        </label>
                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick="closeModal()" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">${isEdit ? 'Simpan Perubahan' : 'Simpan Jenis'}</button>
                        </div>
                    </form>
                </div>
            `, 'max-w-2xl');

            document.getElementById('formTabunganJenis')?.addEventListener('submit', submitJenisForm);
        }

        function rekeningCreateTypeOptions(selectedId = '') {
            return (state.types || []).map((row) => {
                const selected = Number(selectedId || 0) === Number(row.id) ? 'selected' : '';
                return `<option value="${Number(row.id)}" ${selected}>${escapeHtml(row.nama)} (${escapeHtml(row.kode)})${row.is_active ? '' : ' - nonaktif'}</option>`;
            }).join('');
        }

        function rekeningStudentSearchRows(query = '') {
            const normalized = String(query || '').trim().toLowerCase();

            return (state.students || [])
                .filter((row) => {
                    if (normalized === '') {
                        return true;
                    }

                    const haystack = [
                        row.nama || '',
                        row.nisn || '',
                        row.kelas || '',
                        row.label || ''
                    ].join(' ').toLowerCase();

                    return haystack.includes(normalized);
                })
                .slice(0, 12);
        }

        function closeRekeningStudentDropdown() {
            const dropdown = document.getElementById('rekeningStudentDropdown');
            if (!dropdown) {
                return;
            }

            dropdown.classList.add('hidden');
        }

        function selectRekeningStudent(studentId, label) {
            const hiddenInput = document.getElementById('rekeningStudentId');
            const searchInput = document.getElementById('rekeningStudentSearch');
            if (!hiddenInput || !searchInput) {
                return;
            }

            hiddenInput.value = String(studentId || '');
            searchInput.value = String(label || '');
            searchInput.dataset.selectedId = String(studentId || '');
            searchInput.dataset.selectedLabel = String(label || '');
            closeRekeningStudentDropdown();
        }

        function renderRekeningStudentDropdown(query = '') {
            const dropdown = document.getElementById('rekeningStudentDropdown');
            if (!dropdown) {
                return;
            }

            const rows = rekeningStudentSearchRows(query);
            if (rows.length === 0) {
                dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Siswa tidak ditemukan.</div>';
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = rows.map((row) => `
                <button
                    type="button"
                    class="w-full px-3 py-2 text-left hover:bg-indigo-50 transition"
                    data-rekening-student-option="1"
                    data-student-id="${Number(row.id)}"
                    data-student-label="${escapeHtml(row.label)}"
                >
                    <div class="text-xs font-semibold text-gray-800">${escapeHtml(row.nama || '-')}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(row.kelas || '-')} | ${escapeHtml(row.nisn || '-')}</div>
                </button>
            `).join('');

            dropdown.querySelectorAll('[data-rekening-student-option]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectRekeningStudent(
                        Number(button.getAttribute('data-student-id') || 0),
                        String(button.getAttribute('data-student-label') || '')
                    );
                });
            });

            dropdown.classList.remove('hidden');
        }

        function bindRekeningStudentSearch() {
            const searchInput = document.getElementById('rekeningStudentSearch');
            const hiddenInput = document.getElementById('rekeningStudentId');
            const dropdown = document.getElementById('rekeningStudentDropdown');
            if (!searchInput || !hiddenInput || !dropdown || searchInput.dataset.bound === '1') {
                return;
            }

            const clearSelectionIfNeeded = () => {
                const typedValue = String(searchInput.value || '').trim();
                const selectedLabel = String(searchInput.dataset.selectedLabel || '').trim();
                if (typedValue === selectedLabel) {
                    return;
                }

                hiddenInput.value = '';
                searchInput.dataset.selectedId = '';
                searchInput.dataset.selectedLabel = '';
            };

            searchInput.addEventListener('focus', () => {
                renderRekeningStudentDropdown(searchInput.value);
            });

            searchInput.addEventListener('input', () => {
                clearSelectionIfNeeded();
                renderRekeningStudentDropdown(searchInput.value);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    const firstOption = dropdown.querySelector('[data-rekening-student-option]');
                    if (firstOption) {
                        event.preventDefault();
                        firstOption.click();
                    }
                }

                if (event.key === 'Escape') {
                    closeRekeningStudentDropdown();
                }
            });

            searchInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    closeRekeningStudentDropdown();
                }, 120);
            });

            searchInput.dataset.bound = '1';
        }

        function renderRekeningModal(accountId = null) {
            const item = accountId ? getAccountById(accountId) : null;
            const isEdit = !!item;

            showModal(`
                <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="text-sm font-bold text-gray-800">${isEdit ? 'Edit Rekening Tabungan' : 'Tambah Rekening Tabungan'}</h3>
                    </div>
                    <form id="formTabunganRekening" class="p-5 space-y-4">
                        <input type="hidden" name="id" value="${isEdit ? Number(item.id) : ''}">
                        ${isEdit ? `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Siswa</div>
                                    <div class="text-sm font-semibold text-gray-800 mt-1">${escapeHtml(item.siswa_nama || '-')}</div>
                                    <div class="text-[11px] text-gray-500">${escapeHtml(item.kelas || '-')} | ${escapeHtml(item.siswa_nisn || '-')}</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Jenis Tabungan</div>
                                    <div class="text-sm font-semibold text-gray-800 mt-1">${escapeHtml(item.jenis_tabungan || '-')}</div>
                                    <div class="text-[11px] text-gray-500">${escapeHtml(item.jenis_tabungan_kode || '-')}</div>
                                </div>
                            </div>
                        ` : `
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Siswa</label>
                                <input id="rekeningStudentId" name="siswa_id" type="hidden" value="">
                                <div class="relative">
                                    <input id="rekeningStudentSearch" type="text" placeholder="Cari nama siswa, NISN, atau kelas" autocomplete="off" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                    <div id="rekeningStudentDropdown" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">Ketik untuk mencari lalu pilih siswa dari daftar.</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Jenis Tabungan</label>
                                <select name="jenis_tabungan_id" required class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                    <option value="">Pilih jenis tabungan</option>
                                    ${rekeningCreateTypeOptions()}
                                </select>
                            </div>
                        `}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${isEdit ? `
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Nomor Rekening</label>
                                    <input type="text" value="${escapeHtml(item?.nomor_rekening || '')}" readonly class="w-full bg-gray-100 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5 font-mono cursor-not-allowed">
                                </div>
                            ` : ''}
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Tanggal Dibuka</label>
                                <input name="opened_at" type="date" value="${escapeHtml(item?.opened_at || todayLocalValue())}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                            </div>
                            ${!isEdit ? `
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Setoran Awal</label>
                                    <input name="setoran_awal" type="text" inputmode="numeric" autocomplete="off" data-number-format="thousands" value="" placeholder="0" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                </div>
                            ` : ''}
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-gray-700 font-semibold">
                            <input name="is_active" type="checkbox" ${item ? (item.is_active ? 'checked' : '') : 'checked'} class="rounded border-gray-300">
                            Rekening aktif
                        </label>
                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick="closeModal()" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">${isEdit ? 'Simpan Perubahan' : 'Simpan Rekening'}</button>
                        </div>
                    </form>
                </div>
            `, 'max-w-2xl');

            document.getElementById('formTabunganRekening')?.addEventListener('submit', submitRekeningForm);
            bindFormattedNumberInputs(document.getElementById('modalContainer') || document);
            if (!isEdit) {
                bindRekeningStudentSearch();
            }
        }

        function transactionAccountSearchRows(query = '', selectedId = 0, currentTransaction = null) {
            const normalized = String(query || '').trim().toLowerCase();

            return currentAccountOptions()
                .filter((row) => row.is_active || Number(selectedId || 0) === Number(row.id) || Number(currentTransaction?.account_id || 0) === Number(row.id))
                .filter((row) => {
                    if (normalized === '') {
                        return true;
                    }

                    const haystack = [
                        row.nomor_rekening || '',
                        row.siswa_nama || '',
                        row.siswa_nisn || '',
                        row.kelas || '',
                        row.jenis_tabungan || '',
                        row.label || ''
                    ].join(' ').toLowerCase();

                    return haystack.includes(normalized);
                })
                .slice(0, 12);
        }

        function closeTransactionAccountDropdown() {
            const dropdown = document.getElementById('tabunganTransactionAccountDropdown');
            if (!dropdown) {
                return;
            }

            dropdown.classList.add('hidden');
        }

        function selectTransactionAccount(accountId, label = '') {
            const hiddenInput = document.getElementById('tabunganTransactionAccountId');
            const searchInput = document.getElementById('tabunganTransactionAccountSearch');
            if (!hiddenInput || !searchInput) {
                return;
            }

            hiddenInput.value = String(accountId || '');
            searchInput.value = String(label || '');
            searchInput.dataset.selectedId = String(accountId || '');
            searchInput.dataset.selectedLabel = String(label || '');
            closeTransactionAccountDropdown();
            updateTransactionAccountInfo();
        }

        function renderTransactionAccountDropdown(query = '', currentTransaction = null) {
            const dropdown = document.getElementById('tabunganTransactionAccountDropdown');
            const hiddenInput = document.getElementById('tabunganTransactionAccountId');
            if (!dropdown || !hiddenInput) {
                return;
            }

            const rows = transactionAccountSearchRows(query, Number(hiddenInput.value || 0), currentTransaction);
            if (rows.length === 0) {
                dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Rekening tidak ditemukan.</div>';
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = rows.map((row) => `
                <button
                    type="button"
                    class="w-full px-3 py-2 text-left hover:bg-emerald-50 transition"
                    data-transaction-account-option="1"
                    data-account-id="${Number(row.id)}"
                    data-account-label="${escapeHtml(row.label)}"
                >
                    <div class="text-xs font-semibold text-gray-800">${escapeHtml(row.nomor_rekening || '-')} - ${escapeHtml(row.siswa_nama || '-')}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(row.siswa_nisn || '-')} | ${escapeHtml(row.kelas || '-')} | ${escapeHtml(row.jenis_tabungan || '-')}</div>
                </button>
            `).join('');

            dropdown.querySelectorAll('[data-transaction-account-option]').forEach((button) => {
                button.addEventListener('click', () => {
                    selectTransactionAccount(
                        Number(button.getAttribute('data-account-id') || 0),
                        String(button.getAttribute('data-account-label') || '')
                    );
                });
            });

            dropdown.classList.remove('hidden');
        }

        function bindTransactionAccountSearch(currentTransaction = null) {
            const searchInput = document.getElementById('tabunganTransactionAccountSearch');
            const hiddenInput = document.getElementById('tabunganTransactionAccountId');
            const dropdown = document.getElementById('tabunganTransactionAccountDropdown');
            if (!searchInput || !hiddenInput || !dropdown || searchInput.dataset.bound === '1') {
                return;
            }

            const clearSelectionIfNeeded = () => {
                const typedValue = String(searchInput.value || '').trim();
                const selectedLabel = String(searchInput.dataset.selectedLabel || '').trim();
                if (typedValue === selectedLabel) {
                    return;
                }

                hiddenInput.value = '';
                searchInput.dataset.selectedId = '';
                searchInput.dataset.selectedLabel = '';
            };

            searchInput.addEventListener('focus', () => {
                renderTransactionAccountDropdown(searchInput.value, currentTransaction);
            });

            searchInput.addEventListener('input', () => {
                clearSelectionIfNeeded();
                renderTransactionAccountDropdown(searchInput.value, currentTransaction);
                updateTransactionAccountInfo();
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    const firstOption = dropdown.querySelector('[data-transaction-account-option]');
                    if (firstOption) {
                        event.preventDefault();
                        firstOption.click();
                    }
                }

                if (event.key === 'Escape') {
                    closeTransactionAccountDropdown();
                }
            });

            searchInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    closeTransactionAccountDropdown();
                }, 120);
            });

            searchInput.dataset.bound = '1';
        }

        function transactionTypeSelectOptions(selectedType = '') {
            return Object.entries(state.transactionTypeOptions || {}).map(([key, label]) => {
                const selected = String(selectedType || 'setoran') === String(key) ? 'selected' : '';
                return `<option value="${escapeHtml(key)}" ${selected}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function updateTransactionAccountInfo() {
            const select = document.getElementById('tabunganTransactionAccountId');
            const info = document.getElementById('tabunganTransactionAccountInfo');
            if (!select || !info) return;

            const row = currentAccountOptions().find((item) => Number(item.id) === Number(select.value || 0)) || null;
            if (!row) {
                info.innerHTML = '<div class="text-[11px] text-amber-600">Pilih rekening tabungan terlebih dahulu.</div>';
                return;
            }

            info.innerHTML = `
                <div class="rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-[11px] text-sky-800">
                    <div><span class="font-bold">Siswa:</span> ${escapeHtml(row.siswa_nama || '-')} (${escapeHtml(row.kelas || '-')})</div>
                    <div><span class="font-bold">Jenis:</span> ${escapeHtml(row.jenis_tabungan || '-')}</div>
                </div>
            `;
        }

        function renderTransactionModal(transactionId = null, options = {}) {
            const item = transactionId ? getTransactionById(transactionId) : null;
            const isEdit = !!item;
            const selectedAccountId = Number(item?.account_id || options.accountId || 0);
            const selectedAccount = currentAccountOptions().find((row) => Number(row.id) === selectedAccountId) || null;

            showModal(`
                <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="text-sm font-bold text-gray-800">${isEdit ? 'Edit Transaksi Tabungan' : 'Input Transaksi Tabungan'}</h3>
                    </div>
                    <form id="formTabunganTransaction" data-return-history-account-id="${Number(options.returnHistoryAccountId || 0)}" class="p-5 space-y-4">
                        <input type="hidden" name="id" value="${isEdit ? Number(item.id) : ''}">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Rekening Tabungan</label>
                            <input id="tabunganTransactionAccountId" name="account_id" type="hidden" value="${selectedAccountId || ''}">
                            <div class="relative">
                                <input id="tabunganTransactionAccountSearch" type="text" value="${escapeHtml(selectedAccount?.label || '')}" placeholder="Cari no rekening, NISN, atau siswa" autocomplete="off" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                <div id="tabunganTransactionAccountDropdown" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500">Cari dengan nomor rekening, NISN, atau nama siswa.</p>
                            <div id="tabunganTransactionAccountInfo" class="mt-2"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Jenis Transaksi</label>
                                <select name="jenis_transaksi" required class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                    ${transactionTypeSelectOptions(item?.jenis_transaksi || 'setoran')}
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 mb-1">Nominal</label>
                                <input name="nominal" type="text" inputmode="numeric" autocomplete="off" data-number-format="thousands" required value="${escapeHtml(item?.nominal ? formatNumber(item.nominal) : '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Keterangan</label>
                            <input name="keterangan" type="text" maxlength="2000" value="${escapeHtml(item?.keterangan || '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick="closeModal()" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700">${isEdit ? 'Simpan Perubahan' : 'Simpan Transaksi'}</button>
                        </div>
                    </form>
                </div>
            `, 'max-w-2xl');

            bindTransactionAccountSearch(item);
            updateTransactionAccountInfo();
            bindFormattedNumberInputs(document.getElementById('modalContainer') || document);
            document.getElementById('formTabunganTransaction')?.addEventListener('submit', submitTransactionForm);
        }

        async function submitJenisForm(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const id = Number(formData.get('id') || 0);
            const isEdit = id > 0;

            const payload = {
                kode: String(formData.get('kode') || '').trim(),
                nama: String(formData.get('nama') || '').trim(),
                deskripsi: String(formData.get('deskripsi') || '').trim(),
                is_active: formData.get('is_active') ? 1 : 0
            };

            const url = isEdit
                ? getRoute('tabunganSiswaJenisUpdate').replace('__ID__', encodeURIComponent(String(id)))
                : getRoute('tabunganSiswaJenisStore');
            const method = isEdit ? 'PUT' : 'POST';

            try {
                await apiRequest(url, { method, body: JSON.stringify(payload) });
                closeModal();
                await loadJenisData();
                showAlert('success', isEdit ? 'Jenis tabungan berhasil diperbarui.' : 'Jenis tabungan berhasil ditambahkan.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menyimpan jenis tabungan.');
            }
        }

        async function submitRekeningForm(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const id = Number(formData.get('id') || 0);
            const isEdit = id > 0;

            const payload = {
                opened_at: String(formData.get('opened_at') || '').trim(),
                is_active: formData.get('is_active') ? 1 : 0
            };

            if (!isEdit) {
                payload.siswa_id = Number(formData.get('siswa_id') || 0);
                payload.jenis_tabungan_id = Number(formData.get('jenis_tabungan_id') || 0);
                payload.setoran_awal = parseFormattedInteger(formData.get('setoran_awal'));
                if (payload.siswa_id <= 0) {
                    showAlert('error', 'Pilih siswa dari daftar pencarian terlebih dahulu.');
                    return;
                }
            }

            const url = isEdit
                ? getRoute('tabunganSiswaRekeningUpdate').replace('__ID__', encodeURIComponent(String(id)))
                : getRoute('tabunganSiswaRekeningStore');
            const method = isEdit ? 'PUT' : 'POST';

            try {
                await apiRequest(url, { method, body: JSON.stringify(payload) });
                closeModal();
                await loadRekeningData();
                showAlert('success', isEdit ? 'Rekening tabungan berhasil diperbarui.' : 'Rekening tabungan berhasil ditambahkan.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menyimpan rekening tabungan.');
            }
        }

        async function refreshAfterTransactionMutation(returnHistoryAccountId = 0) {
            if (state.currentPage === 'transaksi') {
                await loadTransaksiData();
            }

            if (state.currentPage === 'rekening' || Number(returnHistoryAccountId) > 0) {
                await loadRekeningData();
                if (Number(returnHistoryAccountId) > 0) {
                    await showTabunganRekeningHistoryModal(Number(returnHistoryAccountId));
                }
            }
        }

        async function submitTransactionForm(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const id = Number(formData.get('id') || 0);
            const isEdit = id > 0;
            const returnHistoryAccountId = Number(form.dataset.returnHistoryAccountId || 0);

            const payload = {
                account_id: Number(formData.get('account_id') || 0),
                jenis_transaksi: String(formData.get('jenis_transaksi') || '').trim(),
                nominal: parseFormattedInteger(formData.get('nominal')),
                keterangan: String(formData.get('keterangan') || '').trim()
            };

            if (payload.account_id <= 0) {
                showAlert('error', 'Pilih rekening tabungan dari hasil pencarian terlebih dahulu.');
                return;
            }

            const url = isEdit
                ? getRoute('tabunganSiswaTransaksiUpdate').replace('__ID__', encodeURIComponent(String(id)))
                : getRoute('tabunganSiswaTransaksiStore');
            const method = isEdit ? 'PUT' : 'POST';

            try {
                await apiRequest(url, { method, body: JSON.stringify(payload) });
                closeModal();
                await refreshAfterTransactionMutation(returnHistoryAccountId);
                showAlert('success', isEdit ? 'Transaksi tabungan berhasil diperbarui.' : 'Transaksi tabungan berhasil disimpan.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menyimpan transaksi tabungan.');
            }
        }

        async function deleteJenis(id) {
            const row = getTypeById(id);
            if (!row) return;

            const confirmed = await confirmAction(`Hapus jenis tabungan "${row.nama}"?`, 'Hapus');
            if (!confirmed) return;

            try {
                await apiRequest(
                    getRoute('tabunganSiswaJenisDestroy').replace('__ID__', encodeURIComponent(String(id))),
                    { method: 'DELETE' }
                );
                await loadJenisData();
                showAlert('success', 'Jenis tabungan berhasil dihapus.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menghapus jenis tabungan.');
            }
        }

        async function deleteRekening(id) {
            const row = getAccountById(id);
            if (!row) return;

            const confirmed = await confirmAction(`Hapus rekening "${row.nomor_rekening}"?`, 'Hapus');
            if (!confirmed) return;

            try {
                await apiRequest(
                    getRoute('tabunganSiswaRekeningDestroy').replace('__ID__', encodeURIComponent(String(id))),
                    { method: 'DELETE' }
                );
                await loadRekeningData();
                showAlert('success', 'Rekening tabungan berhasil dihapus.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menghapus rekening tabungan.');
            }
        }

        async function deleteTransaction(id, historyAccountId = 0) {
            const row = getTransactionById(id);
            if (!row) return;

            const reason = await askDeleteReason();
            if (reason === null) return;

            const confirmed = await confirmAction(`Hapus transaksi dengan bukti "${row.nomor_bukti}"?`, 'Hapus');
            if (!confirmed) return;

            try {
                await apiRequest(
                    getRoute('tabunganSiswaTransaksiDestroy').replace('__ID__', encodeURIComponent(String(id))),
                    {
                        method: 'DELETE',
                        body: JSON.stringify({ delete_reason: reason })
                    }
                );
                closeModal();
                await refreshAfterTransactionMutation(historyAccountId);
                showAlert('success', 'Transaksi tabungan berhasil dihapus.');
            } catch (error) {
                showAlert('error', error.message || 'Gagal menghapus transaksi tabungan.');
            }
        }

        async function showRekeningHistory(accountId) {
            const url = getRoute('tabunganSiswaRekeningRiwayat').replace('__ID__', encodeURIComponent(String(accountId)));
            const previousHistoryAccountId = Number(state.historyAccount?.id || 0);

            try {
                const res = await apiRequest(url);
                const account = res.account || null;
                const transactions = Array.isArray(res.transactions) ? res.transactions : [];
                state.historyTransactions = transactions;
                state.historyAccount = account;
                state.historySummary = res.summary || {};
                state.filters.history = previousHistoryAccountId === Number(accountId)
                    ? { ...state.filters.history }
                    : defaultHistoryFilters();
                if (!account) {
                    throw new Error('Data rekening tidak ditemukan.');
                }

                showModal(`
                    <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden flex h-[78vh] max-h-[88vh] flex-col">
                        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60 flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Riwayat Transaksi Rekening</h3>
                                <p class="text-xs text-gray-500 mt-1">Riwayat transaksi khusus untuk satu rekening tabungan siswa.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="showTabunganStatementPicker(${Number(account.id)})" class="bg-rose-600 text-white px-3 py-2 rounded-lg text-xs font-bold hover:bg-rose-700 transition">
                                    <i class="fas fa-file-pdf mr-1"></i> Cetak Rekening Koran
                                </button>
                                <button onclick="closeModal()" class="bg-white text-gray-700 border border-gray-200 px-3 py-2 rounded-lg text-xs font-bold hover:bg-gray-50 transition">Tutup</button>
                            </div>
                        </div>
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3 border-b border-gray-100 bg-gray-50/30">
                            <div class="rounded-lg border border-sky-100 bg-sky-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-sky-600">No Rekening</div>
                                <div class="mt-1 text-sm font-semibold text-sky-900">${escapeHtml(account.nomor_rekening || '-')}</div>
                            </div>
                            <div class="rounded-lg border border-violet-100 bg-violet-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-violet-600">Siswa</div>
                                <div class="mt-1 text-sm font-semibold text-violet-900">${escapeHtml(account.siswa_nama || '-')}</div>
                                <div class="mt-1 text-[11px] text-violet-700">${escapeHtml(account.kelas || '-')} | ${escapeHtml(account.siswa_nisn || '-')}</div>
                            </div>
                            <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-indigo-600">Jenis Tabungan</div>
                                <div class="mt-1 text-sm font-semibold text-indigo-900">${escapeHtml(account.jenis_tabungan || '-')}</div>
                                <div class="mt-1 text-[11px] text-indigo-700">${escapeHtml(account.jenis_tabungan_kode || '-')}</div>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-600">Saldo Akhir</div>
                                <div class="mt-1 text-sm font-semibold text-emerald-900">${formatRupiah(res.summary?.saldo_akhir || 0)}</div>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-amber-600">Jumlah Transaksi</div>
                                <div id="tabunganHistoryTransactionCount" class="mt-1 text-sm font-semibold text-amber-900">${formatNumber(res.summary?.transaction_count || 0)}</div>
                                <div id="tabunganHistoryTransactionCountNote" class="mt-1 text-[11px] text-amber-700">Total transaksi rekening</div>
                            </div>
                        </div>
                        <div class="px-4 py-3 border-b border-gray-100 bg-white">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Tahun</label>
                                    <select id="filterTabunganHistoryYear" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5"></select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Bulan</label>
                                    <select id="filterTabunganHistoryMonth" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5"></select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 mb-1">Nomor Bukti</label>
                                    <input id="filterTabunganHistoryNomorBukti" type="text" placeholder="Cari nomor bukti..." class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                </div>
                                <div class="flex items-end">
                                    <button id="btnResetTabunganHistoryFilters" type="button" class="w-full px-3 py-2.5 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Reset</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 min-h-0 overflow-hidden">
                            <div class="h-full overflow-auto">
                                <table class="w-full min-w-[1120px] text-left border-collapse">
                                    <thead class="bg-gray-50 text-gray-500 text-[10px] uppercase font-semibold border-b border-gray-200 sticky top-0 z-10">
                                        <tr>
                                            <th class="p-3 w-12 text-center">No</th>
                                            <th class="p-3 w-36">Tanggal</th>
                                            <th class="p-3 w-40">No Bukti</th>
                                            <th class="p-3 w-32">Jenis Transaksi</th>
                                            <th class="p-3 w-32 text-right">Nominal</th>
                                            <th class="p-3 w-32 text-right">Saldo Sesudah</th>
                                            <th class="p-3 w-32">Operator</th>
                                            <th class="p-3 w-36 text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-tabungan-history" class="divide-y divide-gray-50 bg-white text-xs text-gray-700"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `, 'max-w-[1400px]');

                renderHistoryFilters();
                bindHistoryFilterListeners();
                renderHistoryTable();
            } catch (error) {
                showAlert('error', error.message || 'Gagal memuat riwayat rekening.');
            }
        }

        function applyRekeningFiltersFromUI() {
            state.filters.rekening.kelas = String(document.getElementById('filterTabunganRekeningKelas')?.value || '').trim();
            state.filters.rekening.jenis_tabungan_id = String(document.getElementById('filterTabunganRekeningJenis')?.value || '').trim();
            state.filters.rekening.status = String(document.getElementById('filterTabunganRekeningStatus')?.value || '').trim();
            state.filters.rekening.q = String(document.getElementById('filterTabunganRekeningKeyword')?.value || '').trim();
        }

        function resetRekeningFilters() {
            state.filters.rekening = {
                kelas: '',
                jenis_tabungan_id: '',
                status: '',
                q: ''
            };
            renderRekeningFilters();
            loadRekeningData();
        }

        function scheduleRekeningFilterReload(delay = 0) {
            if (rekeningFilterTimer !== null) {
                window.clearTimeout(rekeningFilterTimer);
            }

            rekeningFilterTimer = window.setTimeout(() => {
                applyRekeningFiltersFromUI();
                loadRekeningData();
            }, Math.max(0, Number(delay) || 0));
        }

        function bindRekeningFilterListeners() {
            [
                'filterTabunganRekeningKelas',
                'filterTabunganRekeningJenis',
                'filterTabunganRekeningStatus'
            ].forEach((id) => {
                const element = document.getElementById(id);
                if (!element || element.dataset.bound === '1') {
                    return;
                }

                element.addEventListener('change', () => {
                    scheduleRekeningFilterReload(0);
                });
                element.dataset.bound = '1';
            });

            const keywordInput = document.getElementById('filterTabunganRekeningKeyword');
            if (keywordInput && keywordInput.dataset.bound !== '1') {
                keywordInput.addEventListener('input', () => {
                    scheduleRekeningFilterReload(300);
                });
                keywordInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        scheduleRekeningFilterReload(0);
                    }
                });
                keywordInput.dataset.bound = '1';
            }
        }

        function applyTransaksiFiltersFromUI() {
            state.filters.transaksi.kelas = String(document.getElementById('filterTabunganTransaksiKelas')?.value || '').trim();
            state.filters.transaksi.siswa_id = String(document.getElementById('filterTabunganTransaksiSiswa')?.value || '').trim();
            state.filters.transaksi.jenis_tabungan_id = String(document.getElementById('filterTabunganTransaksiJenis')?.value || '').trim();
            state.filters.transaksi.jenis_transaksi = String(document.getElementById('filterTabunganTransaksiTipe')?.value || '').trim();
            state.filters.transaksi.tanggal_dari = String(document.getElementById('filterTabunganTransaksiTanggalDari')?.value || '').trim();
            state.filters.transaksi.tanggal_sampai = String(document.getElementById('filterTabunganTransaksiTanggalSampai')?.value || '').trim();
            state.filters.transaksi.q = String(document.getElementById('filterTabunganTransaksiKeyword')?.value || '').trim();
        }

        function resetTransaksiFilters() {
            state.filters.transaksi = {
                kelas: '',
                siswa_id: '',
                jenis_tabungan_id: '',
                jenis_transaksi: '',
                tanggal_dari: '',
                tanggal_sampai: '',
                q: ''
            };
            renderTransaksiFilters();
            loadTransaksiData();
        }

        window.refreshTabunganJenisData = function (showToast = false) {
            loadJenisData(Boolean(showToast));
        };

        window.refreshTabunganRekeningData = function (showToast = false) {
            loadRekeningData(Boolean(showToast));
        };

        window.refreshTabunganTransaksiData = function (showToast = false) {
            loadTransaksiData(Boolean(showToast));
        };

        window.showTabunganJenisModal = function (id = null) {
            if (!state.canManageTypes) return;
            renderJenisModal(id ? getTypeById(id) : null);
        };

        window.deleteTabunganJenis = function (id) {
            if (!state.canManageTypes) return;
            deleteJenis(id);
        };

        window.showTabunganRekeningModal = function (id = null) {
            if (!state.canManageAccounts) return;
            renderRekeningModal(id);
        };

        window.deleteTabunganRekening = function (id) {
            if (!state.canManageAccounts) return;
            deleteRekening(id);
        };

        window.showTabunganRekeningHistoryModal = function (id) {
            showRekeningHistory(id);
        };

        window.showTabunganStatementPicker = function (accountId) {
            showStatementPicker(accountId);
        };

        window.showTabunganTransactionModal = function (id = null, options = {}) {
            if (!state.canManageTransactions) return;
            if (!id && currentAccountOptions().length === 0) {
                showAlert('error', 'Belum ada rekening tabungan. Tambahkan rekening terlebih dahulu.');
                return;
            }
            renderTransactionModal(id, options || {});
        };

        window.deleteTabunganTransaction = function (id, historyAccountId = 0) {
            if (!state.canManageTransactions) return;
            deleteTransaction(id, historyAccountId);
        };

        window.applyTabunganRekeningFilters = function () {
            applyRekeningFiltersFromUI();
            loadRekeningData();
        };

        window.resetTabunganRekeningFilters = function () {
            resetRekeningFilters();
        };

        window.applyTabunganTransaksiFilters = function () {
            applyTransaksiFiltersFromUI();
            loadTransaksiData();
        };

        window.resetTabunganTransaksiFilters = function () {
            resetTransaksiFilters();
        };

        window.closeModal = window.closeModal || closeModal;

        document.addEventListener('DOMContentLoaded', function () {
            state.currentPage = detectCurrentPage();
            if (!state.currentPage) {
                return;
            }

            if (state.currentPage === 'jenis') {
                loadJenisData();
                return;
            }

            if (state.currentPage === 'rekening') {
                bindRekeningFilterListeners();
                loadRekeningData();
                return;
            }
        });
    })();
</script>
