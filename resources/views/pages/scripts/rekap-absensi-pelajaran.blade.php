<script>
    (function () {
        const API_BASE = '/api';
        const currentUser = window.APP_CURRENT_USER || null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const dateFromInput = document.getElementById('lessonReportDateFrom');
        const dateToInput = document.getElementById('lessonReportDateTo');
        const classFilter = document.getElementById('lessonReportClassFilter');
        const teacherFilter = document.getElementById('lessonReportTeacherFilter');
        const mapelFilter = document.getElementById('lessonReportMapelFilter');
        const sessionStatusFilter = document.getElementById('lessonReportSessionStatusFilter');
        const searchInput = document.getElementById('lessonReportSearchInput');
        const refreshBtn = document.getElementById('lessonReportRefreshBtn');
        const exportBtn = document.getElementById('lessonReportExportBtn');
        const tabSessionsBtn = document.getElementById('lessonReportTabSessions');
        const tabStudentsBtn = document.getElementById('lessonReportTabStudents');
        const studentSearchWrap = document.getElementById('lessonReportStudentSearchWrap');
        const studentSearchInput = document.getElementById('lessonReportStudentSearchInput');
        const summaryText = document.getElementById('lessonReportSummaryText');
        const loadingState = document.getElementById('lessonReportLoadingState');
        const emptyState = document.getElementById('lessonReportEmptyState');
        const sessionsPanel = document.getElementById('lessonReportSessionsPanel');
        const studentsPanel = document.getElementById('lessonReportStudentsPanel');
        const sessionsBody = document.getElementById('lessonReportSessionsBody');
        const studentsBody = document.getElementById('lessonReportStudentsBody');

        const statsEls = {
            totalSessions: document.getElementById('lessonReportStatSessions'),
            students: document.getElementById('lessonReportStatStudents'),
            hadir: document.getElementById('lessonReportStatHadir'),
            terlambat: document.getElementById('lessonReportStatTerlambat'),
            izin: document.getElementById('lessonReportStatIzin'),
            sakit: document.getElementById('lessonReportStatSakit'),
            alfa: document.getElementById('lessonReportStatAlfa'),
            belum: document.getElementById('lessonReportStatBelum'),
        };

        const detailModal = document.getElementById('lessonReportDetailModal');
        const detailCloseBtn = document.getElementById('lessonReportDetailCloseBtn');
        const detailSubhead = document.getElementById('lessonReportDetailSubhead');
        const detailLoading = document.getElementById('lessonReportDetailLoading');
        const detailContent = document.getElementById('lessonReportDetailContent');
        const detailClass = document.getElementById('lessonReportDetailClass');
        const detailMapel = document.getElementById('lessonReportDetailMapel');
        const detailTeacher = document.getElementById('lessonReportDetailTeacher');
        const detailTime = document.getElementById('lessonReportDetailTime');
        const detailOpened = document.getElementById('lessonReportDetailOpened');
        const detailClosed = document.getElementById('lessonReportDetailClosed');
        const detailBody = document.getElementById('lessonReportDetailBody');
        const exportBtnDefaultHtml = exportBtn ? exportBtn.innerHTML : '';

        if (!dateFromInput || !dateToInput || !classFilter || !teacherFilter || !mapelFilter || !sessionStatusFilter || !sessionsBody || !studentsBody) {
            return;
        }

        const state = {
            activeTab: 'sessions',
            loading: false,
            detailLoading: false,
            exporting: false,
            filters: {},
            data: {
                stats: {
                    total_sessions: 0,
                    students: 0,
                    hadir: 0,
                    terlambat: 0,
                    izin: 0,
                    sakit: 0,
                    alfa: 0,
                    belum: 0,
                },
                options: {
                    kelas: [],
                    guru: [],
                    mapel: [],
                },
                sessions: [],
                students: [],
            },
            detailCache: {},
            studentSearch: '',
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function debounce(fn, delay = 350) {
            let timer = null;
            return (...args) => {
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => fn(...args), delay);
            };
        }

        function resolveActionEndpoint(method) {
            const methodName = String(method || '').trim();
            const endpoint = (window.APP_AJAX_ACTIONS || {})[methodName];

            if (!endpoint) {
                throw new Error(`Endpoint "${methodName}" belum dikonfigurasi.`);
            }

            return endpoint;
        }

        async function runMethod(method, ...args) {
            const payload = { args };
            const token = currentUser ? currentUser.token : null;

            if (token) {
                payload.token = token;
            }

            const response = await fetch(resolveActionEndpoint(method), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || 'Gagal memproses permintaan.');
            }

            return result;
        }

        function notify(type, message) {
            const text = String(message || '').trim();
            if (text === '') return;

            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    icon: type,
                    text,
                    confirmButtonColor: '#4f46e5',
                });
                return;
            }

            window.alert(text);
        }

        function getTodayLocalDate() {
            const now = new Date();
            const offset = now.getTimezoneOffset();
            const local = new Date(now.getTime() - offset * 60000);
            return local.toISOString().slice(0, 10);
        }

        function getMonthStart(dateStr) {
            const date = new Date(`${dateStr}T00:00:00`);
            if (Number.isNaN(date.getTime())) return dateStr;
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            return `${year}-${month}-01`;
        }

        function setDefaultFilters() {
            const today = getTodayLocalDate();
            dateFromInput.value = getMonthStart(today);
            dateToInput.value = today;
            sessionStatusFilter.value = 'closed';
        }

        function getUiFilters() {
            return {
                tanggal_dari: dateFromInput.value || '',
                tanggal_sampai: dateToInput.value || '',
                kelas_id: Number(classFilter.value || 0),
                guru_id: Number(teacherFilter.value || 0),
                mapel: String(mapelFilter.value || '').trim(),
                status_sesi: String(sessionStatusFilter.value || 'closed').trim(),
                search: String(searchInput?.value || '').trim(),
            };
        }

        function renderSelectOptions(selectEl, options, selectedValue, placeholder, valueKey = 'id', labelKey = 'nama') {
            if (!selectEl) return;

            const normalizedSelected = String(selectedValue ?? '');
            const rows = [`<option value="${escapeHtml(placeholder.value)}">${escapeHtml(placeholder.label)}</option>`];

            (Array.isArray(options) ? options : []).forEach((item) => {
                const value = String(item?.[valueKey] ?? '');
                const label = String(item?.[labelKey] ?? '');
                rows.push(`<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
            });

            selectEl.innerHTML = rows.join('');
            if ([...selectEl.options].some((option) => option.value === normalizedSelected)) {
                selectEl.value = normalizedSelected;
            }
        }

        function formatPercent(value) {
            const num = Number(value || 0);
            return `${Number.isFinite(num) ? num.toFixed(1) : '0.0'}%`;
        }

        function formatTimeRange(start, end) {
            const left = String(start || '').trim();
            const right = String(end || '').trim();
            if (left === '' && right === '') return '-';
            if (left !== '' && right !== '') return `${left}-${right}`;
            return left || right || '-';
        }

        function getStatusBadge(label, type = 'default') {
            const styles = {
                default: 'bg-gray-100 text-gray-700 border-gray-200',
                success: 'bg-emerald-100 text-emerald-700 border-emerald-200',
                warning: 'bg-amber-100 text-amber-700 border-amber-200',
                info: 'bg-blue-100 text-blue-700 border-blue-200',
                danger: 'bg-rose-100 text-rose-700 border-rose-200',
                slate: 'bg-slate-100 text-slate-700 border-slate-200',
            };
            const tone = styles[type] || styles.default;
            return `<span class="inline-flex items-center justify-center px-2 py-1 rounded-full border text-[10px] font-bold ${tone}">${escapeHtml(label)}</span>`;
        }

        function getStudentStatusBadge(status) {
            const normalized = String(status || '').trim();
            if (normalized === 'Hadir') return getStatusBadge('Hadir', 'success');
            if (normalized === 'Terlambat') return getStatusBadge('Terlambat', 'warning');
            if (normalized === 'Izin') return getStatusBadge('Izin', 'info');
            if (normalized === 'Sakit') return getStatusBadge('Sakit', 'slate');
            if (normalized === 'Alfa') return getStatusBadge('Alfa', 'danger');
            return getStatusBadge('Belum Absen', 'default');
        }

        function getSessionStatusLabel(status) {
            const normalized = String(status || '').trim().toLowerCase();
            if (normalized === 'closed') return 'Ditutup';
            if (normalized === 'open') return 'Berjalan';
            if (normalized === 'not_started') return 'Belum Mulai';
            return '-';
        }

        function setExporting(isExporting) {
            state.exporting = !!isExporting;
            if (!exportBtn) return;

            exportBtn.disabled = state.exporting;
            exportBtn.classList.toggle('opacity-70', state.exporting);
            exportBtn.classList.toggle('cursor-not-allowed', state.exporting);
            exportBtn.innerHTML = state.exporting
                ? '<i class="fas fa-spinner fa-spin"></i><span>Menyiapkan...</span>'
                : exportBtnDefaultHtml;
        }

        function setLoading(isLoading) {
            state.loading = !!isLoading;
            loadingState?.classList.toggle('hidden', !state.loading);
            if (state.loading) {
                emptyState?.classList.add('hidden');
                sessionsPanel?.classList.add('hidden');
                studentsPanel?.classList.add('hidden');
            }
        }

        function renderStats() {
            const stats = state.data.stats || {};
            if (statsEls.totalSessions) statsEls.totalSessions.textContent = String(stats.total_sessions || 0);
            if (statsEls.students) statsEls.students.textContent = String(stats.students || 0);
            if (statsEls.hadir) statsEls.hadir.textContent = String(stats.hadir || 0);
            if (statsEls.terlambat) statsEls.terlambat.textContent = String(stats.terlambat || 0);
            if (statsEls.izin) statsEls.izin.textContent = String(stats.izin || 0);
            if (statsEls.sakit) statsEls.sakit.textContent = String(stats.sakit || 0);
            if (statsEls.alfa) statsEls.alfa.textContent = String(stats.alfa || 0);
            if (statsEls.belum) statsEls.belum.textContent = String(stats.belum || 0);
        }

        function renderSummaryText() {
            const sessionsCount = Array.isArray(state.data.sessions) ? state.data.sessions.length : 0;
            const studentsCount = Array.isArray(state.data.students) ? state.data.students.length : 0;
            const visibleStudentsCount = getVisibleStudentRows().length;
            if (summaryText) {
                if (state.activeTab === 'students') {
                    summaryText.textContent = state.studentSearch.trim() !== ''
                        ? `Menampilkan ${visibleStudentsCount} dari ${studentsCount} siswa.`
                        : `Menampilkan ${studentsCount} siswa.`;
                    return;
                }

                summaryText.textContent = `Menampilkan ${sessionsCount} sesi.`;
            }
        }

        function renderSessionsTable() {
            const rows = Array.isArray(state.data.sessions) ? state.data.sessions : [];
            if (rows.length === 0) {
                sessionsBody.innerHTML = '<tr><td colspan="14" class="p-10 text-center text-gray-400">Tidak ada sesi pelajaran pada filter ini.</td></tr>';
                return;
            }

            sessionsBody.innerHTML = rows.map((row, index) => {
                const statusBadge = row.status_sesi === 'closed'
                    ? getStatusBadge('Ditutup', 'danger')
                    : getStatusBadge('Berjalan', 'success');

                return `
                    <tr class="hover:bg-gray-50/70 transition">
                        <td class="px-4 py-3 text-center text-xs text-gray-500">${index + 1}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.tanggal_label || '-')}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(row.tanggal || '-')}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.kelas_nama || '-')} - ${escapeHtml(row.mata_pelajaran || '-')}</div>
                            <div class="text-xs text-gray-400">Jam ${escapeHtml(formatTimeRange(row.jam_mulai, row.jam_selesai))}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-700">${escapeHtml(row.guru_nama || '-')}</div>
                            <div class="text-xs text-gray-400">Dimulai ${escapeHtml(row.opened_at || '-')} | Ditutup ${escapeHtml(row.closed_at || '-')}</div>
                        </td>
                        <td class="px-4 py-3 text-center">${statusBadge}</td>
                        <td class="px-4 py-3 text-center font-bold text-gray-700">${Number(row.total_siswa || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-emerald-600">${Number(row.hadir || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-amber-600">${Number(row.terlambat || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-blue-600">${Number(row.izin || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-600">${Number(row.sakit || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-rose-600">${Number(row.alfa || 0)}</td>
                        <td class="px-4 py-3 text-center font-semibold text-gray-500">${Number(row.belum || 0)}</td>
                        <td class="px-4 py-3 text-center font-bold text-indigo-600">${escapeHtml(formatPercent(row.kehadiran_rate || 0))}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 text-xs font-bold hover:bg-indigo-100 transition" data-session-detail="${Number(row.session_id || 0)}">
                                <i class="fas fa-list"></i>
                                <span>Detail</span>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getVisibleStudentRows() {
            const rows = Array.isArray(state.data.students) ? state.data.students : [];
            const keyword = String(state.studentSearch || '').trim().toLowerCase();
            if (keyword === '') {
                return rows;
            }

            return rows.filter((row) => {
                const haystack = `${row.nisn || ''} ${row.nama || ''} ${row.kelas_nama || ''}`.toLowerCase();
                return haystack.includes(keyword);
            });
        }

        function renderStudentsTable() {
            const rows = getVisibleStudentRows();
            if (rows.length === 0) {
                const message = state.studentSearch !== ''
                    ? 'Tidak ada siswa yang cocok dengan pencarian.'
                    : 'Tidak ada rekap siswa pada filter ini.';
                studentsBody.innerHTML = `<tr><td colspan="12" class="p-10 text-center text-gray-400">${escapeHtml(message)}</td></tr>`;
                return;
            }

            studentsBody.innerHTML = rows.map((row, index) => `
                <tr class="hover:bg-gray-50/70 transition">
                    <td class="px-4 py-3 text-center text-xs text-gray-500">${index + 1}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">${escapeHtml(row.nisn || '-')}</td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-gray-800">${escapeHtml(row.nama || '-')}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">${escapeHtml(row.kelas_nama || '-')}</td>
                    <td class="px-4 py-3 text-center font-bold text-gray-700">${Number(row.total_sesi || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-emerald-600">${Number(row.hadir || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-amber-600">${Number(row.terlambat || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-blue-600">${Number(row.izin || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-slate-600">${Number(row.sakit || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-rose-600">${Number(row.alfa || 0)}</td>
                    <td class="px-4 py-3 text-center font-semibold text-gray-500">${Number(row.belum || 0)}</td>
                    <td class="px-4 py-3 text-center font-bold text-indigo-600">${escapeHtml(formatPercent(row.kehadiran_rate || 0))}</td>
                </tr>
            `).join('');
        }

        function renderTabState() {
            const isSessionsTab = state.activeTab === 'sessions';
            tabSessionsBtn?.classList.toggle('bg-white', isSessionsTab);
            tabSessionsBtn?.classList.toggle('text-indigo-600', isSessionsTab);
            tabSessionsBtn?.classList.toggle('shadow-sm', isSessionsTab);
            tabSessionsBtn?.classList.toggle('text-gray-500', !isSessionsTab);

            tabStudentsBtn?.classList.toggle('bg-white', !isSessionsTab);
            tabStudentsBtn?.classList.toggle('text-indigo-600', !isSessionsTab);
            tabStudentsBtn?.classList.toggle('shadow-sm', !isSessionsTab);
            tabStudentsBtn?.classList.toggle('text-gray-500', isSessionsTab);

            sessionsPanel?.classList.toggle('hidden', !isSessionsTab || state.loading);
            studentsPanel?.classList.toggle('hidden', isSessionsTab || state.loading);
            studentSearchWrap?.classList.toggle('hidden', isSessionsTab);
        }

        function renderEmptyState() {
            const hasData = (state.data.sessions || []).length > 0 || (state.data.students || []).length > 0;
            emptyState?.classList.toggle('hidden', state.loading || hasData);
            if (!state.loading) {
                sessionsPanel?.classList.toggle('hidden', !hasData || state.activeTab !== 'sessions');
                studentsPanel?.classList.toggle('hidden', !hasData || state.activeTab !== 'students');
            }
        }

        function renderAll() {
            renderStats();
            renderSummaryText();
            renderSessionsTable();
            renderStudentsTable();
            renderTabState();
            renderEmptyState();
        }

        async function loadReport() {
            setLoading(true);
            state.studentSearch = '';
            if (studentSearchInput) studentSearchInput.value = '';

            try {
                const filters = getUiFilters();
                state.filters = filters;
                const result = await runMethod('getPelajaranReportData', filters);
                if (!result?.success) {
                    throw new Error(result?.message || 'Gagal memuat laporan.');
                }

                const data = result.data || {};
                state.data = {
                    stats: data.stats || {},
                    options: data.options || {},
                    sessions: Array.isArray(data.sessions) ? data.sessions : [],
                    students: Array.isArray(data.students) ? data.students : [],
                };
                state.detailCache = {};

                renderSelectOptions(classFilter, state.data.options.kelas || [], filters.kelas_id || 0, { value: '0', label: 'Semua Kelas' });
                renderSelectOptions(teacherFilter, state.data.options.guru || [], filters.guru_id || 0, { value: '0', label: 'Semua Guru' });
                renderSelectOptions(
                    mapelFilter,
                    (state.data.options.mapel || []).map((item) => ({ value: item, label: item })),
                    filters.mapel || '',
                    { value: '', label: 'Semua Mapel' },
                    'value',
                    'label'
                );

                renderAll();
            } catch (error) {
                state.data = {
                    stats: {
                        total_sessions: 0,
                        students: 0,
                        hadir: 0,
                        terlambat: 0,
                        izin: 0,
                        sakit: 0,
                        alfa: 0,
                        belum: 0,
                    },
                    options: { kelas: [], guru: [], mapel: [] },
                    sessions: [],
                    students: [],
                };
                renderAll();
                notify('error', error.message || 'Gagal memuat laporan absensi pelajaran.');
            } finally {
                setLoading(false);
                renderAll();
            }
        }

        function openDetailModal() {
            detailModal?.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeDetailModal() {
            detailModal?.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function setDetailLoading(isLoading) {
            state.detailLoading = !!isLoading;
            detailLoading?.classList.toggle('hidden', !state.detailLoading);
            detailContent?.classList.toggle('hidden', state.detailLoading);
        }

        function renderDetailSession(payload) {
            const session = payload?.session || {};
            const students = Array.isArray(payload?.students) ? payload.students : [];

            detailSubhead.textContent = `${session.tanggal || '-'} | ${session.kelas?.nama || '-'} | ${session.mata_pelajaran || '-'}`;
            detailClass.textContent = session.kelas?.nama || '-';
            detailMapel.textContent = session.mata_pelajaran || '-';
            detailTeacher.textContent = session.guru?.nama || '-';
            detailTime.textContent = formatTimeRange(session.jam_mulai, session.jam_selesai);
            detailOpened.textContent = session.opened_at || '-';
            detailClosed.textContent = session.closed_at || '-';

            if (students.length === 0) {
                detailBody.innerHTML = '<tr><td colspan="6" class="p-10 text-center text-gray-400">Tidak ada data siswa pada sesi ini.</td></tr>';
                return;
            }

            detailBody.innerHTML = students.map((row, index) => `
                <tr class="hover:bg-gray-50/70 transition">
                    <td class="px-4 py-3 text-center text-xs text-gray-500">${index + 1}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700">${escapeHtml(row.nisn || '-')}</td>
                    <td class="px-4 py-3 font-semibold text-gray-800">${escapeHtml(row.nama || '-')}</td>
                    <td class="px-4 py-3 text-center">${getStudentStatusBadge(row.status || '')}</td>
                    <td class="px-4 py-3 text-center text-gray-600">${escapeHtml(row.method || '-')}</td>
                    <td class="px-4 py-3 text-center text-gray-600">${escapeHtml(row.recorded_at || '-')}</td>
                </tr>
            `).join('');
        }

        async function loadSessionDetail(sessionId) {
            if (!Number(sessionId)) return;

            openDetailModal();
            setDetailLoading(true);
            detailBody.innerHTML = '<tr><td colspan="6" class="p-10 text-center text-gray-400">Memuat detail sesi...</td></tr>';

            try {
                const payload = await fetchSessionDetailData(Number(sessionId));
                renderDetailSession(payload);
            } catch (error) {
                detailBody.innerHTML = `<tr><td colspan="6" class="p-10 text-center text-rose-500">${escapeHtml(error.message || 'Gagal memuat detail sesi.')}</td></tr>`;
                notify('error', error.message || 'Gagal memuat detail sesi.');
            } finally {
                setDetailLoading(false);
            }
        }

        async function fetchSessionDetailData(sessionId) {
            const normalizedId = Number(sessionId || 0);
            if (!normalizedId) {
                throw new Error('Sesi pelajaran tidak valid.');
            }

            if (Object.prototype.hasOwnProperty.call(state.detailCache, normalizedId)) {
                return state.detailCache[normalizedId];
            }

            const result = await runMethod('getPelajaranReportSessionDetail', normalizedId);
            if (!result?.success) {
                throw new Error(result?.message || 'Gagal memuat detail sesi.');
            }

            const payload = result.data || {};
            state.detailCache[normalizedId] = payload;

            return payload;
        }

        async function exportReport() {
            const sessions = Array.isArray(state.data.sessions) ? state.data.sessions : [];
            const students = getVisibleStudentRows();
            if (sessions.length === 0 && students.length === 0) {
                notify('info', 'Tidak ada data untuk diexport.');
                return;
            }

            setExporting(true);

            try {
                const result = await runMethod('generateExcel', 'laporan_absensi_pelajaran', getUiFilters());
                if (!result?.success || !result?.url) {
                    throw new Error(result?.message || 'Gagal menyiapkan file export.');
                }

                const link = document.createElement('a');
                link.href = String(result.url);
                link.setAttribute('download', '');
                document.body.appendChild(link);
                link.click();
                link.remove();
            } catch (error) {
                notify('error', error.message || 'Gagal menyiapkan export Excel.');
            } finally {
                setExporting(false);
            }
        }

        const debouncedLoadReport = debounce(() => {
            loadReport();
        }, 400);

        refreshBtn?.addEventListener('click', () => {
            loadReport();
        });

        exportBtn?.addEventListener('click', () => {
            exportReport();
        });

        [dateFromInput, dateToInput, classFilter, teacherFilter, mapelFilter, sessionStatusFilter].forEach((element) => {
            element?.addEventListener('change', () => {
                loadReport();
            });
        });

        searchInput?.addEventListener('input', () => {
            debouncedLoadReport();
        });

        studentSearchInput?.addEventListener('input', (event) => {
            state.studentSearch = String(event.target?.value || '');
            renderStudentsTable();
            renderSummaryText();
        });

        tabSessionsBtn?.addEventListener('click', () => {
            state.activeTab = 'sessions';
            renderSummaryText();
            renderTabState();
            renderEmptyState();
        });

        tabStudentsBtn?.addEventListener('click', () => {
            state.activeTab = 'students';
            renderSummaryText();
            renderTabState();
            renderEmptyState();
        });

        sessionsBody?.addEventListener('click', (event) => {
            const target = event.target instanceof Element
                ? event.target.closest('[data-session-detail]')
                : null;
            if (!target) return;

            const sessionId = Number(target.getAttribute('data-session-detail') || 0);
            loadSessionDetail(sessionId);
        });

        detailCloseBtn?.addEventListener('click', closeDetailModal);
        detailModal?.addEventListener('click', (event) => {
            if (event.target === detailModal || event.target === detailModal.querySelector('.absolute.inset-0')) {
                closeDetailModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) {
                closeDetailModal();
            }
        });

        setDefaultFilters();
        renderAll();
        loadReport();
    })();
</script>
