       @php
           $brandName = $appUiSettings['website_nama'] ?? 'E-ABSENSI';
           $brandSlogan = $appUiSettings['website_slogan'] ?? 'Smart Attendance system';
           $brandLogoUrl = $appUiSettings['website_logo_url'] ?? null;
           $sidebarUser = auth()->user();
           $sidebarAvatarUrl = null;
           if ($sidebarUser && !empty($sidebarUser->avatar_path)) {
               try {
                   $sidebarAvatarUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($sidebarUser->avatar_path);
               } catch (\Throwable $e) {
                   $sidebarAvatarUrl = null;
               }
           }
           $sidebarIzinSakitPendingCount = (int) ($sidebarIzinSakitPendingCount ?? 0);
       @endphp
       <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 text-white transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out flex flex-col h-full shadow-2xl border-r border-white/15" style="background: linear-gradient(180deg, #4967d9 0%, #5b63d6 38%, #6b5fce 70%, #7a58c8 100%);">
          
          <div id="sidebarHeader" class="h-16 flex items-center justify-start px-6 border-b border-white/15 overflow-hidden relative transition-all duration-300 mb-2">
              <div class="absolute top-0 left-0 w-full h-full bg-white/5 pointer-events-none"></div>
              <div class="flex items-center space-x-3 relative z-10 w-full">
                  <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-900/50 shrink-0 transition-all duration-300">
                      <img id="sidebarBrandLogoImg" src="{{ $brandLogoUrl ?? '' }}" alt="Logo" class="w-full h-full object-cover rounded-lg {{ empty($brandLogoUrl) ? 'hidden' : '' }}">
                      <i id="sidebarBrandLogoIcon" class="fas fa-qrcode text-sm {{ !empty($brandLogoUrl) ? 'hidden' : '' }}"></i>
                  </div>
                  <div class="sidebar-label transition-opacity duration-300 whitespace-nowrap">
                      <h1 id="sidebarBrandName" class="font-bold text-base tracking-wide text-white">{{ $brandName }}</h1>
                      <p id="sidebarBrandSlogan" class="text-[9px] text-indigo-100 uppercase tracking-wider font-semibold">{{ $brandSlogan }}</p>
                  </div>
              </div>
          </div>
     
          <nav id="sidebarMenu" class="flex-1 overflow-y-auto overflow-x-hidden px-4 pt-3 space-y-3 pb-5 scrollbar-hide text-sm">
              @auth
                  @can('dashboard.view')
                      <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-home w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Dashboard</span>
                      </a>
                  @endcan

                  @can('scanner.use')
                      <a href="{{ route('scanner') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('scanner') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-qrcode w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Scan Absensi</span>
                      </a>
                  @endcan

                  @can('monitoring.view')
                      <a href="{{ route('monitoring') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('monitoring') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-eye w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Monitoring</span>
                      </a>
                  @endcan

                  @if (auth()->user()?->can('siswa.view') || auth()->user()?->can('guru.view') || auth()->user()?->can('piket.view') || auth()->user()?->can('settings.users.manage'))
                      <details data-user-management {{ request()->routeIs('data-siswa') || request()->routeIs('data-guru') || request()->routeIs('data-piket') || request()->routeIs('role-permission.users.*') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-users w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Kelola Member</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              @can('siswa.view')
                                  <a href="{{ route('data-siswa') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('data-siswa') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-person-digging w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Data Karyawan</span>
                                  </a>
                              @endcan
                              @can('guru.view')
                                  <a href="{{ route('data-guru') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('data-guru') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-helmet-safety w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Data Mandor</span>
                                  </a>
                              @endcan
                              @can('piket.view')
                                  {{-- <a href="{{ route('data-piket') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('data-piket') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-user-clock w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Data Piket</span>
                                  </a> --}}
                                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                                    <i class="fas fa-circle-info w-4 text-center"></i>
                                    <span class="sidebar-label text-[15px]">
                                        (Data Piket) Menu Ini Dinonaktifkan
                                    </span>
                                </div>
                              @endcan
                              @can('settings.users.manage')
                                  <a href="{{ route('role-permission.users.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('role-permission.users.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-user-cog w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Data User</span>
                                  </a>
                              @endcan
                          </div>
                      </details>
                  @endif

                  @if (auth()->user()?->can('kelas.manage') || auth()->user()?->can('kenaikan-kelas.manage'))
                      <details data-akademik-menu {{ request()->routeIs('kelola-kelas') || request()->routeIs('kenaikan-kelas') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-school w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Akademik</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              @can('kelas.manage')
                                  <a href="{{ route('kelola-kelas') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('kelola-kelas') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-school w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Kelola Kelas</span>
                                  </a>
                              @endcan
                              @can('kenaikan-kelas.manage')
                                  {{-- <a href="{{ route('kenaikan-kelas') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('kenaikan-kelas') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-level-up-alt w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Kenaikan Kelas</span>
                                  </a> --}}
                                  <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                                    <i class="fas fa-circle-info w-4 text-center"></i>
                                    <span class="sidebar-label text-[15px]">
                                        (Kenaikan Kelas) Menu Ini Dinonaktifkan
                                    </span>
                                </div>
                              @endcan
                          </div>
                      </details>
                  @endif

                  @if (auth()->user()?->can('jadwal-pelajaran.manage') || auth()->user()?->can('jurnal-mengajar.manage') || auth()->user()?->can('scanner.use'))
                      {{-- <details data-pembelajaran-menu {{ request()->routeIs('jadwal-pelajaran.*') || request()->routeIs('jurnal-mengajar.*') || request()->routeIs('absensi-pelajaran') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-book-reader w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Pembelajaran</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              @can('jadwal-pelajaran.manage')
                                  <a href="{{ route('jadwal-pelajaran.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('jadwal-pelajaran.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-calendar-alt w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Jadwal Pelajaran</span>
                                  </a>
                              @endcan
                              @can('jurnal-mengajar.manage')
                                  <a href="{{ route('jurnal-mengajar.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('jurnal-mengajar.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-book-open w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Jurnal Mengajar</span>
                                  </a>
                              @endcan
                              @can('scanner.use')
                                  <a href="{{ route('absensi-pelajaran') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('absensi-pelajaran') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-book-open w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Absensi Pelajaran</span>
                                  </a>
                              @endcan
                          </div>
                      </details> --}}

                       <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                        <i class="fas fa-circle-info w-4 text-center"></i>
                        <span class="sidebar-label text-[15px]">
                            (Pembelajaran) Menu Ini Dinonaktifkan
                        </span>
                    </div>
                  @endif
                  @if (auth()->user()?->can('poin-pelanggaran.view') || auth()->user()?->can('poin-pelanggaran.manage'))
                      <details data-pelanggaran-siswa {{ request()->routeIs('poin-pelanggaran.*') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-exclamation-triangle w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Pelanggaran Siswa</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              <a href="{{ route('poin-pelanggaran.master.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('poin-pelanggaran.master.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                  <i class="fas fa-list-check w-4 text-center"></i>
                                  <span class="sidebar-label text-[14px] font-medium">Jenis Pelanggaran</span>
                              </a>
                              <a href="{{ route('poin-pelanggaran.riwayat.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('poin-pelanggaran.riwayat.index') || request()->routeIs('poin-pelanggaran.riwayat.store') || request()->routeIs('poin-pelanggaran.riwayat.update') || request()->routeIs('poin-pelanggaran.riwayat.destroy') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                  <i class="fas fa-clipboard-list w-4 text-center"></i>
                                  <span class="sidebar-label text-[14px] font-medium">Riwayat Pelanggaran</span>
                              </a>
                          </div>
                      </details>
                  @endif
                  @if (auth()->user()?->can('izin-sakit.request') || auth()->user()?->can('izin-sakit.approve') || auth()->user()?->can('izin-sakit.manage'))
                      <a href="{{ route('izin-sakit.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('izin-sakit.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-notes-medical w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold flex-1">Izin / Sakit</span>
                          @if ($sidebarIzinSakitPendingCount > 0)
                              <span class="sidebar-label inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-amber-300 text-amber-900 text-[10px] font-bold">
                                  {{ $sidebarIzinSakitPendingCount > 99 ? '99+' : $sidebarIzinSakitPendingCount }}
                              </span>
                          @endif
                      </a>
                  @endif
                  @if (auth()->user()?->can('tabungan-siswa.view') || auth()->user()?->can('tabungan-siswa.manage') || auth()->user()?->can('tabungan-siswa.report') || auth()->user()?->can('tabungan-siswa.jenis.manage'))
                      {{-- <details data-tabungan-siswa-menu {{ request()->routeIs('tabungan-siswa.*') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-piggy-bank w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Tabungan Siswa</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              <a href="{{ route('tabungan-siswa.jenis.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('tabungan-siswa.jenis.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                  <i class="fas fa-list-check w-4 text-center"></i>
                                  <span class="sidebar-label text-[14px] font-medium">Jenis Tabungan</span>
                              </a>
                              <a href="{{ route('tabungan-siswa.rekening.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('tabungan-siswa.rekening.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                  <i class="fas fa-wallet w-4 text-center"></i>
                                  <span class="sidebar-label text-[14px] font-medium">Rekening Tabungan</span>
                              </a>
                          </div>
                      </details> --}}
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                        <i class="fas fa-circle-info w-4 text-center"></i>
                        <span class="sidebar-label text-[15px]">
                            (Tabungan Siswa) Menu Ini Dinonaktifkan
                        </span>
                    </div>
                  @endif
                  @can('absen.manage')
                      {{-- <a href="{{ route('kelola-absen') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('kelola-absen') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-calendar-times w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Jadwal Libur</span>
                      </a> --}}
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                        <i class="fas fa-circle-info w-4 text-center"></i>
                        <span class="sidebar-label text-[15px]">
                            (Jadwal Libur) Menu Ini Dinonaktifkan
                        </span>
                    </div>
                  @endcan

                  @can('kartu-absensi.manage')
                      <a href="{{ route('kartu-absensi.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('kartu-absensi.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-id-card-alt w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Kartu Absensi</span>
                      </a>
                  @endcan

                  @can('notifications.send')
                      {{-- <a href="{{ route('notifications.send.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('notifications.send.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-paper-plane w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Kirim Notifikasi</span>
                      </a> --}}
                      <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                        <i class="fas fa-circle-info w-4 text-center"></i>
                        <span class="sidebar-label text-[15px]">
                            (Kirim Notifikasi) Menu Ini Dinonaktifkan
                        </span>
                    </div>
                  @endcan

                  @can('arsip.manage')

                      {{-- <a href="{{ route('arsip.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('arsip.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-box-archive w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Daftar Arsip</span>
                      </a> --}}

                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                        <i class="fas fa-circle-info w-4 text-center"></i>
                        <span class="sidebar-label text-[15px]">
                            (Daftar Arsip) Menu Ini Dinonaktifkan
                        </span>
                    </div>
                  @endcan

                  @if (auth()->user()?->hasRole('siswa') && auth()->user()?->can('kartu-siswa.view'))

                      {{-- <a href="{{ route('mata-pelajaran-saya') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('mata-pelajaran-saya') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-book w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Mata Pelajaran</span>
                      </a> --}}

                      <a href="{{ route('presensi-saya') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('presensi-saya') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-chart-pie w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Presensi Saya</span>
                      </a>
                      @can('tabungan-siswa.self.view')

                          {{-- <a href="{{ route('tabungan-saya') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('tabungan-saya') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                              <i class="fas fa-piggy-bank w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold">Tabungan Saya</span>
                          </a> --}}

                      @endcan
                      <a href="{{ route('kartu-siswa') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ request()->routeIs('kartu-siswa') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                          <i class="fas fa-id-card w-4 text-center"></i>
                          <span class="sidebar-label text-[15px] font-semibold">Kartu Saya</span>
                      </a>
                  @endif

                  @if (auth()->user()?->can('rekap-absensi.view') || auth()->user()?->can('rekap-absensi-pelajaran.view') || auth()->user()?->can('rekap-bulanan.view') || auth()->user()?->can('rekap-tahunan.view'))
                      <details data-rekap-absensi {{ request()->routeIs('rekap-absensi') || request()->routeIs('rekap-absensi-pelajaran') || request()->routeIs('rekap-bulanan') || request()->routeIs('rekap-tahunan') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-calendar-check w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Rekap Absensi</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              @can('rekap-absensi.view')
                                  <a href="{{ route('rekap-absensi') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('rekap-absensi') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-clipboard-list w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Laporan Absensi</span>
                                  </a>
                              @endcan
                              @can('rekap-absensi-pelajaran.view')
                                  {{-- <a href="{{ route('rekap-absensi-pelajaran') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('rekap-absensi-pelajaran') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-book-reader w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Absensi Pelajaran</span>
                                  </a> --}}
                                  <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                                    <i class="fas fa-circle-info w-4 text-center"></i>
                                    <span class="sidebar-label text-[15px]">
                                        (Absensi Pelajaran) Menu Ini Dinonaktifkan
                                    </span>
                                </div>
                              @endcan
                              @can('rekap-bulanan.view')
                                  <a href="{{ route('rekap-bulanan') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('rekap-bulanan') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-calendar-alt w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Rekap Bulanan</span>
                                  </a>
                              @endcan
                              @can('rekap-tahunan.view')
                                  <a href="{{ route('rekap-tahunan') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('rekap-tahunan') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-calendar-week w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Rekap Tahunan</span>
                                  </a>
                              @endcan
                          </div>
                      </details>
                  @endif

                  @if (auth()->user()?->can('settings.roles.manage') || auth()->user()?->can('settings.general.manage') || auth()->user()?->can('settings.devices.manage') || auth()->user()?->can('settings.notifications.manage') || auth()->user()?->can('settings.backup.manage') || auth()->user()?->can('settings.update.manage'))
                      <details data-superadmin-settings {{ request()->routeIs('role-permission.index') || request()->routeIs('settings.general.*') || request()->routeIs('settings.devices.*') || request()->routeIs('settings.notifications.*') || request()->routeIs('settings.backup.*') || request()->routeIs('settings.update.*') ? 'open' : '' }}>
                          <summary class="list-none flex items-center gap-3 px-3 py-2.5 rounded-lg transition text-gray-300 hover:bg-white/10 hover:text-white cursor-pointer">
                              <i class="fas fa-cog w-4 text-center"></i>
                              <span class="sidebar-label text-[15px] font-semibold flex-1">Pengaturan</span>
                              <i class="fas fa-chevron-right settings-chevron sidebar-label text-[11px] opacity-80"></i>
                          </summary>
                          <div class="mt-2 pl-2 space-y-2">
                              @can('settings.general.manage')
                                  <a href="{{ route('settings.general.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('settings.general.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-sliders-h w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Umum</span>
                                  </a>
                              @endcan
                              @can('settings.devices.manage')
                                  <a href="{{ route('settings.devices.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('settings.devices.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-microchip w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Devices</span>
                                  </a>
                              @endcan
                              @can('settings.notifications.manage')
                                  {{-- <a href="{{ route('settings.notifications.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('settings.notifications.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fab fa-whatsapp w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Notifikasi</span>
                                  </a> --}}

                                <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 italic">
                                    <i class="fas fa-circle-info w-4 text-center"></i>
                                    <span class="sidebar-label text-[15px]">
                                        (Notifikasi) Menu Ini Dinonaktifkan
                                    </span>
                                </div>
                              @endcan
                              @can('settings.backup.manage')
                                  <a href="{{ route('settings.backup.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('settings.backup.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-database w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Backup & Restore</span>
                                  </a>
                              @endcan
                              @can('settings.update.manage')
                                  <a href="{{ route('settings.update.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('settings.update.*') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-download w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Update</span>
                                  </a>
                              @endcan
                              @can('settings.roles.manage')
                                  <a href="{{ route('role-permission.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg transition {{ request()->routeIs('role-permission.index') ? 'bg-white/15 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                                      <i class="fas fa-user-shield w-4 text-center"></i>
                                      <span class="sidebar-label text-[14px] font-medium">Role & Permission</span>
                                  </a>
                              @endcan
                          </div>
                      </details>
                  @endif
              @endauth
          </nav>
          
          <div class="p-4 border-t border-white/15 bg-black/10">
              <div id="userProfileCard" class="flex items-center space-x-3 p-3 bg-black/20 rounded-xl border border-white/10 transition-all duration-300 overflow-hidden">
                  <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 shadow-inner shrink-0 overflow-hidden relative">
                      <img id="navUserAvatarImg" src="{{ $sidebarAvatarUrl ?? '' }}" alt="Avatar" class="w-full h-full object-cover {{ !empty($sidebarAvatarUrl) ? '' : 'hidden' }}">
                      <div id="navUserInitial" class="absolute inset-0 flex items-center justify-center font-bold text-sm text-white {{ !empty($sidebarAvatarUrl) ? 'hidden' : '' }}">U</div>
                  </div>
                  <div id="userInfo" class="sidebar-label transition-opacity duration-300 whitespace-nowrap">
                      <p id="navUserName" class="font-semibold text-xs truncate text-white max-w-[120px]">User</p>
                      <span id="navUserRole" class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-indigo-800 text-indigo-100 border border-indigo-700 tracking-wider">Role</span>
                  </div>
              </div>
          </div>
      </aside>
