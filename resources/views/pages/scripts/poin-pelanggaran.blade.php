<script>
    (function () {
        const state = {
            jenis: [],
            riwayat: [],
            siswa: [],
            kelas: [],
            ringkasan: [],
            canManage: false,
            filters: {
                tanggal_dari: '',
                tanggal_sampai: '',
                kelas: '',
                siswa_id: '',
                q: ''
            }
        };

        const csrfToken = '{{ csrf_token() }}';

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

        function getRoute(name, fallback = '') {
            return String(window.APP_ROUTES?.[name] || fallback || '').trim();
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

        function getJenisUpdateUrl(id) {
            return getRoute('poinPelanggaranJenisUpdate').replace('__ID__', encodeURIComponent(String(id)));
        }

        function getJenisDestroyUrl(id) {
            return getRoute('poinPelanggaranJenisDestroy').replace('__ID__', encodeURIComponent(String(id)));
        }

        function getRiwayatUpdateUrl(id) {
            return getRoute('poinPelanggaranRiwayatUpdate').replace('__ID__', encodeURIComponent(String(id)));
        }

        function getRiwayatDestroyUrl(id) {
            return getRoute('poinPelanggaranRiwayatDestroy').replace('__ID__', encodeURIComponent(String(id)));
        }

        function setRowLoading(targetId, colspan, text) {
            const tbody = document.getElementById(targetId);
            if (!tbody) return;
            tbody.innerHTML = `<tr><td colspan="${colspan}" class="p-8 text-center text-gray-400">${escapeHtml(text)}</td></tr>`;
        }

        function renderRingkasan() {
            const container = document.getElementById('poinPelanggaranRingkasan');
            if (!container) return;

            if (!Array.isArray(state.ringkasan) || state.ringkasan.length === 0) {
                container.innerHTML = '<div class="p-3 rounded-lg border border-dashed border-gray-200 text-xs text-gray-500 bg-gray-50">Belum ada akumulasi poin pelanggaran.</div>';
                return;
            }

            container.innerHTML = state.ringkasan.map((row) => {
                const totalPoin = Number(row.total_poin || 0);
                const badgeClass = totalPoin >= 100
                    ? 'bg-red-100 text-red-700'
                    : (totalPoin >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700');

                return `
                    <div class="rounded-lg border border-gray-200 p-3 bg-white">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-sm text-gray-800">${escapeHtml(row.nama || '-')}</div>
                                <div class="text-[11px] text-gray-500">${escapeHtml(row.nisn || '-')} • ${escapeHtml(row.kelas || '-')}</div>
                            </div>
                            <span class="px-2 py-1 rounded text-[11px] font-bold ${badgeClass}">${totalPoin} poin</span>
                        </div>
                        <div class="mt-2 text-[11px] text-gray-500">Total pelanggaran: <span class="font-semibold text-gray-700">${Number(row.total_pelanggaran || 0)}</span></div>
                    </div>
                `;
            }).join('');
        }

        function renderJenisTable() {
            const tbody = document.getElementById('tbody-jenis-pelanggaran');
            if (!tbody) return;

            if (!Array.isArray(state.jenis) || state.jenis.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-gray-400">Belum ada jenis pelanggaran.</td></tr>';
                return;
            }

            tbody.innerHTML = state.jenis.map((row, index) => {
                const statusBadge = row.is_active
                    ? '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-700 text-[10px] font-bold">Aktif</span>'
                    : '<span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-[10px] font-bold">Nonaktif</span>';

                const actions = state.canManage
                    ? `
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="showJenisPelanggaranModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="deleteJenisPelanggaran(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `
                    : '<span class="text-gray-400 text-[11px]">Read only</span>';

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${index + 1}</td>
                        <td class="p-3 font-semibold text-gray-800">${escapeHtml(row.nama)}</td>
                        <td class="p-3 text-gray-600">${escapeHtml(row.kategori || '-')}</td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded bg-indigo-100 text-indigo-700 text-[11px] font-bold">${Number(row.poin || 0)}</span></td>
                        <td class="p-3 text-center">${statusBadge}</td>
                        <td class="p-3 text-center">${actions}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderRiwayatTable() {
            const tbody = document.getElementById('tbody-riwayat-pelanggaran');
            if (!tbody) return;

            if (!Array.isArray(state.riwayat) || state.riwayat.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="p-8 text-center text-gray-400">Belum ada catatan pelanggaran karyawan.</td></tr>';
                return;
            }

            tbody.innerHTML = state.riwayat.map((row, index) => {
                const actions = state.canManage
                    ? `
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="showRiwayatPelanggaranModal(${Number(row.id)})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="deleteRiwayatPelanggaran(${Number(row.id)})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `
                    : '<span class="text-gray-400 text-[11px]">Read only</span>';

                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${index + 1}</td>
                        <td class="p-3 text-gray-700">${escapeHtml(row.tanggal || '-')}</td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.siswa_nama || '-')}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.siswa_nisn || '-')}</div>
                        </td>
                        <td class="p-3 text-gray-700">${escapeHtml(row.kelas || '-')}</td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.nama_pelanggaran || '-')}</div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(row.kategori || '-')}</div>
                        </td>
                        <td class="p-3 text-center"><span class="px-2 py-1 rounded bg-rose-100 text-rose-700 text-[11px] font-bold">${Number(row.poin || 0)}</span></td>
                        <td class="p-3 text-gray-600">${escapeHtml(row.catatan || '-')}</td>
                        <td class="p-3 text-gray-600">${escapeHtml(row.input_by || '-')}</td>
                        <td class="p-3 text-center">${actions}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderFilterOptions() {
            const kelasSelect = document.getElementById('filterPelanggaranKelas');
            const siswaSelect = document.getElementById('filterPelanggaranSiswa');

            const currentKelas = state.filters.kelas || '';
            const currentSiswa = state.filters.siswa_id || '';

            if (kelasSelect) {
                const kelasOptions = ['<option value="">Semua Gedung</option>'].concat(
                    (state.kelas || []).map((row) => `<option value="${escapeHtml(row.nama)}">${escapeHtml(row.nama)}</option>`)
                );
                kelasSelect.innerHTML = kelasOptions.join('');
                kelasSelect.value = currentKelas;
            }

            if (siswaSelect) {
                const siswaOptions = ['<option value="">Semua Gedung</option>'].concat(
                    (state.siswa || []).map((row) => `<option value="${Number(row.id)}">${escapeHtml(row.nama)} (${escapeHtml(row.kelas || '-')})</option>`)
                );
                siswaSelect.innerHTML = siswaOptions.join('');
                siswaSelect.value = currentSiswa;
            }
        }

        function buildDataUrl() {
            const base = getRoute('poinPelanggaranData');
            const params = new URLSearchParams();
            if (state.filters.tanggal_dari) params.set('tanggal_dari', state.filters.tanggal_dari);
            if (state.filters.tanggal_sampai) params.set('tanggal_sampai', state.filters.tanggal_sampai);
            if (state.filters.kelas) params.set('kelas', state.filters.kelas);
            if (state.filters.siswa_id) params.set('siswa_id', state.filters.siswa_id);
            if (state.filters.q) params.set('q', state.filters.q);
            const query = params.toString();
            return query ? `${base}?${query}` : base;
        }

        async function loadPoinPelanggaranData(showToast = false) {
            setRowLoading('tbody-jenis-pelanggaran', 6, 'Memuat data jenis pelanggaran...');
            setRowLoading('tbody-riwayat-pelanggaran', 9, 'Memuat riwayat pelanggaran...');

            try {
                const res = await apiRequest(buildDataUrl());
                state.jenis = Array.isArray(res?.jenis) ? res.jenis : [];
                state.riwayat = Array.isArray(res?.data) ? res.data : [];
                state.siswa = Array.isArray(res?.siswa) ? res.siswa : [];
                state.kelas = Array.isArray(res?.kelas) ? res.kelas : [];
                state.ringkasan = Array.isArray(res?.ringkasan) ? res.ringkasan : [];
                state.canManage = Boolean(res?.can_manage);

                renderFilterOptions();
                renderJenisTable();
                renderRiwayatTable();
                renderRingkasan();

                if (showToast) {
                    showAlert('success', 'Data poin & pelanggaran diperbarui.');
                }
            } catch (err) {
                setRowLoading('tbody-jenis-pelanggaran', 6, err.message || 'Gagal memuat data.');
                setRowLoading('tbody-riwayat-pelanggaran', 9, err.message || 'Gagal memuat data.');
                renderRingkasan();
                showAlert('error', err.message || 'Gagal memuat data.');
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
                    </div>
                `;
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

        function renderJenisModal(item = null) {
            const isEdit = !!item;
            showModal(`
                <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="text-sm font-bold text-gray-800">${isEdit ? 'Edit Jenis Pelanggaran' : 'Tambah Jenis Pelanggaran'}</h3>
                    </div>
                    <form id="formJenisPelanggaran" class="p-5 space-y-4">
                        <input type="hidden" name="id" value="${isEdit ? Number(item.id) : ''}">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Nama Pelanggaran</label>
                            <input name="nama" type="text" required maxlength="150" value="${escapeHtml(item?.nama || '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Kategori</label>
                            <input name="kategori" type="text" maxlength="80" value="${escapeHtml(item?.kategori || '')}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Poin</label>
                            <input name="poin" type="number" min="1" max="1000" required value="${Number(item?.poin || 1)}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-gray-700 font-semibold">
                            <input name="is_active" type="checkbox" ${item ? (item.is_active ? 'checked' : '') : 'checked'} class="rounded border-gray-300">
                            Jenis pelanggaran aktif
                        </label>
                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick="closeModal()" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">${isEdit ? 'Simpan Perubahan' : 'Simpan Jenis'}</button>
                        </div>
                    </form>
                </div>
            `);

            const form = document.getElementById('formJenisPelanggaran');
            if (form) {
                form.addEventListener('submit', submitJenisPelanggaran);
            }
        }

        function formatSiswaLookupLabel(row) {
            const nama = String(row?.nama || '').trim() || '-';
            const kelas = String(row?.kelas || '').trim() || '-';
            const nisn = String(row?.nisn || '').trim() || '-';
            return `${nama} (${kelas}) - ${nisn}`;
        }

        function bindRiwayatSiswaLookup() {
            const searchInput = document.getElementById('riwayatSiswaSearch');
            const hiddenInput = document.getElementById('riwayatSiswaId');
            const dropdown = document.getElementById('riwayatSiswaDropdown');
            if (!searchInput || !hiddenInput || !dropdown) return;

            const normalizeText = (value) => String(value || '').trim().toLowerCase();

            const hideDropdown = () => {
                dropdown.innerHTML = '';
                dropdown.classList.add('hidden');
            };

            const setSelectedSiswa = (row) => {
                const siswaId = Number(row?.id || 0);
                if (siswaId <= 0) return;

                hiddenInput.value = String(siswaId);
                searchInput.value = formatSiswaLookupLabel(row);
                searchInput.dataset.selectedId = String(siswaId);
                searchInput.classList.remove('border-rose-300');
                hideDropdown();
            };

            const getFilteredSiswa = (keyword) => {
                const q = normalizeText(keyword);
                const rows = !q
                    ? (state.siswa || [])
                    : (state.siswa || []).filter((row) => {
                        const haystack = [
                            row?.nama,
                            row?.nisn,
                            row?.kelas
                        ].map(normalizeText).join(' ');
                        return haystack.includes(q);
                    });

                return rows.slice(0, 30);
            };

            const renderDropdown = (keyword) => {
                const rows = getFilteredSiswa(keyword);
                if (rows.length === 0) {
                    dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Karyawan tidak ditemukan.</div>';
                    dropdown.classList.remove('hidden');
                    return;
                }

                dropdown.innerHTML = rows.map((row) => `
                    <button type="button" data-riwayat-siswa-id="${Number(row.id || 0)}" class="w-full text-left px-3 py-2 hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                        <div class="text-xs font-semibold text-gray-700">${escapeHtml(String(row.nama || '-'))}</div>
                        <div class="text-[11px] text-gray-500">${escapeHtml(String(row.nisn || '-'))} | ${escapeHtml(String(row.kelas || '-'))}</div>
                    </button>
                `).join('');
                dropdown.classList.remove('hidden');

                dropdown.querySelectorAll('[data-riwayat-siswa-id]').forEach((button) => {
                    button.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        const siswaId = Number(button.getAttribute('data-riwayat-siswa-id') || 0);
                        const row = (state.siswa || []).find((item) => Number(item.id || 0) === siswaId) || null;
                        if (!row) return;
                        setSelectedSiswa(row);
                    });
                });
            };

            const clearSelectionOnTyping = () => {
                hiddenInput.value = '';
                searchInput.dataset.selectedId = '';
                searchInput.classList.remove('border-rose-300');
                renderDropdown(searchInput.value);
            };

            searchInput.addEventListener('focus', () => {
                renderDropdown(searchInput.value);
            });
            searchInput.addEventListener('input', clearSelectionOnTyping);
            searchInput.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (String(searchInput.value || '').trim() !== '' && Number(hiddenInput.value || 0) <= 0) {
                        searchInput.classList.add('border-rose-300');
                    }
                    hideDropdown();
                }, 150);
            });

            const selectedId = Number(hiddenInput.value || 0);
            if (selectedId > 0) {
                const selectedRow = (state.siswa || []).find((item) => Number(item.id || 0) === selectedId) || null;
                if (selectedRow) {
                    searchInput.value = formatSiswaLookupLabel(selectedRow);
                    searchInput.dataset.selectedId = String(selectedId);
                }
            }
        }

        function renderRiwayatModal(item = null) {
            const isEdit = !!item;
            const selectedSiswaId = Number(item?.siswa_id || 0);
            const selectedSiswa = (state.siswa || []).find((row) => Number(row.id) === selectedSiswaId) || null;
            const siswaSearchValue = selectedSiswa ? formatSiswaLookupLabel(selectedSiswa) : '';
            const jenisOptions = (state.jenis || [])
                .filter((row) => row.is_active || Number(item?.jenis_pelanggaran_id || 0) === Number(row.id))
                .map((row) => {
                    const selected = Number(item?.jenis_pelanggaran_id || 0) === Number(row.id) ? 'selected' : '';
                    return `<option value="${Number(row.id)}" data-poin="${Number(row.poin || 0)}" ${selected}>${escapeHtml(row.nama)} (${Number(row.poin || 0)} poin)</option>`;
                }).join('');
            const tanggal = item?.tanggal || new Date().toISOString().slice(0, 10);

            showModal(`
                <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
                        <h3 class="text-sm font-bold text-gray-800">${isEdit ? 'Edit Catatan Pelanggaran' : 'Catat Pelanggaran Karyawan'}</h3>
                    </div>
                    <form id="formRiwayatPelanggaran" class="p-5 space-y-4">
                        <input type="hidden" name="id" value="${isEdit ? Number(item.id) : ''}">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Karyawan</label>
                            <div class="relative">
                                <input id="riwayatSiswaSearch" type="text" autocomplete="off" placeholder="Ketik nama atau NISN, lalu pilih karyawan" value="${escapeHtml(siswaSearchValue)}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                <input id="riwayatSiswaId" type="hidden" name="siswa_id" value="${selectedSiswaId > 0 ? selectedSiswaId : ''}">
                                <div id="riwayatSiswaDropdown" class="hidden absolute top-full left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-20"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500">Pilih karyawan dari daftar hasil pencarian.</p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Jenis Pelanggaran</label>
                            <select id="riwayatJenisSelect" name="jenis_pelanggaran_id" required class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                                <option value="">Pilih jenis pelanggaran</option>
                                ${jenisOptions}
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Poin</label>
                            <input id="riwayatPoinPreview" type="text" readonly value="${Number(item?.poin || 0)} poin" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-lg p-2.5 font-semibold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Tanggal</label>
                            <input name="tanggal" type="date" required value="${escapeHtml(tanggal)}" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-700 mb-1">Catatan</label>
                            <textarea name="catatan" rows="3" class="w-full bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg p-2.5">${escapeHtml(item?.catatan || '')}</textarea>
                        </div>
                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button" onclick="closeModal()" class="px-3 py-2 rounded-lg border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">${isEdit ? 'Simpan Perubahan' : 'Simpan Catatan'}</button>
                        </div>
                    </form>
                </div>
            `);

            const jenisSelect = document.getElementById('riwayatJenisSelect');
            if (jenisSelect) {
                jenisSelect.addEventListener('change', syncPoinPreview);
                syncPoinPreview();
            }
            bindRiwayatSiswaLookup();

            const form = document.getElementById('formRiwayatPelanggaran');
            if (form) {
                form.addEventListener('submit', submitRiwayatPelanggaran);
            }
        }

        function syncPoinPreview() {
            const jenisSelect = document.getElementById('riwayatJenisSelect');
            const preview = document.getElementById('riwayatPoinPreview');
            if (!jenisSelect || !preview) return;
            const selected = jenisSelect.options[jenisSelect.selectedIndex];
            const poin = Number(selected?.dataset?.poin || 0);
            preview.value = `${poin} poin`;
        }

        async function submitJenisPelanggaran(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const id = Number(formData.get('id') || 0);

            const payload = {
                nama: String(formData.get('nama') || '').trim(),
                kategori: String(formData.get('kategori') || '').trim(),
                poin: Number(formData.get('poin') || 0),
                is_active: formData.get('is_active') ? 1 : 0
            };

            const isEdit = id > 0;
            const url = isEdit ? getJenisUpdateUrl(id) : getRoute('poinPelanggaranJenisStore');
            const method = isEdit ? 'PUT' : 'POST';

            try {
                await apiRequest(url, { method, body: JSON.stringify(payload) });
                closeModal();
                await loadPoinPelanggaranData();
                showAlert('success', isEdit ? 'Jenis pelanggaran berhasil diperbarui.' : 'Jenis pelanggaran berhasil ditambahkan.');
            } catch (err) {
                showAlert('error', err.message || 'Gagal menyimpan jenis pelanggaran.');
            }
        }

        async function submitRiwayatPelanggaran(event) {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const id = Number(formData.get('id') || 0);
            const siswaId = Number(formData.get('siswa_id') || 0);

            if (siswaId <= 0) {
                showAlert('error', 'Pilih karyawan dari daftar pencarian yang muncul.');
                return;
            }

            const payload = {
                siswa_id: siswaId,
                jenis_pelanggaran_id: Number(formData.get('jenis_pelanggaran_id') || 0),
                tanggal: String(formData.get('tanggal') || '').trim(),
                catatan: String(formData.get('catatan') || '').trim()
            };

            const isEdit = id > 0;
            const url = isEdit ? getRiwayatUpdateUrl(id) : getRoute('poinPelanggaranRiwayatStore');
            const method = isEdit ? 'PUT' : 'POST';

            try {
                await apiRequest(url, { method, body: JSON.stringify(payload) });
                closeModal();
                await loadPoinPelanggaranData();
                showAlert('success', isEdit ? 'Catatan pelanggaran berhasil diperbarui.' : 'Pelanggaran karyawan berhasil dicatat.');
            } catch (err) {
                showAlert('error', err.message || 'Gagal menyimpan catatan pelanggaran.');
            }
        }

        async function deleteJenis(id) {
            const row = (state.jenis || []).find((item) => Number(item.id) === Number(id));
            if (!row) return;

            const message = `Hapus jenis pelanggaran "${row.nama}"?`;
            const confirmed = window.Swal
                ? (await Swal.fire({
                    icon: 'warning',
                    title: 'Konfirmasi',
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626'
                })).isConfirmed
                : window.confirm(message);

            if (!confirmed) return;

            try {
                await apiRequest(getJenisDestroyUrl(id), { method: 'DELETE' });
                await loadPoinPelanggaranData();
                showAlert('success', 'Jenis pelanggaran berhasil dihapus.');
            } catch (err) {
                showAlert('error', err.message || 'Gagal menghapus jenis pelanggaran.');
            }
        }

        async function deleteRiwayat(id) {
            const row = (state.riwayat || []).find((item) => Number(item.id) === Number(id));
            if (!row) return;

            const message = `Hapus catatan pelanggaran karyawan "${row.siswa_nama}"?`;
            const confirmed = window.Swal
                ? (await Swal.fire({
                    icon: 'warning',
                    title: 'Konfirmasi',
                    text: message,
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626'
                })).isConfirmed
                : window.confirm(message);

            if (!confirmed) return;

            try {
                await apiRequest(getRiwayatDestroyUrl(id), { method: 'DELETE' });
                await loadPoinPelanggaranData();
                showAlert('success', 'Catatan pelanggaran berhasil dihapus.');
            } catch (err) {
                showAlert('error', err.message || 'Gagal menghapus catatan pelanggaran.');
            }
        }

        function applyFiltersFromUI() {
            state.filters.tanggal_dari = String(document.getElementById('filterPelanggaranTanggalDari')?.value || '').trim();
            state.filters.tanggal_sampai = String(document.getElementById('filterPelanggaranTanggalSampai')?.value || '').trim();
            state.filters.kelas = String(document.getElementById('filterPelanggaranKelas')?.value || '').trim();
            state.filters.siswa_id = String(document.getElementById('filterPelanggaranSiswa')?.value || '').trim();
            state.filters.q = String(document.getElementById('filterPelanggaranKeyword')?.value || '').trim();
            loadPoinPelanggaranData();
        }

        function resetFilters() {
            state.filters = {
                tanggal_dari: '',
                tanggal_sampai: '',
                kelas: '',
                siswa_id: '',
                q: ''
            };

            const tanggalDari = document.getElementById('filterPelanggaranTanggalDari');
            const tanggalSampai = document.getElementById('filterPelanggaranTanggalSampai');
            const kelas = document.getElementById('filterPelanggaranKelas');
            const siswa = document.getElementById('filterPelanggaranSiswa');
            const keyword = document.getElementById('filterPelanggaranKeyword');

            if (tanggalDari) tanggalDari.value = '';
            if (tanggalSampai) tanggalSampai.value = '';
            if (kelas) kelas.value = '';
            if (siswa) siswa.value = '';
            if (keyword) keyword.value = '';

            loadPoinPelanggaranData();
        }

        function bindFilterListeners() {
            const kelasSelect = document.getElementById('filterPelanggaranKelas');
            if (kelasSelect && kelasSelect.dataset.bound !== '1') {
                kelasSelect.addEventListener('change', applyFiltersFromUI);
                kelasSelect.dataset.bound = '1';
            }

            const keywordInput = document.getElementById('filterPelanggaranKeyword');
            if (keywordInput && keywordInput.dataset.bound !== '1') {
                let debounceTimer = null;
                keywordInput.addEventListener('input', function () {
                    if (debounceTimer) {
                        clearTimeout(debounceTimer);
                    }
                    debounceTimer = setTimeout(function () {
                        applyFiltersFromUI();
                    }, 350);
                });
                keywordInput.dataset.bound = '1';
            }
        }

        window.refreshPoinPelanggaranData = function (showToast = false) {
            loadPoinPelanggaranData(Boolean(showToast));
        };

        window.applyPelanggaranFilters = function () {
            applyFiltersFromUI();
        };

        window.resetPelanggaranFilters = function () {
            resetFilters();
        };

        window.showJenisPelanggaranModal = function (id = null) {
            if (!state.canManage) return;
            if (!id) {
                renderJenisModal(null);
                return;
            }
            const row = (state.jenis || []).find((item) => Number(item.id) === Number(id)) || null;
            renderJenisModal(row);
        };

        window.deleteJenisPelanggaran = function (id) {
            if (!state.canManage) return;
            deleteJenis(id);
        };

        window.showRiwayatPelanggaranModal = function (id = null) {
            if (!state.canManage) return;
            if (!id) {
                renderRiwayatModal(null);
                return;
            }
            const row = (state.riwayat || []).find((item) => Number(item.id) === Number(id)) || null;
            renderRiwayatModal(row);
        };

        window.deleteRiwayatPelanggaran = function (id) {
            if (!state.canManage) return;
            deleteRiwayat(id);
        };

        window.closeModal = window.closeModal || closeModal;

        document.addEventListener('DOMContentLoaded', function () {
            if (!document.getElementById('view-poin-pelanggaran')) {
                return;
            }
            bindFilterListeners();
            loadPoinPelanggaranData();
        });
    })();
</script>
