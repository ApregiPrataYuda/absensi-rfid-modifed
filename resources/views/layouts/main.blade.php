<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') - {{ $appUiSettings['website_nama'] ?? 'Sistem Absensi Pintar' }}</title>
  @if (!empty($appUiSettings['website_favicon_url']))
    <link rel="icon" type="image/png" href="{{ $appUiSettings['website_favicon_url'] }}">
  @endif
  
  @php
    $isAppPage = auth()->check();
  @endphp

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  @if ($isAppPage)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  @endif
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <script>
        @if ($isAppPage)
        window.APP_ROUTES = {
            dashboard: "{{ route('dashboard') }}",
            dataSiswa: "{{ route('data-siswa') }}",
            dataSiswaCardCapture: "{{ route('data-siswa.card-capture-stream') }}",
            dataGuru: "{{ route('data-guru') }}",
            dataPiket: "{{ route('data-piket') }}",
            kelolaAbsen: "{{ route('kelola-absen') }}",
            kelolaKelas: "{{ route('kelola-kelas') }}",
            kelolaKelasData: "{{ route('kelola-kelas.data') }}",
            kelolaKelasStore: "{{ route('kelola-kelas.store') }}",
            kelolaKelasUpdate: "{{ route('kelola-kelas.update', ['kelas' => '__ID__']) }}",
            kelolaKelasDestroy: "{{ route('kelola-kelas.destroy', ['kelas' => '__ID__']) }}",
            jadwalPelajaran: "{{ route('jadwal-pelajaran.index') }}",
            jadwalPelajaranData: "{{ route('jadwal-pelajaran.data') }}",
            jadwalPelajaranStore: "{{ route('jadwal-pelajaran.store') }}",
            jadwalPelajaranUpdate: "{{ route('jadwal-pelajaran.update', ['jadwalPelajaran' => '__ID__']) }}",
            jadwalPelajaranDestroy: "{{ route('jadwal-pelajaran.destroy', ['jadwalPelajaran' => '__ID__']) }}",
            jurnalMengajar: "{{ route('jurnal-mengajar.index') }}",
            jurnalMengajarData: "{{ route('jurnal-mengajar.data') }}",
            jurnalMengajarStore: "{{ route('jurnal-mengajar.store') }}",
            jurnalMengajarUpdate: "{{ route('jurnal-mengajar.update', ['jurnalMengajar' => '__ID__']) }}",
            jurnalMengajarDestroy: "{{ route('jurnal-mengajar.destroy', ['jurnalMengajar' => '__ID__']) }}",
            izinSakit: "{{ route('izin-sakit.index') }}",
            izinSakitData: "{{ route('izin-sakit.data') }}",
            izinSakitStore: "{{ route('izin-sakit.store') }}",
            izinSakitApprove: "{{ route('izin-sakit.approve', ['izinSakitRequest' => '__ID__']) }}",
            izinSakitReject: "{{ route('izin-sakit.reject', ['izinSakitRequest' => '__ID__']) }}",
            izinSakitDestroy: "{{ route('izin-sakit.destroy', ['izinSakitRequest' => '__ID__']) }}",
            poinPelanggaran: "{{ route('poin-pelanggaran.index') }}",
            poinPelanggaranData: "{{ route('poin-pelanggaran.data') }}",
            poinPelanggaranJenisStore: "{{ route('poin-pelanggaran.jenis.store') }}",
            poinPelanggaranJenisUpdate: "{{ route('poin-pelanggaran.jenis.update', ['jenisPelanggaran' => '__ID__']) }}",
            poinPelanggaranJenisDestroy: "{{ route('poin-pelanggaran.jenis.destroy', ['jenisPelanggaran' => '__ID__']) }}",
            poinPelanggaranRiwayatStore: "{{ route('poin-pelanggaran.riwayat.store') }}",
            poinPelanggaranRiwayatUpdate: "{{ route('poin-pelanggaran.riwayat.update', ['poinPelanggaran' => '__ID__']) }}",
            poinPelanggaranRiwayatDestroy: "{{ route('poin-pelanggaran.riwayat.destroy', ['poinPelanggaran' => '__ID__']) }}",
            monitoring: "{{ route('monitoring') }}",
            rekapBulanan: "{{ route('rekap-bulanan') }}",
            rekapTahunan: "{{ route('rekap-tahunan') }}",
            kenaikanKelas: "{{ route('kenaikan-kelas') }}",
            arsip: "{{ route('arsip.index') }}",
            rekapAbsensi: "{{ route('rekap-absensi') }}",
            rekapAbsensiPelajaran: "{{ route('rekap-absensi-pelajaran') }}",
            scanner: "{{ route('scanner') }}",
            absensiPelajaran: "{{ route('absensi-pelajaran') }}",
            tabunganSiswa: "{{ route('tabungan-siswa.index') }}",
            tabunganSiswaJenis: "{{ route('tabungan-siswa.jenis.index') }}",
            tabunganSiswaJenisData: "{{ route('tabungan-siswa.jenis.data') }}",
            tabunganSiswaJenisStore: "{{ route('tabungan-siswa.jenis.store') }}",
            tabunganSiswaJenisUpdate: "{{ route('tabungan-siswa.jenis.update', ['jenisTabungan' => '__ID__']) }}",
            tabunganSiswaJenisDestroy: "{{ route('tabungan-siswa.jenis.destroy', ['jenisTabungan' => '__ID__']) }}",
            tabunganSiswaRekening: "{{ route('tabungan-siswa.rekening.index') }}",
            tabunganSiswaRekeningData: "{{ route('tabungan-siswa.rekening.data') }}",
            tabunganSiswaRekeningStore: "{{ route('tabungan-siswa.rekening.store') }}",
            tabunganSiswaRekeningUpdate: "{{ route('tabungan-siswa.rekening.update', ['account' => '__ID__']) }}",
            tabunganSiswaRekeningDestroy: "{{ route('tabungan-siswa.rekening.destroy', ['account' => '__ID__']) }}",
            tabunganSiswaRekeningRiwayat: "{{ route('tabungan-siswa.rekening.riwayat', ['account' => '__ID__']) }}",
            tabunganSiswaRekeningStatement: "{{ route('tabungan-siswa.rekening.statement', ['account' => '__ID__']) }}",
            tabunganSiswaTransaksi: "{{ route('tabungan-siswa.rekening.index') }}",
            tabunganSiswaTransaksiData: "{{ route('tabungan-siswa.transaksi.data') }}",
            tabunganSiswaTransaksiStore: "{{ route('tabungan-siswa.transaksi.store') }}",
            tabunganSiswaTransaksiUpdate: "{{ route('tabungan-siswa.transaksi.update', ['transaction' => '__ID__']) }}",
            tabunganSiswaTransaksiDestroy: "{{ route('tabungan-siswa.transaksi.destroy', ['transaction' => '__ID__']) }}",
            tabunganSiswaTransaksiPrint: "{{ route('tabungan-siswa.transaksi.print', ['transaction' => '__ID__']) }}",
            tabunganSaya: "{{ route('tabungan-saya') }}",
            kartuSiswa: "{{ route('kartu-siswa') }}",
            mataPelajaranSaya: "{{ route('mata-pelajaran-saya') }}",
            presensiSaya: "{{ route('presensi-saya') }}",
            rolePermission: "{{ route('role-permission.index') }}",
            userManagement: "{{ route('role-permission.users.index') }}",
            settingsGeneral: "{{ route('settings.general.index') }}",
            settingsDevices: "{{ route('settings.devices.index') }}",
            settingsNotifications: "{{ route('settings.notifications.index') }}",
            settingsProfile: "{{ route('settings.profile.index') }}"
        };
        window.APP_LOGOUT_URL = "{{ route('logout') }}";
        @else
        window.APP_ROUTES = {};
        window.APP_LOGOUT_URL = null;
        @endif

        @php
            $appCurrentUser = null;
            if ($isAppPage) {
                $authUser = auth()->user();
                $authUserRole = strtolower((string) ($authUser->getRoleNames()->first() ?? ''));
                $authUserClientRole = $authUserRole === 'super-admin' ? 'admin' : $authUserRole;
                $authUserPermissions = [];
                $authUserAvatarUrl = null;
                try {
                    $authUserPermissions = $authUser->getAllPermissions()->pluck('name')->values()->all();
                } catch (\Throwable $e) {
                    $authUserPermissions = [];
                }
                try {
                    if (!empty($authUser->avatar_path)) {
                        $authUserAvatarUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($authUser->avatar_path);
                    }
                } catch (\Throwable $e) {
                    $authUserAvatarUrl = null;
                }
                $activeToken = \App\Models\AuthToken::query()
                    ->where('user_id', $authUser->id)
                    ->where('expires_at', '>', now())
                    ->orderByDesc('created_at')
                    ->value('token');

                if (!$activeToken) {
                    $activeToken = (string) \Illuminate\Support\Str::uuid();
                    \App\Models\AuthToken::query()->create([
                        'token' => $activeToken,
                        'user_id' => $authUser->id,
                        'siswa_id' => null,
                        'role' => $authUserClientRole,
                        'expires_at' => now()->addDay(),
                        'created_at' => now(),
                    ]);
                }

                $appCurrentUser = [
                    'name' => $authUser->name,
                    'nama' => $authUser->name,
                    'username' => $authUser->username,
                    'nisn' => $authUser->username,
                    'role' => $authUserClientRole,
                    'raw_role' => $authUserRole,
                    'permissions' => $authUserPermissions,
                    'kelas' => $authUser->kelas,
                    'avatar_url' => $authUserAvatarUrl,
                    'token' => $activeToken,
                ];
            }
        @endphp
        @php
            $ajaxActionEndpoints = [];
            if ($isAppPage) {
                foreach (config('ajax-actions.method_routes', []) as $actionMethod => $routeName) {
                    $ajaxActionEndpoints[$actionMethod] = route($routeName);
                }
            }
        @endphp
        window.APP_CURRENT_USER = {!! json_encode($appCurrentUser) !!};
        window.APP_TIMEZONE = @json($appUiSettings['website_timezone'] ?? config('app.timezone', 'Asia/Jakarta'));
        window.APP_TIMEZONE_LABEL = @json($appUiSettings['website_timezone_label'] ?? 'WIB (UTC+07:00)');
        window.APP_WEBSITE_NAME = @json($appUiSettings['website_nama'] ?? 'ABSENSINDO');
        window.APP_API_BASE = @json(rtrim(request()->getBaseUrl(), '/') . '/api');
        window.APP_AJAX_ACTIONS = {!! json_encode($ajaxActionEndpoints) !!};

    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Inter', 'sans-serif'] },
          colors: {
            primary: '#738862', // Indigo 600
            secondary: '#10B981', // Emerald 500
            dark: '#111827', // Gray 900
          }
        }
      }
    }
  </script>

  <style>
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f1f1f1; }
    ::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

    /* Hide sidebar menu scrollbar/track (white line) */
    #sidebarMenu {
      -ms-overflow-style: none; /* IE and Edge */
      scrollbar-width: none; /* Firefox */
    }
    #sidebarMenu::-webkit-scrollbar {
      width: 0;
      height: 0;
      background: transparent;
    }

    /* Force all sidebar text states to white */
    #sidebar .text-gray-300,
    #sidebar .text-indigo-100,
    #sidebar .text-indigo-200 {
      color: #ffffff !important;
    }

    /* Animations */
    .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    /* Glass Effect */
    .glass {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    
    /* Table Styles */
    .table-header { @apply px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50 sticky top-0; }
    .table-cell { @apply px-6 py-4 whitespace-nowrap text-sm text-gray-700 border-b border-gray-100; }

    /* Scan Live Highlight */
    @keyframes scanPulse {
      0%   { background-color: #fef9c3; box-shadow: inset 0 0 0 2px #facc15; }
      50%  { background-color: #fef08a; box-shadow: inset 0 0 0 3px #f59e0b; }
      100% { background-color: #fef9c3; box-shadow: inset 0 0 0 2px #facc15; }
    }
    .scan-row-highlight {
      animation: scanPulse 0.5s ease 3;
      background-color: #fef9c3 !important;
    }
    tr.scan-row-done { background-color: transparent; transition: background-color 1.5s ease; }

    /* Sidebar settings chevron animation */
    details[data-superadmin-settings] > summary .settings-chevron,
    details[data-user-management] > summary .settings-chevron,
    details[data-rekap-absensi] > summary .settings-chevron,
    details[data-pelanggaran-siswa] > summary .settings-chevron,
    details[data-akademik-menu] > summary .settings-chevron,
    details[data-pembelajaran-menu] > summary .settings-chevron,
    details[data-tabungan-siswa-menu] > summary .settings-chevron {
      transition: transform 0.2s ease;
      transform-origin: center;
    }
    details[data-superadmin-settings][open] > summary .settings-chevron,
    details[data-user-management][open] > summary .settings-chevron,
    details[data-rekap-absensi][open] > summary .settings-chevron,
    details[data-pelanggaran-siswa][open] > summary .settings-chevron,
    details[data-akademik-menu][open] > summary .settings-chevron,
    details[data-pembelajaran-menu][open] > summary .settings-chevron,
    details[data-tabungan-siswa-menu][open] > summary .settings-chevron {
      transform: rotate(90deg);
    }

    /* SweetAlert2 polish */
    .swal2-popup {
      border-radius: 16px !important;
      padding: 1rem 1.1rem 1.2rem !important;
      box-shadow: 0 20px 50px rgba(2, 6, 23, 0.22) !important;
    }
    .swal2-title {
      font-size: 1.05rem !important;
      font-weight: 700 !important;
      color: #1f2937 !important;
      margin-top: 0.2rem !important;
    }
    .swal2-html-container {
      margin-top: 0.45rem !important;
      padding: 0 !important;
      font-size: 0.875rem !important;
      color: #374151 !important;
    }
    .swal2-actions {
      margin-top: 0.8rem !important;
      gap: 0.5rem !important;
    }
    .swal2-styled.swal2-confirm {
      border-radius: 10px !important;
      font-size: 0.8rem !important;
      font-weight: 700 !important;
      padding: 0.55rem 0.95rem !important;
      box-shadow: none !important;
    }
    .swal2-styled.swal2-cancel {
      border-radius: 10px !important;
      font-size: 0.8rem !important;
      font-weight: 700 !important;
      padding: 0.55rem 0.95rem !important;
      box-shadow: none !important;
      background: #eef2ff !important;
      color: #4338ca !important;
    }
    .swal2-input {
      border-radius: 10px !important;
      border: 1px solid #d1d5db !important;
      box-shadow: none !important;
      font-size: 0.85rem !important;
      margin: 0.65rem auto 0 !important;
      padding: 0.6rem 0.75rem !important;
      width: calc(100% - 1.1rem) !important;
    }
    .swal2-input:focus {
      border-color: #6366f1 !important;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18) !important;
    }
    .swal2-popup.swal-scan-card-popup {
      border-radius: 8px !important;
      padding: 1rem 1rem 1.1rem !important;
      width: 24rem !important;
    }
    .swal2-popup.swal-scan-card-popup .swal2-title {
      font-size: 1rem !important;
      margin-top: 0 !important;
    }
    .swal2-popup.swal-scan-card-popup .swal2-html-container {
      font-size: 0.8rem !important;
      margin-top: 0.35rem !important;
    }
    .swal2-popup.swal-scan-card-popup .swal2-actions {
      margin-top: 0.85rem !important;
      margin-bottom: 0 !important;
    }
    .swal2-popup.swal-scan-card-popup .swal2-loader {
      margin: 0 auto !important;
    }
    .swal2-close.swal-scan-card-close {
      width: 1.3rem !important;
      height: 1.3rem !important;
      min-width: 1.3rem !important;
      font-size: 0.72rem !important;
      line-height: 1 !important;
      color: #9ca3af !important;
      top: 0.52rem !important;
      right: 0.52rem !important;
      padding: 0 !important;
      border-radius: 5px !important;
      transition: background-color 0.15s ease, color 0.15s ease !important;
    }
    .swal2-close.swal-scan-card-close:hover {
      background: #f3f4f6 !important;
      color: #4b5563 !important;
    }
    .archive-preview-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.5rem;
      margin-top: 0.45rem;
    }
    .archive-preview-card {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #f9fafb;
      padding: 0.55rem 0.65rem;
      text-align: left;
    }
    .archive-preview-card .label {
      display: block;
      font-size: 0.7rem;
      color: #6b7280;
      margin-bottom: 0.2rem;
      font-weight: 600;
    }
    .archive-preview-card .value {
      font-size: 0.88rem;
      color: #111827;
      font-weight: 700;
      line-height: 1.1rem;
    }
    .archive-preview-warning {
      margin-top: 0.6rem;
      border: 1px solid #fde68a;
      background: #fffbeb;
      color: #92400e;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.55rem 0.65rem;
    }
  </style>
</head>
<body class="m-0 min-h-full overflow-x-hidden bg-[#F3F4F6] font-sans antialiased text-slate-800 selection:bg-indigo-500 selection:text-white">
  @yield('body')
  <script>
    (function () {
      const STORAGE_KEY = 'absensindo_sidebar_collapsed';

      function getElements() {
        return {
          sidebar: document.getElementById('sidebar'),
          mainContent: document.getElementById('mainContent'),
          mobileOverlay: document.getElementById('mobileOverlay'),
          sidebarHeader: document.getElementById('sidebarHeader'),
          userProfileCard: document.getElementById('userProfileCard'),
        };
      }

      function getLabelNodes(sidebar) {
        return sidebar ? sidebar.querySelectorAll('.sidebar-label') : [];
      }

      function getMenuLinks(sidebar) {
        return sidebar ? sidebar.querySelectorAll('#sidebarMenu a') : [];
      }

      function openMobile() {
        const { sidebar, mobileOverlay } = getElements();
        if (!sidebar || !mobileOverlay) return;
        sidebar.classList.remove('-translate-x-full');
        mobileOverlay.classList.remove('hidden');
        requestAnimationFrame(() => mobileOverlay.classList.remove('opacity-0'));
      }

      function closeMobile() {
        const { sidebar, mobileOverlay } = getElements();
        if (!sidebar || !mobileOverlay) return;
        sidebar.classList.add('-translate-x-full');
        mobileOverlay.classList.add('opacity-0');
        setTimeout(() => mobileOverlay.classList.add('hidden'), 250);
      }

      function applyDesktopState(collapsed) {
        const { sidebar, mainContent, sidebarHeader, userProfileCard, mobileOverlay } = getElements();
        if (!sidebar || !mainContent) return;

        const labels = getLabelNodes(sidebar);
        const links = getMenuLinks(sidebar);

        sidebar.dataset.collapsed = collapsed ? '1' : '0';
        sidebar.style.width = collapsed ? '5rem' : '16rem';
        mainContent.style.marginLeft = collapsed ? '5rem' : '16rem';

        if (sidebarHeader) {
          sidebarHeader.classList.toggle('justify-center', collapsed);
          sidebarHeader.classList.toggle('justify-start', !collapsed);
        }

        if (userProfileCard) {
          userProfileCard.classList.toggle('justify-center', collapsed);
        }

        labels.forEach((el) => el.classList.toggle('hidden', collapsed));
        links.forEach((a) => a.classList.toggle('justify-center', collapsed));

        if (mobileOverlay) {
          mobileOverlay.classList.add('hidden', 'opacity-0');
        }
      }

      function resetDesktopState() {
        const { sidebar, mainContent, sidebarHeader, userProfileCard } = getElements();
        if (!sidebar || !mainContent) return;

        sidebar.dataset.collapsed = '0';
        sidebar.style.width = '';
        mainContent.style.marginLeft = '';

        if (sidebarHeader) {
          sidebarHeader.classList.remove('justify-center');
          sidebarHeader.classList.add('justify-start');
        }

        if (userProfileCard) {
          userProfileCard.classList.remove('justify-center');
        }

        const labels = getLabelNodes(sidebar);
        const links = getMenuLinks(sidebar);
        labels.forEach((el) => el.classList.remove('hidden'));
        links.forEach((a) => a.classList.remove('justify-center'));
      }

      function isDesktopCollapsed() {
        const { sidebar } = getElements();
        return !!(sidebar && sidebar.dataset.collapsed === '1');
      }

      function handleResize() {
        if (window.innerWidth < 768) {
          resetDesktopState();
          closeMobile();
          return;
        }

        const collapsed = localStorage.getItem(STORAGE_KEY) === '1';
        applyDesktopState(collapsed);
      }

      window.toggleSidebar = function () {
        if (window.innerWidth < 768) {
          const { sidebar } = getElements();
          if (!sidebar) return;
          const isOpen = !sidebar.classList.contains('-translate-x-full');
          if (isOpen) closeMobile();
          else openMobile();
          return;
        }

        const nextCollapsed = !isDesktopCollapsed();
        localStorage.setItem(STORAGE_KEY, nextCollapsed ? '1' : '0');
        applyDesktopState(nextCollapsed);
      };

      document.addEventListener('DOMContentLoaded', handleResize);
      window.addEventListener('resize', handleResize);
    })();
  </script>
  <script>
    (function () {
      function formatRoleLabel(rawRole) {
        const role = String(rawRole || '').toLowerCase().trim();
        if (!role) return 'USER';
        if (role === 'wakel') return 'Mandor';
        if (role === 'kepsek') return 'KEPALA SEKOLAH';
        if (role === 'wakasek') return 'WAKIL KEPALA SEKOLAH';
        if (role === 'bendahara') return 'BENDAHARA';
        if (role === 'siswa') return 'Karyawan';
        if (role === 'admin') return 'Admin';
        if (role === 'super-admin') return 'SUPER ADMIN';
        return role.toUpperCase();
      }

      function initSidebarProfile() {
        const user = window.APP_CURRENT_USER || null;
        if (!user) return;

        const name = String(user.nama || user.name || user.username || 'User').trim();
        const roleSource = String(user.raw_role || user.role || '').trim();

        const nameEl = document.getElementById('navUserName');
        const roleEl = document.getElementById('navUserRole');
        const initialEl = document.getElementById('navUserInitial');
        const sidebarAvatarImg = document.getElementById('navUserAvatarImg');
        const headerAvatarImg = document.getElementById('headerAvatarImg');
        const headerAvatarFallback = document.getElementById('headerAvatarFallback');
        const avatarUrl = String(user.avatar_url || '').trim();

        if (nameEl) nameEl.textContent = name || 'User';
        if (roleEl) roleEl.textContent = formatRoleLabel(roleSource);
        if (initialEl) {
          const initial = (name || 'U').charAt(0).toUpperCase();
          initialEl.textContent = initial;
          initialEl.classList.toggle('hidden', avatarUrl !== '');
        }
        if (sidebarAvatarImg) {
          if (avatarUrl !== '') {
            sidebarAvatarImg.src = avatarUrl;
            sidebarAvatarImg.classList.remove('hidden');
          } else {
            sidebarAvatarImg.src = '';
            sidebarAvatarImg.classList.add('hidden');
          }
        }
        if (headerAvatarImg) {
          if (avatarUrl !== '') {
            headerAvatarImg.src = avatarUrl;
            headerAvatarImg.classList.remove('hidden');
          } else {
            headerAvatarImg.src = '';
            headerAvatarImg.classList.add('hidden');
          }
        }
        if (headerAvatarFallback) {
          headerAvatarFallback.classList.toggle('hidden', avatarUrl !== '');
        }
      }

      document.addEventListener('DOMContentLoaded', initSidebarProfile);
    })();
  </script>
  <script>
    window.showAlert = window.showAlert || function (type, message) {
      const kind = String(type || 'info').toLowerCase();
      const palette = {
        success: { icon: 'fa-check-circle', bg: 'bg-emerald-600' },
        error: { icon: 'fa-exclamation-circle', bg: 'bg-rose-600' },
        warning: { icon: 'fa-exclamation-triangle', bg: 'bg-amber-500' },
        info: { icon: 'fa-info-circle', bg: 'bg-indigo-600' },
      };
      const cfg = palette[kind] || palette.info;
      const text = String(message || '');

      const escapeHtml = (value) => value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const toast = document.createElement('div');
      toast.className = `fixed top-5 right-5 z-[90] ${cfg.bg} text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm font-semibold opacity-0 translate-y-1 transition-all duration-200`;
      toast.innerHTML = `<i class="fas ${cfg.icon} text-base"></i><span>${escapeHtml(text)}</span>`;

      document.body.appendChild(toast);
      requestAnimationFrame(() => {
        toast.classList.remove('opacity-0', 'translate-y-1');
      });

      setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-1');
        setTimeout(() => toast.remove(), 220);
      }, 2600);
    };
  </script>
  @stack('scripts')
</body>
</html>
