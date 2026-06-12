<script>
    (function () {
        const API_BASE = '/api';
        const currentUser = window.APP_CURRENT_USER || null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const dateLabel = document.getElementById('lessonDateLabel');
        const refreshBtn = document.getElementById('lessonRefreshBtn');
        const sessionSelect = document.getElementById('lessonSessionSelect');
        const sessionInfo = document.getElementById('lessonSessionInfo');
        const sessionActionBtn = document.getElementById('lessonSessionActionBtn');
        const broadcastBtn = document.getElementById('lessonBroadcastHadirBtn');
        const modeQrBtn = document.getElementById('lessonModeQrBtn');
        const modeRfidBtn = document.getElementById('lessonModeRfidBtn');
        const qrPanel = document.getElementById('lessonQrPanel');
        const rfidPanel = document.getElementById('lessonRfidPanel');
        const qrOpenBtn = document.getElementById('lessonQrOpenBtn');
        const manualQrInput = document.getElementById('lessonManualQrInput');
        const rfidInput = document.getElementById('lessonRfidInput');
        const pollingStatus = document.getElementById('lessonPollingStatus');
        const pollingStatusText = pollingStatus ? pollingStatus.querySelector('span') : null;
        const lastResultBox = document.getElementById('lessonLastResult');
        const statTotal = document.getElementById('lessonStatTotal');
        const statRecorded = document.getElementById('lessonStatRecorded');
        const statBelum = document.getElementById('lessonStatBelum');
        const rosterBody = document.getElementById('lessonRosterBody');

        if (!dateLabel || !sessionSelect || !sessionInfo || !sessionActionBtn || !rosterBody) {
            return;
        }

        const STATUS_OPTIONS = [
            { value: 'Belum Absen', label: 'Belum' },
            { value: 'Hadir', label: 'Hadir' },
            { value: 'Terlambat', label: 'Terlambat' },
            { value: 'Izin', label: 'Izin' },
            { value: 'Sakit', label: 'Sakit' },
            { value: 'Alfa', label: 'Alfa' },
        ];

        let sessionList = [];
        let activeJadwalId = 0;
        let activeSession = null;
        let activeMode = 'qr';
        let rosterRowMap = new Map();
        let actionBusy = false;
        let sessionDetailLoading = false;
        let sessionDetailRequestSeq = 0;
        let broadcastBusy = false;
        let cameraPopup = null;
        let popupWatchInterval = null;
        let lastScanKey = '';
        let lastScanAt = 0;
        const SCAN_DEBOUNCE_MS = 1500;

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

        function normalizeId(value) {
            const num = Number(value);
            return Number.isFinite(num) ? Math.trunc(num) : 0;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function setLastResult(type, message) {
            if (!lastResultBox) return;

            const text = String(message || '').trim();
            lastResultBox.classList.remove(
                'hidden',
                'bg-emerald-50',
                'border-emerald-200',
                'text-emerald-700',
                'bg-red-50',
                'border-red-200',
                'text-red-700',
                'bg-blue-50',
                'border-blue-200',
                'text-blue-700'
            );

            if (text === '') {
                lastResultBox.classList.add('hidden');
                lastResultBox.textContent = '';
                return;
            }

            lastResultBox.textContent = text;

            if (type === 'success') {
                lastResultBox.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
            } else if (type === 'error') {
                lastResultBox.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
            } else {
                lastResultBox.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-700');
            }
        }

        function setDateText(dateStr) {
            if (!dateStr) {
                dateLabel.textContent = '-';
                return;
            }

            try {
                const dateObj = new Date(`${dateStr}T00:00:00`);
                dateLabel.textContent = new Intl.DateTimeFormat('id-ID', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    timeZone: window.APP_TIMEZONE || 'Asia/Jakarta',
                }).format(dateObj);
            } catch (error) {
                dateLabel.textContent = dateStr;
            }
        }

        function getScheduleItem(jadwalId) {
            return sessionList.find((item) => normalizeId(item.jadwal_id) === normalizeId(jadwalId)) || null;
        }

        function getSessionStatus() {
            return String(activeSession?.status || '').toLowerCase();
        }

        function isSessionSelected() {
            return activeJadwalId > 0;
        }

        function isSessionOpen() {
            return !!(activeSession && normalizeId(activeSession.id) > 0 && getSessionStatus() === 'open');
        }

        function isSessionClosed() {
            return getSessionStatus() === 'closed';
        }

        function isSessionNotStarted() {
            return !isSessionSelected() || !activeSession || getSessionStatus() === 'not_started' || normalizeId(activeSession.id) <= 0;
        }

        function getSessionStatusLabel(item) {
            if (!item || !item.sesi_id) return 'BELUM MULAI';
            const status = String(item.sesi_status || '').toLowerCase();
            if (status === 'closed' || item.sesi_closed_at) return 'DITUTUP';
            return 'BERJALAN';
        }

        function renderSessionSelect(list) {
            sessionList = Array.isArray(list) ? list : [];

            if (sessionList.length === 0) {
                sessionSelect.innerHTML = '<option value="0">Tidak ada jadwal pelajaran hari ini.</option>';
                sessionSelect.value = '0';
                return;
            }

            const options = ['<option value="0">Pilih sesi pelajaran...</option>'];

            sessionList.forEach((item) => {
                const jadwalId = normalizeId(item.jadwal_id);
                const jamMulai = escapeHtml(String(item.jam_mulai || ''));
                const jamSelesai = escapeHtml(String(item.jam_selesai || ''));
                const kelas = escapeHtml(String(item.kelas_nama || '-'));
                const mapel = escapeHtml(String(item.mata_pelajaran || '-'));
                const status = escapeHtml(getSessionStatusLabel(item));
                options.push(
                    `<option value="${jadwalId}">${jamMulai}-${jamSelesai} - ${kelas} - ${mapel} - ${status}</option>`
                );
            });

            sessionSelect.innerHTML = options.join('');

            if (activeJadwalId > 0 && sessionList.some((item) => normalizeId(item.jadwal_id) === activeJadwalId)) {
                sessionSelect.value = String(activeJadwalId);
            } else {
                sessionSelect.value = '0';
            }
        }

        function buildStatusBadge(label, tone) {
            const toneMap = {
                gray: 'bg-gray-100 text-gray-700 border-gray-200',
                emerald: 'bg-emerald-100 text-emerald-700 border-emerald-200',
                red: 'bg-red-100 text-red-700 border-red-200',
                blue: 'bg-blue-100 text-blue-700 border-blue-200',
            };
            const toneClass = toneMap[tone] || toneMap.gray;
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] font-bold ${toneClass}">${escapeHtml(label)}</span>`;
        }

        function renderSessionInfo() {
            if (!isSessionSelected() || !activeSession) {
                sessionInfo.classList.add('hidden');
                sessionInfo.innerHTML = '';
                return;
            }

            const item = getScheduleItem(activeJadwalId);
            const kelas = escapeHtml(String(activeSession?.kelas?.nama || item?.kelas_nama || '-'));
            const mapel = escapeHtml(String(activeSession?.mata_pelajaran || item?.mata_pelajaran || '-'));
            const jamMulai = escapeHtml(String(activeSession?.jam_mulai || item?.jam_mulai || '-'));
            const jamSelesai = escapeHtml(String(activeSession?.jam_selesai || item?.jam_selesai || '-'));
            const startedAt = String(activeSession?.opened_at || '').trim();
            const closedAt = String(activeSession?.closed_at || '').trim();

            let statusBadge = buildStatusBadge('BELUM MULAI', 'gray');
            if (isSessionOpen()) {
                statusBadge = buildStatusBadge('BERJALAN', 'emerald');
            } else if (isSessionClosed()) {
                statusBadge = buildStatusBadge(`DITUTUP - ${closedAt || '-'}`, 'red');
            }

            const startedBadge = startedAt !== ''
                ? buildStatusBadge(`DIMULAI - ${startedAt}`, 'blue')
                : '';

            sessionInfo.classList.remove('hidden');
            sessionInfo.innerHTML = `
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold text-gray-800 text-sm break-words">${kelas} - ${mapel}</div>
                        <div class="text-[11px] text-gray-600 mt-0.5">Jam: ${jamMulai}-${jamSelesai}</div>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        ${startedBadge}
                        ${statusBadge}
                    </div>
                </div>
            `;
        }

        function updateStats(total, recorded) {
            const safeTotal = Number(total) || 0;
            const safeRecorded = Number(recorded) || 0;
            const safeBelum = Math.max(0, safeTotal - safeRecorded);
            if (statTotal) statTotal.textContent = `${safeTotal} Total`;
            if (statRecorded) statRecorded.textContent = `${safeRecorded} Tercatat`;
            if (statBelum) statBelum.textContent = `${safeBelum} Belum`;
        }

        function getSelectTone(status) {
            const value = String(status || 'Belum Absen');
            if (value === 'Hadir') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            if (value === 'Terlambat') return 'bg-amber-50 text-amber-700 border-amber-200';
            if (value === 'Izin') return 'bg-sky-50 text-sky-700 border-sky-200';
            if (value === 'Sakit') return 'bg-violet-50 text-violet-700 border-violet-200';
            if (value === 'Alfa') return 'bg-red-50 text-red-700 border-red-200';
            return 'bg-gray-50 text-gray-600 border-gray-200';
        }

        function applySelectClass(selectEl, status, disabled) {
            if (!selectEl) return;
            selectEl.className = `text-[11px] font-bold py-1.5 px-2 rounded-lg border w-28 focus:ring-2 focus:ring-indigo-500 transition ${getSelectTone(status)}${disabled ? ' opacity-70 cursor-not-allowed' : ''}`;
            selectEl.disabled = !!disabled;
        }

        function buildEmptyRosterRow(title, subtitle, iconClass) {
            return `
                <tr>
                    <td colspan="5" class="py-14 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <i class="${iconClass} text-5xl text-gray-200"></i>
                            <p class="font-semibold text-sm text-gray-400">${escapeHtml(title)}</p>
                            <p class="text-xs text-gray-300">${escapeHtml(subtitle)}</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function buildLoadingRosterRow(message) {
            return `
                <tr>
                    <td colspan="5" class="py-14 text-center">
                        <div class="flex flex-col items-center gap-3">
                            <i class="fas fa-circle-notch fa-spin text-3xl text-indigo-300"></i>
                            <p class="font-semibold text-sm text-gray-500">${escapeHtml(message || 'Memuat data siswa...')}</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function recalculateStatsFromRows() {
            let total = 0;
            let recorded = 0;

            rosterRowMap.forEach((row) => {
                total += 1;
                if (String(row.status || '').trim() !== '') {
                    recorded += 1;
                }
            });

            updateStats(total, recorded);
            updateBroadcastButton();
        }

        function renderRoster(payload) {
            rosterRowMap = new Map();
            const students = Array.isArray(payload?.students) ? payload.students : [];

            if (students.length === 0) {
                rosterBody.innerHTML = buildEmptyRosterRow('Tidak ada siswa pada kelas ini.', 'Daftar siswa tidak tersedia.', 'fas fa-users');
                updateStats(0, 0);
                updateBroadcastButton();
                return;
            }

            rosterBody.innerHTML = students.map((student, index) => {
                const siswaId = normalizeId(student.id);
                const currentStatus = String(student.status || '').trim();
                const displayStatus = currentStatus !== '' ? currentStatus : 'Belum Absen';
                const optionsHtml = STATUS_OPTIONS.map((option) => {
                    const selected = option.value === displayStatus ? 'selected' : '';
                    return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
                }).join('');

                return `
                    <tr data-siswa-id="${siswaId}" class="border-b border-gray-50">
                        <td class="px-3 py-3 text-center text-xs text-gray-400 font-mono">${index + 1}</td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-sm text-gray-900">${escapeHtml(student.nama || '-')}</div>
                            <div class="text-[10px] text-gray-400 font-mono">${escapeHtml(student.nisn || '-')}</div>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <select data-status-select class="text-[11px] font-bold py-1.5 px-2 rounded-lg border w-28">
                                ${optionsHtml}
                            </select>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span data-method class="text-[10px] font-bold text-gray-500">${escapeHtml(student.method || '-')}</span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span data-time class="text-[10px] font-mono text-gray-500">${escapeHtml(student.recorded_at || '-')}</span>
                        </td>
                    </tr>
                `;
            }).join('');

            rosterBody.querySelectorAll('tr[data-siswa-id]').forEach((row) => {
                const siswaId = normalizeId(row.getAttribute('data-siswa-id'));
                const selectEl = row.querySelector('[data-status-select]');
                const methodEl = row.querySelector('[data-method]');
                const timeEl = row.querySelector('[data-time]');
                const status = selectEl ? String(selectEl.value || '') : '';

                rosterRowMap.set(siswaId, {
                    row,
                    selectEl,
                    methodEl,
                    timeEl,
                    status: status === 'Belum Absen' ? '' : status,
                });

                applySelectClass(selectEl, status, !isSessionOpen());

                if (selectEl) {
                    selectEl.addEventListener('change', async () => {
                        const previousValue = rosterRowMap.get(siswaId)?.status || '';
                        const requestedStatus = String(selectEl.value || 'Belum Absen');
                        const previousSelectValue = previousValue !== '' ? previousValue : 'Belum Absen';

                        if (!isSessionOpen()) {
                            selectEl.value = previousSelectValue;
                            applySelectClass(selectEl, previousSelectValue, true);
                            return;
                        }

                        applySelectClass(selectEl, requestedStatus, true);

                        try {
                            const result = await runMethod('setPelajaranAbsensiStatus', normalizeId(activeSession.id), siswaId, requestedStatus);
                            if (!result?.success) {
                                throw new Error(result?.message || 'Gagal memperbarui absensi.');
                            }

                            applyRowUpdate(result.data);
                            recalculateStatsFromRows();
                        } catch (error) {
                            selectEl.value = previousSelectValue;
                            applySelectClass(selectEl, previousSelectValue, false);
                            setLastResult('error', error.message || 'Gagal memperbarui absensi.');
                        }
                    });
                }
            });

            if (payload?.stats) {
                updateStats(payload.stats.total, payload.stats.recorded);
            } else {
                recalculateStatsFromRows();
            }
        }

        function applyRowUpdate(data) {
            if (!data) return;

            const siswaId = normalizeId(data.siswa_id);
            const row = rosterRowMap.get(siswaId);
            if (!row) return;

            const rawStatus = String(data.status || '').trim();
            const selectStatus = rawStatus !== '' ? rawStatus : 'Belum Absen';
            row.status = rawStatus;

            if (row.selectEl) {
                row.selectEl.value = selectStatus;
                applySelectClass(row.selectEl, selectStatus, !isSessionOpen());
            }
            if (row.methodEl) {
                row.methodEl.textContent = String(data.method || '').trim() || '-';
            }
            if (row.timeEl) {
                row.timeEl.textContent = String(data.recorded_at || '').trim() || '-';
            }
        }

        function resetPageState() {
            sessionDetailLoading = false;
            activeSession = null;
            rosterRowMap = new Map();
            sessionInfo.classList.add('hidden');
            sessionInfo.innerHTML = '';
            rosterBody.innerHTML = buildEmptyRosterRow('Pilih sesi pelajaran untuk memulai', 'Daftar siswa akan muncul di sini', 'fas fa-book-open');
            updateStats(0, 0);
            updateActionButton();
            updateBroadcastButton();
            updateInteractionState();
            stopPopupPolling();
        }

        function updateActionButton() {
            if (!sessionActionBtn) return;

            let icon = 'fas fa-ban';
            let label = 'Jadwal Belum Dipilih';
            let enabled = false;
            let buttonClass = 'w-full bg-gray-200 text-gray-500 py-3 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 cursor-not-allowed';

            if (sessionDetailLoading) {
                icon = 'fas fa-circle-notch fa-spin';
                label = 'Memuat Sesi...';
            } else if (actionBusy) {
                icon = 'fas fa-circle-notch fa-spin';
                label = 'Memproses...';
            } else if (isSessionSelected()) {
                if (isSessionClosed()) {
                    icon = 'fas fa-lock';
                    label = 'Sesi Ditutup';
                } else if (isSessionOpen()) {
                    icon = 'fas fa-door-closed';
                    label = 'Tutup Sesi';
                    enabled = true;
                    buttonClass = 'w-full bg-rose-600 hover:bg-rose-700 active:scale-95 text-white py-3 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 shadow-md shadow-rose-200';
                } else {
                    icon = 'fas fa-play';
                    label = 'Mulai Sesi';
                    enabled = true;
                    buttonClass = 'w-full bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white py-3 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 shadow-md shadow-emerald-200';
                }
            }

            sessionActionBtn.disabled = !enabled;
            sessionActionBtn.className = enabled ? buttonClass : 'w-full bg-gray-200 text-gray-500 py-3 rounded-xl font-bold text-xs transition flex items-center justify-center gap-2 cursor-not-allowed';
            sessionActionBtn.innerHTML = `<i class="${icon}"></i><span>${escapeHtml(label)}</span>`;
        }

        function updateBroadcastButton() {
            if (!broadcastBtn) return;

            let label = 'Broadcast Hadir';
            let enabled = false;
            let buttonClass = 'shrink-0 bg-gray-100 text-gray-400 px-3 py-1.5 rounded-lg font-bold text-[11px] transition flex items-center justify-center gap-2 cursor-not-allowed border border-gray-200 whitespace-nowrap';

            if (broadcastBusy) {
                label = 'Broadcast...';
            } else if (isSessionOpen()) {
                const belum = Math.max(0, Number((statBelum?.textContent || '0').split(' ')[0]) || 0);
                if (belum > 0) {
                    enabled = true;
                    label = `Broadcast Hadir (${belum})`;
                    buttonClass = 'shrink-0 bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg font-bold text-[11px] transition flex items-center justify-center gap-2 border border-emerald-200 hover:bg-emerald-100 whitespace-nowrap';
                } else {
                    label = 'Semua Sudah Terisi';
                }
            }

            broadcastBtn.disabled = !enabled;
            broadcastBtn.className = enabled ? buttonClass : 'shrink-0 bg-gray-100 text-gray-400 px-3 py-1.5 rounded-lg font-bold text-[11px] transition flex items-center justify-center gap-2 cursor-not-allowed border border-gray-200 whitespace-nowrap';
            broadcastBtn.innerHTML = `<i class="fas fa-bullhorn"></i><span>${escapeHtml(label)}</span>`;
        }

        function updateInteractionState() {
            const canInteract = isSessionOpen();

            if (qrOpenBtn) {
                qrOpenBtn.disabled = !canInteract;
                qrOpenBtn.className = canInteract
                    ? 'w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 shadow-md shadow-indigo-200'
                    : 'w-full bg-gray-100 text-gray-400 py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 cursor-not-allowed border border-gray-200';
            }

            if (manualQrInput) {
                manualQrInput.disabled = !canInteract;
            }
            if (rfidInput) {
                rfidInput.disabled = !canInteract;
            }

            rosterRowMap.forEach((row) => {
                const selectStatus = row.status !== '' ? row.status : 'Belum Absen';
                applySelectClass(row.selectEl, selectStatus, !canInteract);
            });
        }

        function setMode(mode) {
            activeMode = mode === 'rfid' ? 'rfid' : 'qr';

            if (modeQrBtn) {
                modeQrBtn.className = activeMode === 'qr'
                    ? 'px-3 py-2 rounded-xl text-xs font-bold border border-indigo-200 bg-indigo-600 text-white shadow-sm'
                    : 'px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-gray-100 text-gray-600';
            }

            if (modeRfidBtn) {
                modeRfidBtn.className = activeMode === 'rfid'
                    ? 'px-3 py-2 rounded-xl text-xs font-bold border border-indigo-200 bg-indigo-600 text-white shadow-sm'
                    : 'px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 bg-gray-100 text-gray-600';
            }

            if (qrPanel) qrPanel.classList.toggle('hidden', activeMode !== 'qr');
            if (rfidPanel) rfidPanel.classList.toggle('hidden', activeMode !== 'rfid');
        }

        async function loadSessionDetail(jadwalId, options = {}) {
            const normalizedId = normalizeId(jadwalId);
            const preserveView = !!options.preserveView;
            const clearLastResultOnSuccess = options.clearLastResultOnSuccess !== false;
            if (normalizedId <= 0) {
                sessionDetailRequestSeq += 1;
                activeJadwalId = 0;
                resetPageState();
                return;
            }

            const requestSeq = ++sessionDetailRequestSeq;
            activeJadwalId = normalizedId;
            if (!preserveView) {
                activeSession = null;
                sessionDetailLoading = true;
                rosterRowMap = new Map();
                sessionInfo.classList.add('hidden');
                sessionInfo.innerHTML = '';
                rosterBody.innerHTML = buildLoadingRosterRow('Memuat daftar siswa...');
                updateStats(0, 0);
                updateActionButton();
                updateInteractionState();
                updateBroadcastButton();
                stopPopupPolling();
            }

            try {
                const result = await runMethod('getPelajaranSessionDetail', normalizedId);
                if (!result?.success) {
                    throw new Error(result?.message || 'Gagal memuat sesi.');
                }
                if (requestSeq !== sessionDetailRequestSeq) return;

                activeSession = result.data?.session || null;
                sessionDetailLoading = false;

                renderSessionInfo();
                renderRoster(result.data);
                updateActionButton();
                updateBroadcastButton();
                updateInteractionState();
                if (clearLastResultOnSuccess) {
                    setLastResult('', '');
                }
            } catch (error) {
                if (requestSeq !== sessionDetailRequestSeq) return;
                if (!preserveView) {
                    activeSession = null;
                    sessionDetailLoading = false;
                    sessionInfo.classList.add('hidden');
                    sessionInfo.innerHTML = '';
                    rosterBody.innerHTML = buildEmptyRosterRow('Gagal memuat siswa', 'Coba pilih sesi lagi atau refresh halaman.', 'fas fa-exclamation-circle');
                    updateStats(0, 0);
                    updateActionButton();
                    updateBroadcastButton();
                    updateInteractionState();
                }
                setLastResult('error', error.message || 'Gagal memuat sesi.');
            }
        }

        async function loadSessions(options = {}) {
            const hadSessions = sessionList.length > 0;
            const skipActiveDetailReload = !!options.skipActiveDetailReload;
            if (refreshBtn) refreshBtn.disabled = true;
            sessionSelect.disabled = true;

            if (!hadSessions) {
                sessionSelect.innerHTML = '<option value="0">Memuat sesi pelajaran...</option>';
                sessionSelect.value = '0';
            }

            try {
                const result = await runMethod('getPelajaranSessionsToday');
                if (!result?.success) {
                    throw new Error(result?.message || 'Gagal memuat jadwal pelajaran.');
                }

                setDateText(result.data?.tanggal || '');
                renderSessionSelect(result.data?.sessions || []);

                if (!sessionList.length) {
                    resetPageState();
                } else if (!skipActiveDetailReload && activeJadwalId > 0 && sessionList.some((item) => normalizeId(item.jadwal_id) === activeJadwalId)) {
                    await loadSessionDetail(activeJadwalId);
                } else if (!skipActiveDetailReload) {
                    resetPageState();
                }
            } catch (error) {
                setDateText('');
                renderSessionSelect([]);
                resetPageState();
                setLastResult('error', error.message || 'Gagal memuat jadwal pelajaran.');
            } finally {
                sessionSelect.disabled = false;
                if (refreshBtn) refreshBtn.disabled = false;
            }
        }

        function askConfirm(options) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    title: options.title || 'Lanjutkan?',
                    text: options.text || '',
                    icon: options.icon || 'question',
                    showCancelButton: true,
                    confirmButtonText: options.confirmText || 'Ya',
                    cancelButtonText: options.cancelText || 'Batal',
                    confirmButtonColor: options.confirmColor || '#4f46e5',
                }).then((result) => !!result.isConfirmed);
            }

            return Promise.resolve(window.confirm(options.text || options.title || 'Lanjutkan?'));
        }

        async function handleSessionAction() {
            if (!isSessionSelected() || actionBusy) return;

            if (isSessionClosed()) {
                return;
            }

            actionBusy = true;
            updateActionButton();

            try {
                if (isSessionOpen()) {
                    const confirmed = await askConfirm({
                        title: 'Tutup sesi pelajaran?',
                        text: 'Siswa yang belum tercatat otomatis diisi Alfa.',
                        icon: 'warning',
                        confirmText: 'Ya, Tutup',
                        confirmColor: '#e11d48',
                    });
                    if (!confirmed) return;

                    const result = await runMethod('closePelajaranSession', normalizeId(activeSession?.id));
                    if (!result?.success) {
                        throw new Error(result?.message || 'Gagal menutup sesi.');
                    }

                    if (activeSession) {
                        activeSession = {
                            ...activeSession,
                            status: 'closed',
                            closed_at: String(result.data?.closed_at || activeSession.closed_at || '').trim(),
                        };
                    }

                    renderSessionInfo();
                    updateActionButton();
                    updateBroadcastButton();
                    updateInteractionState();
                    stopPopupPolling();
                    await loadSessionDetail(activeJadwalId, { preserveView: true, clearLastResultOnSuccess: false });
                    await loadSessions({ skipActiveDetailReload: true });
                    setLastResult('success', result.message || 'Sesi ditutup.');
                    return;
                }

                const result = await runMethod('startPelajaranSession', activeJadwalId);
                if (!result?.success) {
                    throw new Error(result?.message || 'Gagal memulai sesi.');
                }

                activeSession = result.data?.session || null;
                renderSessionInfo();
                renderRoster(result.data);
                updateActionButton();
                updateBroadcastButton();
                updateInteractionState();
                setLastResult('success', 'Sesi dimulai.');
                await loadSessions({ skipActiveDetailReload: true });
            } catch (error) {
                setLastResult('error', error.message || 'Gagal memproses sesi.');
            } finally {
                actionBusy = false;
                updateActionButton();
            }
        }

        async function handleBroadcast() {
            if (!isSessionOpen() || broadcastBusy) return;

            const confirmed = await askConfirm({
                title: 'Broadcast Hadir?',
                text: 'Semua siswa yang masih Belum Absen akan diubah menjadi Hadir.',
                icon: 'question',
                confirmText: 'Ya, Broadcast',
                confirmColor: '#059669',
            });

            if (!confirmed) return;

            broadcastBusy = true;
            updateBroadcastButton();

            try {
                const result = await runMethod('broadcastPelajaranHadir', normalizeId(activeSession?.id));
                if (!result?.success) {
                    throw new Error(result?.message || 'Gagal broadcast hadir.');
                }

                const updatedRows = Array.isArray(result.data?.updated) ? result.data.updated : [];
                updatedRows.forEach((row) => applyRowUpdate(row));

                if (result.data?.stats) {
                    updateStats(result.data.stats.total, result.data.stats.recorded);
                } else {
                    recalculateStatsFromRows();
                }

                updateBroadcastButton();
                setLastResult('success', result.message || 'Broadcast hadir berhasil.');
            } catch (error) {
                setLastResult('error', error.message || 'Gagal broadcast hadir.');
            } finally {
                broadcastBusy = false;
                updateBroadcastButton();
            }
        }

        async function submitScan(rawCode, mode, source) {
            const code = String(rawCode || '').trim();
            if (code === '') {
                return { success: false, silent: true };
            }

            if (!isSessionOpen()) {
                const message = 'Sesi belum dibuka atau sudah ditutup.';
                setLastResult('error', message);
                return { success: false, message };
            }

            const now = Date.now();
            if (code === lastScanKey && now - lastScanAt < SCAN_DEBOUNCE_MS) {
                return { success: false, duplicate: true, message: 'Scan sama diabaikan.' };
            }

            lastScanKey = code;
            lastScanAt = now;

            try {
                const result = await runMethod('scanPelajaranAbsensi', normalizeId(activeSession?.id), mode, code);
                if (!result?.success) {
                    throw new Error(result?.message || 'Scan gagal diproses.');
                }

                applyRowUpdate(result.data);
                recalculateStatsFromRows();
                setLastResult('success', `${result.data?.nama || 'Siswa'} - ${result.data?.status || 'OK'}`);

                return {
                    success: true,
                    nama: result.data?.nama || '',
                    kelas: result.data?.kelas || '',
                    status: result.data?.status || '',
                    source,
                };
            } catch (error) {
                setLastResult('error', error.message || 'Scan gagal diproses.');
                return { success: false, message: error.message || 'Scan gagal diproses.' };
            }
        }

        function startPopupPolling() {
            if (pollingStatus) pollingStatus.classList.remove('hidden');
            if (pollingStatusText) {
                pollingStatusText.textContent = 'Menunggu hasil scan dari kamera...';
            }

            if (popupWatchInterval) {
                clearInterval(popupWatchInterval);
            }

            popupWatchInterval = setInterval(() => {
                if (!cameraPopup || cameraPopup.closed) {
                    stopPopupPolling();
                }
            }, 1000);
        }

        function stopPopupPolling() {
            if (popupWatchInterval) {
                clearInterval(popupWatchInterval);
                popupWatchInterval = null;
            }

            if (pollingStatus) pollingStatus.classList.add('hidden');
            if (pollingStatusText) {
                pollingStatusText.textContent = 'Menunggu hasil scan dari kamera...';
            }
        }

        function openCameraPopup() {
            if (!isSessionOpen()) {
                setLastResult('error', 'Mulai sesi terlebih dahulu sebelum membuka kamera live.');
                return;
            }

            if (cameraPopup && !cameraPopup.closed) {
                try {
                    cameraPopup.focus();
                } catch (error) {
                    // no-op
                }
                startPopupPolling();
                return;
            }

            const popupUrl = `${window.APP_ROUTES?.scanner || '/scanner'}?camera=1`;
            cameraPopup = window.open(popupUrl, '_blank');

            if (!cameraPopup) {
                setLastResult('error', 'Tab kamera diblokir browser. Izinkan popup lalu coba lagi.');
                return;
            }

            startPopupPolling();
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                loadSessions();
            });
        }

        sessionSelect.addEventListener('change', () => {
            const jadwalId = normalizeId(sessionSelect.value);
            activeJadwalId = jadwalId;
            loadSessionDetail(jadwalId);
        });

        if (sessionActionBtn) {
            sessionActionBtn.addEventListener('click', () => {
                handleSessionAction();
            });
        }

        if (broadcastBtn) {
            broadcastBtn.addEventListener('click', () => {
                handleBroadcast();
            });
        }

        if (modeQrBtn) {
            modeQrBtn.addEventListener('click', () => setMode('qr'));
        }

        if (modeRfidBtn) {
            modeRfidBtn.addEventListener('click', () => setMode('rfid'));
        }

        if (qrOpenBtn) {
            qrOpenBtn.addEventListener('click', () => {
                openCameraPopup();
            });
        }

        if (manualQrInput) {
            manualQrInput.addEventListener('keydown', async (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const rawValue = manualQrInput.value;
                manualQrInput.value = '';
                await submitScan(rawValue, 'qr', 'manual');
            });
        }

        if (rfidInput) {
            rfidInput.addEventListener('keydown', async (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const rawValue = rfidInput.value;
                rfidInput.value = '';
                await submitScan(rawValue, 'rfid', 'manual');
            });
        }

        window.addEventListener('message', async (event) => {
            const data = event?.data || {};

            if (data.type === 'CAMERA_READY') {
                if (event.source) cameraPopup = event.source;
                startPopupPolling();
                return;
            }

            if (data.type === 'CAMERA_INFO' && pollingStatusText) {
                pollingStatusText.textContent = String(data.message || 'Menunggu hasil scan dari kamera...');
                return;
            }

            if (data.type === 'CAMERA_ERROR') {
                setLastResult('error', data.message || 'Kamera live tidak bisa diakses.');
                return;
            }

            if (data.type !== 'QR_SCAN_RESULT') {
                return;
            }

            const result = await submitScan(String(data.nisn || ''), 'qr', 'camera');

            if (!event.source || typeof event.source.postMessage !== 'function') {
                return;
            }

            if (result.duplicate) {
                event.source.postMessage({ type: 'SCAN_DUPLICATE', nama: 'Sudah discan' }, '*');
                return;
            }

            if (result.success) {
                event.source.postMessage({
                    type: 'SCAN_SUCCESS',
                    nama: result.nama || '',
                    kelas: result.kelas || '',
                }, '*');
                return;
            }

            if (!result.silent) {
                event.source.postMessage({
                    type: 'SCAN_ERROR',
                    message: result.message || 'Scan gagal diproses.',
                }, '*');
            }
        });

        window.addEventListener('beforeunload', () => {
            stopPopupPolling();
        });

        setMode('qr');
        resetPageState();
        loadSessions();
    })();
</script>
