@php
    $studentMobileNavItems = [
        [
            'label' => 'Beranda',
            'route' => 'dashboard',
            'icon' => 'fas fa-house',
            'active' => request()->routeIs('dashboard'),
        ],
        [
            'label' => 'Mapel',
            'route' => 'mata-pelajaran-saya',
            'icon' => 'fas fa-book',
            'active' => request()->routeIs('mata-pelajaran-saya'),
        ],
        [
            'label' => 'Presensi',
            'route' => 'presensi-saya',
            'icon' => 'fas fa-chart-pie',
            'active' => request()->routeIs('presensi-saya'),
        ],
        [
            'label' => 'Tabungan',
            'route' => 'tabungan-saya',
            'icon' => 'fas fa-piggy-bank',
            'active' => request()->routeIs('tabungan-saya'),
        ],
        [
            'label' => 'Profil',
            'route' => 'settings.profile.index',
            'icon' => 'fas fa-user',
            'active' => request()->routeIs('settings.profile.*'),
        ],
    ];
@endphp

<nav class="md:hidden fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 backdrop-blur-xl shadow-[0_-12px_30px_rgba(15,23,42,0.08)]" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
    <div class="grid grid-cols-5 gap-1 px-2 pt-2">
        @foreach ($studentMobileNavItems as $item)
            <a
                href="{{ route($item['route']) }}"
                class="flex min-h-[60px] flex-col items-center justify-center gap-1 rounded-2xl px-1 py-2 text-center transition {{ $item['active'] ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}"
            >
                <i class="{{ $item['icon'] }} text-sm"></i>
                <span class="text-[10px] font-semibold leading-none">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
