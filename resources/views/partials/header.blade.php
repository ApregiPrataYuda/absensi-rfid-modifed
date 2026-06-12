          @php
              $headerUser = auth()->user();
              $headerName = trim((string) ($headerUser?->name ?: $headerUser?->username ?: 'User'));
              $headerUsername = trim((string) ($headerUser?->username ?: 'user'));
              $headerEmail = trim((string) ($headerUser?->email ?: ''));
              $headerAvatarUrl = null;
              if ($headerUser && !empty($headerUser->avatar_path)) {
                  try {
                      $headerAvatarUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($headerUser->avatar_path);
                  } catch (\Throwable $e) {
                      $headerAvatarUrl = null;
                  }
              }
              $headerTimezone = trim((string) ($appUiSettings['website_timezone'] ?? config('app.timezone', 'Asia/Jakarta')));
              $headerTimezoneLabels = [
                  'Asia/Jakarta' => 'WIB (UTC+07:00)',
                  'Asia/Makassar' => 'WITA (UTC+08:00)',
                  'Asia/Jayapura' => 'WIT (UTC+09:00)',
              ];
              $headerTimezoneLabel = (string) ($headerTimezoneLabels[$headerTimezone] ?? $headerTimezone);
              $headerCurrentDate = \Carbon\Carbon::now($headerTimezone)
                  ->locale('id')
                  ->translatedFormat('l, j F Y');
          @endphp
          <header class="h-16 bg-white/80 backdrop-blur-xl sticky top-0 z-20 flex items-center justify-between px-3 sm:px-4 md:px-6 border-b border-slate-200/70 shadow-sm supports-[backdrop-filter]:bg-white/60">
              <div class="flex items-center">
                  <button onclick="toggleSidebar()" class="mr-3 p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 rounded-lg transition-colors focus:outline-none"><i class="fas fa-bars text-lg"></i></button>
                <div>
                    <h2 id="pageTitle" 
                    class="text-base sm:text-lg font-bold text-slate-800 tracking-tight">
                    @yield('title', 'Dashboard')
                    </h2>
                </div>
              </div>
              <div class="flex items-center space-x-2 sm:space-x-4">
                  <div class="text-right hidden md:block">
                      <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Hari ini</p>
                      <p class="text-xs font-bold text-slate-700" id="currentDateDisplay">{{ $headerCurrentDate }}</p>
                      <div class="mt-0.5 flex items-center justify-end gap-2 whitespace-nowrap">
                          <span class="text-[11px] font-semibold text-slate-600" id="currentTimeDisplay">--:--:--</span>
                          <span class="text-[10px] text-slate-500" id="currentTimezoneLabel">{{ $headerTimezoneLabel }}</span>
                      </div>
                  </div>
                  <div class="hidden sm:flex w-8 h-8 rounded-full bg-white border border-slate-200 items-center justify-center text-slate-600 relative cursor-pointer hover:bg-slate-50 hover:border-slate-300 transition shadow-sm">
                      <i class="far fa-bell text-xs"></i>
                      <span class="absolute top-2 right-2 w-1.5 h-1.5 bg-rose-500 rounded-full ring-1 ring-white"></span>
                  </div>

                  <div id="headerProfileDropdownWrap" class="relative">
                      <button id="headerProfileButton" type="button" class="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition shadow-sm" aria-expanded="false">
                          <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center overflow-hidden relative">
                              <img id="headerAvatarImg" src="{{ $headerAvatarUrl ?? '' }}" alt="Avatar" class="w-full h-full object-cover {{ !empty($headerAvatarUrl) ? '' : 'hidden' }}">
                              <i id="headerAvatarFallback" class="far fa-user text-sm {{ !empty($headerAvatarUrl) ? 'hidden' : '' }}"></i>
                          </span>
                          <span class="hidden sm:flex flex-col items-start leading-tight text-left">
                              <span id="headerProfileName" class="text-sm font-semibold text-slate-800 max-w-[130px] truncate">{{ $headerName }}</span>
                              <span id="headerProfileUsername" class="text-[11px] text-slate-500 max-w-[130px] truncate">{{ '@' . $headerUsername }}</span>
                          </span>
                          <i id="headerProfileChevron" class="fas fa-chevron-down text-[10px] text-slate-400 transition-transform"></i>
                      </button>

                      <div id="headerProfileMenu" class="hidden absolute right-0 top-[calc(100%+10px)] w-72 rounded-2xl bg-white border border-slate-200 shadow-xl overflow-hidden z-50">
                          <div class="px-4 py-3 border-b border-slate-100">
                              <p id="headerProfileMenuName" class="text-sm font-semibold text-slate-800">{{ $headerName }}</p>
                              <p id="headerProfileMenuEmail" class="text-xs text-slate-500 truncate">{{ $headerEmail !== '' ? $headerEmail : ('@' . $headerUsername) }}</p>
                          </div>

                          <div class="py-1.5">
                              <button type="button" data-header-profile-action="Pengaturan Profil" class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition flex items-center gap-2.5">
                                  <i class="far fa-user text-slate-400 w-4 text-center"></i>
                                  <span>Pengaturan Profil</span>
                              </button>
                              {{-- <button type="button" data-header-profile-action="Kritik & Saran" class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition flex items-center gap-2.5">
                                  <i class="far fa-comment-alt text-slate-400 w-4 text-center"></i>
                                  <span>Kritik &amp; Saran</span>
                              </button> --}}
                              {{-- <a href="mailto:{{ $headerEmail !== '' ? $headerEmail : 'admin@example.com' }}" class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition flex items-center gap-2.5">
                                  <i class="far fa-envelope text-slate-400 w-4 text-center"></i>
                                  <span>Kontak Kami</span>
                              </a> --}}
                          </div>

                          <div class="border-t border-slate-100 p-1.5">
                              <form method="POST" action="{{ route('logout') }}">
                                  @csrf
                                  <button type="submit" class="w-full text-left px-3 py-2.5 rounded-xl text-sm text-rose-600 hover:bg-rose-50 transition flex items-center gap-2.5">
                                      <i class="fas fa-sign-out-alt w-4 text-center"></i>
                                      <span>Keluar</span>
                                  </button>
                              </form>
                          </div>
                      </div>
                  </div>
              </div>
          </header>
          <script>
              (function () {
                  function formatHeaderCurrentDate() {
                      const tz = window.APP_TIMEZONE || @json($headerTimezone);
                      try {
                          return new Intl.DateTimeFormat('id-ID', {
                              weekday: 'long',
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric',
                              timeZone: tz
                          }).format(new Date());
                      } catch (error) {
                          return new Date().toLocaleDateString('id-ID', {
                              weekday: 'long',
                              year: 'numeric',
                              month: 'long',
                              day: 'numeric'
                          });
                      }
                  }

                  function formatHeaderCurrentTime() {
                      const tz = window.APP_TIMEZONE || @json($headerTimezone);
                      try {
                          return new Intl.DateTimeFormat('id-ID', {
                              hour: '2-digit',
                              minute: '2-digit',
                              second: '2-digit',
                              hour12: false,
                              timeZone: tz
                          }).format(new Date());
                      } catch (error) {
                          return new Date().toLocaleTimeString('id-ID', {
                              hour: '2-digit',
                              minute: '2-digit',
                              second: '2-digit',
                              hour12: false
                          });
                      }
                  }

                  window.updateHeaderCurrentDate = window.updateHeaderCurrentDate || function () {
                      const dateNode = document.getElementById('currentDateDisplay');
                      const timeNode = document.getElementById('currentTimeDisplay');
                      const timezoneNode = document.getElementById('currentTimezoneLabel');
                      if (!dateNode) return;
                      dateNode.textContent = formatHeaderCurrentDate();
                      if (timeNode) {
                          timeNode.textContent = formatHeaderCurrentTime();
                      }
                      if (timezoneNode) {
                          timezoneNode.textContent = String(window.APP_TIMEZONE_LABEL || @json($headerTimezoneLabel));
                      }
                  };

                  function initHeaderProfileDropdown() {
                      const wrap = document.getElementById('headerProfileDropdownWrap');
                      const button = document.getElementById('headerProfileButton');
                      const menu = document.getElementById('headerProfileMenu');
                      const chevron = document.getElementById('headerProfileChevron');
                      if (!wrap || !button || !menu) return;

                      const closeMenu = () => {
                          menu.classList.add('hidden');
                          button.setAttribute('aria-expanded', 'false');
                          if (chevron) chevron.classList.remove('rotate-180');
                      };

                      const openMenu = () => {
                          menu.classList.remove('hidden');
                          button.setAttribute('aria-expanded', 'true');
                          if (chevron) chevron.classList.add('rotate-180');
                      };

                      button.addEventListener('click', function (event) {
                          event.stopPropagation();
                          if (menu.classList.contains('hidden')) openMenu();
                          else closeMenu();
                      });

                      document.addEventListener('click', function (event) {
                          if (!wrap.contains(event.target)) closeMenu();
                      });

                      document.addEventListener('keydown', function (event) {
                          if (event.key === 'Escape') closeMenu();
                      });

                      menu.querySelectorAll('[data-header-profile-action]').forEach(function (item) {
                          item.addEventListener('click', function () {
                              closeMenu();
                              const label = String(item.getAttribute('data-header-profile-action') || 'Menu');
                              if (label === 'Pengaturan Profil') {
                                  window.location.href = window.APP_ROUTES?.settingsProfile || '/settings/profile';
                                  return;
                              }
                              if (typeof window.showAlert === 'function') {
                                  window.showAlert('info', label + ' belum tersedia.');
                              }
                          });
                      });

                      window.updateHeaderCurrentDate();
                      window.setInterval(window.updateHeaderCurrentDate, 1000);
                  }

                  if (document.readyState === 'loading') {
                      document.addEventListener('DOMContentLoaded', initHeaderProfileDropdown);
                  } else {
                      initHeaderProfileDropdown();
                  }
              })();
          </script>
