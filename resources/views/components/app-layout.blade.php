@props(['title' => 'Dashboard'])
@php
    $user = auth()->user();
    $userPhoto = $user->employee?->photo_url;

    $roleLabels = [
        'super_admin' => 'Super Admin',
        'hrd'         => 'HRD',
        'supervisor'  => 'Supervisor',
        'direktur'    => 'CEO/Direktur',
        'karyawan'    => 'Karyawan',
    ];

    $managers = ['super_admin', 'hrd', 'supervisor', 'direktur'];

    // 'route' = nama route. Menu yang route-nya belum dibuat tampil nonaktif,
    // lalu otomatis jadi link begitu route dengan nama tsb didaftarkan.
    $menu = [
        ['label' => 'Dashboard',          'image' => 'DashboardIcon.png',       'route' => 'dashboard',        'roles' => null],
        ['label' => 'Absensi',            'image' => 'AbsensiIcon.png',         'route' => 'attendance.index', 'roles' => null],
        ['label' => 'Karyawan',           'image' => 'KaryawanIcon.png',        'route' => 'employees.index',  'roles' => $managers],
        ['label' => 'Penggajian',         'image' => 'PenggajianIcon.png',      'route' => 'payroll.index',    'roles' => null],
        ['label' => 'Izin & Cuti',        'image' => 'IzinCutiIcon.png',        'route' => 'leaves.index',     'roles' => null],
        ['label' => 'Tracking Lokasi',    'image' => 'TrackingLokasiIcon.png',  'route' => 'tracking.index',   'roles' => null],
        ['label' => 'Manajemen Pengguna', 'image' => 'ManajemenPenggunaIcon.png','route' => 'users.index',     'roles' => ['super_admin']],
        ['label' => 'Settings',           'image' => 'SettingsIcon.png',        'route' => 'settings.index',   'roles' => ['super_admin']],
    ];

    $menu = array_filter($menu, fn ($m) => $m['roles'] === null || in_array($user->role, $m['roles'], true));
    $hasRoute = fn (string $name) => \Illuminate\Support\Facades\Route::has($name);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - PT. Tinta Inspirasi Indonesia</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=poppins:400,500,600" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f6fb] font-sans text-gray-900 antialiased"
      x-data="{ open: window.matchMedia('(min-width: 1024px)').matches }"
      @keydown.escape.window="if (window.innerWidth < 1024) open = false">

    {{-- Overlay (hanya mobile) --}}
    <div x-cloak x-show="open" x-transition.opacity
         class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden"
         @click="open = false" aria-hidden="true"></div>

    {{-- ================= SIDEBAR ================= --}}
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-48 -translate-x-full flex-col bg-white shadow-card transition-transform duration-200 lg:translate-x-0"
           :class="{ 'translate-x-0!': open, '-translate-x-full!': !open }"
           aria-label="Menu utama">

        <div class="flex justify-center px-6 pb-8 pt-7">
            <img src="{{ asset('images/logoPT.png') }}" alt="PT. Tinta Inspirasi Indonesia" class="size-20 object-contain">
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto pb-4">
            @foreach ($menu as $item)
                @php
                    $ready  = $hasRoute($item['route']);
                    $active = $ready && request()->routeIs($item['route']);
                @endphp

                @if ($ready)
                    <a href="{{ route($item['route']) }}"
                       @if ($active) aria-current="page" @endif
                       class="relative flex items-center gap-3 px-6 py-3 text-[13px] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue-500 {{ $active ? 'bg-blue-50 font-medium text-blue-600' : 'text-gray-800 hover:bg-gray-50' }}">
                        @if ($active)
                            <span class="absolute inset-y-0 left-0 w-1.5 rounded-r-full bg-blue-600" aria-hidden="true"></span>
                        @endif
                        <img src="{{ asset('images/'.$item['image']) }}" alt="" aria-hidden="true" class="size-4 shrink-0">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span aria-disabled="true" title="Halaman belum tersedia"
                          class="flex cursor-not-allowed items-center gap-3 px-6 py-3 text-[13px] text-gray-400">
                        <img src="{{ asset('images/'.$item['image']) }}" alt="" aria-hidden="true" class="size-4 shrink-0">
                        {{ $item['label'] }}
                    </span>
                @endif
            @endforeach
        </nav>
    </aside>

    {{-- ================= KONTEN ================= --}}
    <div class="transition-[padding] duration-200 lg:pl-48" :class="{ 'lg:pl-0!': !open }">
        <header class="flex items-start justify-between gap-4 px-4 pt-4 sm:px-5 lg:px-4">
            <div class="flex min-w-0 items-start gap-4">
                <button type="button" @click="open = !open"
                        :aria-expanded="open.toString()" aria-controls="sidebar" aria-label="Tampilkan atau sembunyikan menu"
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-blue-600 shadow-card focus-visible:outline-2 focus-visible:outline-blue-500">
                    <x-icon name="panel" />
                </button>
                <div class="min-w-0 flex-1 pt-1">{{ $heading ?? '' }}</div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                @if ($hasRoute('notifications.index'))
                    <a href="{{ route('notifications.index') }}" aria-label="Notifikasi"
                       class="flex size-10 items-center justify-center rounded-full border border-blue-400 bg-white text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                        <img src="{{ asset('images/Notifications.png') }}" alt="" aria-hidden="true" class="size-4">
                    </a>
                @else
                    <span aria-disabled="true" title="Notifikasi belum tersedia"
                          class="flex size-10 cursor-not-allowed items-center justify-center rounded-full border border-blue-200 bg-white text-gray-400">
                        <img src="{{ asset('images/Notifications.png') }}" alt="" aria-hidden="true" class="size-4">
                    </span>
                @endif

                {{-- Avatar + menu akun --}}
                <div class="relative" x-data="{ menu: false }" @keydown.escape="menu = false" @click.outside="menu = false">
                    <button type="button" @click="menu = !menu" :aria-expanded="menu.toString()" aria-haspopup="true" aria-label="Menu akun"
                            class="block rounded-full border border-blue-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                        <x-avatar :name="$user->nama_tampil" :photo="$userPhoto" size="size-10" />
                    </button>
                    <div x-cloak x-show="menu" x-transition
                         class="absolute right-0 z-20 mt-2 w-56 rounded-xl bg-white p-2 shadow-lg ring-1 ring-gray-100">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-medium text-gray-900">{{ $user->nama_tampil }}</p>
                            <p class="text-xs text-gray-500">{{ $roleLabels[$user->role] ?? $user->role }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 pt-1">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <x-icon name="logout" size="size-4" /> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="px-4 pb-8 pt-6 sm:px-5 lg:px-4">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
