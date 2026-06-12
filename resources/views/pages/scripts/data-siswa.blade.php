<script>
    // ==========================================================================
    // 1. GLOBAL STATE & KONFIGURASI
    // ==========================================================================
    const API_BASE = window.APP_API_BASE || '/api';
    const DEPLOYMENT_URL = window.location.origin + '/scanner';
    let currentUser = null;
    let role = '';
    let html5QrCode = null;
    let isScanning = false;
    let isSidebarOpen = true;
    let cameraPopup = null;
    let siswaCardScanEventSource = null;
    let siswaCardScanTimeoutId = null;
    let siswaCardScanLoadingVisible = false;
    const SISWA_CARD_SCAN_TIMEOUT_MS = 45000;

    // --- SCAN LIVE TABLE STATE ---
    let scanLiveCount = 0;
    let scanLiveMap = {}; // nisn -> tr element 
    
    // --- SMART CACHE ---
    let appCache = {
        siswa: null,
        guru: null
    };

    // --- CHART & UTILS ---
    let dashboardChart = null;
    let existingClasses = []; // Untuk autocomplete kelas
    let guruChartInstance = null;
    let adminChartInstance = null;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function getCurrentUserToken() {
        return currentUser ? currentUser.token : null;
    }

    function resolveActionEndpoint(method) {
        const methodName = String(method || '').trim();
        const endpoint = (window.APP_AJAX_ACTIONS || {})[methodName];

        if (!endpoint) {
            throw new Error(`Endpoint "${methodName}" belum dikonfigurasi.`);
        }

        return endpoint;
    }

    async function runPageAction(method, args = []) {
        const payload = {
            args: Array.isArray(args) ? args : [args],
        };
        const token = getCurrentUserToken();

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
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(result.message || 'Gagal memproses permintaan.');
        }

        return result;
    }

    function createActionRunner() {
        const buildRunner = (state = {}) => new Proxy({}, {
            get(target, prop) {
                if (prop === 'withSuccessHandler') {
                    return (callback) => buildRunner({ ...state, success: callback });
                }

                if (prop === 'withFailureHandler') {
                    return (callback) => buildRunner({ ...state, failure: callback });
                }

                if (prop === 'withUserObject') {
                    return () => buildRunner(state);
                }

                if (prop === 'then') {
                    return undefined;
                }

                return (...methodArgs) => {
                    runPageAction(String(prop), methodArgs)
                        .then((result) => {
                            if (typeof state.success === 'function') {
                                state.success(result);
                            }
                        })
                        .catch((error) => {
                            if (typeof state.failure === 'function') {
                                state.failure(error.message || error);
                            }
                        });

                    return buildRunner();
                };
            },
        });

        return buildRunner();
    }

    const actionRunner = createActionRunner();

    // Sinkronkan tanggal header (timezone Asia/Jakarta)
    if (typeof window.updateHeaderCurrentDate === 'function') {
        window.updateHeaderCurrentDate();
    } else {
        const dateElement = document.getElementById('currentDateDisplay');
        if (dateElement) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: (window.APP_TIMEZONE || 'Asia/Jakarta') };
            dateElement.textContent = new Intl.DateTimeFormat('id-ID', options).format(new Date());
        }
    }

    const tableState = {
                siswa: { fullData: [], filtered: [], limit: 10, page: 1, search: '', classFilter: '' },
                // UPDATE BAGIAN INI: Tambahkan classFilter: ''
                guru: { fullData: [], filtered: [], limit: 10, page: 1, search: '', classFilter: '' },
                
                libur: { fullData: [], filtered: [], limit: 10, page: 1, search: '' },
                rekap: { fullData: [], filtered: [], limit: 10, page: 1, search: '' },
                monitoring: { fullData: [], filtered: [], limit: 10, page: 1, search: '', statusFilter: '' }
            };

function handleTableSearch(type, query) {
        tableState[type].search = query.toLowerCase();
        tableState[type].page = 1; // Reset ke halaman 1 saat search
        processTableData(type);
    }

// FUNGSI BARU: Handle perubahan dropdown filter kelas
    function handleTableClassFilter(type, value) {
        if (tableState[type]) {
            tableState[type].classFilter = value;
            tableState[type].page = 1; // Reset ke halaman 1
            processTableData(type);
        }
    }
    
function handleTableStatusFilter(type, status) {
        if (tableState[type]) {
            tableState[type].statusFilter = status;
            tableState[type].page = 1; // Reset ke halaman 1
            processTableData(type);
        }
    }

    function handleTableLimit(type, limit) {
        tableState[type].limit = limit === 'all' ? Infinity : parseInt(limit);
        tableState[type].page = 1; // Reset ke halaman 1 saat ganti limit
        processTableData(type);
    }

    function changePage(type, direction) {
        const state = tableState[type];
        const maxPage = Math.ceil(state.filtered.length / state.limit);
        const newPage = state.page + direction;
        
        if (newPage >= 1 && newPage <= maxPage) {
            state.page = newPage;
            processTableData(type);
        }
    }

function processTableData(type) {
        const state = tableState[type];
        
        // 1. Mulai dari data mentah (Full Data)
        let result = [...state.fullData];

        // 2. FILTER KHUSUS: KELAS (Untuk Siswa & Guru)
        // Logika ini akan jalan jika dropdown filter kelas dipilih
        if ((type === 'siswa' || type === 'guru') && state.classFilter) {
            result = result.filter(item => item.kelas === state.classFilter);
        }

        // 3. FILTER KHUSUS: STATUS KEHADIRAN (Untuk Monitoring)
        if (type === 'monitoring' && state.statusFilter) {
            result = result.filter(item => item.status === state.statusFilter);
        }

        // 4. FILTER UMUM: PENCARIAN (SEARCH)
        if (state.search) {
            const query = state.search.toLowerCase();
            result = result.filter(item => 
                Object.values(item).some(val => 
                    String(val).toLowerCase().includes(query)
                )
            );
        }

        // 5. Simpan hasil penyaringan ke state
        state.filtered = result;

        // 6. LOGIKA PAGINASI
        const total = state.filtered.length;
        const totalPages = Math.ceil(total / state.limit);
        
        // Koreksi halaman jika melebihi total halaman (misal setelah search/filter)
        if (state.page > totalPages && totalPages > 0) state.page = totalPages;
        if (total === 0) state.page = 1;

        const startIdx = (state.page - 1) * state.limit;
        const endIdx = startIdx + state.limit;
        
        // Ambil data untuk halaman saat ini
        const pagedData = state.filtered.slice(startIdx, endIdx);

        // 7. RENDER TABEL (Pilih renderer berdasarkan tipe)
        if (type === 'siswa') renderSiswaRows(pagedData, startIdx);
        else if (type === 'guru') renderGuruRows(pagedData, startIdx);
        else if (type === 'libur') renderLiburRows(pagedData, startIdx);
        else if (type === 'rekap') renderRekapRows(pagedData);
        else if (type === 'monitoring') renderMonitoringRows(pagedData, startIdx);

        // 8. UPDATE UI PAGINATION (Footer Tabel)
        updatePaginationUI(type, startIdx, pagedData.length, total, state.page, totalPages);
    }

    function updatePaginationUI(type, startIdx, currentCount, total, currentPage, totalPages) {
        const infoEl = document.getElementById(`info-${type}`);
        const btnPrev = document.getElementById(`btn-prev-${type}`);
        const btnNext = document.getElementById(`btn-next-${type}`);
        
        if (total === 0) {
            infoEl.textContent = 'Tidak ada data ditemukan.';
            btnPrev.disabled = true;
            btnNext.disabled = true;
        } else {
            const end = startIdx + currentCount;
            infoEl.textContent = `Menampilkan ${startIdx + 1} - ${end} dari ${total} data`;
            btnPrev.disabled = currentPage === 1;
            btnNext.disabled = currentPage >= totalPages;
        }
    }
    // ==========================================================================
    // 2. SESSION MANAGEMENT
    // ==========================================================================
    function checkSession() {
        if (window.APP_CURRENT_USER) {
            currentUser = window.APP_CURRENT_USER;

            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
            }

            initDashboard();
        }
    }

    // ==========================================================================
    // 3. UI NAVIGATION & SIDEBAR
    // ==========================================================================
    function showView() {}

function initDashboard() {
    role = String(currentUser.role || '').toLowerCase();
    currentUser.role = role;
    const name = currentUser.nama || currentUser.username;
    document.getElementById('navUserName').textContent = name;
    
    // Tampilkan Role dengan format yang rapi
    let displayRole = role ? role.toUpperCase() : 'USER';
    if (role === 'wakel') displayRole = 'WALI KELAS';
    if (role === 'kepsek') displayRole = 'KEPALA SEKOLAH';
    if (role === 'wakasek') displayRole = 'WAKIL KEPALA SEKOLAH';
    document.getElementById('navUserRole').textContent = displayRole;
    
    document.getElementById('navUserInitial').textContent = name.charAt(0).toUpperCase();

    // Sidebar menu dirender dari Blade (server-side permission check), tidak di-generate ulang via JS.
    
    // --- AMBIL DATA KELAS (Untuk Autocomplete/Dropdown) ---
    loadKelasSuggestions();
    bootstrapCurrentPage();
}
    
    // --- DROPDOWN KELAS LOGIC ---
    function loadKelasSuggestions() {
        console.log("Memuat daftar Gedung dari Konfigurasi...");
        
                actionRunner.withSuccessHandler(function(classes) {
            const normalizedClasses = Array.isArray(classes)
                ? classes
                : (classes && Array.isArray(classes.data) ? classes.data : []);

            existingClasses = normalizedClasses;

            // Populate semua dropdown yang ada di HTML saat ini
            populateAllClassDropdowns();

        }).withFailureHandler(function(err) {
            console.error('Gagal memuat daftar gedung:', err);
            existingClasses = [];
            populateAllClassDropdowns();
        }).getDaftarKelas(); // Memanggil fungsi backend yang baru kita ubah
    }

function populateAllClassDropdowns() {
    const dropdownIds = [
        'filterKelas',      // Di Data Siswa
        'filterKelasGuru',  // Di Data Guru (jika ada)
        'rekapKelas',       // Di Rekap Bulanan
        'fKelasRekap',      // Di Rekap Absensi
        'promoKelasAsal',   // Di Kenaikan Kelas
        'promoKelasTujuan'  // Di Kenaikan Kelas
    ];

    dropdownIds.forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            // Simpan value yang sedang dipilih (jika ada) biar tidak ke-reset total
            const currentValue = select.value;
            
            // Simpan opsi default/pertama (biasanya "-- Pilih Kelas --" atau "Semua Kelas")
            const defaultOption = select.options[0] ? select.options[0].cloneNode(true) : null;
            
            // Opsional khusus: Untuk Promo Tujuan ada opsi LULUS
            let extraOption = null;
            if(id === 'promoKelasTujuan') {
               extraOption = select.querySelector('option[value="LULUS"]');
            }

            // Kosongkan dropdown
            select.innerHTML = '';
            
            // Kembalikan opsi default
            if (defaultOption) select.appendChild(defaultOption);
            if (extraOption) select.appendChild(extraOption.cloneNode(true));

            // Isi dengan data dari Konfigurasi
            existingClasses.forEach(kelas => {
                const option = document.createElement('option');
                option.value = kelas;
                option.text = kelas;
                select.appendChild(option);
            });

            // Restore value jika masih valid
            if (existingClasses.includes(currentValue)) {
                select.value = currentValue;
            }
        }
    });
}
    function openKelasDropdown() {
        const input = document.getElementById('inputKelas');
        const dropdown = document.getElementById('dropdownKelasList');
        if (!dropdown) return;
        renderKelasDropdown(existingClasses);
        dropdown.classList.remove('hidden');
    }

    function filterKelasDropdown(keyword) {
        const filtered = existingClasses.filter(c => c.toLowerCase().includes(keyword.toLowerCase()));
        renderKelasDropdown(filtered);
    }

    function renderKelasDropdown(items) {
        const dropdown = document.getElementById('dropdownKelasList');
        if (!items || items.length === 0) {
            dropdown.innerHTML = '<div class="px-4 py-3 text-xs text-gray-400 italic">Kelas tidak ditemukan. Ketik untuk membuat baru.</div>';
            return;
        }
        dropdown.innerHTML = items.map(kelas => `
            <div onclick="selectKelas('${kelas}')" class="px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm text-gray-700 transition-colors border-b border-gray-50 last:border-none">
                ${kelas}
            </div>
        `).join('');
    }

    function selectKelas(value) {
        const input = document.getElementById('inputKelas');
        if (input) {
            input.value = value;
            closeKelasDropdown();
        }
    }

    function closeKelasDropdown() {
        const dropdown = document.getElementById('dropdownKelasList');
        if (dropdown) {
            setTimeout(() => dropdown.classList.add('hidden'), 200);
        }
    }

    // ==========================================================================
    // 6. FUNGSI REFRESH DATA
    // ==========================================================================
function refreshData(type) {
        const btnIcon = event ? event.currentTarget.querySelector('i') : null;
        if(btnIcon) btnIcon.classList.add('fa-spin');

        if (type === 'siswa') {
            tableState.siswa.fullData = []; // Clear cache
            loadDataSiswa();       
            showAlert('success', 'Data karyawan diperbarui.');
        } 
        else if (type === 'guru') {
             tableState.guru.fullData = []; // Clear cache
            loadDataGuru();        
            showAlert('success', 'Data karyawan diperbarui.');
        }
        else if (type === 'dashboard') {
            if (role === 'admin') loadAdminDashboard();
            else if (role === 'wakel') loadGuruDashboard();
            else loadSiswaDashboard();
            showAlert('success', 'Statistik Dashboard diperbarui.');
        }

        else if (type === 'monitoring') {
            tableState.monitoring.fullData = []; 
            loadMonitoringAbsensi(); 
            showAlert('success', 'Data monitoring diperbarui.');
        }

        if(btnIcon) setTimeout(() => btnIcon.classList.remove('fa-spin'), 1000);
    }

    // ==========================================================================
    // 7. HALAMAN & LOGIKA DATA
    // ==========================================================================
    
    // --- ADMIN DASHBOARD ---
function loadAdminDashboard() {
    window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
}

    function renderAdminChart(hadir, sakit, izin, alpa, belum) {
        const ctx = document.getElementById('adminAttendanceChart');
        if (!ctx) return;

        if (adminChartInstance) {
            adminChartInstance.destroy();
        }

        if (typeof ChartDataLabels !== 'undefined') { Chart.register(ChartDataLabels); }

        adminChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Belum Absen'],
                datasets: [{
                    label: 'Jumlah Siswa',
                    data: [hadir, sakit, izin, alpa, belum],
                    backgroundColor: [
                        '#10B981', // Emerald (Hadir)
                        '#EAB308', // Yellow (Sakit)
                        '#3B82F6', // Blue (Izin)
                        '#EF4444', // Red (Alpa)
                        '#9CA3AF'  // Gray (Belum)
                    ],
                    borderRadius: 8,
                    barPercentage: 0.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        formatter: (val) => val > 0 ? val : '',
                        font: { weight: 'bold' },
                        color: '#666'
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 2] } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // --- GURU DASHBOARD ---
function loadGuruDashboard() {
    window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
}

    // Fungsi Render Chart Guru
    function renderGuruChart(hadir, sakit, izin, alpa, belumAbsen) {
        const ctx = document.getElementById('guruAttendanceChart');
        if (!ctx) return;

        // Hapus chart lama jika ada (mencegah glitch overlap)
        if (guruChartInstance) {
            guruChartInstance.destroy();
        }

        if (typeof ChartDataLabels !== 'undefined') { Chart.register(ChartDataLabels); }

        guruChartInstance = new Chart(ctx, {
            type: 'bar', // Diagram Batang
            data: {
                labels: ['Hadir', 'Sakit', 'Izin', 'Alpa', 'Belum Absen'],
                datasets: [{
                    label: 'Jumlah Siswa',
                    data: [hadir, sakit, izin, alpa, belumAbsen],
                    backgroundColor: [
                        '#10B981', // Hadir (Green)
                        '#F59E0B', // Sakit (Yellow)
                        '#3B82F6', // Izin (Blue)
                        '#EF4444', // Alpa (Red)
                        '#9CA3AF'  // Belum Absen (Gray)
                    ],
                    borderRadius: 6,
                    barPercentage: 0.6,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }, // Sembunyikan legenda default
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        formatter: (value) => value > 0 ? value : '', // Hanya tampilkan label jika > 0
                        font: { weight: 'bold', size: 11 },
                        color: '#4B5563'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4], color: '#F3F4F6' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Helper: Animasi Angka (Counter Up)
    function animateValue(id, start, end, duration) {
        const obj = document.getElementById(id);
        if(!obj) return;
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            obj.innerHTML = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // --- SISWA DASHBOARD (DENGAN CEK LIBUR) ---
    function loadSiswaDashboard() {
    window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
}

function patchedEscapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function findSiswaByNisn(nisn) {
        const target = String(nisn ?? '').trim();
        if (target === '') {
            return null;
        }

        return (tableState.siswa.fullData || []).find((row) => String(row?.nisn ?? '').trim() === target) || null;
    }

    function handleSiswaTableAction(action, nisn) {
        const siswa = findSiswaByNisn(nisn);
        if (!siswa) {
            showAlert('error', 'Data karyawan tidak ditemukan. Coba muat ulang halaman.');
            return;
        }

        if (action === 'view') {
            viewSiswa(siswa);
            return;
        }

        if (action === 'edit') {
            editSiswa(siswa);
            return;
        }

        if (action === 'delete') {
            deleteSiswaConfirm(siswa.nisn, siswa.nama);
            return;
        }

        if (action === 'qr') {
            generateQRForSiswa(siswa.nisn, siswa.nama, siswa.kelas);
        }
    }

    function bindSiswaTableActions() {
        const tbody = document.getElementById('tbody-siswa');
        if (!tbody || tbody.dataset.actionBound === '1') {
            return;
        }

        tbody.dataset.actionBound = '1';
        tbody.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-siswa-action]');
            if (!button || !tbody.contains(button)) {
                return;
            }

            handleSiswaTableAction(
                button.getAttribute('data-siswa-action') || '',
                button.getAttribute('data-siswa-nisn') || ''
            );
        });
    }

function renderSiswaRows(data, startIdx) {
        const tbody = document.getElementById('tbody-siswa');
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">Data tidak ditemukan.</td></tr>';
            return;
        }
        tbody.innerHTML = data.map((siswa, i) => `
        <tr class="hover:bg-gray-50 transition border-b border-gray-50 group">
            <td class="p-4 text-center text-gray-500 text-sm">${startIdx + i + 1}</td>
            <td class="p-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold mr-3">
                        ${patchedEscapeHtml((siswa.nama || '').charAt(0))}
                    </div>
                    <div>
                        <div class="font-bold text-sm text-gray-900">${patchedEscapeHtml(siswa.nama || '-')}</div>
                        <div class="text-xs text-gray-500 md:hidden">${patchedEscapeHtml(siswa.nisn || '-')}</div>
                    </div>
                </div>
            </td>
            <td class="p-4 hidden md:table-cell text-sm text-gray-600 font-mono">${patchedEscapeHtml(siswa.nisn || '-')}</td>
            <td class="p-4 hidden sm:table-cell"><span class="px-2 py-1 bg-blue-50 text-blue-700 rounded text-xs font-bold">${patchedEscapeHtml(siswa.kelas || '-')}</span></td>
            <td class="p-4 hidden lg:table-cell text-sm text-gray-700">${patchedEscapeHtml(siswa.jenisKelamin || '-')}</td>
            <td class="p-4 hidden xl:table-cell text-sm text-gray-700">${patchedEscapeHtml(siswa.agama || '-')}</td>
            <td class="p-4 hidden xl:table-cell text-sm text-gray-700">${patchedEscapeHtml(siswa.noHp || '-')}</td>
            <td class="p-4 text-center">
                <div class="flex justify-center space-x-2 opacity-80 group-hover:opacity-100">
                    <button type="button" data-siswa-action="view" data-siswa-nisn="${patchedEscapeHtml(siswa.nisn || '')}" class="p-2 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition" title="Lihat Detail">
                        <i class="fas fa-eye"></i>
                    </button>
                    
                    <button type="button" data-siswa-action="edit" data-siswa-nisn="${patchedEscapeHtml(siswa.nisn || '')}" class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit"><i class="fas fa-edit"></i></button>
                    <button type="button" data-siswa-action="delete" data-siswa-nisn="${patchedEscapeHtml(siswa.nisn || '')}" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus"><i class="fas fa-trash"></i></button>
                   
                </div>
            </td>
        </tr>`).join('');
    }

    // --- DATA GURU ---
function loadDataGuru() {
    window.location.href = window.APP_ROUTES?.dataGuru || '/data-guru';
}
function saveSiswa(e, isEdit) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
        submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
    }

    const fd = new FormData(form);
    const siswaData = {
        nama: fd.get('nama'),
        nisn: fd.get('nisn'),
        jenisKelamin: fd.get('jenisKelamin'),
        tanggalLahir: fd.get('tanggalLahir'),
        agama: fd.get('agama'),
        namaAyah: fd.get('namaAyah'),
        namaIbu: fd.get('namaIbu'),
        noHp: fd.get('noHp'),
        kelas: fd.get('kelas'),
        nomorKartu: fd.get('nomorKartu'),
        alamat: fd.get('alamat')
    };

    // --- PERUBAHAN UTAMA DISINI ---
    // Ambil token dari user yang sedang login
    const token = currentUser ? currentUser.token : null; 
    // ------------------------------

    const restoreSubmitButton = () => {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
        submitBtn.innerHTML = originalBtnHtml;
    };

    const callback = (res) => {
        restoreSubmitButton();
        if (res.success) {
            closeModal();
            tableState.siswa.fullData = [];
            loadDataSiswa();
            showAlert('success', res.message);
        } else {
            showAlert('error', res.message);
        }
    };

    const failureCallback = (err) => {
        restoreSubmitButton();
        showAlert('error', 'Terjadi kesalahan: ' + err);
    };

    // Kirim Token ke Server
    if (isEdit) {
        const oldNisn = fd.get('oldNisn');
        actionRunner
            .withSuccessHandler(callback)
            .withFailureHandler(failureCallback)
            .updateSiswa(oldNisn, siswaData);
    } else {
        actionRunner
            .withSuccessHandler(callback)
            .withFailureHandler(failureCallback)
            .addSiswa(siswaData);
    }
}
    // --- REKAP ABSENSI ---
function loadRekapAbsensi() {
    window.location.href = window.APP_ROUTES?.rekapAbsensi || '/rekap-absensi';
}

function applyFilter() {
        const emptyState = document.getElementById('rekapEmptyState');
        const container = document.getElementById('rekapContainer');
        const loading = document.getElementById('rekapLoading');

        emptyState.classList.add('hidden');
        container.classList.add('hidden');
        loading.classList.remove('hidden');

        // UPDATE: Ambil nilai kelas juga
        const filter = {
            tanggalMulai: document.getElementById('fStart').value,
            tanggalAkhir: document.getElementById('fEnd').value,
            kelas: document.getElementById('fKelasRekap').value // BARU
        };

        actionRunner.withSuccessHandler(result => {
            loading.classList.add('hidden');
            container.classList.remove('hidden');
            
            if (result.success) {
                tableState.rekap.fullData = result.data;
                processTableData('rekap');
            } else {
                 tableState.rekap.fullData = [];
                 processTableData('rekap');
            }
        }).getAbsensiList(filter);
    }

    // --- MONITORING ---
function loadMonitoringAbsensi() {
    window.location.href = window.APP_ROUTES?.monitoring || '/monitoring';
}

function exportMonitoringExcel() {
    // 1. Ambil elemen tombol
    const btn = document.getElementById('btnExportMonitoring');
    const originalContent = btn.innerHTML;

    // 2. AMBIL TANGGAL DARI INPUT (BARU)
    const startDate = document.getElementById('exportMonStart').value;
    const endDate = document.getElementById('exportMonEnd').value;

    if (!startDate || !endDate) {
        showAlert('error', 'Harap pilih tanggal mulai dan akhir untuk export.');
        return;
    }

    // 3. Ubah tampilan tombol menjadi "Loading"
    btn.disabled = true;
    btn.classList.add('cursor-not-allowed', 'opacity-75');
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span class="hidden sm:inline">Memproses...</span>';
    
    // 4. Siapkan Filter
    const myClass = (currentUser && role === 'wakel') ? currentUser.kelas : null;
    
    const filters = {
        kelas: myClass,
        tanggalMulai: startDate, // Kirim parameter tanggal
        tanggalAkhir: endDate
    };

    // 5. Panggil Backend
    actionRunner.withSuccessHandler(result => {
        btn.disabled = false;
        btn.classList.remove('cursor-not-allowed', 'opacity-75');
        btn.innerHTML = originalContent;

        if (result.success) {
            const link = document.createElement('a');
            link.href = result.url;
            link.setAttribute('download', '');
            document.body.appendChild(link);
            link.click();
            link.remove();
            showAlert('success', 'Data monitoring berhasil di-export!');
        } else {
            showAlert('error', result.message);
        }
    })
    .withFailureHandler(err => {
        btn.disabled = false;
        btn.classList.remove('cursor-not-allowed', 'opacity-75');
        btn.innerHTML = originalContent;
        showAlert('error', 'Gagal menghubungi server: ' + err);
    })
    .generateExcel('monitoring', filters);
}
    
function renderMonitoringRows(data, startIdx) {
    const tbody = document.getElementById('tbody-monitoring');
    
    // 1. Cek jika data kosong
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-gray-400 italic bg-white">Tidak ada data ditemukan.</td></tr>';
        return;
    }

    // 2. Cek Hak Akses (Hanya Guru/Admin yang bisa edit dropdown)
    const canEdit = (role === 'wakel' || role === 'admin');
    const cursorClass = canEdit ? 'cursor-pointer' : 'cursor-not-allowed opacity-70';
    const disabledAttr = canEdit ? '' : 'disabled';

    // 3. Render Baris Tabel
    tbody.innerHTML = data.map((d, i) => {
        
        // --- A. LOGIKA WARNA DROPDOWN STATUS (Kolom H) ---
        let statusColor = 'bg-gray-100 text-gray-600';
        if(d.status === 'Hadir') statusColor = 'bg-green-100 text-green-700';
        else if(d.status === 'Izin') statusColor = 'bg-blue-100 text-blue-700';
        else if(d.status === 'Sakit') statusColor = 'bg-yellow-100 text-yellow-700';
        else if(d.status === 'Alpa') statusColor = 'bg-red-100 text-red-700';

        // --- B. LOGIKA TAMPILAN KETERANGAN WAKTU (Kolom G) ---
        let ketText = d.keterangan || "-"; // Ambil teks dari server (misal: "Terlambat (15 m)")
        let ketStyle = "text-gray-400 font-mono text-[10px]"; // Default style
        let ketIcon = "";

        // Deteksi kata kunci dalam teks keterangan untuk memberi warna
        if (String(ketText).includes("Terlambat")) {
            // MERAH: Jika Terlambat
            ketStyle = "text-rose-600 font-bold bg-rose-50 px-2 py-1 rounded border border-rose-100 text-[10px]";
            ketIcon = '<i class="fas fa-history mr-1"></i>';
        } else if (String(ketText).includes("Pulang Cepat")) {
            // ORANYE: Jika Pulang Cepat
            ketStyle = "text-orange-600 font-bold bg-orange-50 px-2 py-1 rounded border border-orange-100 text-[10px]";
            ketIcon = '<i class="fas fa-running mr-1"></i>';
        } else if (ketText === "Tepat Waktu") {
            // HIJAU: Jika Tepat Waktu
            ketStyle = "text-emerald-600 font-bold text-[10px]";
            ketIcon = '<i class="fas fa-check-double mr-1"></i>';
        }

        // --- C. HTML ROW ---
        return `
        <tr class="hover:bg-gray-50 border-b border-gray-50 transition group">
            <td class="p-4 text-center text-gray-400 text-xs">${startIdx + i + 1}</td>
            
            <td class="p-4">
                <div class="font-bold text-sm text-gray-900">${d.nama}</div>
                <div class="text-xs text-gray-500 font-mono">${d.nisn}</div>
            </td>
            
            <td class="p-4 text-center">
                <span class="bg-indigo-50 text-indigo-600 px-2 py-1 rounded text-xs font-bold border border-indigo-100">${d.kelas}</span>
            </td>
            
            <td class="p-4 text-center text-xs font-mono text-gray-600">${d.jamDatang}</td>
            
            <td class="p-4 text-center text-xs font-mono text-gray-600">${d.jamPulang}</td>

            <td class="p-4 text-center align-middle">
                 <span class="${ketStyle} inline-block whitespace-nowrap">${ketIcon}${ketText}</span>
            </td>

            <td class="p-4 text-center relative">
                <select onchange="changeStatus('${d.nisn}', '${d.nama}', '${d.kelas}', this)" 
                        class="text-xs font-bold py-1.5 px-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 shadow-sm appearance-none text-center w-32 ${statusColor} ${cursorClass}"
                        ${disabledAttr}>
                    <option value="Belum Absen" ${d.status === 'Belum Absen' ? 'selected' : ''}>Belum Absen</option>
                    <option value="Hadir" ${d.status === 'Hadir' ? 'selected' : ''}>Hadir</option>
                    <option value="Izin" ${d.status === 'Izin' ? 'selected' : ''}>Izin</option>
                    <option value="Sakit" ${d.status === 'Sakit' ? 'selected' : ''}>Sakit</option>
                    <option value="Alpa" ${d.status === 'Alpa' ? 'selected' : ''}>Alpa</option>
                </select>
                ${canEdit ? '<i class="fas fa-chevron-down absolute right-6 top-1/2 transform -translate-y-1/2 text-[10px] pointer-events-none opacity-40"></i>' : ''}
            </td>
        </tr>`;
    }).join('');
}

    // Fungsi Handler saat Guru mengubah Status
function changeStatus(nisn, nama, kelas, selectElement) {
    const newStatus = selectElement.value;
    
    // Visual Feedback: Disable sementara dropdown saat memproses
    selectElement.disabled = true;
    selectElement.style.opacity = '0.5';

    // --- KEAMANAN: AMBIL TOKEN USER ---
    const token = currentUser ? currentUser.token : null;
    // ----------------------------------

    actionRunner.withSuccessHandler(res => {
        // Aktifkan kembali dropdown
        selectElement.disabled = false;
        selectElement.style.opacity = '1';

        if (res.success) {
            // Update Warna Dropdown secara langsung agar interaktif (UI Feedback)
            let newColor = 'bg-gray-100 text-gray-600';
            if(newStatus === 'Hadir') newColor = 'bg-green-100 text-green-700';
            else if(newStatus === 'Izin') newColor = 'bg-blue-100 text-blue-700';
            else if(newStatus === 'Sakit') newColor = 'bg-yellow-100 text-yellow-700';
            else if(newStatus === 'Alpa') newColor = 'bg-red-100 text-red-700';
            
            // Reset class dan tambahkan yang baru (pertahankan base styles)
            selectElement.className = `text-xs font-bold py-1.5 px-2 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 shadow-sm appearance-none text-center w-32 cursor-pointer ${newColor}`;
            
            // Opsional: Tampilkan notifikasi kecil
            // showAlert('success', 'Status diperbarui'); 
        } else {
            // Jika gagal (misal token expired/tidak ada hak akses)
            showAlert('error', 'Gagal update: ' + res.message);
            // Reload tabel untuk mengembalikan status asli dari server
            loadMonitoringAbsensi();
        }
    })
    .withFailureHandler(error => {
        selectElement.disabled = false;
        selectElement.style.opacity = '1';
        showAlert('error', 'Error koneksi: ' + error);
    })
    .updateAbsensiStatus(token, nisn, nama, kelas, newStatus); // <-- Token dikirim
}
    // --- KARTU SISWA ---
function loadQRCodeSiswa() {
    window.location.href = window.APP_ROUTES?.kartuSiswa || '/kartu-siswa';
}
    // --- SCANNER ---
    function loadScanAbsensi() {
    window.location.href = window.APP_ROUTES?.scanner || '/scanner';
}

    function openCameraPopup() {
        const popupUrl = `${window.APP_ROUTES?.scanner || '/scanner'}?camera=1`;
        cameraPopup = window.open(popupUrl, 'qr_cam', 'width=500,height=700,resizable=yes');
        if (!cameraPopup) {
            showAlert('error', 'Popup kamera diblokir browser. Izinkan popup lalu coba lagi.');
            return;
        }
        const pollingStatus = document.getElementById('pollingStatus');
        if (pollingStatus) pollingStatus.classList.remove('hidden');
    }

    function startPolling() {}

    function stopPolling() {
        const pollingStatus = document.getElementById('pollingStatus');
        if (pollingStatus) pollingStatus.classList.add('hidden');
    }

    function scanFromFile(input) {
        if (!input.files || !input.files[0]) return;
        const resDiv = document.getElementById('fileResult');
        resDiv.innerHTML = `<div class="text-center text-indigo-600 text-sm py-3 animate-pulse"><i class="fas fa-circle-notch fa-spin mr-2"></i>Memindai QR...</div>`;
        Html5Qrcode.scanFile(input.files[0], true)
            .then(decodedText => { resDiv.innerHTML = ''; onScanSuccess(decodedText); })
            .catch(() => { resDiv.innerHTML = `<div class="bg-red-50 text-red-600 p-3 rounded-xl border border-red-100 text-xs text-center">QR tidak terdeteksi. Coba foto lebih dekat.</div>`; });
        input.value = '';
    }

    function submitManualNisn() {
        const nisn = document.getElementById('manualNisn')?.value?.trim();
        if (!nisn) { showAlert('error', 'Masukkan NIK terlebih dahulu'); return; }
        document.getElementById('manualNisn').value = '';
        onScanSuccess(nisn);
    }

    function onScanSuccess(decodedText) {
        if (!decodedText || decodedText.trim() === "" || decodedText === "undefined") return;
        if (isScanning) return;

        const key = String(decodedText).trim();

        // Cegah scan duplikat
        if (scanLiveMap[key]) {
            highlightScanRow(scanLiveMap[key], document.getElementById('scanTableWrapper'));
            if (cameraPopup && !cameraPopup.closed) {
                const tr = scanLiveMap[key];
                const namaEl  = tr ? tr.querySelector('td:nth-child(2) .font-bold') : null;
                const kelasEl = tr ? tr.querySelector('td:nth-child(3) span') : null;
                try { cameraPopup.postMessage({ type: 'SCAN_DUPLICATE', nama: namaEl ? namaEl.textContent : key, kelas: kelasEl ? kelasEl.textContent : '' }, '*'); } catch(e) {}
            }
            return;
        }

        isScanning = true;

        // Lookup nama siswa ke server (TIDAK mencatat absensi)
        const myRole  = currentUser ? role  : '';
        const myKelas = currentUser ? currentUser.kelas : '';

        actionRunner
            .withSuccessHandler(result => {
                isScanning = false;
                if (result.success) {
                    addToScanQueue({ nisn: key, nama: result.nama, kelas: result.kelas });
                    if (cameraPopup && !cameraPopup.closed) {
                        try { cameraPopup.postMessage({ type: 'SCAN_SUCCESS', nama: result.nama, kelas: result.kelas }, '*'); } catch(e) {}
                    }
                } else {
                    showAlert('error', result.message || 'NIK tidak ditemukan');
                    if (cameraPopup && !cameraPopup.closed) {
                        try { cameraPopup.postMessage({ type: 'SCAN_ERROR', message: result.message || 'NIK tidak ditemukan' }, '*'); } catch(e) {}
                    }
                }
            })
            .withFailureHandler(err => {
                isScanning = false;
                showAlert('error', 'Error lookup: ' + (err.message || String(err)));
            })
            .lookupSiswaForScan(key);
    }

    function showScanModal(content) {}
    function closeScanModal() { isScanning = false; }

    // ==========================================================================
    // SCAN KOLEKTIF  antrian lokal, kirim sekaligus ke server
    // ==========================================================================

    function addToScanQueue(siswa) {
        const tbody   = document.getElementById('tbody-scan-live');
        const wrapper = document.getElementById('scanTableWrapper');
        if (!tbody) return;

        const emptyRow = document.getElementById('scan-empty-row');
        if (emptyRow) emptyRow.remove();

        const now = new Date();
        const jamScan = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        scanLiveCount++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-50 transition-colors duration-700 scan-row-highlight';
        tr.setAttribute('data-nisn', siswa.nisn);
        tr.innerHTML = `
            <td class="px-3 py-3 text-center text-xs text-gray-400 font-mono">${scanLiveCount}</td>
            <td class="px-4 py-3">
                <div class="font-bold text-sm text-gray-900">${siswa.nama}</div>
                <div class="text-[10px] text-gray-400 font-mono">${siswa.nisn}</div>
            </td>
            <td class="px-3 py-3 text-center">
                <span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded text-[10px] font-bold border border-indigo-100">${siswa.kelas}</span>
            </td>
            <td class="px-3 py-3 text-center text-xs font-mono text-gray-600">${jamScan}</td>
            <td class="px-3 py-3 text-center">
                <button onclick="removeScanRow(this, '${siswa.nisn}')" class="w-6 h-6 flex items-center justify-center rounded-full text-gray-300 hover:bg-red-100 hover:text-red-500 transition mx-auto" title="Hapus dari antrian">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        scanLiveMap[siswa.nisn] = tr;

        const badge = document.getElementById('scanCountBadge');
        if (badge) badge.textContent = scanLiveCount + ' Siswa';
        updateBatchBtn();
        highlightScanRow(tr, wrapper);
    }

    function removeScanRow(btn, nisn) {
        const tr = btn.closest('tr');
        if (!tr) return;
        tr.remove();
        delete scanLiveMap[nisn];

        const rows = document.querySelectorAll('#tbody-scan-live tr[data-nisn]');
        rows.forEach((r, i) => { r.cells[0].textContent = i + 1; });
        scanLiveCount = rows.length;

        const badge = document.getElementById('scanCountBadge');
        if (badge) badge.textContent = scanLiveCount + ' Siswa';

        if (scanLiveCount === 0) {
            const tbody = document.getElementById('tbody-scan-live');
            tbody.innerHTML = `<tr id="scan-empty-row"><td colspan="5" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                    <i class="fas fa-qrcode text-5xl text-gray-200"></i>
                    <p class="font-semibold text-sm text-gray-400">Belum ada siswa dalam antrian</p>
                    <p class="text-xs text-gray-300">Scan QR untuk menambahkan siswa</p>
                </div></td></tr>`;
        }
        updateBatchBtn();
    }

    function updateBatchBtn() {
        const btn   = document.getElementById('btnSubmitBatch');
        const label = document.getElementById('btnSubmitBatchLabel');
        if (!btn) return;
        if (scanLiveCount > 0) {
            btn.disabled = false;
            btn.style.pointerEvents = '';
            btn.className = 'w-full py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white shadow-md shadow-emerald-200';
            label.textContent = `Kirim Absensi (${scanLiveCount} Siswa)`;
        } else {
            btn.disabled = true;
            btn.style.pointerEvents = 'none';
            btn.className = 'w-full py-4 rounded-xl font-bold text-sm transition flex items-center justify-center gap-2 bg-gray-200 text-gray-400 cursor-not-allowed';
            label.textContent = 'Kirim Absensi (0 Siswa)';
        }
    }

    function submitBatchAbsensi() {
        if (scanLiveCount === 0) return;

        const rows = document.querySelectorAll('#tbody-scan-live tr[data-nisn]');
        const nisnList = Array.from(rows).map(r => r.getAttribute('data-nisn'));

        const myRole  = currentUser ? role  : '';
        const myKelas = currentUser ? currentUser.kelas : '';

        Swal.fire({
            title: `Kirim ${nisnList.length} Absensi?`,
            html: `<p style="color:#6b7280;font-size:13px">Data akan dikirim sekaligus ke server dan dicatat secara permanen.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '\u2713 Ya, Kirim Sekarang',
            cancelButtonText: 'Batal'
        }).then(res => {
            if (!res.isConfirmed) return;
            showLoading();
            actionRunner
                .withSuccessHandler(result => {
                    hideLoading();
                    if (result.success) {
                        showBatchResultModal(result.results);
                        resetScanTable();
                    } else {
                        Swal.fire('Gagal', result.message, 'error');
                    }
                })
                .withFailureHandler(err => {
                    hideLoading();
                    Swal.fire('Error Server', err.message || String(err), 'error');
                })
                .batchScanAbsensi(nisnList, myRole, myKelas);
        });
    }

    function showBatchResultModal(results) {
        const berhasil = results.filter(r => r.success);
        const gagal    = results.filter(r => !r.success);

        const berhasilHtml = berhasil.map(r =>
            `<div class="flex items-center gap-2 py-1 border-b border-gray-50">
                <span class="text-emerald-500 text-xs">\u2713</span>
                <div class="flex-1 text-left"><span class="font-semibold text-xs text-gray-800">${r.nama}</span> <span class="text-[10px] text-gray-400">(${r.kelas})</span></div>
                <span class="text-[10px] text-gray-400 font-mono">${r.jamDatang || r.jamPulang || ''}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded ${r.type==='datang' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'}">${r.type==='datang' ? 'Masuk' : 'Pulang'}</span>
            </div>`
        ).join('');

        const gagalHtml = gagal.length > 0 ? `
            <div class="mt-3 bg-red-50 rounded-xl p-3 border border-red-100">
                <p class="text-xs font-bold text-red-600 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> ${gagal.length} Gagal</p>
                ${gagal.map(r => `<div class="text-xs text-red-500 py-0.5">\u2022 ${r.nama || r.nisn}: ${r.message}</div>`).join('')}
            </div>` : '';

        Swal.fire({
            title: `<span style="font-size:18px">\u2705 Absensi Tersimpan</span>`,
            html: `
                <div style="text-align:center;margin-bottom:12px">
                    <span style="font-size:13px;color:#6b7280">${berhasil.length} dari ${results.length} data berhasil disimpan</span>
                </div>
                <div style="max-height:300px;overflow-y:auto;text-align:left;padding:0 4px">
                    ${berhasilHtml}
                </div>
                ${gagalHtml}`,
            icon: berhasil.length === results.length ? 'success' : 'warning',
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#111827',
        });
    }

    function highlightScanRow(tr, wrapper) {
        document.querySelectorAll('.scan-row-highlight').forEach(el => el.classList.remove('scan-row-highlight'));
        tr.classList.add('scan-row-highlight');
        const wrapperEl = wrapper || document.getElementById('scanTableWrapper');
        if (wrapperEl && tr) {
            wrapperEl.scrollTo({ top: tr.offsetTop - (wrapperEl.clientHeight / 2) + (tr.offsetHeight / 2), behavior: 'smooth' });
        }
        setTimeout(() => tr.classList.remove('scan-row-highlight'), 3000);
    }

    function resetScanTable() {
        const tbody = document.getElementById('tbody-scan-live');
        if (!tbody) return;
        tbody.innerHTML = `<tr id="scan-empty-row"><td colspan="5" class="py-16 text-center">
            <div class="flex flex-col items-center gap-3">
                <i class="fas fa-qrcode text-5xl text-gray-200"></i>
                <p class="font-semibold text-sm text-gray-400">Belum ada karyawan dalam antrian</p>
                <p class="text-xs text-gray-300">Scan QR untuk menambahkan karyawan</p>
            </div></td></tr>`;
        scanLiveCount = 0;
        scanLiveMap   = {};
        const badge = document.getElementById('scanCountBadge');
        if (badge) badge.textContent = '0 karyawan';
        updateBatchBtn();
    }

    function updateScanLiveTable() {}

    function stopAndBack(redirect = true) {
        stopPolling();
        if (cameraPopup && !cameraPopup.closed) { cameraPopup.close(); cameraPopup = null; }
        if (html5QrCode) { try { html5QrCode.stop().catch(() => {}); } catch(e) {} html5QrCode = null; }
        isScanning = false;
        if (redirect && currentUser) returnToDashboard();
    }
    function returnToDashboard() {
        if(role === 'admin' || role === 'wakasek' || role === 'kepsek') loadAdminDashboard();
        else if(role === 'wakel') loadGuruDashboard();
        else loadSiswaDashboard();
    }

    // --- MODALS ---
    function showLoading() { document.getElementById('loadingOverlay').classList.remove('hidden'); }
    function hideLoading() { document.getElementById('loadingOverlay').classList.add('hidden'); }

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

        if (typeof content === 'string') {
            host.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            host.innerHTML = '';
            host.appendChild(content);
        } else {
            return;
        }

        shell.classList.remove('hidden');
        shell.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        stopSiswaCardScan();
        const shell = getModalShell(false);
        if (!shell) return;
        const host = shell.querySelector('[data-modal-host]');
        if (host) host.innerHTML = '';
        shell.classList.add('hidden');
        shell.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    // --- MODAL GENERATORS (SISWA & GURU) ---
    let siswaModalNode = null;
    let guruModalNode = null;

    function buildModalNode(html) {
        const template = document.createElement('template');
        template.innerHTML = String(html || '').trim();
        return template.content.firstElementChild;
    }

    function getSiswaModalNode() {
        if (!siswaModalNode) {
            siswaModalNode = buildModalNode(createSiswaModal());
        }
        return siswaModalNode;
    }

    function getGuruModalNode() {
        if (!guruModalNode) {
            guruModalNode = buildModalNode(createGuruModal());
        }
        return guruModalNode;
    }

    function setFormFieldValue(form, name, value) {
        if (!form || !name) return;
        const field = form.querySelector(`[name="${name}"]`);
        if (!field) return;
        field.value = value ?? '';
    }

    function ensureHiddenField(form, name, value) {
        if (!form || !name) return;
        let field = form.querySelector(`input[type="hidden"][name="${name}"]`);
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            form.appendChild(field);
        }
        field.value = value ?? '';
    }

    function removeHiddenField(form, name) {
        if (!form || !name) return;
        const field = form.querySelector(`input[type="hidden"][name="${name}"]`);
        if (field) field.remove();
    }

    function getSiswaModalForm() {
        const modalNode = getSiswaModalNode();
        return modalNode?.querySelector('form') || null;
    }

    function getSiswaCardInput(form = null) {
        const targetForm = form || getSiswaModalForm();
        return targetForm?.querySelector('[name="nomorKartu"]') || null;
    }

    function getSiswaCardStatus(form = null) {
        const targetForm = form || getSiswaModalForm();
        return targetForm?.querySelector('[data-siswa-card-status]') || null;
    }

    function setSiswaCardStatus(message, tone = 'muted') {
        const statusNode = getSiswaCardStatus();
        const normalizedMessage = String(message || '').trim();

        if (!statusNode) {
            if (normalizedMessage !== '' && tone === 'error' && typeof showAlert === 'function') {
                showAlert('error', normalizedMessage);
            }
            return;
        }

        if (normalizedMessage === '') {
            statusNode.className = 'hidden';
            statusNode.textContent = '';
            return;
        }

        const toneClasses = {
            muted: 'text-gray-500',
            loading: 'text-indigo-600',
            success: 'text-emerald-600',
            error: 'text-rose-600',
        };

        statusNode.className = `mt-2 text-[11px] font-semibold ${toneClasses[tone] || toneClasses.muted}`;
        statusNode.textContent = normalizedMessage;
    }

    function resetSiswaCardScanRuntime() {
        if (siswaCardScanTimeoutId !== null) {
            window.clearTimeout(siswaCardScanTimeoutId);
            siswaCardScanTimeoutId = null;
        }

        if (!siswaCardScanEventSource) {
            return;
        }

        siswaCardScanEventSource.close();
        siswaCardScanEventSource = null;
    }

    function showSiswaCardScanLoadingModal() {
        if (typeof Swal === 'undefined') {
            return;
        }

        siswaCardScanLoadingVisible = true;

        Swal.fire({
            title: 'Menunggu Scan Kartu',
            html: 'Tempelkan kartu ke mesin absensi untuk membaca nomor kartu.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            showCloseButton: true,
            closeButtonHtml: '&times;',
            customClass: {
                popup: 'swal-scan-card-popup',
                closeButton: 'swal-scan-card-close',
            },
            didOpen: () => {
                Swal.showLoading();
            },
            didClose: () => {
                if (!siswaCardScanLoadingVisible) {
                    return;
                }

                siswaCardScanLoadingVisible = false;

                if (!siswaCardScanEventSource && siswaCardScanTimeoutId === null) {
                    return;
                }

                resetSiswaCardScanRuntime();
                setSiswaCardStatus('Pemindaian dibatalkan.', 'muted');
            },
        });
    }

    function hideSiswaCardScanLoadingModal() {
        if (!siswaCardScanLoadingVisible) {
            return;
        }

        siswaCardScanLoadingVisible = false;

        if (typeof Swal === 'undefined' || !Swal.isVisible()) {
            return;
        }

        const currentTitle = Swal.getTitle()?.textContent || '';
        if (currentTitle === 'Menunggu Scan Kartu') {
            Swal.close();
        }
    }

    function showSiswaCardScanSuccessModal(code) {
        const normalizedCode = String(code || '').trim().toUpperCase();
        if (normalizedCode === '') {
            return;
        }

        if (typeof Swal === 'undefined') {
            showAlert('success', `Nomor kartu ${normalizedCode} masuk otomatis.`);
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Kartu Berhasil Dibaca',
            text: `Nomor kartu ${normalizedCode} masuk otomatis.`,
            showCloseButton: true,
            closeButtonHtml: '&times;',
            showConfirmButton: false,
            customClass: {
                popup: 'swal-scan-card-popup',
                closeButton: 'swal-scan-card-close',
            },
            timer: 1400,
            timerProgressBar: true,
        });
    }

    function stopSiswaCardScan() {
        hideSiswaCardScanLoadingModal();
        resetSiswaCardScanRuntime();
    }

    function startSiswaCardScan() {
        const form = getSiswaModalForm();
        const cardInput = getSiswaCardInput(form);

        if (!form || !cardInput) {
            return;
        }

        if (typeof EventSource === 'undefined') {
            setSiswaCardStatus('Browser ini tidak mendukung scan realtime. Isi nomor kartu manual.', 'error');
            return;
        }

        if (siswaCardScanEventSource) {
            return;
        }

        setSiswaCardStatus('Menunggu scan kartu dari device...', 'loading');
        showSiswaCardScanLoadingModal();

        const eventSource = new EventSource(window.APP_ROUTES?.dataSiswaCardCapture || '/data-siswa/card-capture-stream');
        siswaCardScanEventSource = eventSource;

        siswaCardScanTimeoutId = window.setTimeout(() => {
            if (siswaCardScanEventSource !== eventSource) {
                return;
            }

            stopSiswaCardScan();
            setSiswaCardStatus('Waktu tunggu scan habis. Klik Scan lagi atau isi manual.', 'error');
        }, SISWA_CARD_SCAN_TIMEOUT_MS);

        eventSource.addEventListener('captured', function (event) {
            if (siswaCardScanEventSource !== eventSource) {
                return;
            }

            let capturedCode = '';

            try {
                const payload = JSON.parse(String(event?.data || '{}'));
                const code = String(payload?.code || '').trim().toUpperCase();

                if (code === '') {
                    throw new Error('Kode kartu tidak valid.');
                }

                capturedCode = code;
                cardInput.value = code;
                setSiswaCardStatus(`Kartu terbaca: ${code}`, 'success');
            } catch (error) {
                setSiswaCardStatus(error?.message || 'Data scan tidak valid.', 'error');
            } finally {
                stopSiswaCardScan();
                if (capturedCode !== '') {
                    showSiswaCardScanSuccessModal(capturedCode);
                }
            }
        });

        eventSource.addEventListener('timeout', function () {
            if (siswaCardScanEventSource !== eventSource) {
                return;
            }

            stopSiswaCardScan();
            setSiswaCardStatus('Tidak ada scan baru terdeteksi. Klik Scan lagi.', 'error');
        });

        eventSource.onerror = function () {
            if (siswaCardScanEventSource !== eventSource) {
                return;
            }

            stopSiswaCardScan();
            setSiswaCardStatus('Koneksi scan terputus. Coba klik Scan lagi.', 'error');
        };
    }

    function openSiswaModal(s = null) {
        stopSiswaCardScan();
        const isEdit = !!s;
        const modalNode = getSiswaModalNode();
        const title = modalNode?.querySelector('h3');
        if (title) title.textContent = isEdit ? 'Edit Data Karyawan' : 'Registrasi Karyawan Baru';

        const form = modalNode?.querySelector('form');
        if (!form) return;
        form.setAttribute('onsubmit', `saveSiswa(event, ${isEdit})`);

        setFormFieldValue(form, 'nama', isEdit ? s.nama : '');
        setFormFieldValue(form, 'nisn', isEdit ? s.nisn : '');
        setFormFieldValue(form, 'kelas', isEdit ? s.kelas : '');
        setFormFieldValue(form, 'jenisKelamin', isEdit ? (s.jenisKelamin || s.jenis_kelamin || 'Laki-laki') : 'Laki-laki');
        setFormFieldValue(form, 'tanggalLahir', isEdit ? (s.tanggalLahir || s.tanggal_lahir || '') : '');
        setFormFieldValue(form, 'agama', isEdit ? (s.agama || '') : 'Islam');
        setFormFieldValue(form, 'namaAyah', isEdit ? s.namaAyah : '');
        setFormFieldValue(form, 'namaIbu', isEdit ? s.namaIbu : '');
        setFormFieldValue(form, 'noHp', isEdit ? (s.noHp || s.no_hp || '') : '');
        setFormFieldValue(form, 'alamat', isEdit ? s.alamat : '');
        setFormFieldValue(form, 'nomorKartu', isEdit ? (s.nomorKartu || s.nomor_kartu || '') : '');
        setSiswaCardStatus('', 'muted');

        const nisnInput = form.querySelector('[name="nisn"]');
        if (nisnInput) {
            nisnInput.readOnly = false;
            nisnInput.classList.toggle('opacity-60', false);
            nisnInput.classList.toggle('cursor-not-allowed', false);
        }

        if (isEdit) {
            ensureHiddenField(form, 'oldNisn', s?.nisn ?? '');
        } else {
            removeHiddenField(form, 'oldNisn');
        }

        showModal(modalNode);
    }

    function openGuruModal(guruData = null) {
        const guru = guruData || null;
        const isEdit = !!guru;
        const modalNode = getGuruModalNode();
        const title = modalNode?.querySelector('h3');
        if (title) title.textContent = isEdit ? 'Edit Akun Guru' : 'Tambah Akun Guru';

        const form = modalNode?.querySelector('form');
        if (!form) return;
        form.setAttribute('onsubmit', `saveGuru(event, ${isEdit})`);

        setFormFieldValue(form, 'username', isEdit ? guru.username : '');
        setFormFieldValue(form, 'nama', isEdit ? (guru.name || guru.nama || '') : '');
        setFormFieldValue(form, 'email', isEdit ? guru.email : '');
        setFormFieldValue(form, 'kelas', isEdit ? guru.kelas : '');
        setFormFieldValue(form, 'noHp', isEdit ? (guru.noHp || guru.no_hp || '') : '');
        setFormFieldValue(form, 'jenisKelamin', isEdit ? (guru.jenisKelamin || guru.jenis_kelamin || '') : '');
        setFormFieldValue(form, 'tanggalLahir', isEdit ? (guru.tanggalLahir || guru.tanggal_lahir || '') : '');
        setFormFieldValue(form, 'agama', isEdit ? guru.agama : '');
        setFormFieldValue(form, 'alamat', isEdit ? guru.alamat : '');

        const passwordInput = form.querySelector('[name="password"]');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.required = !isEdit;
            passwordInput.placeholder = isEdit ? 'Kosongkan jika tidak diubah' : 'Password';
        }

        if (isEdit) {
            ensureHiddenField(form, 'oldUsername', guru?.username || '');
        } else {
            removeHiddenField(form, 'oldUsername');
        }

        showModal(modalNode);
    }

    function showAddSiswaModal() {
        openSiswaModal(null);
    }

    function editSiswa(s) {
        openSiswaModal(s || null);
    }

    function showAddGuruModal() {
        openGuruModal(null);
    }

    function editGuru(guruData) {
        openGuruModal(guruData || null);
    }

    function createSiswaModal(s = null) {
        const isEdit = s !== null;
        const inputClass = "w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 transition-all";
        const labelClass = "block mb-1 text-xs font-bold text-gray-500 uppercase tracking-wide";
        
        return `
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-xl font-bold text-gray-800">${isEdit ? 'Edit Data Siswa' : 'Registrasi Siswa Baru'}</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
            </div>
            <div class="p-6 max-h-[75vh] overflow-y-auto">
                <form onsubmit="saveSiswa(event, ${isEdit})" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="${labelClass}">Nama Lengkap</label>
                            <input type="text" name="nama" value="${s?.nama || ''}" required class="${inputClass}" placeholder="Nama Karyawan">
                        </div>
                        <div>
                            <label class="${labelClass}">Nomor Kartu</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    name="nomorKartu"
                                    value="${s?.nomorKartu || ''}"
                                    class="${inputClass} font-mono uppercase"
                                    placeholder="Contoh: 04AABBCC"
                                >
                                <button
                                    type="button"
                                    onclick="startSiswaCardScan()"
                                    class="inline-flex items-center justify-center gap-2 h-[42px] px-3 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100 font-bold text-xs hover:bg-indigo-100 transition shrink-0"
                                >
                                    <i class="fas fa-id-card"></i> Scan
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="${labelClass}">NIK</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="nisn" value="${s?.nisn ?? ''}" required class="${inputClass}" placeholder="NIK Karyawan">
                        </div>
                        
                        <div class="relative group">
                            <label class="${labelClass}">Gedung - Lantai</label>
                            <input type="text" name="kelas" id="inputKelas" 
                                value="${role === 'wakel' ? (currentUser?.kelas || s?.kelas || '') : (s?.kelas || '')}" 
                                required 
                                class="${inputClass}${role === 'wakel' ? ' opacity-60 cursor-not-allowed' : ''}" 
                                placeholder="Ketik atau pilih gedung" 
                                ${role === 'wakel' ? 'readonly' : ''}
                                ${role === 'wakel' ? '' : 'autocomplete="off"'}
                                ${role === 'wakel' ? '' : 'onfocus="openKelasDropdown()"'}
                                ${role === 'wakel' ? '' : 'oninput="filterKelasDropdown(this.value)"'}
                                ${role === 'wakel' ? '' : 'onblur="closeKelasDropdown()"'} >
                            <div id="dropdownKelasList" class="hidden absolute z-20 w-full bg-white border border-gray-200 rounded-lg shadow-xl max-h-40 overflow-y-auto mt-1 scrollbar-hide"></div>
                            ${role === 'wakel' ? '<p class="text-[10px] text-gray-400 mt-1">Gedung dikunci sesuai akun Admin.</p>' : ''}
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="${labelClass}">Jenis Kelamin</label>
                            <select name="jenisKelamin" class="${inputClass}">
                                <option value="Laki-laki" ${s?.jenisKelamin === 'Laki-laki' ? 'selected' : ''}>Laki-laki</option>
                                <option value="Perempuan" ${s?.jenisKelamin === 'Perempuan' ? 'selected' : ''}>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label class="${labelClass}">Tanggal Lahir</label>
                            <input type="date" name="tanggalLahir" value="${s?.tanggalLahir || ''}" required class="${inputClass}">
                        </div>
                        <div>
                            <label class="${labelClass}">Agama</label>
                            <select name="agama" class="${inputClass}">
                                <option value="Islam" ${s?.agama === 'Islam' ? 'selected' : ''}>Islam</option>
                                <option value="Kristen" ${s?.agama === 'Kristen' ? 'selected' : ''}>Kristen</option>
                                <option value="Katolik" ${s?.agama === 'Katolik' ? 'selected' : ''}>Katolik</option>
                                <option value="Hindu" ${s?.agama === 'Hindu' ? 'selected' : ''}>Hindu</option>
                                <option value="Buddha" ${s?.agama === 'Buddha' ? 'selected' : ''}>Buddha</option>
                                <option value="Lainnya" ${s?.agama === 'Lainnya' ? 'selected' : ''}>Lainnya</option>
                            </select>
                        </div>

                         <div>
                            <label class="${labelClass}">No. Handphone</label>
                            <input type="tel" name="noHp" value="${s?.noHp || ''}" class="${inputClass}">
                        </div>
                    </div>

                    

                    <div>
                        <label class="${labelClass}">Alamat Lengkap</label>
                        <textarea name="alamat" rows="2" class="${inputClass}">${s?.alamat || ''}</textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModal()" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl border border-gray-200 bg-white text-gray-700 font-semibold text-sm hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"><i class="fas fa-times text-xs"></i>Batal</button>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-sm shadow-md hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition transform active:scale-[0.98]"><i class="fas fa-save text-xs"></i>Simpan Data</button>
                    </div>
                    ${isEdit ? `<input type="hidden" name="oldNisn" value="${s?.nisn ?? ''}">` : ''}
                </form>
            </div>
        </div>`;
    }

// Fungsi Trigger untuk menampilkan modal
    function viewSiswa(siswa) {
        showModal(createViewSiswaModal(siswa));
    }

    // Fungsi Generator HTML Modal Detail
    function createViewSiswaModal(s) {
        // Helper untuk styling label dan value agar rapi
        const item = (label, value, icon) => `
            <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas ${icon} text-gray-400 text-xs"></i>
                    <span class="text-[10px] uppercase font-bold text-gray-500 tracking-wider">${label}</span>
                </div>
                <div class="text-sm font-bold text-gray-800 break-words">${patchedEscapeHtml(value || '-')}</div>
            </div>
        `;

        return `
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-2xl w-full animate-fade-in relative">
            
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white flex justify-between items-start">
                <div class="flex gap-4 items-center">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-2xl font-bold border-2 border-white/30 shadow-inner">
                        ${patchedEscapeHtml((s.nama || '').charAt(0))}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold tracking-tight">${patchedEscapeHtml(s.nama || '-')}</h3>
                        <p class="opacity-90 text-sm flex items-center gap-2">
                            <i class="far fa-id-card"></i> ${patchedEscapeHtml(s.nisn || '-')}
                            <span class="bg-white/20 px-2 py-0.5 rounded text-xs font-bold ml-2">${patchedEscapeHtml(s.kelas || '-')}</span>
                        </p>
                    </div>
                </div>
                <button onclick="closeModal()" class="bg-white/10 hover:bg-white/20 p-2 rounded-lg transition text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 max-h-[70vh] overflow-y-auto">
                
                <div class="mb-6">
                    <h4 class="text-sm font-bold text-emerald-700 mb-3 flex items-center gap-2">
                        <i class="fas fa-user-circle"></i> Data Pribadi
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        ${item('Jenis Kelamin', s.jenisKelamin, 'fa-venus-mars')}
                        ${item('Tanggal Lahir', s.tanggalLahir, 'fa-birthday-cake')}
                        ${item('Agama', s.agama, 'fa-pray')}
                        ${item('No. Handphone', s.noHp, 'fa-phone')}
                    </div>
                </div>

                

                <div>
                    <h4 class="text-sm font-bold text-emerald-700 mb-3 flex items-center gap-2">
                        <i class="fas fa-map-marker-alt"></i> Alamat Lengkap
                    </h4>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 flex gap-3 items-start">
                        <i class="fas fa-home text-gray-400 mt-1"></i>
                        <p class="text-sm text-gray-700 leading-relaxed font-medium">
                            ${patchedEscapeHtml(s.alamat || 'Alamat belum diisi.')}
                        </p>
                    </div>
                </div>

            </div>

            <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                <button type="button" data-siswa-modal-edit="${patchedEscapeHtml(s.nisn || '')}" onclick="editSiswa(findSiswaByNisn(this.dataset.siswaModalEdit)); closeModal();" class="px-5 py-2.5 bg-amber-100 text-amber-700 rounded-xl font-bold text-sm hover:bg-amber-200 transition flex items-center gap-2">
                    <i class="fas fa-edit"></i> Edit Data
                </button>
                <button onclick="closeModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl font-bold text-sm hover:bg-gray-300 transition">
                    Tutup
                </button>
            </div>
        </div>`;
    }

// UPDATE: Modal Guru kini memiliki input Kelas
    function createGuruModal(guru = null) {
        const isEdit = guru !== null;
        const inputClass = "w-full bg-gray-50 border border-gray-200 text-gray-900 text-sm rounded-lg focus:ring-purple-500 focus:border-purple-500 block p-3 transition-all mb-4";
        
        // Generate opsi kelas dari existingClasses (Global Variable)
        let kelasOptions = '<option value="">-- Pilih Kelas (Opsional) --</option>';
        if (existingClasses && existingClasses.length > 0) {
            existingClasses.forEach(k => {
                const selected = (guru && guru.kelas === k) ? 'selected' : '';
                kelasOptions += `<option value="${k}" ${selected}>${k}</option>`;
            });
        }

        return `
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full relative overflow-hidden">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-3 text-2xl shadow-sm"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3 class="font-bold text-xl text-gray-800">${isEdit ? 'Edit Akun Guru' : 'Tambah Guru'}</h3>
            </div>
            
            <form onsubmit="saveGuru(event, ${isEdit})">
                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Username</label>
                <input name="username" value="${guru?.username || ''}" placeholder="Username" required class="${inputClass}">
                
                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Password</label>
                <input name="password" value="${guru?.password || ''}" placeholder="Password" required class="${inputClass}">
                
                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Wali Kelas Untuk</label>
                <select name="kelas" class="${inputClass}">
                    ${kelasOptions}
                </select>
                <p class="text-[10px] text-gray-400 -mt-3 mb-4">Jika dipilih, guru hanya bisa melihat siswa di kelas ini.</p>
                
                ${isEdit ? `<input type="hidden" name="oldUsername" value="${guru.username}">` : ''}

                <div class="flex gap-3 mt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-100 text-gray-600 py-3 rounded-xl font-bold hover:bg-gray-200 transition">Batal</button>
                    <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-bold shadow-lg transition transform active:scale-95">Simpan</button>
                </div>
            </form>
        </div>`;
    }

    // --- FORM ACTIONS (SIMPAN & UPDATE) ---
    // FUNCTION UPDATE: Simpan Guru dengan Loading Button
function saveGuru(e, isEdit) {
    e.preventDefault();
    
    // 1. Ambil elemen tombol untuk efek loading
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML; 

    // 2. Ubah tampilan tombol menjadi Loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Menyimpan...';
    btn.classList.add('opacity-75', 'cursor-not-allowed');

    // 3. Ambil Data Form
    const fd = new FormData(form);
    // Tambahkan tanda kutip satu (') agar dianggap text di Excel/Sheet (mencegah format angka)
    const username = "'" + fd.get('username'); 
    const password = "'" + fd.get('password');
    const kelas = fd.get('kelas');

    // --- KEAMANAN: AMBIL TOKEN USER ---
    const token = currentUser ? currentUser.token : null;
    // ----------------------------------

    // 4. Callback saat selesai
    const onComplete = (r) => {
        // Kembalikan tombol ke kondisi semula
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');

        if (r && r.success) {
            closeModal();
            // Reset cache & reload tabel
            tableState.guru.fullData = [];
            loadDataGuru();
            showAlert('success', isEdit ? 'Data mandor berhasil diperbarui' : 'Akun mandor berhasil dibuat');
        } else {
            showAlert('error', r ? r.message : 'Terjadi kesalahan tidak diketahui');
        }
    };

    // Callback jika koneksi gagal
    const onFailure = (error) => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
        showAlert('error', 'Gagal koneksi server: ' + error);
    };

    // 5. Eksekusi ke Server (Kirim Token sebagai parameter pertama)
    if (isEdit) {
        const oldUsername = fd.get('oldUsername');
        actionRunner
            .withSuccessHandler(onComplete)
            .withFailureHandler(onFailure)
            .updateGuru(token, oldUsername, username, password, kelas); // <-- Token dikirim
    } else {
        actionRunner
            .withSuccessHandler(onComplete)
            .withFailureHandler(onFailure)
            .addGuru(token, username, password, kelas); // <-- Token dikirim
    }
}
    
function deleteSiswaConfirm(nisn, nama) {
        // Panggil SweetAlert untuk konfirmasi
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Data karyawan "${nama}" akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444', // Warna Merah
            cancelButtonColor: '#6B7280',  // Warna Abu
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            // Jika tombol "Ya, Hapus!" diklik
            if (result.isConfirmed) {
                showLoading(); // Tampilkan loading overlay

                // Panggil Backend
                actionRunner.withSuccessHandler(r => {
                    hideLoading();
                    
                    if (r.success) {
                        // Refresh Tabel Siswa
                        tableState.siswa.fullData = []; // Clear cache
                        loadDataSiswa(); // Reload data
                        
                        // Pesan Sukses
                        Swal.fire(
                            'Terhapus!',
                            'Data karyawan berhasil dihapus.',
                            'success'
                        );
                    } else {
                        // Pesan Gagal (Misal: Token expired atau bukan admin)
                        Swal.fire(
                            'Gagal!',
                            r.message,
                            'error'
                        );
                    }
                })
                .withFailureHandler(err => {
                    hideLoading();
                    Swal.fire('Error', 'Terjadi kesalahan server: ' + err, 'error');
                })
                .deleteSiswa(nisn);
            }
        });
    }
// ==========================================================================
    // FITUR IMPORT EXCEL SISWA
    // ==========================================================================
    
    function triggerImportSiswa() {
        document.getElementById('fileInputSiswa').click();
    }

function handleFileImportSiswa(input) {
        const file = input.files[0];
        if (!file) return;

        showLoading(); // Tampilkan loading overlay

        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // Ambil sheet pertama
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            // Konversi ke JSON
            // defval: "" memastikan sel kosong tetap terbaca sebagai string kosong
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });

            if (jsonData.length === 0) {
                hideLoading();
                showAlert('error', 'File Excel kosong atau format salah.');
                input.value = ''; // Reset input
                return;
            }

            // === VALIDASI HEADER (DIPERBAIKI) ===
            const firstRow = jsonData[0];

            // Cek apakah ada kolom 'Nama' ATAU 'Nama Lengkap'
            const hasNama = firstRow.hasOwnProperty('Nama') || firstRow.hasOwnProperty('Nama Lengkap');
            // Cek apakah ada kolom 'NISN'
            const hasNISN = firstRow.hasOwnProperty('NISN');

            if (!hasNama || !hasNISN) {
                hideLoading();
                showAlert('error', 'Format Excel salah! Pastikan ada kolom header: Nama (atau Nama Lengkap) dan NISN.');
                input.value = '';
                return;
            }
            // ====================================

            // Kirim ke Backend
            processImportSiswa(jsonData, input);
        };

        reader.onerror = function() {
            hideLoading();
            showAlert('error', 'Gagal membaca file.');
            input.value = '';
        };

        reader.readAsArrayBuffer(file);
    }

function processImportSiswa(data, inputElement) {
        // Mapping data Excel ke Format Database
        // Menggunakan operator || (OR) agar bisa membaca variasi nama kolom
        
        const formattedData = data.map(row => {
            // Helper untuk membersihkan data string
            const clean = (val) => (val ? String(val).trim() : '');

            return {
                // Prioritas 1: Nama Lengkap, Prioritas 2: Nama
                nama: clean(row['Nama Lengkap'] || row['Nama']),
                
                // NISN (Hapus kutip jika ada)
                nisn: clean(row['NISN']).replace(/'/g, ""), 
                
                // Prioritas 1: Jenis Kelamin, Prioritas 2: JK
                jenisKelamin: clean(row['Jenis Kelamin'] || row['JK']), 
                
                // Prioritas 1: Tanggal Lahir, Prioritas 2: Tgl Lahir
                // Pastikan format di Excel Text (YYYY-MM-DD) atau Date
                tanggalLahir: clean(row['Tanggal Lahir'] || row['Tgl Lahir']), 
                
                agama: clean(row['Agama']),
                
                namaAyah: clean(row['Nama Ayah']),
                
                namaIbu: clean(row['Nama Ibu']),
                
                // Prioritas 1: No Handphone, Prioritas 2: HP, Prioritas 3: No HP
                noHp: clean(row['No Handphone'] || row['HP'] || row['No HP']),
                
                kelas: clean(row['Kelas']),
                
                alamat: clean(row['Alamat'])
            };
        });

        // Debugging: Cek data pertama di console browser jika ingin memastikan
        console.log("Data Siap Import:", formattedData[0]);

        actionRunner
            .withSuccessHandler(res => {
                hideLoading();
                inputElement.value = ''; 
                
                if (res.success) {
                    tableState.siswa.fullData = [];
                    loadDataSiswa(); 
                    let msg = `Berhasil: ${res.added}, Gagal/Duplikat: ${res.skipped}`;
                    showAlert('success', 'Import Selesai! ' + msg);
                } else {
                    showAlert('error', res.message);
                }
            })
            .withFailureHandler(err => {
                hideLoading();
                inputElement.value = '';
                showAlert('error', 'Error Server: ' + err);
            })
            .importSiswaBulk(formattedData);
    }


// ==========================================================================
    // FITUR IMPORT EXCEL GURU
    // ==========================================================================
    
    function triggerImportGuru() {
        document.getElementById('fileInputGuru').click();
    }

    function handleFileImportGuru(input) {
        const file = input.files[0];
        if (!file) return;

        showLoading(); 

        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });

            if (jsonData.length === 0) {
                hideLoading();
                showAlert('error', 'File Excel kosong atau format salah.');
                input.value = ''; 
                return;
            }

            // Validasi Header
            const firstRow = jsonData[0];
            // Kita cek header 'Username' dan 'Password'
            const hasUser = firstRow.hasOwnProperty('Username');
            const hasPass = firstRow.hasOwnProperty('Password');

            if (!hasUser || !hasPass) {
                hideLoading();
                showAlert('error', 'Format Excel salah! Pastikan ada kolom header: Username dan Password.');
                input.value = '';
                return;
            }

            processImportGuru(jsonData, input);
        };

        reader.onerror = function() {
            hideLoading();
            showAlert('error', 'Gagal membaca file.');
            input.value = '';
        };

        reader.readAsArrayBuffer(file);
    }

    function processImportGuru(data, inputElement) {
        // Mapping data Excel ke Format Database
        const formattedData = data.map(row => {
            const clean = (val) => (val ? String(val).trim() : '');
            return {
                username: clean(row['Username']),
                password: clean(row['Password']),
                // Opsional: Jika guru tersebut adalah Wali Kelas
                kelas: clean(row['Kelas'] || row['Wali Kelas']) 
            };
        });

        actionRunner
            .withSuccessHandler(res => {
                hideLoading();
                inputElement.value = ''; 
                
                if (res.success) {
                    tableState.guru.fullData = []; // Clear cache
                    loadDataGuru(); // Reload tabel
                    let msg = `Berhasil: ${res.added}, Gagal/Duplikat: ${res.skipped}`;
                    showAlert('success', 'Import Guru Selesai! ' + msg);
                } else {
                    showAlert('error', res.message);
                }
            })
            .withFailureHandler(err => {
                hideLoading();
                inputElement.value = '';
                showAlert('error', 'Error Server: ' + err);
            })
            .importGuruBulk(formattedData);
    }

// ==========================================
// LOGIKA REKAP BULANAN
// ==========================================

function loadMenuRekapBulanan() {
    window.location.href = window.APP_ROUTES?.rekapBulanan || '/rekap-bulanan';
}

function loadDataRekapBulanan() {
    const bulan = document.getElementById('rekapBulan').value;
    const tahun = document.getElementById('rekapTahun').value;
    const kelas = document.getElementById('rekapKelas').value;

    const tbody = document.getElementById('tbody-rekap-bulanan');
    const thead = document.getElementById('thead-rekap-bulanan');
    
    tbody.innerHTML = '<tr><td colspan="10" class="p-8 text-center"><i class="fas fa-circle-notch fa-spin mr-2"></i>Mengambil data...</td></tr>';

    actionRunner.withSuccessHandler(res => {
        if(!res.success) {
            tbody.innerHTML = `<tr><td colspan="10" class="p-4 text-center text-red-500">${res.message}</td></tr>`;
            return;
        }
        
        const data = res.data; // { daysInMonth, students: [...] }
        
        // 1. RENDER HEADER
        let headerHTML = '<th class="p-3 w-10 text-center bg-gray-100 sticky left-0 z-20">No</th>';
        headerHTML += '<th class="p-3 w-40 bg-gray-100 sticky left-10 z-20">Nama Siswa</th>';
        
        // Loop tanggal 1 s.d akhir bulan
        for(let d=1; d<=data.daysInMonth; d++) {
            headerHTML += `<th class="p-1 text-center w-8 text-[10px] border-l border-gray-200">${d}</th>`;
        }
        // Header Statistik
        headerHTML += '<th class="p-2 w-8 text-center bg-green-50 text-green-700 border-l">H</th>';
        headerHTML += '<th class="p-2 w-8 text-center bg-yellow-50 text-yellow-700">S</th>';
        headerHTML += '<th class="p-2 w-8 text-center bg-blue-50 text-blue-700">I</th>';
        headerHTML += '<th class="p-2 w-8 text-center bg-red-50 text-red-700">A</th>';
        headerHTML += '<th class="p-2 w-12 text-center bg-gray-100">%</th>';
        
        thead.innerHTML = headerHTML;

        // 2. RENDER BODY
        if(data.students.length === 0) {
            tbody.innerHTML = '<tr><td colspan="40" class="p-8 text-center text-gray-400">Tidak ada data siswa.</td></tr>';
            return;
        }

        let rowsHTML = '';
        data.students.forEach((siswa, idx) => {
            let row = `<tr class="hover:bg-gray-50 border-b border-gray-100 group">`;
            row += `<td class="p-3 text-center text-gray-500 sticky left-0 bg-white group-hover:bg-gray-50 z-10">${idx+1}</td>`;
            row += `<td class="p-3 font-bold text-gray-700 text-xs sticky left-10 bg-white group-hover:bg-gray-50 z-10 truncate max-w-[150px]" title="${siswa.nama}">${siswa.nama}</td>`;
            
            // Loop Kode Harian
            siswa.dailyCodes.forEach(day => {
                let colorClass = '';
                let bgClass = '';
                if(day.isHoliday) bgClass = 'bg-red-50'; // Kolom Libur
                
                if(day.code === 'H') colorClass = 'text-green-600 font-bold';
                else if(day.code === 'S') colorClass = 'text-yellow-600 font-bold bg-yellow-50';
                else if(day.code === 'I') colorClass = 'text-blue-600 font-bold bg-blue-50';
                else if(day.code === 'A') colorClass = 'text-red-600 font-bold bg-red-50';
                else if(day.code === 'L') { colorClass = 'text-red-300'; bgClass = 'bg-red-50'; }
                
                row += `<td class="p-1 text-center text-[10px] border-l border-gray-100 ${colorClass} ${bgClass}">${day.code}</td>`;
            });

            // Statistik
            row += `<td class="p-2 text-center text-xs font-bold text-green-700 bg-green-50/30 border-l">${siswa.stats.h}</td>`;
            row += `<td class="p-2 text-center text-xs font-bold text-yellow-700 bg-yellow-50/30">${siswa.stats.s}</td>`;
            row += `<td class="p-2 text-center text-xs font-bold text-blue-700 bg-blue-50/30">${siswa.stats.i}</td>`;
            row += `<td class="p-2 text-center text-xs font-bold text-red-700 bg-red-50/30">${siswa.stats.a}</td>`;
            row += `<td class="p-2 text-center text-xs font-bold text-gray-700 bg-gray-50">${siswa.stats.percent}%</td>`;
            
            row += `</tr>`;
            rowsHTML += row;
        });
        
        tbody.innerHTML = rowsHTML;

    }).getMonthlyReportData(parseInt(bulan), parseInt(tahun), kelas);
}

function renderRekapTable(result) {
    if (!result.success) {
        document.getElementById('tbody-rekap-bulanan').innerHTML = `<tr><td colspan="100%" class="p-8 text-center text-red-500">${result.message}</td></tr>`;
        return;
    }

    const { daysInMonth, students } = result.data;
    const thead = document.getElementById('thead-rekap-bulanan');
    const tbody = document.getElementById('tbody-rekap-bulanan');

    // --- 1. GENERATE HEADER ---
    let headerHtml = `
        <th class="p-3 w-10 sticky left-0 bg-gray-100 z-20 border-r border-gray-200">No</th>
        <th class="p-3 w-64 sticky left-10 bg-gray-100 z-20 border-r border-gray-200 shadow-sm">Nama Siswa</th>
    `;
    
    // Ambil info libur dari siswa pertama (karena pola libur sama untuk semua)
    // Kita asumsikan siswa pertama ada datanya.
    const sampleDaily = students.length > 0 ? students[0].dailyCodes : [];

    // Loop Header Tanggal
    for (let d = 1; d <= daysInMonth; d++) {
        // Cek apakah hari ini libur berdasarkan data sample
        // index array mulai dari 0, tanggal mulai dari 1. Jadi index = d-1
        const dayInfo = sampleDaily[d-1]; 
        const isHoliday = dayInfo ? dayInfo.isHoliday : false;
        
        // Warna Header: Jika libur, background merah muda, teks merah
        const headerClass = isHoliday 
            ? "bg-red-50 text-red-600 border-red-100 font-bold" 
            : "bg-gray-50 text-gray-600 border-gray-200";

        headerHtml += `<th class="p-1 w-8 text-center border-l ${headerClass} text-[10px]">${d}</th>`;
    }
    
    // Header Statistik
    headerHtml += `
        <th class="p-2 w-10 text-center bg-green-50 text-green-700 border-l-2 border-gray-200">H</th>
        <th class="p-2 w-10 text-center bg-yellow-50 text-yellow-700">S</th>
        <th class="p-2 w-10 text-center bg-blue-50 text-blue-700">I</th>
        <th class="p-2 w-10 text-center bg-red-50 text-red-700">A</th>
        <th class="p-2 w-16 text-center bg-indigo-50 text-indigo-700 font-bold">%</th>
    `;
    thead.innerHTML = headerHtml;

    // --- 2. GENERATE BODY ---
    if (students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="100%" class="p-8 text-center text-gray-400">Tidak ada data siswa.</td></tr>';
        return;
    }

    let bodyHtml = '';
    students.forEach((siswa, index) => {
        let dailyCells = '';
        
        siswa.dailyCodes.forEach(day => {
            let bgClass = '';
            let textClass = 'text-gray-400';
            let content = day.code;

            // LOGIKA WARNA BARU
            if (day.isHoliday) {
                // HARI LIBUR: Full Block Warna Merah Muda (Arsir)
                bgClass = 'bg-red-50 pattern-grid-lg'; // pattern opsional
                textClass = 'text-red-300 select-none'; 
                content = ''; // Kosongkan visual atau isi 'L' samar
            } else {
                // HARI EFEKTIF
                if (day.code === 'H') { 
                    bgClass = 'bg-green-100'; 
                    textClass = 'text-green-700 font-bold'; 
                }
                else if (day.code === 'S') { 
                    bgClass = 'bg-yellow-100'; 
                    textClass = 'text-yellow-700 font-bold'; 
                }
                else if (day.code === 'I') { 
                    bgClass = 'bg-blue-100'; 
                    textClass = 'text-blue-700 font-bold'; 
                }
                else if (day.code === 'A') { 
                    // ALPHA: Merah Lebih Gelap/Kontras
                    bgClass = 'bg-red-100'; 
                    textClass = 'text-red-600 font-bold'; 
                }
                else { 
                    content = '-'; 
                }
            }
            
            // Border diperhalus
            const borderClass = day.isHoliday ? 'border-red-50' : 'border-gray-100';
            dailyCells += `<td class="p-1 text-center border ${borderClass} ${bgClass} ${textClass} text-[10px]">${content}</td>`;
        });

        const stats = siswa.stats;
        const percentColor = stats.percent < 70 ? 'text-red-600' : (stats.percent < 90 ? 'text-yellow-600' : 'text-green-600');

        bodyHtml += `
            <tr class="hover:bg-gray-50 transition border-b border-gray-50">
                <td class="p-2 text-center border-r border-gray-200 sticky left-0 bg-white z-10 font-medium text-gray-500">${index + 1}</td>
                <td class="p-2 border-r border-gray-200 sticky left-10 bg-white z-10 shadow-sm whitespace-nowrap overflow-hidden text-ellipsis max-w-[200px]" title="${siswa.nama}">
                    <div class="font-bold text-gray-700 text-xs">${siswa.nama}</div>
                    <div class="text-[10px] text-gray-400">${siswa.nisn}</div>
                </td>
                ${dailyCells}
                <td class="p-1 text-center font-bold text-green-600 bg-green-50/30 border-l-2 border-gray-100">${stats.h}</td>
                <td class="p-1 text-center font-bold text-yellow-600 bg-yellow-50/30">${stats.s}</td>
                <td class="p-1 text-center font-bold text-blue-600 bg-blue-50/30">${stats.i}</td>
                <td class="p-1 text-center font-bold text-red-600 bg-red-50/30">${stats.a}</td>
                <td class="p-1 text-center font-bold ${percentColor} bg-indigo-50 border-l border-gray-200">${stats.percent}%</td>
            </tr>
        `;
    });

    tbody.innerHTML = bodyHtml;
}

function exportRekapBulananExcel() {
    const btn = document.getElementById('btnExportRekap');
    const originalContent = btn.innerHTML;
    const bulan = document.getElementById('rekapBulan').value;
    const tahun = document.getElementById('rekapTahun').value;
    
    // AMBIL FILTER KELAS
    let kelas = document.getElementById('rekapKelas').value;
    if (role === 'wakel' && currentUser.kelas) {
        kelas = currentUser.kelas;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Loading...';

    const filters = {
        bulan: parseInt(bulan),
        tahun: parseInt(tahun),
        kelas: kelas // Kirim kelas yang dipilih
    };

    actionRunner.withSuccessHandler(result => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        if (result.success) {
            const link = document.createElement('a');
            link.href = result.url;
            link.setAttribute('download', '');
            document.body.appendChild(link);
            link.click();
            link.remove();
        } else {
            showAlert('error', result.message);
        }
    })
    .withFailureHandler(err => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        showAlert('error', 'Gagal: ' + err);
    })
    .generateExcel('laporan_bulanan', filters);
}

// ==========================================
// LOGIKA KENAIKAN KELAS & ARSIP
// ==========================================

function loadKenaikanKelas() {
    window.location.href = window.APP_ROUTES?.kenaikanKelas || '/kenaikan-kelas';
}

function loadSiswaUntukPromosi() {
    const asal = document.getElementById('promoKelasAsal').value;
    const tujuan = document.getElementById('promoKelasTujuan').value;

    if (!asal || !tujuan) {
        showAlert('error', 'Harap pilih Kelas Asal dan Kelas Tujuan.');
        return;
    }

    if (asal === tujuan) {
        showAlert('error', 'Kelas Asal dan Tujuan tidak boleh sama.');
        return;
    }

    showLoading(); // Overlay loading (buat function ini jika belum ada) atau manual:
    document.getElementById('tbody-promo-siswa').innerHTML = '<tr><td colspan="4" class="p-4 text-center"><i class="fas fa-circle-notch fa-spin"></i> Mengambil data siswa...</td></tr>';
    document.getElementById('container-promo-siswa').classList.remove('hidden');
    document.getElementById('promo-placeholder').classList.add('hidden');

    actionRunner.withSuccessHandler(siswaList => {
        hideLoading();
        renderTablePromosi(siswaList, tujuan);
    }).getSiswaByKelas(asal);
}

function renderTablePromosi(siswaList, namaKelasTujuan) {
    const tbody = document.getElementById('tbody-promo-siswa');
    tbody.innerHTML = '';

    if (siswaList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="p-8 text-center text-gray-500">Tidak ada siswa di kelas ini.</td></tr>';
        return;
    }

    siswaList.forEach((s, i) => {
        const tr = document.createElement('tr');
        tr.className = "hover:bg-gray-50 transition border-b border-gray-50";
        
        // Label tombol switch
        const labelNaik = namaKelasTujuan === 'LULUS' ? 'Lulus' : 'Naik Kelas';
        const classNaik = namaKelasTujuan === 'LULUS' ? 'text-green-600' : 'text-indigo-600';
        
        tr.innerHTML = `
            <td class="p-3 text-center text-gray-500">${i + 1}</td>
            <td class="p-3 font-medium text-gray-800">${s.nama}</td>
            <td class="p-3 text-gray-500 text-xs">${s.nisn}</td>
            <td class="p-3 text-center">
                <div class="flex items-center justify-center gap-4 promo-row" data-nisn="${s.nisn}" data-nama="${s.nama}">
                    
                    <label class="cursor-pointer flex items-center gap-2 p-2 rounded-lg border border-transparent hover:bg-green-50 transition">
                        <input type="radio" name="status_${s.nisn}" value="NAIK" checked class="w-4 h-4 text-green-600 focus:ring-green-500">
                        <span class="text-xs font-bold ${classNaik}">${labelNaik}</span>
                    </label>

                    <label class="cursor-pointer flex items-center gap-2 p-2 rounded-lg border border-transparent hover:bg-red-50 transition">
                        <input type="radio" name="status_${s.nisn}" value="TINGGAL" class="w-4 h-4 text-red-600 focus:ring-red-500">
                        <span class="text-xs font-bold text-red-600">Tinggal Kelas</span>
                    </label>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function executePromotion() {
    const asal = document.getElementById('promoKelasAsal').value;
    const tujuan = document.getElementById('promoKelasTujuan').value;
    
    // Kumpulkan Data
    const rows = document.querySelectorAll('.promo-row');
    const promoData = [];
    let countNaik = 0;
    let countTinggal = 0;

    rows.forEach(row => {
        const nisn = row.getAttribute('data-nisn');
        const nama = row.getAttribute('data-nama');
        // Cari radio button yang checked dalam baris ini
        const status = document.querySelector(`input[name="status_${nisn}"]:checked`).value;
        
        promoData.push({
            nisn: nisn,
            nama: nama,
            status: status // 'NAIK' atau 'TINGGAL'
        });

        if (status === 'NAIK') countNaik++;
        else countTinggal++;
    });

    if (promoData.length === 0) return;

    Swal.fire({
        title: 'Konfirmasi Kenaikan',
        html: `
            Anda akan memproses kelas <b>${asal}</b> ke <b>${tujuan}</b>.<br><br>
            <ul class="text-left text-sm bg-gray-50 p-3 rounded">
                <li> <b>${countNaik}</b> siswa Naik/Lulus</li>
                <li> <b>${countTinggal}</b> siswa Tinggal Kelas</li>
            </ul>
            <br>Lanjutkan?
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        confirmButtonText: 'Ya, Proses!'
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.getElementById('btn-eksekusi-promo');
            const originalTxt = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sedang memproses database...';

            actionRunner.withSuccessHandler(res => {
                btn.disabled = false;
                btn.innerHTML = originalTxt;
                
                if (res.success) {
                    Swal.fire('Berhasil!', `Data berhasil diperbarui.`, 'success');
                    // Reset View
                    loadKenaikanKelas();
                    // Refresh Cache Data Siswa
                    loadDataSiswa();
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            }).processIndividualPromotion(asal, tujuan, promoData);
        }
    });
}
function showModalArsip() {
    Swal.fire({
        title: 'Tutup Tahun Ajaran (Arsip)',
        html: `
            <p class="text-xs text-gray-600 mb-3">Backup data absensi ke file baru & reset database absensi utama.</p>
            <input type="text" id="swal-input-arsip" class="swal2-input" placeholder="Nama File Arsip (misal: Absensi 2024-2025)">
        `,
        showCancelButton: true,
        confirmButtonText: 'Arsipkan & Reset',
        confirmButtonColor: '#d33',
        preConfirm: () => {
            const nama = Swal.getPopup().querySelector('#swal-input-arsip').value;
            if (!nama) Swal.showValidationMessage('Nama arsip wajib diisi');
            return nama;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Panggil fungsi backend archiveAndResetYear (sama seperti sebelumnya)
            showLoading();
            actionRunner.withSuccessHandler(res => {
                hideLoading();
                if(res.success) Swal.fire('Sukses', 'Data berhasil diarsipkan', 'success');
                else Swal.fire('Gagal', res.message, 'error');
            }).archiveAndResetYear(result.value);
        }
    });
}

function renderMappingTable(classes) {
    const tbody = document.getElementById('tbody-mapping-kelas');
    tbody.innerHTML = '';

    // Sort kelas agar rapi (1A, 1B, 2A...)
    classes.sort();

    classes.forEach(kelasAsal => {
        // Buat opsi dropdown untuk target kelas
        let options = `<option value="">-- Pilih --</option>`;
        options += `<option value="LULUS" class="font-bold text-red-600"> LULUS (Jadi Alumni)</option>`;
        
        classes.forEach(kelasTujuan => {
            // Logika auto-select sederhana (Misal 1-A -> 2-A)
            // Ini opsional, user bisa ganti manual
            options += `<option value="${kelasTujuan}">${kelasTujuan}</option>`;
        });

        const tr = document.createElement('tr');
        tr.className = "hover:bg-gray-50 transition";
        tr.innerHTML = `
            <td class="p-3 font-bold text-gray-700 border-b border-gray-100">
                <input type="hidden" class="input-kelas-asal" value="${kelasAsal}">
                ${kelasAsal}
            </td>
            <td class="p-3 text-center text-gray-400 border-b border-gray-100">
                <i class="fas fa-arrow-right"></i>
            </td>
            <td class="p-3 border-b border-gray-100">
                <select class="input-kelas-tujuan w-full p-1 border border-gray-300 rounded text-sm focus:ring-indigo-500">
                    ${options}
                </select>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function processKenaikanKelas() {
    // 1. Kumpulkan Data Mapping
    const mapping = [];
    const rows = document.querySelectorAll('#tbody-mapping-kelas tr');
    let isValid = true;

    rows.forEach(row => {
        const asal = row.querySelector('.input-kelas-asal').value;
        const tujuan = row.querySelector('.input-kelas-tujuan').value;
        
        if (tujuan) {
            mapping.push({ asal: asal, tujuan: tujuan });
        }
    });

    if (mapping.length === 0) {
        showAlert('error', 'Belum ada kenaikan kelas yang dipilih.');
        return;
    }

    // 2. Konfirmasi SweetAlert
    Swal.fire({
        title: 'Konfirmasi Kenaikan?',
        text: `Anda akan memproses ${mapping.length} aturan kenaikan kelas. Data siswa akan berubah permanen!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4F46E5',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Proses Sekarang!'
    }).then((result) => {
        if (result.isConfirmed) {
            // UI Loading
            const btn = document.getElementById('btn-proses-naik');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Memproses...';

            actionRunner.withSuccessHandler(res => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (res.success) {
                    Swal.fire('Berhasil!', `Data berhasil diperbarui. ${res.movedCount} siswa dipindahkan/lulus.`, 'success');
                    // Refresh data
                    loadDataSiswa(); // Refresh cache siswa
                    loadKenaikanKelas(); // Reset form
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            }).processGradePromotion(mapping);
        }
    });
}

function processTutupTahun() {
    const namaArsip = document.getElementById('namaArsip').value.trim();
    
    if (!namaArsip) {
        showAlert('error', 'Harap isi Label Arsip terlebih dahulu (Misal: Arsip 2024-2025)');
        return;
    }

    Swal.fire({
        title: 'TUTUP TAHUN AJARAN?',
        html: `Anda akan mengarsipkan data ke file <b>"${namaArsip}"</b> dan <span style="color:red; font-weight:bold;">MENGHAPUS SEMUA DATA ABSENSI</span> di aplikasi ini.<br><br>Tindakan ini tidak dapat dibatalkan!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#059669', // Emerald
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Saya Paham & Lanjutkan'
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.getElementById('btn-proses-arsip');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-cog fa-spin"></i> Mengarsipkan... (Bisa memakan waktu)';

            actionRunner.withSuccessHandler(res => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                if (res.success) {
                    Swal.fire('Selesai!', `Data absensi berhasil diarsipkan ke spreadsheet baru.<br><a href="${res.url}" target="_blank" class="text-blue-600 underline">Buka Arsip</a>`, 'success');
                    document.getElementById('namaArsip').value = '';
                } else {
                    Swal.fire('Gagal', res.message, 'error');
                }
            }).archiveAndResetYear(namaArsip);
        }
    });
}


// ==========================================================================
    // FITUR IMPORT EXCEL HARI LIBUR
    // ==========================================================================
    
    function triggerImportLibur() {
        document.getElementById('fileInputLibur').click();
    }

function handleFileImportLibur(input) {
        const file = input.files[0];
        if (!file) return;

        showLoading(); 

        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            
            // --- PERBAIKAN DISINI: Tambahkan cellDates: true ---
            // Opsi ini memaksa SheetJS mengubah angka Excel menjadi Date Object JS yang benar
            const workbook = XLSX.read(data, { type: 'array', cellDates: true });
            // ----------------------------------------------------
            
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });

            if (jsonData.length === 0) {
                hideLoading();
                showAlert('error', 'File Excel kosong atau format salah.');
                input.value = ''; 
                return;
            }

            // Validasi Header
            const firstRow = jsonData[0];
            const hasTanggal = firstRow.hasOwnProperty('Tanggal');
            const hasKet = firstRow.hasOwnProperty('Keterangan');

            if (!hasTanggal || !hasKet) {
                hideLoading();
                showAlert('error', 'Format Excel salah! Pastikan ada kolom header: Tanggal dan Keterangan.');
                input.value = '';
                return;
            }

            processImportLibur(jsonData, input);
        };

        reader.onerror = function() {
            hideLoading();
            showAlert('error', 'Gagal membaca file.');
            input.value = '';
        };

        reader.readAsArrayBuffer(file);
    }

function processImportLibur(data, inputElement) {
        // Helper: Mengubah Date Object JS ke string "YYYY-MM-DD"
        // Ini penting untuk mencegah tanggal mundur 1 hari karena perbedaan zona waktu
        const formatDateCorrectly = (dateInput) => {
            if (!dateInput) return "";
            
            // Jika input sudah berupa Date Object (karena cellDates: true)
            if (dateInput instanceof Date) {
                const year = dateInput.getFullYear();
                const month = String(dateInput.getMonth() + 1).padStart(2, '0');
                const day = String(dateInput.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            // Jika masih string, kembalikan apa adanya
            return String(dateInput).trim();
        };

        // Mapping data Excel ke Format Backend
        const formattedData = data.map(row => {
            const clean = (val) => (val ? String(val).trim() : '');
            
            // Ambil tanggal dan format ulang
            let rawTanggal = row['Tanggal'];
            let fixedTanggal = formatDateCorrectly(rawTanggal);

            return {
                tanggal: fixedTanggal, 
                keterangan: clean(row['Keterangan'])
            };
        });

        actionRunner
            .withSuccessHandler(res => {
                hideLoading();
                inputElement.value = ''; 
                
                if (res.success) {
                    loadKelolaAbsen(); 
                    let msg = `Berhasil: ${res.added}, Gagal/Duplikat: ${res.skipped}`;
                    showAlert('success', 'Import Hari Libur Selesai! ' + msg);
                } else {
                    showAlert('error', res.message);
                }
            })
            .withFailureHandler(err => {
                hideLoading();
                inputElement.value = '';
                showAlert('error', 'Error Server: ' + err);
            })
            .importHariLiburBulk(formattedData);
    }

// Tambahkan di bagian script

// ====================================
// FITUR DOWNLOAD KARTU MASSAL (BULK)
// ====================================
// 
async function downloadKartuSiswaBulk() {
    // 1. Cek Filter & Data
    const filterKelas = document.getElementById('filterKelasSiswa').value;
    // Pastikan tableState.siswa.fullData ada
    const allSiswa = (tableState.siswa && tableState.siswa.fullData) ? tableState.siswa.fullData : [];

    if (!allSiswa || allSiswa.length === 0) {
        showAlert('error', 'Data karyawan belum dimuat. Refresh data dulu.');
        return;
    }

    // 2. Filter Data
    let dataToPrint = allSiswa;
    if (filterKelas && filterKelas !== "") {
        dataToPrint = allSiswa.filter(s => s.kelas === filterKelas);
    }

    if (dataToPrint.length === 0) {
        showAlert('error', 'Tidak ada siswa ditemukan.');
        return;
    }

    // Konfirmasi
    const result = await Swal.fire({
        title: 'Cetak Kartu Karyawan',
        text: `Mencetak ${dataToPrint.length} kartu. Layout: 8 Kartu per Halaman (A4).`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Cetak Sekarang',
        cancelButtonText: 'Batal'
    });
    if (!result.isConfirmed) return;

    // 3. Proses PDF
    Swal.fire({
        title: 'Generate PDF...',
        html: 'Sedang menyusun kartu...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    setTimeout(async () => {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');

            const pickValue = (obj, keys, fallback = '-') => {
                for (const key of keys) {
                    const value = obj?.[key];
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        return String(value).trim();
                    }
                }
                return fallback;
            };

            const truncateText = (value, maxLength) => {
                const text = String(value || '').trim();
                if (!text) return '-';
                return text.length > maxLength ? `${text.slice(0, maxLength - 3)}...` : text;
            };

            const normalizeGender = (rawValue) => {
                const value = String(rawValue || '').trim().toLowerCase();
                if (['l', 'lk', 'laki', 'laki-laki', 'pria', 'male'].includes(value)) return 'Laki-laki';
                if (['p', 'pr', 'perempuan', 'wanita', 'female'].includes(value)) return 'Perempuan';
                return rawValue || '-';
            };

            const formatDateLabel = (rawValue) => {
                const value = String(rawValue || '').trim();
                if (!value || value === '-') return '-';
                const parsed = new Date(value);
                if (!Number.isNaN(parsed.getTime())) {
                    return parsed.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    });
                }
                return value;
            };

            const now = new Date();
            const printedAtLabel = now.toLocaleString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1;
            const academicYearStart = currentMonth >= 7 ? currentYear : (currentYear - 1);
            const academicYearLabel = `${academicYearStart}/${academicYearStart + 1}`;
            
            // --- KONFIGURASI GRID ---
            const pageWidth = 210;  // Lebar A4
            const pageHeight = 297; // Tinggi A4
            const cardWidth = 86;   // Lebar Kartu ID Standar
            const cardHeight = 54;  // Tinggi Kartu ID Standar
            
            // Jarak antar kartu
            const gapX = 10; 
            const gapY = 10; 

            // Hitung Margin Otomatis
            const marginX = (pageWidth - ((cardWidth * 2) + gapX)) / 2;
            const marginY = (pageHeight - ((cardHeight * 4) + (gapY * 3))) / 2;

            let x = marginX;
            let y = marginY;
            let col = 0;
            let row = 0;

            // Temp div untuk QR Generator (Hidden)
            const tempDiv = document.createElement('div');
            // Gunakan absolute off-screen agar canvas tetap ter-render dengan benar oleh browser
            tempDiv.style.position = 'absolute';
            tempDiv.style.left = '-9999px';
            tempDiv.style.top = '-9999px';
            document.body.appendChild(tempDiv);

            for (let i = 0; i < dataToPrint.length; i++) {
                const s = dataToPrint[i];

                // --- PERBAIKAN 1: BERSIHKAN DATA NISN (SANITIZER) ---
                // Mengambil hanya angka dan huruf, membuang spasi/karakter aneh
                let rawNisn = s.nisn || "0000";
                let cleanNisn = String(rawNisn).replace(/[^a-zA-Z0-9]/g, "").trim();
                if (!cleanNisn) cleanNisn = '0000';

                const namaCetak = truncateText((s.nama || '').toUpperCase(), 26);
                const kelasCetak = truncateText(pickValue(s, ['kelas']), 12);
                const genderCetak = truncateText(normalizeGender(pickValue(s, ['jenisKelamin', 'jenis_kelamin', 'gender'], '-')), 12);
                const tanggalLahirCetak = truncateText(
                    formatDateLabel(
                        pickValue(s, ['tanggalLahir', 'tanggal_lahir', 'tglLahir', 'tgl_lahir', 'birthDate', 'birth_date'], '-')
                    ),
                    14
                );
                const kontakCetak = truncateText(
                    pickValue(s, ['noHpOrtu', 'no_hp_ortu', 'noWaOrtu', 'no_wa_ortu', 'noHp', 'no_hp', 'noWa', 'no_wa'], '-'),
                    14
                );
                const nomorKartu = String(i + 1).padStart(4, '0');

                // ==========================================
                // DESAIN KARTU
                // ==========================================

                // 1. BACKGROUND & BORDER
                doc.setFillColor(226, 232, 240);
                doc.roundedRect(x + 0.9, y + 0.9, cardWidth, cardHeight, 2.5, 2.5, 'F');
                doc.setDrawColor(186, 230, 253);
                doc.setFillColor(255, 255, 255);
                doc.roundedRect(x, y, cardWidth, cardHeight, 2.5, 2.5, 'FD');

                // 2. HEADER
                doc.setFillColor(30, 64, 175);
                doc.roundedRect(x, y, cardWidth, 15, 2.5, 2.5, 'F');
                doc.setFillColor(59, 130, 246);
                doc.rect(x, y + 12.5, cardWidth, 2.5, 'F');

                // Label tahun ajaran
                doc.setFillColor(219, 234, 254);
                doc.roundedRect(x + cardWidth - 25, y + 3, 20, 5.5, 1.2, 1.2, 'F');
                doc.setTextColor(30, 64, 175);
                doc.setFont("helvetica", "bold");
                doc.setFontSize(4.8);
                doc.text(academicYearLabel, x + cardWidth - 15, y + 6.8, {align:'center'});

                // Teks header
                doc.setTextColor(255, 255, 255);
                doc.setFont("helvetica", "bold");
                doc.setFontSize(7.8);
                doc.text("KARTU ABSENSI KARYAWAN", x + 4.5, y + 6);
                doc.setFont("helvetica", "normal");
                let websiteName = String(window.APP_WEBSITE_NAME || '').replace(/\s+/g, ' ').trim();
                if (!websiteName) websiteName = 'PT DUTA INDO RAYA';
                websiteName = websiteName.toUpperCase();

                let websiteFontSize = 5.4;
                doc.setFontSize(websiteFontSize);

                if (typeof doc.getTextWidth === 'function') {
                    const maxWidth = cardWidth - 9; // 4.5mm padding kiri/kanan
                    const textWidth = doc.getTextWidth(websiteName);
                    if (textWidth > maxWidth && textWidth > 0) {
                        websiteFontSize = Math.max(3.8, websiteFontSize * (maxWidth / textWidth));
                        doc.setFontSize(websiteFontSize);
                    }
                }

                doc.text(websiteName, x + 4.5, y + 10);

                // 3. QR CODE
                const qrPanelX = x + 4.5;
                const qrPanelY = y + 18;
                const qrPanelWidth = 30;
                const qrPanelHeight = 30;
                const qrSize = 22;
                const qrX = qrPanelX + 4;
                const qrY = qrPanelY + 3;

                doc.setFillColor(248, 250, 252);
                doc.setDrawColor(226, 232, 240);
                doc.roundedRect(qrPanelX, qrPanelY, qrPanelWidth, qrPanelHeight, 1.5, 1.5, 'FD');
                
                // --- PERBAIKAN 2: GENERATE QR DENGAN LEVEL 'L' ---
                tempDiv.innerHTML = ''; // Kosongkan dulu
                
                // Cek apakah data valid
                if(cleanNisn) {
                    try {
                        new QRCode(tempDiv, {
                            text: cleanNisn, // Gunakan data bersih
                            width: 120, height: 120,
                            // PENTING: Gunakan Level L agar muat data panjang tanpa error overflow
                            correctLevel: QRCode.CorrectLevel.L, 
                            colorDark : "#000000", colorLight : "#ffffff"
                        });
                        
                        // Ambil gambar dari canvas
                        const canvas = tempDiv.querySelector('canvas');
                        if(canvas) {
                            const qrUrl = canvas.toDataURL("image/png");
                            doc.addImage(qrUrl, 'PNG', qrX, qrY, qrSize, qrSize);
                        }
                    } catch (e) {
                        console.error("Gagal buat QR untuk " + s.nama, e);
                    }
                }

                // Label kecil di bawah QR
                doc.setFontSize(4.8);
                doc.setTextColor(100, 116, 139);
                doc.text("Scan Absensi", qrPanelX + (qrPanelWidth / 2), qrPanelY + qrPanelHeight - 1.8, {align:'center'});

                // 4. DATA SISWA
                const textX = x + 37.5;
                const valueX = textX + 28;
                
                // NAMA
                doc.setFont("helvetica", "bold");
                doc.setFontSize(8.2);
                doc.setTextColor(15, 23, 42);
                doc.text(namaCetak, textX, y + 21.5);

                // Garis pemisah
                doc.setDrawColor(226, 232, 240);
                doc.line(textX, y + 23.2, x + cardWidth - 4.5, y + 23.2);

                // DETAIL
                doc.setFontSize(5.2);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 116, 139);
                doc.text("NIK", textX, y + 27.6);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(15, 23, 42);
                doc.text(`: ${cleanNisn}`, valueX, y + 27.6);
                
                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 116, 139);
                doc.text("Gedung", textX, y + 31.5);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(15, 23, 42);
                doc.text(`: ${kelasCetak}`, valueX, y + 31.5);

                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 116, 139);
                doc.text("Jenis Kelamin", textX, y + 35.4);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(15, 23, 42);
                doc.text(`: ${genderCetak}`, valueX, y + 35.4);

                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 116, 139);
                doc.text("Tanggal Lahir", textX, y + 39.3);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(15, 23, 42);
                doc.text(`: ${tanggalLahirCetak}`, valueX, y + 39.3);

                doc.setFont("helvetica", "normal");
                doc.setTextColor(100, 116, 139);
                doc.text("Kontak", textX, y + 43.2);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(15, 23, 42);
                doc.text(`: ${kontakCetak}`, valueX, y + 43.2);

                // Footer
                doc.setDrawColor(226, 232, 240);
                doc.line(x + 4.5, y + cardHeight - 5.5, x + cardWidth - 4.5, y + cardHeight - 5.5);
                doc.setFont("helvetica", "normal");
                doc.setFontSize(4.4);
                doc.setTextColor(100, 116, 139);
                doc.text(`No ${nomorKartu}`, x + 4.8, y + cardHeight - 2.5);
                doc.text(`Cetak ${printedAtLabel}`, x + cardWidth - 4.8, y + cardHeight - 2.5, { align: 'right' });

                // ==========================================
                // GRID LOGIC
                // ==========================================
                col++;
                if (col >= 2) { 
                    col = 0;
                    row++;
                    x = marginX; 
                    y += cardHeight + gapY; 
                } else { 
                    x += cardWidth + gapX;
                }

                // Pindah Halaman
                if (row >= 4 && i < dataToPrint.length - 1) {
                    doc.addPage();
                    col = 0; 
                    row = 0; 
                    x = marginX; 
                    y = marginY;
                }
            }

            // Bersihkan elemen temp
            document.body.removeChild(tempDiv);
            
            const fileName = filterKelas ? `Kartu_${filterKelas}.pdf` : `Kartu_Semua_karyawan.pdf`;
            doc.save(fileName);

            Swal.fire({
                icon: 'success',
                title: 'Selesai!',
                text: 'PDF Kartu berhasil diunduh.',
                timer: 2000
            });
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Gagal membuat PDF: ' + error.message, 'error');
        }
    }, 500);
}

    function deleteGuruConfirm(username) {
        if (confirm(`Hapus akses untuk guru: ${username}?`)) {
            showLoading(); // Tampilkan overlay loading

            // --- KEAMANAN: AMBIL TOKEN USER ---
            const token = currentUser ? currentUser.token : null;
            // ----------------------------------

            actionRunner.withSuccessHandler(r => {
                hideLoading();
                
                if (r.success) {
                    // Bersihkan cache data guru
                    tableState.guru.fullData = []; 
                    // Reload tabel
                    loadDataGuru();
                    showAlert('success', 'Akun guru berhasil dihapus');
                } else {
                    showAlert('error', r.message);
                }
            })
            .withFailureHandler(error => {
                hideLoading();
                showAlert('error', 'Gagal menghapus: ' + error);
            })
            .deleteGuru(token, username); // <-- Token dikirim
        }
    }

function handleArchiveAndReset() {
        const backupNameInput = document.getElementById('backupName');
        // Ambil value dan hapus spasi berlebih
        const backupName = backupNameInput ? backupNameInput.value.trim() : ''; 

        Swal.fire({
            title: 'Tutup Tahun Ajaran?',
            text: "Sistem akan membackup data saat ini (Absensi, Siswa, Kelas) lalu MERESET data utama untuk tahun ajaran baru. Tindakan ini tidak bisa dibatalkan!",
            icon: 'warning',
            input: 'text', // Opsional: Bisa minta input lagi disini atau pakai input form sebelumnya
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Tutup & Reset!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading();
                
                // PANGGIL BACKEND
                actionRunner
                    .withSuccessHandler(function(res) {
                        hideLoading();
                        if (res.success) {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: res.message + '\nFile Backup: ' + res.backupName, // Tampilkan nama file
                                icon: 'success'
                            }).then(() => {
                                loadDashboardData(); // Refresh data
                            });
                        } else {
                            showAlert('error', res.message);
                        }
                    })
                    .withFailureHandler(function(err) {
                        hideLoading();
                        showAlert('error', 'Gagal server: ' + err);
                    })
                    .archiveAndReset(backupName); // <--- PERBAIKAN: Masukkan parameter backupName
            }
        });
    }

    // Fungsi untuk Tampilkan/Sembunyikan Password
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (passwordInput.type === 'password') {
            // Ubah ke Text (Tampilkan)
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            // Ubah ke Password (Sembunyikan)
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    function downloadTemplate(type) {
        // Tampilkan loading overlay
        showLoading();

        actionRunner
            .withSuccessHandler(function(res) {
                hideLoading();
                if (res.success) {
                    const exportLink = document.createElement('a');
                    exportLink.href = res.url;
                    exportLink.setAttribute('download', '');
                    document.body.appendChild(exportLink);
                    exportLink.click();
                    exportLink.remove();
                } else {
                    showAlert('error', res.message);
                }
            })
            .withFailureHandler(function(err) {
                hideLoading();
                showAlert('error', 'Gagal: ' + err);
            })
            .getTemplateExcel(type);
    }
    function generateQRForSiswa(nisn, nama, kelas) {
        loadQRCodeSiswa(nisn, nama, kelas);
    }

    // ==========================================================================
    // PATCH LARAVEL VIEW DATA LOADER
    // ==========================================================================
    function patchedRun(method, ...args) {
        return runPageAction(method, args);
    }

    function patchedEsc(value) {
        return String(value ?? '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'");
    }

    function patchedSetText(id, value, fallback = '-') {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? fallback;
    }

    function patchedPopulateFilterKelas(id, rows) {
        const select = document.getElementById(id);
        if (!select) return;
        const current = select.value || '';
        const kelasList = [...new Set((rows || []).map((r) => String(r.kelas || '').trim()).filter(Boolean))].sort();

        select.innerHTML = '<option value="">All Gedung - Lantai</option>' + kelasList
            .map((k) => `<option value="${k}">${k}</option>`)
            .join('');

        if (current && kelasList.includes(current)) {
            select.value = current;
        }
    }

    function renderSiswaLoadingState() {
        const tbody = document.getElementById('tbody-siswa');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="p-8 text-center">
                    <div class="inline-flex items-center gap-2 text-indigo-600 text-sm font-semibold">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span>Memuat data karyawan...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderGuruLoadingState() {
        const tbody = document.getElementById('tbody-guru');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="p-8 text-center">
                    <div class="inline-flex items-center gap-2 text-purple-600 text-sm font-semibold">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span>Memuat data guru...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    window.loadDataSiswa = function loadDataSiswaPatched() {
        const tbody = document.getElementById('tbody-siswa');
        if (!tbody) return;

        bindSiswaTableActions();
        renderSiswaLoadingState();
        patchedRun('getSiswaList')
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat data karyawan.');
                tableState.siswa.fullData = Array.isArray(res.data) ? res.data : [];
                patchedPopulateFilterKelas('filterKelasSiswa', tableState.siswa.fullData);
                processTableData('siswa');
            })
            .catch((err) => {
                tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-red-500">${patchedEsc(err.message || err)}</td></tr>`;
            });
    };

    window.renderGuruRows = function renderGuruRowsPatched(data, startIdx) {
        const tbody = document.getElementById('tbody-guru');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-gray-400">Data guru tidak ditemukan.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map((guru, i) => `
            <tr class="hover:bg-gray-50 transition border-b border-gray-50 group">
                <td class="p-3 text-center text-gray-500 text-xs">${startIdx + i + 1}</td>
                <td class="p-3 text-sm font-bold text-gray-800">${guru.username || '-'}</td>
                <td class="p-3 text-sm">
                    <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs font-bold">${guru.kelas || '-'}</span>
                </td>
                <td class="p-3 text-xs font-mono text-gray-500">${guru.password || '******'}</td>
                <td class="p-3 text-center">
                    <div class="flex justify-center space-x-2 opacity-80 group-hover:opacity-100">
                        <button onclick='editGuru(${JSON.stringify(guru)})' class="p-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition" title="Edit"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteGuruConfirm('${patchedEsc(guru.username)}')" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Hapus"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');
    };

    window.loadDataGuru = function loadDataGuruPatched() {
        const tbody = document.getElementById('tbody-guru');
        if (!tbody) {
            window.location.href = window.APP_ROUTES?.dataGuru || '/data-guru';
            return;
        }

        renderGuruLoadingState();
        patchedRun('getGuruList')
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat data guru.');
                tableState.guru.fullData = Array.isArray(res.data) ? res.data : [];
                patchedPopulateFilterKelas('filterKelasGuru', tableState.guru.fullData);
                processTableData('guru');
            })
            .catch((err) => {
                tbody.innerHTML = `<tr><td colspan="5" class="p-8 text-center text-red-500">${patchedEsc(err.message || err)}</td></tr>`;
            });
    };

    window.renderLiburRows = function renderLiburRowsPatched(data, startIdx) {
        const tbody = document.getElementById('tbody-libur');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="p-8 text-center text-gray-400">Belum ada hari libur.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map((row, i) => `
            <tr class="hover:bg-gray-50 border-b border-gray-50">
                <td class="p-4 text-center text-xs text-gray-500">${startIdx + i + 1}</td>
                <td class="p-4 text-sm font-mono text-gray-700">${row.tanggal || '-'}</td>
                <td class="p-4 text-sm text-gray-800">${row.keterangan || '-'}</td>
                <td class="p-4 text-center">
                    <button ${row.canDelete === false ? 'disabled' : ''} onclick="deleteLibur('${patchedEsc(row.tanggal)}', ${row.canDelete === false ? 'false' : 'true'})" class="p-2 ${row.canDelete === false ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-red-50 text-red-600 hover:bg-red-100 transition'} rounded-lg" title="${row.canDelete === false ? 'Hari libur lewat tidak bisa dihapus' : 'Hapus'}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    };

    window.loadDataLibur = function loadDataLiburPatched() {
        const tbody = document.getElementById('tbody-libur');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="4" class="p-8 text-center text-gray-500">Memuat hari libur...</td></tr>';
        patchedRun('getHariLiburList')
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat hari libur.');
                tableState.libur.fullData = Array.isArray(res.data) ? res.data : [];
                processTableData('libur');
            })
            .catch((err) => {
                tbody.innerHTML = `<tr><td colspan="4" class="p-8 text-center text-red-500">${patchedEsc(err.message || err)}</td></tr>`;
            });
    };

    window.loadGlobalConfig = function loadGlobalConfigPatched() {
        if (!document.getElementById('conf_masuk_mulai')) return;
        patchedRun('getAppConfig')
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat konfigurasi.');
                const cfg = res.data || {};
                patchedSetText('conf_masuk_mulai', cfg.jam_masuk_mulai);
                patchedSetText('conf_masuk_akhir', cfg.jam_masuk_akhir);
                patchedSetText('conf_pulang_mulai', cfg.jam_pulang_mulai);
                patchedSetText('conf_pulang_akhir', cfg.jam_pulang_akhir);
                const f1 = document.getElementById('conf_masuk_mulai'); if (f1) f1.value = cfg.jam_masuk_mulai || '06:00';
                const f2 = document.getElementById('conf_masuk_akhir'); if (f2) f2.value = cfg.jam_masuk_akhir || '07:15';
                const f3 = document.getElementById('conf_pulang_mulai'); if (f3) f3.value = cfg.jam_pulang_mulai || '15:00';
                const f4 = document.getElementById('conf_pulang_akhir'); if (f4) f4.value = cfg.jam_pulang_akhir || '17:00';
            })
            .catch((err) => showAlert('error', err.message || String(err)));
    };

    window.saveGlobalConfig = function saveGlobalConfigPatched(e) {
        e.preventDefault();
        const payload = {
            jam_masuk_mulai: document.getElementById('conf_masuk_mulai')?.value || '06:00',
            jam_masuk_akhir: document.getElementById('conf_masuk_akhir')?.value || '07:15',
            jam_pulang_mulai: document.getElementById('conf_pulang_mulai')?.value || '15:00',
            jam_pulang_akhir: document.getElementById('conf_pulang_akhir')?.value || '17:00'
        };

        patchedRun('saveAppConfig', payload)
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal menyimpan konfigurasi.');
                showAlert('success', res.message || 'Konfigurasi disimpan.');
            })
            .catch((err) => showAlert('error', err.message || String(err)));
    };

    window.handleAddLibur = function handleAddLiburPatched(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = {
            tanggal: (fd.get('tanggal') || '').toString().trim(),
            keterangan: (fd.get('keterangan') || '').toString().trim()
        };
        if (!payload.tanggal || !payload.keterangan) {
            showAlert('error', 'Tanggal dan keterangan wajib diisi.');
            return;
        }

        patchedRun('addHariLibur', payload)
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal menambah hari libur.');
                e.target.reset();
                loadDataLibur();
                showAlert('success', res.message || 'Hari libur ditambahkan.');
            })
            .catch((err) => showAlert('error', err.message || String(err)));
    };

    window.deleteLibur = function deleteLiburPatched(tanggal, canDelete = true) {
        if (canDelete === false) {
            showAlert('info', 'Hari libur yang sudah lewat tidak dapat dihapus.');
            return;
        }
        if (!confirm(`Hapus hari libur ${tanggal}?`)) return;
        patchedRun('deleteHariLibur', tanggal)
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal menghapus hari libur.');
                loadDataLibur();
                showAlert('success', res.message || 'Hari libur dihapus.');
            })
            .catch((err) => showAlert('error', err.message || String(err)));
    };

    window.renderRekapRows = function renderRekapRowsPatched(data) {
        const tbody = document.getElementById('tbody-rekap');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-gray-400">Tidak ada data rekap.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map((row, idx) => {
            let statusClass = 'bg-gray-100 text-gray-700';
            if (row.status === 'Hadir') statusClass = 'bg-green-100 text-green-700';
            else if (row.status === 'Sakit') statusClass = 'bg-yellow-100 text-yellow-700';
            else if (row.status === 'Izin') statusClass = 'bg-blue-100 text-blue-700';
            else if (row.status === 'Alpa') statusClass = 'bg-red-100 text-red-700';

            return `
                <tr class="hover:bg-gray-50 border-b border-gray-50">
                    <td class="p-4 text-center text-xs text-gray-500">${idx + 1}</td>
                    <td class="p-4 text-xs font-mono text-gray-600">${row.tanggal || '-'}</td>
                    <td class="p-4 text-sm">
                        <div class="font-bold text-gray-900">${row.nama || '-'}</div>
                        <div class="text-xs text-gray-500 font-mono">${row.nisn || '-'}</div>
                    </td>
                    <td class="p-4 text-center text-xs font-bold text-indigo-700">${row.kelas || '-'}</td>
                    <td class="p-4 text-center text-xs font-mono">${row.jamDatang || '-'}</td>
                    <td class="p-4 text-center text-xs font-mono">${row.jamPulang || '-'}</td>
                    <td class="p-4 text-center text-xs text-gray-600">${row.keterangan || '-'}</td>
                    <td class="p-4 text-center"><span class="px-2 py-1 rounded text-xs font-bold ${statusClass}">${row.status || '-'}</span></td>
                </tr>
            `;
        }).join('');
    };

    window.loadMonitoringAbsensi = function loadMonitoringAbsensiPatched() {
        const tbody = document.getElementById('tbody-monitoring');
        if (!tbody) {
            window.location.href = window.APP_ROUTES?.monitoring || '/monitoring';
            return;
        }

        const kelasFilter = role === 'wakel' && currentUser?.kelas ? currentUser.kelas : null;
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-gray-500">Memuat data monitoring...</td></tr>';
        patchedRun('getMonitoringRealtime', kelasFilter)
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat monitoring.');
                tableState.monitoring.fullData = Array.isArray(res.data) ? res.data : [];
                processTableData('monitoring');
                const now = new Date();
                patchedSetText('monitoringDate', now.toLocaleDateString('id-ID'));
            })
            .catch((err) => {
                tbody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-500">${patchedEsc(err.message || err)}</td></tr>`;
            });
    };

    window.loadDashboardData = function loadDashboardDataPatched() {
        const hasDashboard =
            document.getElementById('view-admin-dashboard') ||
            document.getElementById('view-guru-dashboard') ||
            document.getElementById('view-siswa-dashboard');
        if (!hasDashboard) return;

        const kelasFilter = (role === 'wakel' || role === 'siswa') && currentUser?.kelas ? currentUser.kelas : null;

        patchedRun('getMonitoringRealtime', kelasFilter)
            .then((res) => {
                if (!res.success) throw new Error(res.message || 'Gagal memuat dashboard.');
                const rows = Array.isArray(res.data) ? res.data : [];

                const hadir = rows.filter((r) => r.status === 'Hadir').length;
                const sakit = rows.filter((r) => r.status === 'Sakit').length;
                const izin = rows.filter((r) => r.status === 'Izin').length;
                const alpa = rows.filter((r) => r.status === 'Alpa').length;
                const belum = rows.filter((r) => r.status === 'Belum Absen').length;
                const total = rows.length;

                patchedSetText('admStatTotal', total);
                patchedSetText('admStatHadir', hadir);
                patchedSetText('admStatSakit', sakit);
                patchedSetText('admStatIzin', izin);
                patchedSetText('admStatAlpa', alpa);
                patchedSetText('statGuruTotal', total);
                patchedSetText('statGuruSakit', sakit);
                patchedSetText('statGuruIzin', izin);
                patchedSetText('statGuruAlpa', alpa);

                if (document.getElementById('adminAttendanceChart')) {
                    renderAdminChart(hadir, sakit, izin, alpa, belum);
                }
                if (document.getElementById('guruAttendanceChart')) {
                    renderGuruChart(hadir, sakit, izin, alpa, belum);
                }

                if (document.getElementById('view-siswa-dashboard')) {
                    const nisn = String(currentUser?.nisn || currentUser?.username || '').trim();
                    const siswaRow = rows.find((r) => String(r.nisn) === nisn) || rows.find((r) => String(r.nama) === String(currentUser?.nama || ''));
                    patchedSetText('dashGreeting', currentUser?.nama || currentUser?.username || 'Siswa');
                    patchedSetText('profileNameSidebar', currentUser?.nama || '-');
                    patchedSetText('profileNisnSidebar', nisn || '-');
                    patchedSetText('profileKelasSidebar', currentUser?.kelas || '-');
                    patchedSetText('valMasuk', siswaRow?.jamDatang || '--:--');
                    patchedSetText('valPulang', siswaRow?.jamPulang || '--:--');
                    patchedSetText('dashStatusBadge', siswaRow?.status || 'Belum Absen');

                    const alertBelum = document.getElementById('alertBelumAbsen');
                    if (alertBelum) {
                        if (!siswaRow || siswaRow.status === 'Belum Absen') alertBelum.classList.remove('hidden');
                        else alertBelum.classList.add('hidden');
                    }
                }
            })
            .catch((err) => showAlert('error', err.message || String(err)));
    };

    window.loadAdminDashboard = function loadAdminDashboardPatched() {
        if (document.getElementById('view-admin-dashboard')) return loadDashboardData();
        window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
    };

    window.loadGuruDashboard = function loadGuruDashboardPatched() {
        if (document.getElementById('view-guru-dashboard')) return loadDashboardData();
        window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
    };

    window.loadSiswaDashboard = function loadSiswaDashboardPatched() {
        if (document.getElementById('view-siswa-dashboard')) return loadDashboardData();
        window.location.href = window.APP_ROUTES?.dashboard || '/dashboard';
    };

    window.loadKelolaAbsen = function loadKelolaAbsenPatched() {
        if (document.getElementById('tbody-libur')) {
            loadGlobalConfig();
            loadDataLibur();
            return;
        }
        window.location.href = window.APP_ROUTES?.kelolaAbsen || '/jadwal-libur';
    };

    window.exportToExcel = function exportToExcelPatched() {
        const start = document.getElementById('fStart')?.value;
        const end = document.getElementById('fEnd')?.value;
        const kelas = document.getElementById('fKelasRekap')?.value || null;
        if (!start || !end) {
            showAlert('error', 'Isi tanggal awal dan akhir terlebih dahulu.');
            return;
        }

        patchedRun('generateExcel', 'laporan_absensi', {
            tanggalMulai: start,
            tanggalAkhir: end,
            kelas
        }).then((res) => {
            if (!res.success) throw new Error(res.message || 'Gagal export.');
            const exportLink = document.createElement('a');
                    exportLink.href = res.url;
                    exportLink.setAttribute('download', '');
                    document.body.appendChild(exportLink);
                    exportLink.click();
                    exportLink.remove();
        }).catch((err) => showAlert('error', err.message || String(err)));
    };

    window.loadQRCodeSiswa = async function loadQRCodeSiswaPatched(nisn, nama, kelas) {
        const container = document.getElementById('kartuSiswaContainer');
        if (!container) {
            const targetUrl = window.APP_ROUTES?.kartuSiswa || '/kartu-siswa';
            const qs = new URLSearchParams({
                nisn: nisn || '',
                nama: nama || '',
                kelas: kelas || ''
            }).toString();
            window.location.href = `${targetUrl}?${qs}`;
            return;
        }

        const params = new URLSearchParams(window.location.search);
        let data = {
            nisn: nisn || params.get('nisn') || currentUser?.nisn || currentUser?.username || '',
            nama: nama || params.get('nama') || currentUser?.nama || currentUser?.name || '',
            kelas: kelas || params.get('kelas') || currentUser?.kelas || ''
        };

        if (data.nisn && (!data.nama || !data.kelas)) {
            try {
                const res = await patchedRun('getSiswaList');
                if (res.success && Array.isArray(res.data)) {
                    const match = res.data.find((s) => String(s.nisn) === String(data.nisn));
                    if (match) {
                        data.nama = data.nama || match.nama;
                        data.kelas = data.kelas || match.kelas;
                    }
                }
            } catch (_) {}
        }

        if (!data.nisn) {
            container.innerHTML = '<div class="bg-white rounded-xl p-8 text-center text-gray-500 border border-gray-200">Data kartu siswa tidak tersedia.</div>';
            return;
        }

        const qrId = 'qrcode-kartu-siswa';
        container.innerHTML = `
            <div class="max-w-md mx-auto bg-white rounded-2xl shadow border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-5 text-white text-center">
                    <h3 class="font-bold text-lg">Kartu Pelajar Digital</h3>
                    <p class="text-xs text-indigo-100 mt-1">Gunakan QR ini untuk absensi</p>
                </div>
                <div class="p-6 text-center space-y-4">
                    <div class="w-24 h-24 mx-auto rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl font-bold">
                        ${(data.nama || 'S').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900">${data.nama || '-'}</p>
                        <p class="text-xs text-gray-500 font-mono">${data.nisn}</p>
                        <p class="text-xs text-indigo-700 font-bold mt-1">Kelas ${data.kelas || '-'}</p>
                    </div>
                    <div id="${qrId}" class="flex justify-center py-2"></div>
                </div>
            </div>
        `;

        const qrTarget = document.getElementById(qrId);
        if (qrTarget) {
            qrTarget.innerHTML = '';
            new QRCode(qrTarget, {
                text: String(data.nisn),
                width: 180,
                height: 180,
                colorDark: '#111827',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    };

    function patchedInitDateDefaults() {
        const start = document.getElementById('fStart');
        const end = document.getElementById('fEnd');
        if (!start || !end) return;
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const iso = `${yyyy}-${mm}-${dd}`;
        if (!start.value) start.value = iso;
        if (!end.value) end.value = iso;
    }

    function patchedInitRekapYear() {
        const select = document.getElementById('rekapTahun');
        if (!select || select.options.length > 0) return;
        const year = new Date().getFullYear();
        for (let y = year - 2; y <= year + 1; y++) {
            const opt = document.createElement('option');
            opt.value = String(y);
            opt.text = String(y);
            if (y === year) opt.selected = true;
            select.appendChild(opt);
        }
    }

    function patchedFillTodayLabels() {
        const todayText = new Date().toLocaleDateString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        patchedSetText('adminDateDisplay', todayText);
        patchedSetText('guruDashboardDate', todayText);
        patchedSetText('dashDate', todayText);
    }

    function bootstrapCurrentPage() {
        patchedFillTodayLabels();

        if (document.getElementById('tbody-siswa')) {
            loadDataSiswa();
        }

        if (document.getElementById('tbody-guru')) {
            loadDataGuru();
        }

        if (document.getElementById('tbody-monitoring')) {
            loadMonitoringAbsensi();
        }

        if (document.getElementById('fStart') && document.getElementById('fEnd') && typeof applyFilter === 'function') {
            patchedInitDateDefaults();
            applyFilter();
        }

        if (document.getElementById('rekapTahun')) {
            patchedInitRekapYear();
            const month = document.getElementById('rekapBulan');
            if (month && !month.value) month.value = String(new Date().getMonth());
            if (typeof loadDataRekapBulanan === 'function') {
                loadDataRekapBulanan();
            }
        }

        if (document.getElementById('tbody-libur')) {
            loadKelolaAbsen();
        }

        if (
            document.getElementById('view-admin-dashboard') ||
            document.getElementById('view-guru-dashboard') ||
            document.getElementById('view-siswa-dashboard')
        ) {
            loadDashboardData();
        }

        if (document.getElementById('kartuSiswaContainer')) {
            loadQRCodeSiswa();
        }
    }

    // ==========================================================================
    // 10. START APP
    // ==========================================================================
    checkSession();
</script>















