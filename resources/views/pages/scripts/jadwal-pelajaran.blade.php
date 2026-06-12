<script>
    (function () {
        const state = {
            fullData: [],
            filtered: [],
            kelasOptions: [],
            guruOptions: [],
            dayOptions: {},
            limit: 10,
            page: 1,
            search: '',
            dayFilter: ''
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
            return String(window.APP_ROUTES?.jadwalPelajaranUpdate || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function getDestroyUrl(id) {
            return String(window.APP_ROUTES?.jadwalPelajaranDestroy || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function updatePagination() {
            const total = state.filtered.length;
            const infoEl = document.getElementById('info-jadwal-pelajaran');
            const btnPrev = document.getElementById('btn-prev-jadwal-pelajaran');
            const btnNext = document.getElementById('btn-next-jadwal-pelajaran');

            const totalPages = state.limit === Infinity ? 1 : Math.max(1, Math.ceil(total / state.limit));
            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            if (total === 0) {
                if (infoEl) infoEl.textContent = 'Tidak ada data jadwal pelajaran.';
                if (btnPrev) btnPrev.disabled = true;
                if (btnNext) btnNext.disabled = true;
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const endIdx = state.limit === Infinity ? total : Math.min(startIdx + state.limit, total);

            if (infoEl) {
                infoEl.textContent = `Menampilkan ${startIdx + 1} - ${endIdx} dari ${total} jadwal`;
            }
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= totalPages;
        }

        function renderRows() {
            const tbody = document.getElementById('tbody-jadwal-pelajaran');
            if (!tbody) return;

            const total = state.filtered.length;
            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">Data jadwal pelajaran tidak ditemukan.</td></tr>';
                updatePagination();
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const rows = state.limit === Infinity
                ? state.filtered
                : state.filtered.slice(startIdx, startIdx + state.limit);

            tbody.innerHTML = rows.map((row, i) => `
                <tr class="hover:bg-gray-50">
                    <td class="p-3 text-center text-gray-500">${startIdx + i + 1}</td>
                    <td class="p-3">${escapeHtml(row.hari_label || '-')}</td>
                    <td class="p-3">${escapeHtml(row.jam_mulai || '-')}-${escapeHtml(row.jam_selesai || '-')}</td>
                    <td class="p-3">${escapeHtml(row.kelas_nama || '-')}</td>
                    <td class="p-3">${escapeHtml(row.mata_pelajaran || '-')}</td>
                    <td class="p-3 hidden md:table-cell">${escapeHtml(row.guru_nama || '-')}</td>
                    <td class="p-3 hidden lg:table-cell">${escapeHtml(row.ruang || '-')}</td>
                    <td class="p-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="showEditJadwalPelajaranModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="confirmDeleteJadwalPelajaran(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            updatePagination();
        }

        function renderLoading(message = 'Memuat data jadwal pelajaran...') {
            const tbody = document.getElementById('tbody-jadwal-pelajaran');
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

        function renderDayFilter() {
            const select = document.getElementById('filterHariJadwalPelajaran');
            if (!select) return;

            const current = String(state.dayFilter || '');
            const options = Object.entries(state.dayOptions || {})
                .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
                .join('');

            select.innerHTML = '<option value="">Semua Hari</option>' + options;
            select.value = current;
        }

        function applyFilters() {
            let data = [...state.fullData];

            if (state.dayFilter !== '') {
                const day = Number(state.dayFilter);
                data = data.filter((item) => Number(item.hari) === day);
            }

            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter((item) => {
                    const blob = [
                        item.hari_label,
                        item.kelas_nama,
                        item.guru_nama,
                        item.mata_pelajaran,
                        item.ruang,
                        item.keterangan,
                        item.jam_mulai,
                        item.jam_selesai
                    ].map((x) => String(x ?? '').toLowerCase()).join(' ');
                    return blob.includes(q);
                });
            }

            state.filtered = data;
            renderRows();
        }

        async function loadJadwalData(showToast = false) {
            renderLoading();
            try {
                const res = await apiRequest(window.APP_ROUTES?.jadwalPelajaranData);
                state.fullData = Array.isArray(res?.data) ? res.data : [];
                state.kelasOptions = Array.isArray(res?.kelas) ? res.kelas : [];
                state.guruOptions = Array.isArray(res?.guru) ? res.guru : [];
                state.dayOptions = (res?.day_options && typeof res.day_options === 'object') ? res.day_options : {};
                state.page = 1;
                renderDayFilter();
                applyFilters();
                if (showToast) {
                    showAlert('success', 'Data jadwal pelajaran diperbarui.');
                }
            } catch (err) {
                const tbody = document.getElementById('tbody-jadwal-pelajaran');
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

        function getJadwalFormHTML(data = {}, jadwalId = null) {
            const isEdit = jadwalId !== null && jadwalId !== undefined;
            const labelClass = 'block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide';
            const inputClass = 'w-full bg-white border border-gray-200 text-sm rounded-lg p-2.5 focus:ring-indigo-500 focus:border-indigo-500';

            const kelasOptions = state.kelasOptions.map((kelas) => `
                <option value="${Number(kelas.id)}" ${Number(data.kelas_id) === Number(kelas.id) ? 'selected' : ''}>${escapeHtml(kelas.nama)}</option>
            `).join('');

            const guruOptions = state.guruOptions.map((guru) => `
                <option value="${Number(guru.id)}" ${Number(data.guru_id) === Number(guru.id) ? 'selected' : ''}>${escapeHtml(guru.name || guru.username)}</option>
            `).join('');

            const dayOptions = Object.entries(state.dayOptions || {}).map(([value, label]) => `
                <option value="${Number(value)}" ${Number(data.hari) === Number(value) ? 'selected' : ''}>${escapeHtml(label)}</option>
            `).join('');

            return `
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-800">${isEdit ? 'Edit Jadwal Pelajaran' : 'Tambah Jadwal Pelajaran'}</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
                    </div>
                    <div class="p-6">
                        <form onsubmit="saveJadwalPelajaran(event, ${isEdit ? Number(jadwalId) : 'null'})" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="${labelClass}">Kelas</label>
                                    <select id="jadwal-kelas-id" required class="${inputClass}">
                                        <option value="">Pilih Kelas</option>
                                        ${kelasOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="${labelClass}">Guru</label>
                                    <select id="jadwal-guru-id" class="${inputClass}">
                                        <option value="">Tanpa Guru</option>
                                        ${guruOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="${labelClass}">Hari</label>
                                    <select id="jadwal-hari" required class="${inputClass}">
                                        <option value="">Pilih Hari</option>
                                        ${dayOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="${labelClass}">Jam Mulai</label>
                                    <input id="jadwal-jam-mulai" type="time" required value="${escapeHtml(data.jam_mulai || '')}" class="${inputClass}">
                                </div>
                                <div>
                                    <label class="${labelClass}">Jam Selesai</label>
                                    <input id="jadwal-jam-selesai" type="time" required value="${escapeHtml(data.jam_selesai || '')}" class="${inputClass}">
                                </div>
                            </div>
                            <div>
                                <label class="${labelClass}">Mata Pelajaran</label>
                                <input id="jadwal-mapel" type="text" required value="${escapeHtml(data.mata_pelajaran || '')}" class="${inputClass}" placeholder="Contoh: Matematika">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="${labelClass}">Ruang</label>
                                    <input id="jadwal-ruang" type="text" value="${escapeHtml(data.ruang || '')}" class="${inputClass}" placeholder="Opsional">
                                </div>
                                <div>
                                    <label class="${labelClass}">Keterangan</label>
                                    <input id="jadwal-keterangan" type="text" value="${escapeHtml(data.keterangan || '')}" class="${inputClass}" placeholder="Opsional">
                                </div>
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

        function collectJadwalFormData() {
            const kelasId = Number(document.getElementById('jadwal-kelas-id')?.value || 0);
            const guruValue = String(document.getElementById('jadwal-guru-id')?.value || '').trim();
            const hari = Number(document.getElementById('jadwal-hari')?.value || 0);
            const jamMulai = String(document.getElementById('jadwal-jam-mulai')?.value || '').trim();
            const jamSelesai = String(document.getElementById('jadwal-jam-selesai')?.value || '').trim();
            const mapel = String(document.getElementById('jadwal-mapel')?.value || '').trim();
            const ruang = String(document.getElementById('jadwal-ruang')?.value || '').trim();
            const keterangan = String(document.getElementById('jadwal-keterangan')?.value || '').trim();

            if (kelasId <= 0) {
                showAlert('error', 'Kelas wajib dipilih.');
                return null;
            }
            if (hari < 1 || hari > 7) {
                showAlert('error', 'Hari wajib dipilih.');
                return null;
            }
            if (!jamMulai || !jamSelesai) {
                showAlert('error', 'Jam mulai dan selesai wajib diisi.');
                return null;
            }
            if (jamSelesai <= jamMulai) {
                showAlert('error', 'Jam selesai harus lebih besar dari jam mulai.');
                return null;
            }
            if (!mapel) {
                showAlert('error', 'Mata pelajaran wajib diisi.');
                return null;
            }

            return {
                kelas_id: kelasId,
                guru_id: guruValue === '' ? null : Number(guruValue),
                hari,
                jam_mulai: jamMulai,
                jam_selesai: jamSelesai,
                mata_pelajaran: mapel,
                ruang: ruang === '' ? null : ruang,
                keterangan: keterangan === '' ? null : keterangan,
            };
        }

        async function saveJadwalPelajaran(event, jadwalId) {
            event.preventDefault();
            const payload = collectJadwalFormData();
            if (!payload) return;

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
            }

            try {
                const isEdit = jadwalId !== null && jadwalId !== undefined && String(jadwalId) !== 'null';
                const url = isEdit ? getUpdateUrl(jadwalId) : window.APP_ROUTES?.jadwalPelajaranStore;
                const method = isEdit ? 'PUT' : 'POST';

                const res = await apiRequest(url, {
                    method,
                    body: JSON.stringify(payload)
                });

                closeModal();
                await loadJadwalData();
                showAlert('success', res.message || (isEdit ? 'Jadwal pelajaran diperbarui.' : 'Jadwal pelajaran ditambahkan.'));
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

        async function deleteJadwalPelajaran(id) {
            try {
                const res = await apiRequest(getDestroyUrl(id), { method: 'DELETE' });
                await loadJadwalData();
                showAlert('success', res.message || 'Jadwal pelajaran berhasil dihapus.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            }
        }

        function showAddJadwalPelajaranModal() {
            showModal(getJadwalFormHTML({}, null));
        }

        function showEditJadwalPelajaranModal(id) {
            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data jadwal tidak ditemukan.');
                return;
            }

            showModal(getJadwalFormHTML(row, row.id));
        }

        function confirmDeleteJadwalPelajaran(id) {
            const row = state.fullData.find((item) => Number(item.id) === Number(id));
            if (!row) {
                showAlert('error', 'Data jadwal tidak ditemukan.');
                return;
            }

            Swal.fire({
                title: 'Hapus jadwal?',
                html: `Jadwal <b>${escapeHtml(row.mata_pelajaran)}</b> akan dihapus.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteJadwalPelajaran(row.id);
                }
            });
        }

        function handleJadwalPelajaranLimit(value) {
            state.limit = value === 'all' ? Infinity : Math.max(1, parseInt(value, 10) || 10);
            state.page = 1;
            renderRows();
        }

        function handleJadwalPelajaranSearch(value) {
            state.search = String(value || '').trim().toLowerCase();
            state.page = 1;
            applyFilters();
        }

        function handleJadwalPelajaranDayFilter(value) {
            state.dayFilter = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function changeJadwalPelajaranPage(direction) {
            const totalPages = state.limit === Infinity
                ? 1
                : Math.max(1, Math.ceil(state.filtered.length / state.limit));
            const next = state.page + direction;
            if (next < 1 || next > totalPages) return;
            state.page = next;
            renderRows();
        }

        function refreshJadwalPelajaranData() {
            loadJadwalData(true);
        }

        window.showModal = showModal;
        window.closeModal = closeModal;
        window.showAddJadwalPelajaranModal = showAddJadwalPelajaranModal;
        window.showEditJadwalPelajaranModal = showEditJadwalPelajaranModal;
        window.confirmDeleteJadwalPelajaran = confirmDeleteJadwalPelajaran;
        window.saveJadwalPelajaran = saveJadwalPelajaran;
        window.refreshJadwalPelajaranData = refreshJadwalPelajaranData;
        window.handleJadwalPelajaranLimit = handleJadwalPelajaranLimit;
        window.handleJadwalPelajaranSearch = handleJadwalPelajaranSearch;
        window.handleJadwalPelajaranDayFilter = handleJadwalPelajaranDayFilter;
        window.changeJadwalPelajaranPage = changeJadwalPelajaranPage;

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('view-jadwal-pelajaran')) {
                loadJadwalData();
            }
        });
    })();
</script>

