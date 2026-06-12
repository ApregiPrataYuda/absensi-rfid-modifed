<script>
    (function () {
        const state = {
            fullData: [],
            filtered: [],
            kelasOptions: [],
            guruOptions: [],
            jadwalOptions: [],
            statusOptions: {},
            limit: 10,
            page: 1,
            search: '',
            kelasFilter: '',
            statusFilter: '',
            dateFrom: '',
            dateTo: ''
        };

        const csrfToken = '{{ csrf_token() }}';
        const showAlert = window.showAlert || function (type, message) {
            console[type === 'error' ? 'error' : 'log'](message);
        };

        const dayLabels = {
            1: 'Senin',
            2: 'Selasa',
            3: 'Rabu',
            4: 'Kamis',
            5: 'Jumat',
            6: 'Sabtu',
            7: 'Minggu'
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

        function getUpdateUrl(id) {
            return String(window.APP_ROUTES?.jurnalMengajarUpdate || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function getDestroyUrl(id) {
            return String(window.APP_ROUTES?.jurnalMengajarDestroy || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function updatePagination() {
            const total = state.filtered.length;
            const infoEl = document.getElementById('info-jurnal-mengajar');
            const btnPrev = document.getElementById('btn-prev-jurnal');
            const btnNext = document.getElementById('btn-next-jurnal');

            const totalPages = state.limit === Infinity ? 1 : Math.max(1, Math.ceil(total / state.limit));
            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            if (total === 0) {
                if (infoEl) infoEl.textContent = 'Tidak ada data jurnal mengajar.';
                if (btnPrev) btnPrev.disabled = true;
                if (btnNext) btnNext.disabled = true;
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const endIdx = state.limit === Infinity ? total : Math.min(startIdx + state.limit, total);

            if (infoEl) {
                infoEl.textContent = `Menampilkan ${startIdx + 1} - ${endIdx} dari ${total} jurnal`;
            }
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= totalPages;
        }

        function renderRows() {
            const tbody = document.getElementById('tbody-jurnal-mengajar');
            if (!tbody) return;

            const total = state.filtered.length;
            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">Data jurnal mengajar tidak ditemukan.</td></tr>';
                updatePagination();
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const rows = state.limit === Infinity
                ? state.filtered
                : state.filtered.slice(startIdx, startIdx + state.limit);

            tbody.innerHTML = rows.map((row, i) => {
                const statusText = row.status === 'draft' ? 'Draft' : 'Selesai';
                const statusClass = row.status === 'draft'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-emerald-100 text-emerald-700';
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${startIdx + i + 1}</td>
                        <td class="p-3">${escapeHtml(formatDateDisplay(row.tanggal))}</td>
                        <td class="p-3">${escapeHtml(row.kelas_nama || '-')}</td>
                        <td class="p-3 hidden md:table-cell">${escapeHtml(row.guru_nama || '-')}</td>
                        <td class="p-3">${escapeHtml(row.mata_pelajaran || '-')}</td>
                        <td class="p-3">${escapeHtml(row.topik_materi || '-')}</td>
                        <td class="p-3 hidden lg:table-cell">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold ${statusClass}">${statusText}</span>
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="showEditJurnalMengajarModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button onclick="confirmDeleteJurnalMengajar(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            updatePagination();
        }

        function renderLoading(message = 'Memuat data jurnal mengajar...') {
            const tbody = document.getElementById('tbody-jurnal-mengajar');
            if (!tbody) return;
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="p-8 text-center">
                        <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>${escapeHtml(message)}</span>
                        </div>
                    </td>
                </tr>
            `;
        }

        function renderFilters() {
            const kelasSelect = document.getElementById('filter-jurnal-kelas');
            if (kelasSelect) {
                const current = String(state.kelasFilter || '');
                kelasSelect.innerHTML = '<option value="">Semua Kelas</option>' + state.kelasOptions.map((kelas) => `
                    <option value="${Number(kelas.id)}">${escapeHtml(kelas.nama)}</option>
                `).join('');
                kelasSelect.value = current;
            }

            const statusSelect = document.getElementById('filter-jurnal-status');
            if (statusSelect) {
                const current = String(state.statusFilter || '');
                statusSelect.innerHTML = '<option value="">Semua Status</option>' + Object.entries(state.statusOptions || {}).map(([key, label]) => `
                    <option value="${escapeHtml(key)}">${escapeHtml(label)}</option>
                `).join('');
                statusSelect.value = current;
            }
        }

        function applyFilters() {
            let data = [...state.fullData];

            if (state.kelasFilter !== '') {
                const kelasId = Number(state.kelasFilter);
                data = data.filter((item) => Number(item.kelas_id) === kelasId);
            }

            if (state.statusFilter !== '') {
                data = data.filter((item) => String(item.status || '') === state.statusFilter);
            }

            if (state.dateFrom !== '') {
                data = data.filter((item) => String(item.tanggal || '') >= state.dateFrom);
            }
            if (state.dateTo !== '') {
                data = data.filter((item) => String(item.tanggal || '') <= state.dateTo);
            }

            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter((item) => {
                    const blob = [
                        item.tanggal,
                        item.kelas_nama,
                        item.guru_nama,
                        item.mata_pelajaran,
                        item.topik_materi,
                        item.ringkasan_pembelajaran,
                        item.tugas_siswa,
                        item.catatan,
                        item.status
                    ].map((x) => String(x ?? '').toLowerCase()).join(' ');
                    return blob.includes(q);
                });
            }

            state.filtered = data;
            renderRows();
        }

        async function loadJurnalData(showToast = false) {
            renderLoading();
            try {
                const res = await apiRequest(window.APP_ROUTES?.jurnalMengajarData);
                state.fullData = Array.isArray(res?.data) ? res.data : [];
                state.kelasOptions = Array.isArray(res?.kelas) ? res.kelas : [];
                state.guruOptions = Array.isArray(res?.guru) ? res.guru : [];
                state.jadwalOptions = Array.isArray(res?.jadwal) ? res.jadwal : [];
                state.statusOptions = (res?.status_options && typeof res.status_options === 'object') ? res.status_options : {};
                state.page = 1;
                renderFilters();
                applyFilters();
                if (showToast) {
                    showAlert('success', 'Data jurnal mengajar diperbarui.');
                }
            } catch (err) {
                const tbody = document.getElementById('tbody-jurnal-mengajar');
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-red-500">${escapeHtml(err.message || err)}</td></tr>`;
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
                        <div data-modal-host class="relative w-full max-w-4xl"></div>
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

        function buildJadwalLabel(item) {
            const dayLabel = dayLabels[Number(item.hari)] || `Hari ${item.hari}`;
            return `${dayLabel} ${item.jam_mulai}-${item.jam_selesai} | ${item.kelas_nama} | ${item.mata_pelajaran} | ${item.guru_nama}`;
        }

        function getJurnalFormHTML(data = {}, jurnalId = null) {
            const isEdit = jurnalId !== null && jurnalId !== undefined;
            const labelClass = 'block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide';
            const inputClass = 'w-full bg-white border border-gray-200 text-sm rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500';

            const kelasOptions = state.kelasOptions.map((kelas) => `
                <option value="${Number(kelas.id)}" ${Number(data.kelas_id) === Number(kelas.id) ? 'selected' : ''}>${escapeHtml(kelas.nama)}</option>
            `).join('');

            const guruOptions = state.guruOptions.map((guru) => `
                <option value="${Number(guru.id)}" ${Number(data.guru_id) === Number(guru.id) ? 'selected' : ''}>${escapeHtml(guru.name || guru.username)}</option>
            `).join('');

            const jadwalOptions = state.jadwalOptions.map((item) => `
                <option
                    value="${Number(item.id)}"
                    data-kelas-id="${Number(item.kelas_id || 0)}"
                    data-guru-id="${Number(item.guru_id || 0)}"
                    data-mapel="${escapeHtml(item.mata_pelajaran || '')}"
                    ${Number(data.jadwal_pelajaran_id) === Number(item.id) ? 'selected' : ''}
                >${escapeHtml(buildJadwalLabel(item))}</option>
            `).join('');

            const statusOptions = Object.entries(state.statusOptions || {}).map(([key, label]) => `
                <option value="${escapeHtml(key)}" ${String(data.status || 'selesai') === String(key) ? 'selected' : ''}>${escapeHtml(label)}</option>
            `).join('');

            return `
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-800">${isEdit ? 'Edit Jurnal Mengajar' : 'Tambah Jurnal Mengajar'}</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
                    </div>
                    <div class="p-6 max-h-[80vh] overflow-y-auto">
                        <form onsubmit="saveJurnalMengajar(event, ${isEdit ? Number(jurnalId) : 'null'})" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="${labelClass}">Tanggal</label>
                                    <input id="jurnal-tanggal" type="date" required value="${escapeHtml(data.tanggal || '')}" class="${inputClass}">
                                </div>
                                <div>
                                    <label class="${labelClass}">Kelas</label>
                                    <select id="jurnal-kelas-id" required class="${inputClass}">
                                        <option value="">Pilih Kelas</option>
                                        ${kelasOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="${labelClass}">Guru</label>
                                    <select id="jurnal-guru-id" required class="${inputClass}">
                                        <option value="">Pilih Guru</option>
                                        ${guruOptions}
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="${labelClass}">Referensi Jadwal</label>
                                    <select id="jurnal-jadwal-id" class="${inputClass}" onchange="applyJurnalFromJadwal()">
                                        <option value="">Tanpa Referensi</option>
                                        ${jadwalOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="${labelClass}">Mata Pelajaran</label>
                                    <input id="jurnal-mapel" type="text" required value="${escapeHtml(data.mata_pelajaran || '')}" class="${inputClass}" placeholder="Contoh: Bahasa Indonesia">
                                </div>
                                <div>
                                    <label class="${labelClass}">Status</label>
                                    <select id="jurnal-status" required class="${inputClass}">
                                        ${statusOptions}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="${labelClass}">Topik Materi</label>
                                <input id="jurnal-topik" type="text" required value="${escapeHtml(data.topik_materi || '')}" class="${inputClass}" placeholder="Contoh: Persamaan Linear Dua Variabel">
                            </div>

                            <div>
                                <label class="${labelClass}">Ringkasan Pembelajaran</label>
                                <textarea id="jurnal-ringkasan" rows="3" class="${inputClass}" placeholder="Ringkasan proses pembelajaran">${escapeHtml(data.ringkasan_pembelajaran || '')}</textarea>
                            </div>

                            <div>
                                <label class="${labelClass}">Tugas Siswa</label>
                                <textarea id="jurnal-tugas" rows="2" class="${inputClass}" placeholder="Tugas / PR / tindak lanjut">${escapeHtml(data.tugas_siswa || '')}</textarea>
                            </div>

                            <div>
                                <label class="${labelClass}">Catatan</label>
                                <textarea id="jurnal-catatan" rows="2" class="${inputClass}" placeholder="Catatan tambahan">${escapeHtml(data.catatan || '')}</textarea>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" onclick="closeModal()" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg border border-gray-200 bg-white text-gray-700 font-semibold text-xs hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"><i class="fas fa-times text-[10px]"></i>Batal</button>
                                <button type="submit" class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-lg bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-sm hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition"><i class="fas fa-save text-[10px]"></i>${isEdit ? 'Perbarui' : 'Simpan'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
        }

        function applyJurnalFromJadwal() {
            const jadwalSelect = document.getElementById('jurnal-jadwal-id');
            const kelasSelect = document.getElementById('jurnal-kelas-id');
            const guruSelect = document.getElementById('jurnal-guru-id');
            const mapelInput = document.getElementById('jurnal-mapel');
            if (!jadwalSelect || !kelasSelect || !guruSelect || !mapelInput) return;

            const selected = jadwalSelect.options[jadwalSelect.selectedIndex];
            if (!selected || String(selected.value || '').trim() === '') return;

            const kelasId = String(selected.dataset.kelasId || '').trim();
            const guruId = String(selected.dataset.guruId || '').trim();
            const mapel = String(selected.dataset.mapel || '').trim();

            if (kelasId !== '' && kelasSelect.querySelector(`option[value="${kelasId}"]`)) {
                kelasSelect.value = kelasId;
            }
            if (guruId !== '' && guruSelect.querySelector(`option[value="${guruId}"]`)) {
                guruSelect.value = guruId;
            }
            if (mapel !== '') {
                mapelInput.value = mapel;
            }
        }

        function collectJurnalFormData() {
            const tanggal = String(document.getElementById('jurnal-tanggal')?.value || '').trim();
            const kelasId = Number(document.getElementById('jurnal-kelas-id')?.value || 0);
            const guruId = Number(document.getElementById('jurnal-guru-id')?.value || 0);
            const jadwalValue = String(document.getElementById('jurnal-jadwal-id')?.value || '').trim();
            const mapel = String(document.getElementById('jurnal-mapel')?.value || '').trim();
            const status = String(document.getElementById('jurnal-status')?.value || '').trim();
            const topik = String(document.getElementById('jurnal-topik')?.value || '').trim();
            const ringkasan = String(document.getElementById('jurnal-ringkasan')?.value || '').trim();
            const tugas = String(document.getElementById('jurnal-tugas')?.value || '').trim();
            const catatan = String(document.getElementById('jurnal-catatan')?.value || '').trim();

            if (!tanggal) {
                showAlert('error', 'Tanggal wajib diisi.');
                return null;
            }
            if (kelasId <= 0) {
                showAlert('error', 'Kelas wajib dipilih.');
                return null;
            }
            if (guruId <= 0) {
                showAlert('error', 'Guru wajib dipilih.');
                return null;
            }
            if (!mapel) {
                showAlert('error', 'Mata pelajaran wajib diisi.');
                return null;
            }
            if (!topik) {
                showAlert('error', 'Topik materi wajib diisi.');
                return null;
            }
            if (!status) {
                showAlert('error', 'Status wajib dipilih.');
                return null;
            }

            return {
                tanggal,
                kelas_id: kelasId,
                guru_id: guruId,
                jadwal_pelajaran_id: jadwalValue === '' ? null : Number(jadwalValue),
                mata_pelajaran: mapel,
                topik_materi: topik,
                ringkasan_pembelajaran: ringkasan === '' ? null : ringkasan,
                tugas_siswa: tugas === '' ? null : tugas,
                catatan: catatan === '' ? null : catatan,
                status
            };
        }

        async function saveJurnalMengajar(event, jurnalId) {
            event.preventDefault();
            const payload = collectJurnalFormData();
            if (!payload) return;

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
            }

            try {
                const isEdit = jurnalId !== null && jurnalId !== undefined && String(jurnalId) !== 'null';
                const url = isEdit ? getUpdateUrl(jurnalId) : window.APP_ROUTES?.jurnalMengajarStore;
                const method = isEdit ? 'PUT' : 'POST';

                const res = await apiRequest(url, {
                    method,
                    body: JSON.stringify(payload)
                });

                closeModal();
                await loadJurnalData();
                showAlert('success', res.message || (isEdit ? 'Jurnal mengajar diperbarui.' : 'Jurnal mengajar ditambahkan.'));
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

        async function deleteJurnalMengajar(id) {
            try {
                const res = await apiRequest(getDestroyUrl(id), { method: 'DELETE' });
                await loadJurnalData();
                showAlert('success', res.message || 'Jurnal mengajar berhasil dihapus.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            }
        }

        function showAddJurnalMengajarModal() {
            showModal(getJurnalFormHTML({ tanggal: new Date().toISOString().slice(0, 10), status: 'selesai' }, null));
        }

        function showEditJurnalMengajarModal(id) {
            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data jurnal tidak ditemukan.');
                return;
            }

            showModal(getJurnalFormHTML(row, row.id));
        }

        function confirmDeleteJurnalMengajar(id) {
            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data jurnal tidak ditemukan.');
                return;
            }

            Swal.fire({
                title: 'Hapus jurnal?',
                html: `Jurnal <b>${escapeHtml(row.mata_pelajaran)}</b> tanggal <b>${escapeHtml(formatDateDisplay(row.tanggal))}</b> akan dihapus.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteJurnalMengajar(row.id);
                }
            });
        }

        function handleJurnalSearch(value) {
            state.search = String(value || '').trim().toLowerCase();
            state.page = 1;
            applyFilters();
        }

        function handleJurnalKelasFilter(value) {
            state.kelasFilter = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function handleJurnalStatusFilter(value) {
            state.statusFilter = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function handleJurnalDateFilter() {
            state.dateFrom = String(document.getElementById('filter-jurnal-tanggal-dari')?.value || '').trim();
            state.dateTo = String(document.getElementById('filter-jurnal-tanggal-sampai')?.value || '').trim();
            state.page = 1;
            applyFilters();
        }

        function changeJurnalPage(direction) {
            const totalPages = state.limit === Infinity
                ? 1
                : Math.max(1, Math.ceil(state.filtered.length / state.limit));
            const next = state.page + direction;
            if (next < 1 || next > totalPages) return;
            state.page = next;
            renderRows();
        }

        function refreshJurnalMengajarData() {
            loadJurnalData(true);
        }

        window.showModal = showModal;
        window.closeModal = closeModal;
        window.showAddJurnalMengajarModal = showAddJurnalMengajarModal;
        window.showEditJurnalMengajarModal = showEditJurnalMengajarModal;
        window.confirmDeleteJurnalMengajar = confirmDeleteJurnalMengajar;
        window.saveJurnalMengajar = saveJurnalMengajar;
        window.applyJurnalFromJadwal = applyJurnalFromJadwal;
        window.refreshJurnalMengajarData = refreshJurnalMengajarData;
        window.handleJurnalSearch = handleJurnalSearch;
        window.handleJurnalKelasFilter = handleJurnalKelasFilter;
        window.handleJurnalStatusFilter = handleJurnalStatusFilter;
        window.handleJurnalDateFilter = handleJurnalDateFilter;
        window.changeJurnalPage = changeJurnalPage;

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('view-jurnal-mengajar')) {
                loadJurnalData();
            }
        });
    })();
</script>

