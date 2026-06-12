<script>
    (function () {
        const state = {
            fullData: [],
            filtered: [],
            siswaOptions: [],
            statusOptions: {},
            jenisOptions: {},
            canRequest: false,
            canApprove: false,
            canManage: false,
            serverStudentView: false,
            isStudentView: false,
            limit: 10,
            page: 1,
            search: '',
            statusFilter: '',
            jenisFilter: '',
            dateFrom: '',
            dateTo: ''
        };

        const csrfToken = '{{ csrf_token() }}';
        const showAlert = window.showAlert || function (type, message) {
            console[type === 'error' ? 'error' : 'log'](message);
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatDateDisplay(value) {
            const raw = String(value || '').trim();
            if (!raw || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) return '-';
            const [y, m, d] = raw.split('-');
            return `${d}-${m}-${y}`;
        }

        function formatTanggalRange(start, end) {
            const a = formatDateDisplay(start);
            const b = formatDateDisplay(end);
            if (a === '-' && b === '-') return '-';
            if (a === b) return a;
            return `${a} s/d ${b}`;
        }

        async function apiRequest(url, options = {}) {
            const method = (options.method || 'GET').toUpperCase();
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
                const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                throw new Error(firstError || payload.message || 'Permintaan gagal diproses.');
            }

            return payload;
        }

        function getApproveUrl(id) {
            return String(window.APP_ROUTES?.izinSakitApprove || '').replace('__ID__', encodeURIComponent(String(id)));
        }

        function getRejectUrl(id) {
            return String(window.APP_ROUTES?.izinSakitReject || '').replace('__ID__', encodeURIComponent(String(id)));
        }

        function getDestroyUrl(id) {
            return String(window.APP_ROUTES?.izinSakitDestroy || '').replace('__ID__', encodeURIComponent(String(id)));
        }

        function updateAddButtonVisibility() {
            const btn = document.getElementById('btn-add-izin-sakit');
            if (!btn) return;
            btn.classList.toggle('hidden', !state.canRequest);
        }

        function getVisibleColspan() {
            return state.isStudentView ? 6 : 9;
        }

        function resolveCurrentRole() {
            return String(
                window.APP_CURRENT_USER?.raw_role
                || window.APP_CURRENT_USER?.role
                || ''
            ).trim().toLowerCase();
        }

        function normalizeIdentity(value) {
            return String(value || '').trim().toLowerCase();
        }

        function isLikelyStudentSelfScope() {
            if (state.siswaOptions.length !== 1) {
                return false;
            }

            const user = window.APP_CURRENT_USER || {};
            const userKeys = [
                normalizeIdentity(user.nisn),
                normalizeIdentity(user.username)
            ].filter(Boolean);

            if (userKeys.length === 0) {
                return false;
            }

            const onlySiswa = state.siswaOptions[0] || null;
            const siswaNisn = normalizeIdentity(onlySiswa?.nisn);

            return siswaNisn !== '' && userKeys.includes(siswaNisn);
        }

        function resolveSelfSiswaOption() {
            const user = window.APP_CURRENT_USER || {};
            const userKeys = [
                normalizeIdentity(user.nisn),
                normalizeIdentity(user.username)
            ].filter(Boolean);

            if (userKeys.length === 0) {
                return state.siswaOptions[0] || null;
            }

            const found = state.siswaOptions.find((item) => {
                return userKeys.includes(normalizeIdentity(item?.nisn));
            });

            return found || state.siswaOptions[0] || null;
        }

        function resolveStudentViewMode() {
            if (state.serverStudentView) {
                return true;
            }

            const role = resolveCurrentRole();
            if (role === 'siswa' || role === 'student' || role.includes('siswa')) {
                return true;
            }

            if (!state.canApprove && !state.canManage && isLikelyStudentSelfScope()) {
                return true;
            }

            if (!state.canApprove && !state.canManage && state.siswaOptions.length === 1) {
                return true;
            }

            if (role !== '') {
                return false;
            }

            return !state.canApprove && !state.canManage && state.siswaOptions.length === 1;
        }

        function applyRoleBasedView() {
            const isStudent = state.isStudentView;

            const desc = document.getElementById('izin-sakit-description');
            if (desc) {
                desc.textContent = isStudent
                    ? 'Silakan ajukan izin/sakit Anda sesuai kebutuhan. Pengajuan akan diproses setelah persetujuan guru atau wali kelas.'
                    : 'Silakan kelola pengajuan izin/sakit siswa sesuai alur persetujuan sekolah.';
            }

            const searchInput = document.getElementById('filter-izin-search');
            if (searchInput) {
                searchInput.placeholder = isStudent
                    ? 'Cari jenis/status/alasan...'
                    : 'Cari siswa/alasan...';
            }

            const thSiswa = document.getElementById('th-izin-siswa');
            const thKelas = document.getElementById('th-izin-kelas');
            const thPengaju = document.getElementById('th-izin-pengaju');
            if (thSiswa) {
                thSiswa.classList.toggle('hidden', isStudent);
            }
            if (thKelas) {
                if (isStudent) {
                    thKelas.classList.add('hidden');
                    thKelas.classList.remove('md:table-cell');
                } else {
                    thKelas.classList.remove('hidden');
                    thKelas.classList.add('md:table-cell');
                }
            }
            if (thPengaju) {
                if (isStudent) {
                    thPengaju.classList.add('hidden');
                    thPengaju.classList.remove('lg:table-cell');
                } else {
                    thPengaju.classList.remove('hidden');
                    thPengaju.classList.add('lg:table-cell');
                }
            }
        }

        function renderFilters() {
            const jenisSelect = document.getElementById('filter-izin-jenis');
            if (jenisSelect) {
                const current = String(state.jenisFilter || '');
                jenisSelect.innerHTML = '<option value="">Semua Jenis</option>' + Object.entries(state.jenisOptions || {}).map(([key, label]) => `
                    <option value="${escapeHtml(key)}">${escapeHtml(label)}</option>
                `).join('');
                jenisSelect.value = current;
            }

            const statusSelect = document.getElementById('filter-izin-status');
            if (statusSelect) {
                const current = String(state.statusFilter || '');
                statusSelect.innerHTML = '<option value="">Semua Status</option>' + Object.entries(state.statusOptions || {}).map(([key, label]) => `
                    <option value="${escapeHtml(key)}">${escapeHtml(label)}</option>
                `).join('');
                statusSelect.value = current;
            }
        }

        function updatePagination() {
            const total = state.filtered.length;
            const infoEl = document.getElementById('info-izin-sakit');
            const btnPrev = document.getElementById('btn-prev-izin-sakit');
            const btnNext = document.getElementById('btn-next-izin-sakit');

            const totalPages = state.limit === Infinity ? 1 : Math.max(1, Math.ceil(total / state.limit));
            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            if (total === 0) {
                if (infoEl) infoEl.textContent = 'Tidak ada data izin/sakit.';
                if (btnPrev) btnPrev.disabled = true;
                if (btnNext) btnNext.disabled = true;
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const endIdx = state.limit === Infinity ? total : Math.min(startIdx + state.limit, total);

            if (infoEl) infoEl.textContent = `Menampilkan ${startIdx + 1} - ${endIdx} dari ${total} data`;
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= totalPages;
        }

        function renderRows() {
            const tbody = document.getElementById('tbody-izin-sakit');
            if (!tbody) return;
            const colspan = getVisibleColspan();

            if (state.filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${colspan}" class="p-8 text-center text-gray-400">Data izin/sakit tidak ditemukan.</td></tr>`;
                updatePagination();
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const rows = state.limit === Infinity
                ? state.filtered
                : state.filtered.slice(startIdx, startIdx + state.limit);

            tbody.innerHTML = rows.map((row, i) => {
                const jenis = String(row.jenis || '').toLowerCase();
                const status = String(row.status || '').toLowerCase();
                const jenisClass = jenis === 'sakit'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-blue-100 text-blue-700';
                const statusClass = status === 'approved'
                    ? 'bg-emerald-100 text-emerald-700'
                    : (status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-yellow-100 text-yellow-700');
                const siswaColClass = state.isStudentView ? 'hidden' : '';
                const kelasColClass = state.isStudentView ? 'hidden' : 'hidden md:table-cell';
                const pengajuColClass = state.isStudentView ? 'hidden' : 'hidden lg:table-cell';

                const actions = [];
                if (status === 'pending' && state.canApprove) {
                    actions.push(`
                        <button onclick="approveIzinSakit(${Number(row.id)})" class="p-2 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition" title="Setujui">
                            <i class="fas fa-check"></i>
                        </button>
                    `);
                    actions.push(`
                        <button onclick="rejectIzinSakit(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Tolak">
                            <i class="fas fa-times"></i>
                        </button>
                    `);
                }

                if (state.canManage || (Boolean(row.requested_by_me) && status === 'pending')) {
                    actions.push(`
                        <button onclick="deleteIzinSakit(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    `);
                }

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${startIdx + i + 1}</td>
                        <td class="p-3">${escapeHtml(formatTanggalRange(row.tanggal_mulai, row.tanggal_selesai))}</td>
                        <td class="p-3 ${siswaColClass}">
                            <div class="font-semibold">${escapeHtml(row.siswa_nama || '-')}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.siswa_nisn || '-')}</div>
                        </td>
                        <td class="p-3 ${kelasColClass}">${escapeHtml(row.kelas || '-')}</td>
                        <td class="p-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-semibold ${jenisClass}">${escapeHtml((state.jenisOptions?.[jenis] || row.jenis || '-'))}</span>
                        </td>
                        <td class="p-3 hidden lg:table-cell max-w-[240px]">
                            <div class="truncate" title="${escapeHtml(row.alasan || '-')}">${escapeHtml(row.alasan || '-')}</div>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-semibold ${statusClass}">${escapeHtml((state.statusOptions?.[status] || row.status || '-'))}</span>
                        </td>
                        <td class="p-3 ${pengajuColClass}">
                            <div>${escapeHtml(row.requested_by_name || '-')}</div>
                            ${row.approved_by_name && row.approved_by_name !== '-' ? `<div class="text-[11px] text-gray-500">Approval: ${escapeHtml(row.approved_by_name)}</div>` : ''}
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                ${actions.length > 0 ? actions.join('') : '<span class="text-gray-400 text-[11px]">-</span>'}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            updatePagination();
        }

        function applyFilters() {
            let rows = [...state.fullData];

            if (state.statusFilter !== '') {
                rows = rows.filter((item) => String(item.status || '') === state.statusFilter);
            }
            if (state.jenisFilter !== '') {
                rows = rows.filter((item) => String(item.jenis || '') === state.jenisFilter);
            }
            if (state.dateFrom !== '') {
                rows = rows.filter((item) => String(item.tanggal_mulai || '') >= state.dateFrom);
            }
            if (state.dateTo !== '') {
                rows = rows.filter((item) => String(item.tanggal_selesai || '') <= state.dateTo);
            }
            if (state.search) {
                const q = state.search.toLowerCase();
                rows = rows.filter((item) => [
                    item.siswa_nama,
                    item.siswa_nisn,
                    item.kelas,
                    item.jenis,
                    item.status,
                    item.alasan,
                    item.requested_by_name,
                    item.approved_by_name,
                    item.approval_note
                ].map((x) => String(x || '').toLowerCase()).join(' ').includes(q));
            }

            state.filtered = rows;
            renderRows();
        }

        function renderLoading(message = 'Memuat data izin/sakit...') {
            const tbody = document.getElementById('tbody-izin-sakit');
            if (!tbody) return;
            const colspan = getVisibleColspan();
            tbody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="p-8 text-center">
                        <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>${escapeHtml(message)}</span>
                        </div>
                    </td>
                </tr>
            `;
        }

        async function loadIzinSakitData(showToast = false) {
            renderLoading();
            try {
                const dataUrl = String(window.APP_ROUTES?.izinSakitData || '').trim();
                if (dataUrl === '') {
                    throw new Error('Route izin-sakit.data tidak tersedia di APP_ROUTES.');
                }

                const res = await apiRequest(dataUrl);
                state.fullData = Array.isArray(res?.data) ? res.data : [];
                state.siswaOptions = Array.isArray(res?.siswa) ? res.siswa : [];
                state.statusOptions = (res?.status_options && typeof res.status_options === 'object') ? res.status_options : {};
                state.jenisOptions = (res?.jenis_options && typeof res.jenis_options === 'object') ? res.jenis_options : {};
                state.canRequest = Boolean(res?.can_request);
                state.canApprove = Boolean(res?.can_approve);
                state.canManage = Boolean(res?.can_manage);
                state.serverStudentView = Boolean(res?.is_student);
                state.isStudentView = resolveStudentViewMode();
                state.page = 1;
                renderFilters();
                updateAddButtonVisibility();
                applyRoleBasedView();
                applyFilters();
                if (showToast) {
                    showAlert('success', 'Data izin/sakit diperbarui.');
                }
            } catch (err) {
                const tbody = document.getElementById('tbody-izin-sakit');
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="${getVisibleColspan()}" class="p-8 text-center text-red-500">${escapeHtml(err.message || err)}</td></tr>`;
                }
            }
        }

        function getModalShell(create = false) {
            const container = document.getElementById('modalContainer');
            if (!container) return null;

            let shell = container.querySelector('[data-modal-shell]');
            if (!shell && create) {
                container.innerHTML = `
                    <div data-modal-shell class="fixed inset-0 z-50 hidden items-center justify-center p-4">
                        <div class="absolute inset-0 bg-gray-900/45 transition-opacity" onclick="closeModal()"></div>
                        <div data-modal-host class="relative w-full max-w-2xl"></div>
                    </div>`;
                shell = container.querySelector('[data-modal-shell]');
            }

            return shell;
        }

        function showModal(content) {
            const shell = getModalShell(true);
            if (!shell) return;
            const host = shell.querySelector('[data-modal-host]');
            if (!host) return;

            host.innerHTML = content;
            shell.classList.remove('hidden');
            shell.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            const shell = getModalShell(false);
            if (!shell) return;
            const host = shell.querySelector('[data-modal-host]');
            if (host) host.innerHTML = '';
            shell.classList.add('hidden');
            shell.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function getSiswaLabel(siswa) {
            if (!siswa || typeof siswa !== 'object') {
                return '-';
            }

            const nama = String(siswa.nama || '-').trim();
            const nisn = String(siswa.nisn || '-').trim() || '-';
            const kelas = String(siswa.kelas || '-').trim() || '-';

            return `${nama} (${nisn}) - ${kelas}`;
        }

        function findSiswaById(id) {
            const key = Number(id || 0);
            if (key <= 0) return null;
            return state.siswaOptions.find((item) => Number(item.id) === key) || null;
        }

        function hideSiswaDropdown() {
            const dropdown = document.getElementById('izin-siswa-dropdown');
            if (!dropdown) return;
            dropdown.innerHTML = '';
            dropdown.classList.add('hidden');
        }

        function setSelectedSiswaOnForm(siswa) {
            const hiddenInput = document.getElementById('izin-siswa-id');
            const searchInput = document.getElementById('izin-siswa-search');
            const siswaText = document.getElementById('izin-siswa-locked-text');

            const selectedId = Number(siswa?.id || 0);
            const selectedLabel = selectedId > 0 ? getSiswaLabel(siswa) : '';

            if (hiddenInput) {
                hiddenInput.value = selectedId > 0 ? String(selectedId) : '';
            }
            if (searchInput) {
                searchInput.value = selectedLabel;
                searchInput.dataset.selectedId = selectedId > 0 ? String(selectedId) : '';
            }
            if (siswaText) {
                siswaText.textContent = selectedLabel || '-';
            }
        }

        function getFilteredSiswaOptions(keyword) {
            const q = String(keyword || '').trim().toLowerCase();
            const rows = !q
                ? state.siswaOptions
                : state.siswaOptions.filter((item) => {
                    const haystack = [
                        item.nama,
                        item.nisn,
                        item.kelas
                    ].map((x) => String(x || '').toLowerCase()).join(' ');
                    return haystack.includes(q);
                });

            return rows.slice(0, 25);
        }

        function renderSiswaDropdown(keyword) {
            const dropdown = document.getElementById('izin-siswa-dropdown');
            if (!dropdown) return;

            const rows = getFilteredSiswaOptions(keyword);
            if (rows.length === 0) {
                dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Siswa tidak ditemukan.</div>';
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = rows.map((item) => `
                <button type="button" data-siswa-option-id="${Number(item.id)}" class="w-full text-left px-3 py-2 hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                    <div class="text-xs font-semibold text-gray-700">${escapeHtml(item.nama || '-')}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(`${item.nisn || '-'} | ${item.kelas || '-'}`)}</div>
                </button>
            `).join('');
            dropdown.classList.remove('hidden');

            dropdown.querySelectorAll('[data-siswa-option-id]').forEach((button) => {
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    const siswa = findSiswaById(button.getAttribute('data-siswa-option-id'));
                    if (!siswa) return;
                    setSelectedSiswaOnForm(siswa);
                    hideSiswaDropdown();
                });
            });
        }

        function initSiswaInputOnModal() {
            const hiddenInput = document.getElementById('izin-siswa-id');
            if (!hiddenInput) return;

            if (state.isStudentView) {
                const ownSiswa = resolveSelfSiswaOption();
                setSelectedSiswaOnForm(ownSiswa);
                hideSiswaDropdown();
                return;
            }

            const siswaLocked = state.siswaOptions.length <= 1;
            if (siswaLocked) {
                const ownSiswa = state.siswaOptions[0] || null;
                setSelectedSiswaOnForm(ownSiswa);
                hideSiswaDropdown();
                return;
            }

            const searchInput = document.getElementById('izin-siswa-search');
            if (!searchInput) return;

            if (state.siswaOptions.length === 1) {
                setSelectedSiswaOnForm(state.siswaOptions[0]);
            }

            searchInput.addEventListener('focus', () => {
                renderSiswaDropdown(searchInput.value);
            });

            searchInput.addEventListener('input', () => {
                hiddenInput.value = '';
                searchInput.dataset.selectedId = '';
                renderSiswaDropdown(searchInput.value);
            });

            searchInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    hideSiswaDropdown();
                }, 150);
            });
        }

        function getAddFormHTML() {
            const labelClass = 'block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide';
            const inputClass = 'w-full bg-white border border-gray-200 text-sm rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500';
            const hideSiswaField = state.isStudentView;
            const siswaLocked = !hideSiswaField && state.siswaOptions.length <= 1;

            const jenisOptions = Object.entries(state.jenisOptions || {}).map(([key, label]) => `
                <option value="${escapeHtml(key)}">${escapeHtml(label)}</option>
            `).join('');

            return `
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-800">Pengajuan Izin / Sakit</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
                    </div>
                    <div class="p-6">
                        <form onsubmit="saveIzinSakit(event)" class="space-y-4">
                            <div>
                                <input type="hidden" id="izin-siswa-id" value="">
                                ${hideSiswaField ? '' : siswaLocked ? `
                                    <label class="${labelClass}">Siswa</label>
                                    <div id="izin-siswa-locked-text" class="w-full bg-gray-50 border border-gray-200 text-sm rounded-lg p-2.5 text-gray-700">-</div>
                                ` : `
                                    <label class="${labelClass}">Siswa</label>
                                    <div class="relative">
                                        <input id="izin-siswa-search" type="text" class="${inputClass}" placeholder="Ketik nama/NISN/kelas siswa" autocomplete="off">
                                        <div id="izin-siswa-dropdown" class="hidden absolute top-full left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-20"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500">Ketik untuk mencari siswa, lalu pilih dari dropdown.</p>
                                `}
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="${labelClass}">Jenis</label>
                                    <select id="izin-jenis" required class="${inputClass}">
                                        ${jenisOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="${labelClass}">Tanggal Mulai</label>
                                    <input id="izin-tanggal-mulai" type="date" required class="${inputClass}">
                                </div>
                                <div>
                                    <label class="${labelClass}">Tanggal Selesai</label>
                                    <input id="izin-tanggal-selesai" type="date" required class="${inputClass}">
                                </div>
                            </div>
                            <div>
                                <label class="${labelClass}">Alasan</label>
                                <textarea id="izin-alasan" rows="4" required class="${inputClass}" placeholder="Tuliskan alasan pengajuan"></textarea>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" onclick="closeModal()" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg border border-gray-200 bg-white text-gray-700 font-semibold text-xs hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"><i class="fas fa-times text-[10px]"></i>Batal</button>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-sm hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition"><i class="fas fa-save text-[10px]"></i>Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
        }

        function collectAddPayload() {
            const siswaId = Number(document.getElementById('izin-siswa-id')?.value || 0);
            const jenis = String(document.getElementById('izin-jenis')?.value || '').trim();
            const tanggalMulai = String(document.getElementById('izin-tanggal-mulai')?.value || '').trim();
            const tanggalSelesai = String(document.getElementById('izin-tanggal-selesai')?.value || '').trim();
            const alasan = String(document.getElementById('izin-alasan')?.value || '').trim();

            if (siswaId <= 0) {
                showAlert('error', 'Siswa wajib dipilih.');
                return null;
            }
            if (!jenis) {
                showAlert('error', 'Jenis wajib dipilih.');
                return null;
            }
            if (!tanggalMulai || !tanggalSelesai) {
                showAlert('error', 'Tanggal mulai dan selesai wajib diisi.');
                return null;
            }
            if (tanggalSelesai < tanggalMulai) {
                showAlert('error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
                return null;
            }
            if (!alasan) {
                showAlert('error', 'Alasan wajib diisi.');
                return null;
            }

            return {
                siswa_id: siswaId,
                jenis,
                tanggal_mulai: tanggalMulai,
                tanggal_selesai: tanggalSelesai,
                alasan
            };
        }

        async function saveIzinSakit(event) {
            event.preventDefault();
            const payload = collectAddPayload();
            if (!payload) return;

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
            }

            try {
                const res = await apiRequest(window.APP_ROUTES?.izinSakitStore, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                closeModal();
                await loadIzinSakitData();
                showAlert('success', res.message || 'Pengajuan berhasil dibuat.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = originalText;
                }
            }
        }

        function showAddIzinSakitModal() {
            if (!state.canRequest) {
                showAlert('error', 'Anda tidak memiliki izin membuat pengajuan.');
                return;
            }

            if (state.siswaOptions.length === 0) {
                showAlert('error', 'Data siswa tidak tersedia untuk pengajuan.');
                return;
            }

            showModal(getAddFormHTML());
            initSiswaInputOnModal();
        }

        async function approveIzinSakit(id) {
            if (!state.canApprove) {
                showAlert('error', 'Anda tidak memiliki izin approval.');
                return;
            }

            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data pengajuan tidak ditemukan.');
                return;
            }

            const result = await Swal.fire({
                title: 'Setujui Pengajuan?',
                html: `Setujui ${escapeHtml((state.jenisOptions?.[row.jenis] || row.jenis || '-'))} untuk <b>${escapeHtml(row.siswa_nama || '-')}</b>?`,
                input: 'textarea',
                inputLabel: 'Catatan approval (opsional)',
                inputPlaceholder: 'Tambahkan catatan...',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Setujui',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#16a34a'
            });

            if (!result.isConfirmed) return;

            try {
                const res = await apiRequest(getApproveUrl(id), {
                    method: 'PUT',
                    body: JSON.stringify({ approval_note: String(result.value || '').trim() || null })
                });
                await loadIzinSakitData();
                showAlert('success', res.message || 'Pengajuan disetujui.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            }
        }

        async function rejectIzinSakit(id) {
            if (!state.canApprove) {
                showAlert('error', 'Anda tidak memiliki izin approval.');
                return;
            }

            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data pengajuan tidak ditemukan.');
                return;
            }

            const result = await Swal.fire({
                title: 'Tolak Pengajuan?',
                html: `Tolak ${escapeHtml((state.jenisOptions?.[row.jenis] || row.jenis || '-'))} untuk <b>${escapeHtml(row.siswa_nama || '-')}</b>?`,
                input: 'textarea',
                inputLabel: 'Catatan penolakan (opsional)',
                inputPlaceholder: 'Tambahkan alasan penolakan...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Tolak',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#d97706'
            });

            if (!result.isConfirmed) return;

            try {
                const res = await apiRequest(getRejectUrl(id), {
                    method: 'PUT',
                    body: JSON.stringify({ approval_note: String(result.value || '').trim() || null })
                });
                await loadIzinSakitData();
                showAlert('success', res.message || 'Pengajuan ditolak.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            }
        }

        function deleteIzinSakit(id) {
            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data pengajuan tidak ditemukan.');
                return;
            }

            Swal.fire({
                title: 'Hapus pengajuan?',
                html: `Pengajuan untuk <b>${escapeHtml(row.siswa_nama || '-')}</b> akan dihapus.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then(async (result) => {
                if (!result.isConfirmed) return;

                try {
                    const res = await apiRequest(getDestroyUrl(id), { method: 'DELETE' });
                    await loadIzinSakitData();
                    showAlert('success', res.message || 'Pengajuan berhasil dihapus.');
                } catch (err) {
                    showAlert('error', err.message || String(err));
                }
            });
        }

        function handleIzinSakitSearch(value) {
            state.search = String(value || '').trim().toLowerCase();
            state.page = 1;
            applyFilters();
        }

        function handleIzinSakitStatusFilter(value) {
            state.statusFilter = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function handleIzinSakitJenisFilter(value) {
            state.jenisFilter = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function handleIzinSakitDateFilter() {
            state.dateFrom = String(document.getElementById('filter-izin-tanggal-dari')?.value || '').trim();
            state.dateTo = String(document.getElementById('filter-izin-tanggal-sampai')?.value || '').trim();
            state.page = 1;
            applyFilters();
        }

        function handleIzinSakitLimit(value) {
            state.limit = value === 'all' ? Infinity : Math.max(1, parseInt(value, 10) || 10);
            state.page = 1;
            renderRows();
        }

        function changeIzinSakitPage(direction) {
            const totalPages = state.limit === Infinity
                ? 1
                : Math.max(1, Math.ceil(state.filtered.length / state.limit));
            const next = state.page + direction;
            if (next < 1 || next > totalPages) return;
            state.page = next;
            renderRows();
        }

        function refreshIzinSakitData() {
            loadIzinSakitData(true);
        }

        window.showModal = showModal;
        window.closeModal = closeModal;
        window.showAddIzinSakitModal = showAddIzinSakitModal;
        window.saveIzinSakit = saveIzinSakit;
        window.approveIzinSakit = approveIzinSakit;
        window.rejectIzinSakit = rejectIzinSakit;
        window.deleteIzinSakit = deleteIzinSakit;
        window.handleIzinSakitSearch = handleIzinSakitSearch;
        window.handleIzinSakitStatusFilter = handleIzinSakitStatusFilter;
        window.handleIzinSakitJenisFilter = handleIzinSakitJenisFilter;
        window.handleIzinSakitDateFilter = handleIzinSakitDateFilter;
        window.handleIzinSakitLimit = handleIzinSakitLimit;
        window.changeIzinSakitPage = changeIzinSakitPage;
        window.refreshIzinSakitData = refreshIzinSakitData;

        function bootstrapIzinSakitPage() {
            if (document.getElementById('view-izin-sakit')) {
                loadIzinSakitData();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootstrapIzinSakitPage);
        } else {
            bootstrapIzinSakitPage();
        }
    })();
</script>
