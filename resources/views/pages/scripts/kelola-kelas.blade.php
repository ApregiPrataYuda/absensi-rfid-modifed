<script>
    (function () {
        const state = {
            fullData: [],
            filtered: [],
            guruOptions: [],
            defaultJam: {
                jam_masuk_mulai: '06:00',
                jam_masuk_akhir: '07:15',
                jam_masuk_telat: '07:15',
                jam_pulang_mulai: '15:00',
                jam_pulang_akhir: '17:00'
            },
            limit: 10,
            page: 1,
            search: '',
            tingkat: ''
        };

        const csrfToken = '{{ csrf_token() }}';
        const hariList = [
            { hari: 1, label: 'Senin' },
            { hari: 2, label: 'Selasa' },
            { hari: 3, label: 'Rabu' },
            { hari: 4, label: 'Kamis' },
            { hari: 5, label: 'Jumat' },
            { hari: 6, label: 'Sabtu' },
            { hari: 7, label: 'Minggu' }
        ];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatTime(value) {
            const raw = String(value ?? '').trim();
            if (!raw) return '-';
            return raw.length >= 5 ? raw.slice(0, 5) : raw;
        }

        function normalizeTimeValue(value) {
            const raw = String(value ?? '').trim();
            if (!raw) return '';
            if (/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(raw)) return raw;
            if (/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/.test(raw)) return raw.slice(0, 5);
            return '';
        }

        function buildInitialJadwal(data = {}) {
            const byDay = new Map(
                (Array.isArray(data.jadwal_harian) ? data.jadwal_harian : [])
                    .map((item) => [Number(item?.hari), item])
            );

            const defaultJamMasukMulai = normalizeTimeValue(state.defaultJam?.jam_masuk_mulai) || '06:00';
            const defaultJamMasukAkhir = normalizeTimeValue(state.defaultJam?.jam_masuk_akhir) || '07:15';
            const defaultJamMasukTelat = normalizeTimeValue(state.defaultJam?.jam_masuk_telat) || defaultJamMasukAkhir;
            const defaultJamPulangMulai = normalizeTimeValue(state.defaultJam?.jam_pulang_mulai) || '15:00';
            const defaultJamPulangAkhir = normalizeTimeValue(state.defaultJam?.jam_pulang_akhir) || '17:00';

            return hariList.map((hariItem) => {
                const source = byDay.get(hariItem.hari) || {};
                const isLiburDefault = hariItem.hari === 7;

                return {
                    hari: hariItem.hari,
                    label: hariItem.label,
                    is_libur: Boolean(source?.is_libur ?? isLiburDefault),
                    jam_masuk_mulai: normalizeTimeValue(source?.jam_masuk_mulai) || defaultJamMasukMulai,
                    jam_masuk_akhir: normalizeTimeValue(source?.jam_masuk_akhir) || defaultJamMasukAkhir,
                    jam_masuk_telat: normalizeTimeValue(source?.jam_masuk_telat) || defaultJamMasukTelat,
                    jam_pulang_mulai: normalizeTimeValue(source?.jam_pulang_mulai) || defaultJamPulangMulai,
                    jam_pulang_akhir: normalizeTimeValue(source?.jam_pulang_akhir) || defaultJamPulangAkhir
                };
            });
        }

        function getRingkasanJam(row) {
            const jadwal = Array.isArray(row?.jadwal_harian) ? row.jadwal_harian : [];
            const aktifSenin = jadwal.find((item) => Number(item?.hari) === 1 && !item?.is_libur);
            const aktifPertama = aktifSenin || jadwal.find((item) => !item?.is_libur);

            if (aktifPertama) {
                return {
                    jamMasuk: `${formatTime(aktifPertama.jam_masuk_mulai)} - ${formatTime(aktifPertama.jam_masuk_akhir)}`,
                    jamPulang: `${formatTime(aktifPertama.jam_pulang_mulai)} - ${formatTime(aktifPertama.jam_pulang_akhir)}`,
                    label: aktifSenin ? 'Senin' : (hariList.find((h) => h.hari === Number(aktifPertama.hari))?.label || 'Jadwal')
                };
            }

            return {
                jamMasuk: `${formatTime(state.defaultJam?.jam_masuk_mulai)} - ${formatTime(state.defaultJam?.jam_masuk_akhir)}`,
                jamPulang: `${formatTime(state.defaultJam?.jam_pulang_mulai)} - ${formatTime(state.defaultJam?.jam_pulang_akhir)}`,
                label: 'Konfigurasi'
            };
        }

        function extractTingkat(nama) {
            const match = String(nama ?? '').trim().match(/^(\d{1,2})/);
            return match ? match[1] : '';
        }

        const showAlert = window.showAlert || function (type, message) {
            console[type === 'error' ? 'error' : 'log'](message);
        };

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
            return String(window.APP_ROUTES?.kelolaKelasUpdate || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function getDestroyUrl(id) {
            return String(window.APP_ROUTES?.kelolaKelasDestroy || '')
                .replace('__ID__', encodeURIComponent(String(id)));
        }

        function updatePagination() {
            const total = state.filtered.length;
            const infoEl = document.getElementById('info-kelas');
            const btnPrev = document.getElementById('btn-prev-kelas');
            const btnNext = document.getElementById('btn-next-kelas');

            const totalPages = state.limit === Infinity ? 1 : Math.max(1, Math.ceil(total / state.limit));
            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            if (total === 0) {
                if (infoEl) infoEl.textContent = 'Tidak ada data kelas.';
                if (btnPrev) btnPrev.disabled = true;
                if (btnNext) btnNext.disabled = true;
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const endIdx = state.limit === Infinity ? total : Math.min(startIdx + state.limit, total);

            if (infoEl) {
                infoEl.textContent = `Menampilkan ${startIdx + 1} - ${endIdx} dari ${total} kelas`;
            }
            if (btnPrev) btnPrev.disabled = state.page <= 1;
            if (btnNext) btnNext.disabled = state.page >= totalPages;
        }

        function renderRows() {
            const tbody = document.getElementById('tbody-kelas');
            if (!tbody) return;

            const total = state.filtered.length;
            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-400">Data kelas tidak ditemukan.</td></tr>';
                updatePagination();
                return;
            }

            const startIdx = state.limit === Infinity ? 0 : (state.page - 1) * state.limit;
            const rows = state.limit === Infinity
                ? state.filtered
                : state.filtered.slice(startIdx, startIdx + state.limit);

            tbody.innerHTML = rows.map((row, i) => {
                const ringkasan = getRingkasanJam(row);
                const jamMasuk = ringkasan.jamMasuk;
                const jamPulang = ringkasan.jamPulang;
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 text-center text-gray-500">${startIdx + i + 1}</td>
                        <td class="p-3">
                            <div class="font-semibold text-gray-800">${escapeHtml(row.nama)}</div>
                            <div class="text-[10px] text-gray-400 lg:hidden">Masuk ${escapeHtml(jamMasuk)} | Pulang ${escapeHtml(jamPulang)} | Siswa ${escapeHtml(row.jumlah_siswa ?? 0)}</div>
                        </td>
                        <td class="p-3 hidden md:table-cell">${escapeHtml(row.wali_kelas_nama || '-')}</td>
                        <td class="p-3 hidden sm:table-cell">
                            <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-bold">${row.kapasitas ?? '-'}</span>
                        </td>
                        <td class="p-3 hidden md:table-cell">
                            <span class="px-2 py-1 bg-emerald-50 text-emerald-700 rounded text-xs font-bold">${escapeHtml(row.jumlah_siswa ?? 0)}</span>
                        </td>
                        <td class="p-3 hidden lg:table-cell">
                            <div class="text-[11px] text-gray-700"><span class="font-bold">Masuk:</span> ${escapeHtml(jamMasuk)}</div>
                            <div class="text-[11px] text-gray-700"><span class="font-bold">Pulang:</span> ${escapeHtml(jamPulang)}</div>
                            <div class="text-[10px] text-gray-400 mt-1">Acuan: ${escapeHtml(ringkasan.label)}</div>
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="showEditKelasModal(${row.id})" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button onclick="confirmDeleteKelas(${row.id})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            updatePagination();
        }

        function renderKelasLoading(message = 'Memuat data kelas...') {
            const tbody = document.getElementById('tbody-kelas');
            if (!tbody) return;

            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="p-8 text-center">
                        <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>${escapeHtml(message)}</span>
                        </div>
                    </td>
                </tr>
            `;
        }

        function renderTingkatFilter() {
            const select = document.getElementById('filterTingkatKelas');
            if (!select) return;

            const current = state.tingkat || '';
            const items = [...new Set(
                state.fullData
                    .map((item) => extractTingkat(item.nama))
                    .filter(Boolean)
            )].sort((a, b) => Number(a) - Number(b));

            select.innerHTML = '<option value="">Semua Tingkat</option>' + items
                .map((item) => `<option value="${escapeHtml(item)}">${escapeHtml(item)}</option>`)
                .join('');
            select.value = current;
        }

        function applyFilters() {
            let data = [...state.fullData];

            if (state.tingkat) {
                data = data.filter((item) => extractTingkat(item.nama) === state.tingkat);
            }

            if (state.search) {
                const q = state.search.toLowerCase();
                data = data.filter((item) => {
                    const blob = [
                        item.nama,
                        item.wali_kelas_nama,
                        item.kapasitas,
                        item.jumlah_siswa
                    ].map((x) => String(x ?? '').toLowerCase()).join(' ');
                    return blob.includes(q);
                });
            }

            state.filtered = data;
            renderRows();
        }

        async function loadKelasData(showToast = false) {
            const tbody = document.getElementById('tbody-kelas');
            if (!tbody) return;

            renderKelasLoading('Memuat data kelas...');
            try {
                const res = await apiRequest(window.APP_ROUTES?.kelolaKelasData);
                state.fullData = Array.isArray(res?.data) ? res.data : [];
                state.guruOptions = Array.isArray(res?.guru) ? res.guru : [];
                if (res?.default_jam && typeof res.default_jam === 'object') {
                    state.defaultJam = {
                        jam_masuk_mulai: normalizeTimeValue(res.default_jam.jam_masuk_mulai) || '06:00',
                        jam_masuk_akhir: normalizeTimeValue(res.default_jam.jam_masuk_akhir) || '07:15',
                        jam_masuk_telat: normalizeTimeValue(res.default_jam.jam_masuk_telat) || normalizeTimeValue(res.default_jam.jam_masuk_akhir) || '07:15',
                        jam_pulang_mulai: normalizeTimeValue(res.default_jam.jam_pulang_mulai) || '15:00',
                        jam_pulang_akhir: normalizeTimeValue(res.default_jam.jam_pulang_akhir) || '17:00'
                    };
                }
                state.page = 1;
                renderTingkatFilter();
                applyFilters();
                if (showToast) {
                    showAlert('success', 'Data kelas diperbarui.');
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-500">${escapeHtml(err.message || err)}</td></tr>`;
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

        function getGuruDisplayName(guru) {
            const name = String(guru?.name || '').trim();
            const username = String(guru?.username || '').trim();
            if (name !== '' && username !== '' && name.toLowerCase() !== username.toLowerCase()) {
                return `${name} (${username})`;
            }
            return name || username;
        }

        function renderWaliKelasDropdown(items) {
            const dropdown = document.getElementById('dropdownWaliKelasList');
            if (!dropdown) return;

            if (!items || items.length === 0) {
                dropdown.innerHTML = '<div class="px-4 py-3 text-xs text-gray-400 italic">Guru tidak ditemukan.</div>';
                return;
            }

            dropdown.innerHTML = items.map((guru) => `
                <div onclick="selectWaliKelas(${Number(guru.id)})" class="px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm text-gray-700 transition-colors border-b border-gray-50 last:border-none">
                    ${escapeHtml(getGuruDisplayName(guru))}
                </div>
            `).join('');
        }

        function openWaliKelasDropdown() {
            const dropdown = document.getElementById('dropdownWaliKelasList');
            if (!dropdown) return;

            renderWaliKelasDropdown(state.guruOptions);
            dropdown.classList.remove('hidden');
        }

        function filterWaliKelasDropdown(keyword) {
            const q = String(keyword || '').trim().toLowerCase();
            const hidden = document.getElementById('kelasWali');
            if (hidden) hidden.value = '';

            const filtered = state.guruOptions.filter((guru) => {
                const blob = [guru.name, guru.username]
                    .map((val) => String(val || '').toLowerCase())
                    .join(' ');
                return blob.includes(q);
            });

            renderWaliKelasDropdown(filtered);
            const dropdown = document.getElementById('dropdownWaliKelasList');
            if (dropdown) dropdown.classList.remove('hidden');
        }

        function selectWaliKelas(guruId) {
            const guru = state.guruOptions.find((item) => Number(item.id) === Number(guruId)) || null;
            const input = document.getElementById('kelasWaliSearch');
            const hidden = document.getElementById('kelasWali');

            if (input) input.value = guru ? getGuruDisplayName(guru) : '';
            if (hidden) hidden.value = guru ? String(guru.id) : '';
            closeWaliKelasDropdown();
        }

        function closeWaliKelasDropdown() {
            const dropdown = document.getElementById('dropdownWaliKelasList');
            if (!dropdown) return;
            setTimeout(() => dropdown.classList.add('hidden'), 200);
        }

        function setJadwalRowState(hari, isLibur) {
            const row = document.getElementById(`jadwalRow-${hari}`);
            if (row) {
                row.classList.toggle('bg-gray-50/80', Boolean(isLibur));
            }

            ['kelasJadwalMasukMulai', 'kelasJadwalMasukAkhir', 'kelasJadwalMasukTelat', 'kelasJadwalPulangMulai', 'kelasJadwalPulangAkhir'].forEach((prefix) => {
                const el = document.getElementById(`${prefix}-${hari}`);
                if (!el) return;

                el.disabled = Boolean(isLibur);
                el.classList.toggle('opacity-60', Boolean(isLibur));
                el.classList.toggle('bg-gray-100', Boolean(isLibur));
                el.classList.toggle('cursor-not-allowed', Boolean(isLibur));
            });
        }

        function toggleJadwalLibur(hari) {
            const checkbox = document.getElementById(`kelasJadwalLibur-${hari}`);
            if (!checkbox) return;
            setJadwalRowState(hari, checkbox.checked);
        }

        function getKelasFormHTML(data = {}, kelasId = null) {
            const inputClass = 'w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all';
            const labelClass = 'block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide';
            const jamInputClass = 'w-[84px] min-w-[84px] bg-white border border-gray-200 text-gray-900 text-sm rounded-md focus:ring-indigo-500 focus:border-indigo-500 px-1.5 py-1 h-8 transition-all';
            const isEdit = kelasId !== null && kelasId !== undefined;
            const selectedGuru = state.guruOptions.find((guru) => Number(guru.id) === Number(data.wali_kelas)) || null;
            const selectedGuruLabel = selectedGuru ? getGuruDisplayName(selectedGuru) : '';
            const selectedGuruId = selectedGuru ? Number(selectedGuru.id) : '';
            const jadwalAwal = buildInitialJadwal(data);
            const jadwalRows = jadwalAwal.map((item) => `
                <tr id="jadwalRow-${item.hari}" class="border-t border-gray-100 ${item.is_libur ? 'bg-gray-50/80' : ''}">
                    <td class="py-2 pr-2 text-[13px] font-bold text-gray-800">${item.label}</td>
                    <td class="py-2 px-2 text-center">
                        <input id="kelasJadwalLibur-${item.hari}" type="checkbox" ${item.is_libur ? 'checked' : ''} onchange="toggleJadwalLibur(${item.hari})" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="py-2 pl-1 pr-2">
                        <div class="grid grid-cols-[84px_auto_84px_auto_84px] gap-x-0.5 items-center justify-start">
                            <input id="kelasJadwalMasukMulai-${item.hari}" type="time" step="900" value="${escapeHtml(normalizeTimeValue(item.jam_masuk_mulai))}" class="${jamInputClass} ${item.is_libur ? 'opacity-60 bg-gray-100 cursor-not-allowed' : ''}" ${item.is_libur ? 'disabled' : ''}>
                            <span class="text-gray-400 font-bold text-xs">-</span>
                            <input id="kelasJadwalMasukAkhir-${item.hari}" type="time" step="900" value="${escapeHtml(normalizeTimeValue(item.jam_masuk_akhir))}" class="${jamInputClass} ${item.is_libur ? 'opacity-60 bg-gray-100 cursor-not-allowed' : ''}" ${item.is_libur ? 'disabled' : ''}>
                            <span class="text-gray-400 font-bold text-xs">-</span>
                            <input id="kelasJadwalMasukTelat-${item.hari}" type="time" step="900" value="${escapeHtml(normalizeTimeValue(item.jam_masuk_telat))}" class="${jamInputClass} ${item.is_libur ? 'opacity-60 bg-gray-100 cursor-not-allowed' : ''}" ${item.is_libur ? 'disabled' : ''}>
                        </div>
                    </td>
                    <td class="py-2 pl-2 pr-0">
                        <div class="grid grid-cols-[84px_auto_84px] gap-x-0.5 items-center justify-start">
                            <input id="kelasJadwalPulangMulai-${item.hari}" type="time" step="900" value="${escapeHtml(normalizeTimeValue(item.jam_pulang_mulai))}" class="${jamInputClass} ${item.is_libur ? 'opacity-60 bg-gray-100 cursor-not-allowed' : ''}" ${item.is_libur ? 'disabled' : ''}>
                            <span class="text-gray-400 font-bold text-xs">-</span>
                            <input id="kelasJadwalPulangAkhir-${item.hari}" type="time" step="900" value="${escapeHtml(normalizeTimeValue(item.jam_pulang_akhir))}" class="${jamInputClass} ${item.is_libur ? 'opacity-60 bg-gray-100 cursor-not-allowed' : ''}" ${item.is_libur ? 'disabled' : ''}>
                        </div>
                    </td>
                </tr>
            `).join('');

            return `
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="text-xl font-bold text-gray-800">${isEdit ? 'Edit Kelas' : 'Tambah Kelas'}</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
                    </div>
                    <div class="p-6 max-h-[75vh] overflow-y-auto">
                        <form onsubmit="saveKelas(event, ${isEdit ? kelasId : 'null'})" class="space-y-4">
                            <div>
                                <label class="${labelClass}">Nama Kelas</label>
                                <input id="kelasNama" type="text" value="${escapeHtml(data.nama || '')}" placeholder="Contoh: 10 IPA 1" required class="${inputClass}">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="${labelClass}">Wali Kelas</label>
                                    <div class="relative">
                                        <input id="kelasWaliSearch" type="text" value="${escapeHtml(selectedGuruLabel)}" placeholder="Cari nama/username guru" class="${inputClass}" autocomplete="off" onfocus="openWaliKelasDropdown()" oninput="filterWaliKelasDropdown(this.value)" onblur="closeWaliKelasDropdown()">
                                        <input id="kelasWali" type="hidden" value="${selectedGuruId}">
                                        <div id="dropdownWaliKelasList" class="hidden absolute z-20 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-40 overflow-y-auto mt-1"></div>
                                    </div>
                                </div>
                                <div>
                                    <label class="${labelClass}">Kapasitas</label>
                                    <input id="kelasKapasitas" type="number" min="1" value="${escapeHtml(data.kapasitas ?? '')}" placeholder="40" class="${inputClass}">
                                </div>
                            </div>
                            <div class="border border-gray-100 rounded-xl p-3 bg-gray-50/50">
                                <p class="text-xs font-bold text-gray-600 mb-3 uppercase">Jam Absensi Per Kelas</p>
                                <div class="overflow-x-auto">
                                    <table class="inline-table w-auto min-w-0 border-collapse">
                                        <thead>
                                            <tr class="text-[11px] uppercase tracking-wide text-gray-500">
                                                <th class="py-1.5 pr-2 text-left font-bold">Hari</th>
                                                <th class="py-1.5 px-2 text-center font-bold">Libur?</th>
                                                <th class="py-1.5 px-0 text-center font-bold">Jam Masuk (Awal - Akhir - Telat)</th>
                                                <th class="py-1.5 pl-2 pr-0 text-left font-bold">Jam Pulang (Awal - Akhir)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${jadwalRows}
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-[11px] text-gray-500 mt-3">
                                    Centang <b>Libur</b> agar hari tersebut tidak dihitung alpa otomatis.
                                </p>
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

        function initJadwalRowStates() {
            hariList.forEach((hariItem) => {
                const checkbox = document.getElementById(`kelasJadwalLibur-${hariItem.hari}`);
                if (!checkbox) return;
                setJadwalRowState(hariItem.hari, checkbox.checked);
            });
        }

        function collectKelasFormData() {
            const nama = document.getElementById('kelasNama')?.value?.trim() || '';
            const waliKelasRaw = document.getElementById('kelasWali')?.value?.trim() || '';
            const waliKelasText = document.getElementById('kelasWaliSearch')?.value?.trim() || '';
            const kapasitasRaw = document.getElementById('kelasKapasitas')?.value?.trim() || '';

            if (!nama) {
                showAlert('error', 'Nama kelas wajib diisi.');
                return null;
            }

            if (waliKelasText !== '' && waliKelasRaw === '') {
                showAlert('error', 'Pilih wali kelas dari dropdown.');
                return null;
            }

            const waliKelas = waliKelasRaw === '' ? null : parseInt(waliKelasRaw, 10);
            if (waliKelasRaw !== '' && (!Number.isFinite(waliKelas) || waliKelas <= 0)) {
                showAlert('error', 'Wali kelas tidak valid.');
                return null;
            }

            const kapasitas = kapasitasRaw === '' ? null : parseInt(kapasitasRaw, 10);
            if (kapasitasRaw !== '' && (!Number.isFinite(kapasitas) || kapasitas <= 0)) {
                showAlert('error', 'Kapasitas harus berupa angka lebih dari 0.');
                return null;
            }

            const jadwalHarian = [];
            for (const hariItem of hariList) {
                const hari = Number(hariItem.hari);
                const isLibur = Boolean(document.getElementById(`kelasJadwalLibur-${hari}`)?.checked);
                const jamMasukMulai = normalizeTimeValue(document.getElementById(`kelasJadwalMasukMulai-${hari}`)?.value);
                const jamMasukAkhir = normalizeTimeValue(document.getElementById(`kelasJadwalMasukAkhir-${hari}`)?.value);
                const jamMasukTelat = normalizeTimeValue(document.getElementById(`kelasJadwalMasukTelat-${hari}`)?.value);
                const jamPulangMulai = normalizeTimeValue(document.getElementById(`kelasJadwalPulangMulai-${hari}`)?.value);
                const jamPulangAkhir = normalizeTimeValue(document.getElementById(`kelasJadwalPulangAkhir-${hari}`)?.value);

                if (!isLibur) {
                    if (!jamMasukMulai || !jamMasukAkhir || !jamMasukTelat || !jamPulangMulai || !jamPulangAkhir) {
                        showAlert('error', `Jadwal ${hariItem.label} belum lengkap.`);
                        return null;
                    }

                    if (jamMasukMulai > jamMasukAkhir) {
                        showAlert('error', `Jam masuk ${hariItem.label} tidak valid (awal > akhir).`);
                        return null;
                    }

                    if (jamPulangMulai > jamPulangAkhir) {
                        showAlert('error', `Jam pulang ${hariItem.label} tidak valid (awal > akhir).`);
                        return null;
                    }

                    if (jamMasukAkhir > jamMasukTelat) {
                        showAlert('error', `Jam telat ${hariItem.label} harus >= jam masuk akhir.`);
                        return null;
                    }

                    if (jamMasukTelat > jamPulangMulai) {
                        showAlert('error', `Jam telat ${hariItem.label} harus <= jam pulang mulai.`);
                        return null;
                    }
                }

                jadwalHarian.push({
                    hari,
                    is_libur: isLibur,
                    jam_masuk_mulai: jamMasukMulai || null,
                    jam_masuk_akhir: jamMasukAkhir || null,
                    jam_masuk_telat: jamMasukTelat || null,
                    jam_pulang_mulai: jamPulangMulai || null,
                    jam_pulang_akhir: jamPulangAkhir || null,
                    keterangan: isLibur ? hariItem.label : null
                });
            }

            return {
                nama,
                wali_kelas: waliKelas,
                kapasitas,
                jadwal_harian: jadwalHarian
            };
        }

        async function saveKelas(event, kelasId) {
            event.preventDefault();
            const payload = collectKelasFormData();
            if (!payload) return;

            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
            }

            try {
                const isEdit = kelasId !== null && kelasId !== undefined && String(kelasId) !== 'null';
                const url = isEdit ? getUpdateUrl(kelasId) : window.APP_ROUTES?.kelolaKelasStore;
                const method = isEdit ? 'PUT' : 'POST';

                const res = await apiRequest(url, {
                    method,
                    body: JSON.stringify(payload)
                });

                closeModal();
                await loadKelasData();
                showAlert('success', res.message || (isEdit ? 'Kelas diperbarui.' : 'Kelas ditambahkan.'));
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

        async function deleteKelas(id) {
            try {
                const res = await apiRequest(getDestroyUrl(id), { method: 'DELETE' });
                await loadKelasData();
                showAlert('success', res.message || 'Kelas dihapus.');
            } catch (err) {
                showAlert('error', err.message || String(err));
            }
        }

        function showAddKelasModal() {
            showModal(getKelasFormHTML({}, null));
            initJadwalRowStates();
        }

        function showEditKelasModal(dataOrId) {
            let kelas = null;
            if (typeof dataOrId === 'number' || /^\d+$/.test(String(dataOrId))) {
                const id = Number(dataOrId);
                kelas = state.fullData.find((item) => Number(item.id) === id) || null;
            } else if (dataOrId && typeof dataOrId === 'object') {
                kelas = dataOrId;
            }

            if (!kelas) {
                showAlert('info', 'Data kelas tidak ditemukan.');
                return;
            }

            showModal(getKelasFormHTML(kelas, kelas.id));
            initJadwalRowStates();
        }

        function confirmDeleteKelas(dataOrId) {
            let kelas = null;
            if (typeof dataOrId === 'number' || /^\d+$/.test(String(dataOrId))) {
                const id = Number(dataOrId);
                kelas = state.fullData.find((item) => Number(item.id) === id) || null;
            } else if (dataOrId && typeof dataOrId === 'object') {
                kelas = dataOrId;
            }

            if (!kelas || !kelas.id) {
                showAlert('info', 'Data kelas tidak ditemukan.');
                return;
            }

            Swal.fire({
                title: 'Hapus kelas?',
                html: `Kelas <b>${escapeHtml(kelas.nama)}</b> akan dihapus.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteKelas(kelas.id);
                }
            });
        }

        function refreshData(type) {
            if (type !== 'kelas') return;
            loadKelasData(true);
        }

        function downloadTemplate(type) {
            if (type !== 'kelas') return;

            const rows = [
                ['nama', 'wali_kelas', 'kapasitas'],
                ['10 IPA 1', '12', '40']
            ];
            const csv = rows.map((row) => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'template_kelas.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        function triggerImportKelas() {
            const input = document.getElementById('fileInputKelas');
            if (input) input.click();
        }

        function handleFileImportKelas(input) {
            if (!input || !input.files || !input.files[0]) return;
            showAlert('info', 'Import kelas belum diaktifkan pada versi ini.');
            input.value = '';
        }

        function handleTableLimit(type, value) {
            if (type !== 'kelas') return;
            state.limit = value === 'all' ? Infinity : Math.max(1, parseInt(value, 10) || 10);
            state.page = 1;
            renderRows();
        }

        function handleTableSearch(type, value) {
            if (type !== 'kelas') return;
            state.search = String(value || '').trim().toLowerCase();
            state.page = 1;
            applyFilters();
        }

        function handleTableClassFilter(type, value) {
            if (type !== 'kelas') return;
            state.tingkat = String(value || '');
            state.page = 1;
            applyFilters();
        }

        function changePage(type, direction) {
            if (type !== 'kelas') return;
            const totalPages = state.limit === Infinity
                ? 1
                : Math.max(1, Math.ceil(state.filtered.length / state.limit));
            const next = state.page + direction;
            if (next < 1 || next > totalPages) return;
            state.page = next;
            renderRows();
        }

        window.showModal = showModal;
        window.closeModal = closeModal;
        window.showAddKelasModal = showAddKelasModal;
        window.showEditKelasModal = showEditKelasModal;
        window.confirmDeleteKelas = confirmDeleteKelas;
        window.saveKelas = saveKelas;
        window.refreshData = refreshData;
        window.downloadTemplate = downloadTemplate;
        window.triggerImportKelas = triggerImportKelas;
        window.handleFileImportKelas = handleFileImportKelas;
        window.handleTableLimit = handleTableLimit;
        window.handleTableSearch = handleTableSearch;
        window.handleTableClassFilter = handleTableClassFilter;
        window.changePage = changePage;
        window.openWaliKelasDropdown = openWaliKelasDropdown;
        window.filterWaliKelasDropdown = filterWaliKelasDropdown;
        window.selectWaliKelas = selectWaliKelas;
        window.closeWaliKelasDropdown = closeWaliKelasDropdown;
        window.toggleJadwalLibur = toggleJadwalLibur;

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('view-kelola-kelas')) {
                loadKelasData();
            }
        });
    })();
</script>
