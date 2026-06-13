<script>
    (function () {
        const initialCardRecords = @json($cardRecords);
        const initialStudentRecords = @json($studentRecords);
        const dataUrl = @json($dataUrl);
        const streamUrl = @json($streamUrl);
        const storeUrl = @json($storeUrl);
        const itemUrlTemplate = @json($updateUrlTemplate);
        const csrfToken = @json(csrf_token());
        const showAlert = window.showAlert || function (type, message) {
            console[type === 'error' ? 'error' : 'log'](message);
        };
        const AUTO_REFRESH_INTERVAL_MS = 5000;
        const state = {
            page: 1,
            limit: 10,
            search: '',
            status: '',
            isRefreshing: false,
        };
        let autoRefreshTimerId = null;
        let kartuAbsensiEventSource = null;

        let cardRecords = Array.isArray(initialCardRecords)
            ? initialCardRecords.map(normalizeCardRecord)
            : [];
        let studentRecords = Array.isArray(initialStudentRecords)
            ? initialStudentRecords.map(normalizeStudentRecord)
            : [];

        sortCardRecords();

        function getView() {
            return document.getElementById('view-kartu-absensi');
        }

        function getKartuAbsensiAutoRefreshStatus() {
            return document.getElementById('kartu-absensi-auto-refresh-status');
        }

        function updateKartuAbsensiAutoRefreshStatus(message = '', iconClass = 'fa-wifi') {
            const statusElement = getKartuAbsensiAutoRefreshStatus();
            if (!statusElement) {
                return;
            }

            statusElement.innerHTML = `
                <i class="fas ${escapeHtml(iconClass)}"></i>
                ${escapeHtml(message || `Auto refresh ${Math.round(AUTO_REFRESH_INTERVAL_MS / 1000)} detik`)}
            `;
        }

        function stampKartuAbsensiSyncStatus() {
            const syncTime = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });

            if (isKartuAbsensiRealtimeConnected()) {
                updateKartuAbsensiAutoRefreshStatus(`Realtime ${syncTime}`, 'fa-wifi');
                return;
            }

            updateKartuAbsensiAutoRefreshStatus(`Auto refresh ${syncTime}`, 'fa-sync-alt');
        }

        function isKartuAbsensiRealtimeConnected() {
            if (typeof EventSource === 'undefined' || !kartuAbsensiEventSource) {
                return false;
            }

            return kartuAbsensiEventSource.readyState === EventSource.OPEN;
        }

        function normalize(value) {
            return String(value || '').trim().toLowerCase();
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeCardRecord(record) {
            return {
                id: Number(record?.id || 0),
                code: String(record?.code || '').trim().toUpperCase(),
                siswa_id: record?.siswa_id === null || record?.siswa_id === undefined || record?.siswa_id === ''
                    ? null
                    : Number(record.siswa_id),
                student_name: String(record?.student_name || '').trim(),
                student_nisn: String(record?.student_nisn || '').trim(),
                student_class: String(record?.student_class || '').trim(),
                last_scanned_at: record?.last_scanned_at || null,
                last_scanned_date: record?.last_scanned_date || null,
                last_scanned_time: record?.last_scanned_time || null,
                last_scanned_source: record?.last_scanned_source || null,
            };
        }

        function normalizeStudentRecord(record) {
            return {
                id: Number(record?.id || 0),
                nama: String(record?.nama || '').trim(),
                nisn: String(record?.nisn || '').trim(),
                kelas: String(record?.kelas || '').trim(),
            };
        }

        function sortCardRecords() {
            cardRecords.sort((left, right) => {
                const leftLinked = left.siswa_id ? 1 : 0;
                const rightLinked = right.siswa_id ? 1 : 0;

                if (leftLinked !== rightLinked) {
                    return leftLinked - rightLinked;
                }

                const leftScan = left.last_scanned_at ? Date.parse(left.last_scanned_at) || 0 : 0;
                const rightScan = right.last_scanned_at ? Date.parse(right.last_scanned_at) || 0 : 0;

                if (leftScan !== rightScan) {
                    return rightScan - leftScan;
                }

                return Number(right.id || 0) - Number(left.id || 0);
            });
        }

        function getItemUrl(cardId) {
            return String(itemUrlTemplate).replace('__ID__', encodeURIComponent(String(cardId)));
        }

        function getStudentOptionLabel(student) {
            const kelas = student?.kelas ? ` - ${student.kelas}` : '';
            return `${student?.nama || '-'} (${student?.nisn || '-'})${kelas}`;
        }

        function getSelectedStudent(studentId) {
            if (studentId === null || studentId === undefined || studentId === '') {
                return null;
            }

            return studentRecords.find((student) => Number(student.id) === Number(studentId)) || null;
        }

        function getSearchBlob(record) {
            return normalize([
                record.code,
                record.student_name,
                record.student_nisn,
                record.student_class,
                record.last_scanned_date,
                record.last_scanned_time,
                record.last_scanned_source,
            ].join(' '));
        }

        function getFilteredRecords() {
            const search = normalize(state.search);
            const status = normalize(state.status);

            return cardRecords.filter((record) => {
                const matchesSearch = search === '' || getSearchBlob(record).includes(search);
                const recordStatus = record.siswa_id ? 'linked' : 'unlinked';
                const matchesStatus = status === '' || recordStatus === status;

                return matchesSearch && matchesStatus;
            });
        }

        function buildRowHtml(record, rowNumber) {
            const studentHtml = record.siswa_id
                ? `
                    <div class="font-semibold text-gray-900">${escapeHtml(record.student_name)}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(record.student_nisn)}</div>
                `
                : `
                    <div class="font-semibold text-amber-700">Belum ditautkan</div>
                    <div class="text-[11px] text-amber-600">Kartu belum punya pemilik</div>
                `;

            const scanHtml = record.last_scanned_at
                ? `
                    <div class="font-semibold text-gray-900">${escapeHtml(record.last_scanned_date)}</div>
                    <div class="text-[11px] text-gray-500">${escapeHtml(record.last_scanned_time)} | ${escapeHtml(record.last_scanned_source || 'unknown')}</div>
                `
                : '<span class="text-gray-400">Belum pernah discan</span>';

            return `
                <tr class="hover:bg-gray-50">
                    <td class="p-3 text-center text-gray-400 font-mono">${rowNumber}</td>
                    <td class="p-3 align-top">
                        <div class="font-mono font-semibold text-gray-900 uppercase">${escapeHtml(record.code)}</div>
                        <div class="text-[10px] text-gray-400">ID #${escapeHtml(record.id)}</div>
                    </td>
                    <td class="p-3 align-top">${studentHtml}</td>
                    <td class="p-3 hidden md:table-cell align-top">
                        <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 text-gray-700 text-[11px] font-semibold">
                            ${escapeHtml(record.student_class || '-')}
                        </span>
                    </td>
                    <td class="p-3 hidden lg:table-cell align-top">${scanHtml}</td>
                    <td class="p-3 align-top text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button type="button" onclick="showEditKartuAbsensiModal(${record.id})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" onclick="confirmDeleteKartuAbsensi(${record.id})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function renderKartuAbsensiLoading(message = 'Memuat data kartu absensi...') {
            const tbody = document.getElementById('tbody-kartu-absensi');
            const info = document.getElementById('info-kartu-absensi');
            const prevButton = document.getElementById('btn-prev-kartu-absensi');
            const nextButton = document.getElementById('btn-next-kartu-absensi');

            if (!tbody) {
                return;
            }

            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="p-8 text-center">
                        <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>${escapeHtml(message)}</span>
                        </div>
                    </td>
                </tr>
            `;

            if (info) {
                info.textContent = message;
            }

            if (prevButton) {
                prevButton.disabled = true;
            }

            if (nextButton) {
                nextButton.disabled = true;
            }
        }

        function renderKartuAbsensiTable() {
            const tbody = document.getElementById('tbody-kartu-absensi');
            const info = document.getElementById('info-kartu-absensi');
            const prevButton = document.getElementById('btn-prev-kartu-absensi');
            const nextButton = document.getElementById('btn-next-kartu-absensi');

            if (!tbody) {
                return;
            }

            const filteredRecords = getFilteredRecords();
            const totalRecords = filteredRecords.length;
            const totalPages = state.limit === Infinity
                ? 1
                : Math.max(1, Math.ceil(totalRecords / state.limit));

            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            if (cardRecords.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-400">Belum ada kartu absensi terdaftar.</td></tr>';
                if (info) info.textContent = 'Menampilkan 0 data';
                if (prevButton) prevButton.disabled = true;
                if (nextButton) nextButton.disabled = true;
                return;
            }

            if (totalRecords === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-400">Tidak ada data yang cocok dengan filter saat ini.</td></tr>';
                if (info) info.textContent = 'Tidak ada data yang cocok';
                if (prevButton) prevButton.disabled = true;
                if (nextButton) nextButton.disabled = true;
                return;
            }

            const start = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const end = state.limit === Infinity ? totalRecords : Math.min(start + state.limit, totalRecords);
            const visibleRecords = filteredRecords.slice(start, end);

            tbody.innerHTML = visibleRecords
                .map((record, index) => buildRowHtml(record, start + index + 1))
                .join('');

            if (info) {
                info.textContent = `Menampilkan ${start + 1}-${end} dari ${totalRecords} data`;
            }

            if (prevButton) {
                prevButton.disabled = state.page <= 1;
            }

            if (nextButton) {
                nextButton.disabled = state.limit === Infinity || state.page >= totalPages;
            }
        }

        function getModalShell(create = false) {
            const container = document.getElementById('modalContainer');
            if (!container) return null;

            let shell = container.querySelector('[data-kartu-modal-shell]');
            if (!shell && create) {
                container.insertAdjacentHTML('beforeend', `
                    <div data-kartu-modal-shell class="fixed inset-0 z-50 hidden items-center justify-center p-4">
                        <div class="absolute inset-0 bg-gray-900/45 transition-opacity" onclick="closeKartuAbsensiModal()"></div>
                        <div data-kartu-modal-host class="relative w-full max-w-xl overflow-visible"></div>
                    </div>
                `);
                shell = container.querySelector('[data-kartu-modal-shell]');
            }

            return shell;
        }

        function showKartuAbsensiModal(content) {
            const shell = getModalShell(true);
            if (!shell) return;

            const host = shell.querySelector('[data-kartu-modal-host]');
            if (!host) return;

            host.innerHTML = content;
            shell.classList.remove('hidden');
            shell.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeKartuAbsensiModal() {
            const shell = getModalShell(false);
            if (!shell) return;

            const host = shell.querySelector('[data-kartu-modal-host]');
            if (host) {
                host.innerHTML = '';
            }

            shell.classList.add('hidden');
            shell.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function renderStudentDropdownItems(keyword = '') {
            const dropdown = document.getElementById('kartuAbsensiStudentDropdown');
            if (!dropdown) return;

            const query = normalize(keyword);
            const filtered = query === ''
                ? studentRecords
                : studentRecords.filter((student) => {
                    const blob = normalize([
                        student.nama,
                        student.nisn,
                        student.kelas,
                    ].join(' '));

                    return blob.includes(query);
                });

            const items = [`
                <button
                    type="button"
                    onclick="selectKartuAbsensiStudent('')"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm text-gray-600 transition border-b border-gray-100"
                >
                    Belum ditautkan
                </button>
            `];

            if (filtered.length === 0) {
                items.push('<div class="px-3 py-3 text-xs text-gray-400 italic">Siswa tidak ditemukan.</div>');
            } else {
                filtered.forEach((student) => {
                    items.push(`
                        <button
                            type="button"
                            onclick="selectKartuAbsensiStudent(${Number(student.id)})"
                            class="w-full text-left px-3 py-2 hover:bg-indigo-50 transition border-b border-gray-50 last:border-none"
                        >
                            <div class="text-sm font-semibold text-gray-800">${escapeHtml(student.nama)}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(student.nisn)}${student.kelas ? ` | ${escapeHtml(student.kelas)}` : ''}</div>
                        </button>
                    `);
                });
            }

            dropdown.innerHTML = items.join('');
        }

        function openKartuAbsensiStudentDropdown() {
            const dropdown = document.getElementById('kartuAbsensiStudentDropdown');

            if (!dropdown) return;

            renderStudentDropdownItems('');
            dropdown.classList.remove('hidden');
        }

        function filterKartuAbsensiStudentDropdown(keyword) {
            const hidden = document.getElementById('kartuAbsensiStudentId');
            if (hidden) {
                hidden.value = '';
            }

            renderStudentDropdownItems(keyword);

            const dropdown = document.getElementById('kartuAbsensiStudentDropdown');
            if (dropdown) {
                dropdown.classList.remove('hidden');
            }
        }

        function selectKartuAbsensiStudent(studentId) {
            const input = document.getElementById('kartuAbsensiStudentSearch');
            const hidden = document.getElementById('kartuAbsensiStudentId');
            const student = getSelectedStudent(studentId);

            if (input) {
                input.value = student ? getStudentOptionLabel(student) : '';
            }

            if (hidden) {
                hidden.value = student ? String(student.id) : '';
            }

            closeKartuAbsensiStudentDropdown();
        }

        function closeKartuAbsensiStudentDropdown() {
            const dropdown = document.getElementById('kartuAbsensiStudentDropdown');
            if (!dropdown) return;

            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 200);
        }

        function getFormHtml(cardId = null) {
            const isEdit = cardId !== null && cardId !== undefined;
            const card = isEdit
                ? cardRecords.find((item) => Number(item.id) === Number(cardId)) || null
                : null;

            const title = isEdit ? 'Edit Kartu Absensi' : 'Tambah Kartu Absensi';
            const submitLabel = isEdit ? 'Perbarui' : 'Simpan';
            const selectedStudent = getSelectedStudent(card?.siswa_id ?? null);
            const selectedStudentLabel = selectedStudent ? getStudentOptionLabel(selectedStudent) : '';
            const codeInputClass = isEdit
                ? 'w-full bg-gray-100 border border-gray-200 text-gray-500 text-sm rounded-lg block p-2.5 transition-all font-mono uppercase cursor-not-allowed'
                : 'w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all font-mono uppercase';

            return `
                <div class="bg-white rounded-2xl shadow-2xl overflow-visible">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-800">${title}</h3>
                        <button type="button" onclick="closeKartuAbsensiModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    <div class="p-6 overflow-visible">
                        <div id="kartuAbsensiFormError" class="hidden mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
                        <form onsubmit="submitKartuAbsensiForm(event, ${isEdit ? Number(card.id) : 'null'})" class="space-y-4">
                            <div>
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Kode Kartu</label>
                                <input
                                    id="kartuAbsensiCodeInput"
                                    type="text"
                                    value="${escapeHtml(card?.code || '')}"
                                    placeholder="Contoh: 04AABBCC"
                                    class="${codeInputClass}"
                                    ${isEdit ? 'readonly' : 'required'}
                                >
                                
                            </div>

                            <div>
                                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide">Tautkan ke Karyawan</label>
                                <div class="relative z-30">
                                    <input
                                        id="kartuAbsensiStudentSearch"
                                        type="text"
                                        value="${escapeHtml(selectedStudentLabel)}"
                                        placeholder="Cari nama atau NIK Karyawan"
                                        autocomplete="off"
                                        onfocus="openKartuAbsensiStudentDropdown()"
                                        oninput="filterKartuAbsensiStudentDropdown(this.value)"
                                        onblur="closeKartuAbsensiStudentDropdown()"
                                        class="w-full bg-white border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all"
                                    >
                                    <input id="kartuAbsensiStudentId" type="hidden" value="${card?.siswa_id ? escapeHtml(card.siswa_id) : ''}">
                                    <div id="kartuAbsensiStudentDropdown" class="hidden absolute left-0 right-0 z-40 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-56 overflow-y-auto"></div>
                                </div>
                                <p class="mt-2 text-[11px] text-gray-500">Biarkan kosong jika kartu belum ingin ditautkan ke Karyawan.</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" onclick="closeKartuAbsensiModal()" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg border border-gray-200 bg-white text-gray-700 font-semibold text-xs hover:bg-gray-50 hover:border-gray-300 transition">
                                    <i class="fas fa-times text-[10px]"></i>Batal
                                </button>
                                <button type="submit" id="kartuAbsensiSubmitButton" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-sm hover:from-indigo-700 hover:to-blue-700 transition">
                                    <i class="fas fa-save text-[10px]"></i>${submitLabel}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
        }

        function setFormError(message) {
            const errorBox = document.getElementById('kartuAbsensiFormError');
            if (!errorBox) return;

            if (!message) {
                errorBox.textContent = '';
                errorBox.classList.add('hidden');
                return;
            }

            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        async function apiRequest(url, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            };

            if (method !== 'GET') {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers,
                body: options.body ? JSON.stringify(options.body) : undefined,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const firstError = payload?.errors
                    ? Object.values(payload.errors)[0]?.[0]
                    : null;
                throw new Error(firstError || payload.message || 'Permintaan gagal diproses.');
            }

            return payload;
        }

        function applyServerData(payload) {
            cardRecords = Array.isArray(payload?.cards)
                ? payload.cards.map(normalizeCardRecord)
                : [];
            studentRecords = Array.isArray(payload?.students)
                ? payload.students.map(normalizeStudentRecord)
                : [];
            sortCardRecords();
        }

        function upsertCardRecord(record) {
            const normalized = normalizeCardRecord(record);
            const index = cardRecords.findIndex((item) => Number(item.id) === Number(normalized.id));

            if (index >= 0) {
                cardRecords[index] = normalized;
            } else {
                cardRecords.push(normalized);
            }

            sortCardRecords();
        }

        function showAddKartuAbsensiModal() {
            setFormError('');
            showKartuAbsensiModal(getFormHtml());
        }

        function showEditKartuAbsensiModal(cardId) {
            const card = cardRecords.find((item) => Number(item.id) === Number(cardId)) || null;
            if (!card) {
                showAlert('error', 'Data kartu tidak ditemukan.');
                return;
            }

            setFormError('');
            showKartuAbsensiModal(getFormHtml(card.id));
        }

        async function submitKartuAbsensiForm(event, cardId = null) {
            event.preventDefault();
            setFormError('');

            const codeInput = document.getElementById('kartuAbsensiCodeInput');
            const studentSearchInput = document.getElementById('kartuAbsensiStudentSearch');
            const studentHiddenInput = document.getElementById('kartuAbsensiStudentId');
            const submitButton = document.getElementById('kartuAbsensiSubmitButton');

            const code = String(codeInput?.value || '').trim().toUpperCase();
            const studentSearchValue = String(studentSearchInput?.value || '').trim();
            const studentValue = String(studentHiddenInput?.value || '').trim();
            const siswaId = studentValue === '' ? null : Number(studentValue);
            const isEdit = cardId !== null && cardId !== undefined;

            if (!isEdit && code === '') {
                setFormError('Kode kartu wajib diisi.');
                return;
            }

            if (studentSearchValue !== '' && studentValue === '') {
                setFormError('Pilih siswa dari dropdown atau kosongkan field pencarian.');
                return;
            }

            if (studentValue !== '' && (!Number.isFinite(siswaId) || siswaId <= 0)) {
                setFormError('Siswa yang dipilih tidak valid.');
                return;
            }

            const originalButtonHtml = submitButton ? submitButton.innerHTML : '';

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-75', 'cursor-not-allowed');
                submitButton.innerHTML = '<i class="fas fa-circle-notch fa-spin text-[10px]"></i>Menyimpan...';
            }

            try {
                const payload = {
                    siswa_id: siswaId,
                };

                if (!isEdit) {
                    payload.code = code;
                }

                const response = await apiRequest(isEdit ? getItemUrl(cardId) : storeUrl, {
                    method: isEdit ? 'PUT' : 'POST',
                    body: payload,
                });

                upsertCardRecord(response?.data || {});
                state.page = 1;
                renderKartuAbsensiTable();
                closeKartuAbsensiModal();
                showAlert('success', response?.message || (isEdit ? 'Kartu absensi diperbarui.' : 'Kartu absensi ditambahkan.'));
            } catch (error) {
                setFormError(error?.message || 'Permintaan gagal diproses.');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-75', 'cursor-not-allowed');
                    submitButton.innerHTML = originalButtonHtml;
                }
            }
        }

        async function deleteKartuAbsensi(cardId) {
            const response = await apiRequest(getItemUrl(cardId), {
                method: 'DELETE',
            });

            cardRecords = cardRecords.filter((item) => Number(item.id) !== Number(cardId));
            renderKartuAbsensiTable();
            showAlert('success', response?.message || 'Kartu absensi dihapus.');
        }

        function confirmDeleteKartuAbsensi(cardId) {
            const card = cardRecords.find((item) => Number(item.id) === Number(cardId)) || null;
            if (!card) {
                showAlert('error', 'Data kartu tidak ditemukan.');
                return;
            }

            const handleDelete = async function () {
                try {
                    await deleteKartuAbsensi(cardId);
                } catch (error) {
                    showAlert('error', error?.message || 'Gagal menghapus kartu.');
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus kartu?',
                    html: `Kode <b>${escapeHtml(card.code)}</b> akan dihapus.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) {
                        handleDelete();
                    }
                });
                return;
            }

            if (window.confirm(`Hapus kartu ${card.code}?`)) {
                handleDelete();
            }
        }

        function toggleKartuAbsensiCreatePanel() {
            showAddKartuAbsensiModal();
        }

        function stopKartuAbsensiAutoRefresh() {
            if (autoRefreshTimerId === null) {
                return;
            }

            window.clearInterval(autoRefreshTimerId);
            autoRefreshTimerId = null;
        }

        async function loadKartuAbsensiData(options = {}) {
            const {
                showToast = false,
                triggerButton = null,
                showLoading = true,
            } = options;

            if (state.isRefreshing) {
                return;
            }

            state.isRefreshing = true;

            const button = triggerButton instanceof HTMLElement ? triggerButton : null;
            const icon = button ? button.querySelector('i') : null;

            if (button) {
                button.disabled = true;
                button.classList.add('opacity-75', 'cursor-not-allowed');
            }

            if (icon) {
                icon.classList.add('fa-spin');
            }

            if (showLoading) {
                renderKartuAbsensiLoading();
            }

            try {
                const response = await apiRequest(dataUrl);
                applyServerData(response?.data || {});
                renderKartuAbsensiTable();
                stampKartuAbsensiSyncStatus();

                if (showToast) {
                    showAlert('success', 'Data kartu absensi diperbarui.');
                }
            } finally {
                state.isRefreshing = false;

                if (icon) {
                    icon.classList.remove('fa-spin');
                }

                if (button) {
                    button.disabled = false;
                    button.classList.remove('opacity-75', 'cursor-not-allowed');
                }
            }
        }

        async function refreshKartuAbsensiPage(triggerButton = null) {
            try {
                await loadKartuAbsensiData({
                    showToast: true,
                    triggerButton,
                    showLoading: true,
                });
            } catch (error) {
                renderKartuAbsensiTable();
                showAlert('error', error?.message || 'Gagal memuat ulang data kartu absensi.');
            }
        }

        async function runKartuAbsensiAutoRefresh() {
            if (!getView() || document.hidden) {
                return;
            }

            try {
                await loadKartuAbsensiData({
                    showLoading: false,
                });
            } catch (error) {
                updateKartuAbsensiAutoRefreshStatus('Auto refresh tertunda');
            }
        }

        function startKartuAbsensiAutoRefresh() {
            stopKartuAbsensiAutoRefresh();

            autoRefreshTimerId = window.setInterval(runKartuAbsensiAutoRefresh, AUTO_REFRESH_INTERVAL_MS);
        }

        function disconnectKartuAbsensiStream() {
            if (!kartuAbsensiEventSource) {
                return;
            }

            kartuAbsensiEventSource.close();
            kartuAbsensiEventSource = null;
        }

        function handleKartuAbsensiStreamSync(event) {
            try {
                const payload = JSON.parse(String(event?.data || '{}'));
                applyServerData(payload);
                renderKartuAbsensiTable();
                stampKartuAbsensiSyncStatus();
            } catch (error) {
                updateKartuAbsensiAutoRefreshStatus('Data realtime tidak valid', 'fa-sync-alt');
                startKartuAbsensiAutoRefresh();
            }
        }

        function connectKartuAbsensiStream() {
            if (!getView() || document.hidden) {
                return;
            }

            if (typeof EventSource === 'undefined' || !streamUrl) {
                updateKartuAbsensiAutoRefreshStatus(`Browser tidak mendukung SSE, auto refresh ${Math.round(AUTO_REFRESH_INTERVAL_MS / 1000)} detik`, 'fa-sync-alt');
                startKartuAbsensiAutoRefresh();
                return;
            }

            if (kartuAbsensiEventSource) {
                return;
            }

            updateKartuAbsensiAutoRefreshStatus('Menghubungkan realtime...', 'fa-circle-notch fa-spin');

            const eventSource = new EventSource(streamUrl);
            kartuAbsensiEventSource = eventSource;

            eventSource.addEventListener('open', function () {
                stopKartuAbsensiAutoRefresh();
                updateKartuAbsensiAutoRefreshStatus('Realtime tersambung', 'fa-wifi');
            });

            eventSource.addEventListener('sync', handleKartuAbsensiStreamSync);

            eventSource.onerror = function () {
                if (kartuAbsensiEventSource !== eventSource) {
                    return;
                }

                updateKartuAbsensiAutoRefreshStatus(`Koneksi realtime terganggu, fallback ${Math.round(AUTO_REFRESH_INTERVAL_MS / 1000)} detik`, 'fa-sync-alt');
                startKartuAbsensiAutoRefresh();
            };
        }

        function handleKartuAbsensiLimit(value) {
            state.limit = value === 'all' ? Infinity : Math.max(1, parseInt(value, 10) || 10);
            state.page = 1;
            renderKartuAbsensiTable();
        }

        function handleKartuAbsensiStatusFilter(value) {
            state.status = value;
            state.page = 1;
            renderKartuAbsensiTable();
        }

        function handleKartuAbsensiSearch(value) {
            state.search = value;
            state.page = 1;
            renderKartuAbsensiTable();
        }

        function changeKartuAbsensiPage(direction) {
            state.page += Number(direction) || 0;
            renderKartuAbsensiTable();
        }

        window.showAddKartuAbsensiModal = showAddKartuAbsensiModal;
        window.showEditKartuAbsensiModal = showEditKartuAbsensiModal;
        window.openKartuAbsensiStudentDropdown = openKartuAbsensiStudentDropdown;
        window.filterKartuAbsensiStudentDropdown = filterKartuAbsensiStudentDropdown;
        window.selectKartuAbsensiStudent = selectKartuAbsensiStudent;
        window.closeKartuAbsensiStudentDropdown = closeKartuAbsensiStudentDropdown;
        window.submitKartuAbsensiForm = submitKartuAbsensiForm;
        window.confirmDeleteKartuAbsensi = confirmDeleteKartuAbsensi;
        window.closeKartuAbsensiModal = closeKartuAbsensiModal;
        window.toggleKartuAbsensiCreatePanel = toggleKartuAbsensiCreatePanel;
        window.refreshKartuAbsensiPage = refreshKartuAbsensiPage;
        window.handleKartuAbsensiLimit = handleKartuAbsensiLimit;
        window.handleKartuAbsensiStatusFilter = handleKartuAbsensiStatusFilter;
        window.handleKartuAbsensiSearch = handleKartuAbsensiSearch;
        window.changeKartuAbsensiPage = changeKartuAbsensiPage;

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                disconnectKartuAbsensiStream();
                stopKartuAbsensiAutoRefresh();
                updateKartuAbsensiAutoRefreshStatus('Realtime dijeda', 'fa-pause');
                return;
            }

            connectKartuAbsensiStream();
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (getView()) {
                renderKartuAbsensiTable();
                connectKartuAbsensiStream();
            }
        });
    })();
</script>
