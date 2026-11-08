@php
    use App\Models\User;

    // Warna lencana peran (class ditulis utuh agar terdeteksi Tailwind)
    $roleStyle = [
        'super_admin' => 'border-indigo-300 bg-indigo-50 text-indigo-600',
        'hrd' => 'border-fuchsia-300 bg-fuchsia-50 text-fuchsia-500',
        'supervisor' => 'border-cyan-300 bg-cyan-50 text-cyan-600',
        'direktur' => 'border-teal-300 bg-teal-50 text-teal-600',
        'karyawan' => 'border-orange-300 bg-orange-50 text-orange-500',
    ];

    // Label + warna status tampilan (User::display_status)
    $statusStyle = [
        'aktif' => ['Aktif', 'border-green-400 bg-green-50 text-green-600'],
        'nonaktif' => ['Nonaktif', 'border-gray-300 bg-gray-100 text-gray-500'],
        'menunggu' => ['Menunggu', 'border-amber-400 bg-amber-50 text-amber-600'],
        'ditolak' => ['Ditolak', 'border-red-300 bg-red-50 text-red-500'],
    ];

    // Tab peran di atas tabel
    $tabs = [null => 'Semua Pengguna', 'super_admin' => 'Super Admin', 'karyawan' => 'Karyawan', 'hrd' => 'HRD', 'lainnya' => 'Lainnya'];

    // Query dasar untuk tautan tab: semua filter kecuali peran dan halaman
    $base = request()->except(['role', 'page']);
    $tabUrl = fn (?string $role) => route('users.index', array_filter($base + ['role' => $role], fn ($v) => $v !== null && $v !== ''));

    $photo = fn (User $u) => $u->foto_profil ? asset('storage/'.ltrim($u->foto_profil, '/')) : null;
@endphp

<x-app-layout title="Manajemen Pengguna">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Manajemen Pengguna</h1>
        <p class="mt-0.5 text-xs text-gray-700">Kelola akun pengguna yang terdaftar</p>
    </x-slot:heading>

    {{-- Rentang tanggal registrasi, di pojok kanan atas seperti prototype --}}
    <x-slot:actions>
        <div class="relative" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
            <button type="button" @click="open = !open" aria-haspopup="true" :aria-expanded="open.toString()"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-800 shadow-card hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                <x-icon name="calendar" size="size-4" />
                <span class="sr-only sm:not-sr-only">{{ $rangeLabel }}</span>
                <x-icon name="chevron-down" size="size-4" class="hidden sm:block" />
            </button>

            <form x-cloak x-show="open" x-transition method="GET" action="{{ route('users.index') }}"
                  class="absolute right-0 z-30 mt-2 w-72 max-w-[calc(100vw-1.5rem)] space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-gray-100">
                {{-- Pertahankan filter lain saat rentang tanggal diterapkan --}}
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <input type="hidden" name="role" value="{{ $filters['role'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <p class="text-xs font-medium text-gray-900">Tanggal akun terdaftar</p>
                <div>
                    <label for="range-from" class="mb-1 block text-xs text-gray-600">Dari</label>
                    <input id="range-from" type="date" name="from" value="{{ $filters['from']?->toDateString() }}"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 text-xs text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label for="range-to" class="mb-1 block text-xs text-gray-600">Sampai</label>
                    <input id="range-to" type="date" name="to" value="{{ $filters['to']?->toDateString() }}"
                           class="h-10 w-full rounded-lg border border-gray-300 px-3 text-xs text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="h-10 flex-1 rounded-lg bg-blue-600 text-xs font-medium text-white hover:bg-blue-700">Terapkan</button>
                    <a href="{{ route('users.index', array_filter(['q' => $filters['q'], 'role' => $filters['role'], 'status' => $filters['status']])) }}"
                       class="flex h-10 flex-1 items-center justify-center rounded-lg border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>
    </x-slot:actions>

    {{-- Pesan hasil aksi --}}
    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- ================= KARTU RINGKASAN ================= --}}
    <section aria-label="Ringkasan akun" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total Pengguna', $stats['total'], 'Semua akun terdaftar', 'employees', 'bg-indigo-100 text-indigo-600'],
            ['Pengguna Aktif', $stats['aktif'], $stats['aktif_pct'].'% dari total pengguna', 'users', 'bg-green-100 text-green-600'],
            ['Pengguna Nonaktif', $stats['nonaktif'], $stats['nonaktif_pct'].'% dari total pengguna', 'power', 'bg-amber-100 text-amber-500'],
            ['Menunggu Persetujuan', $stats['menunggu'], $stats['ditolak'] > 0 ? $stats['ditolak'].' registrasi ditolak' : 'Perlu ditinjau Super Admin', 'attendance', 'bg-red-100 text-red-500'],
        ] as [$label, $value, $caption, $icon, $tone])
            <div class="flex items-start gap-4 rounded-2xl bg-white p-5 shadow-card">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full {{ $tone }}"><x-icon :name="$icon" size="size-6" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $value }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $caption }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- ================= DATA PENGGUNA ================= --}}
    <section class="mt-6 min-w-0 rounded-2xl bg-white shadow-card" aria-labelledby="users-heading">

        {{-- Judul + toolbar --}}
        <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
            <h2 id="users-heading" class="text-base font-semibold text-gray-900">Data Pengguna</h2>

            <form method="GET" action="{{ route('users.index') }}" role="search" class="flex flex-wrap items-center gap-3">
                {{-- Pertahankan tab peran dan rentang tanggal --}}
                @if ($filters['from'])<input type="hidden" name="from" value="{{ $filters['from']->toDateString() }}">@endif
                @if ($filters['to'])<input type="hidden" name="to" value="{{ $filters['to']->toDateString() }}">@endif
                @if ($filters['role'] === 'lainnya')<input type="hidden" name="role" value="lainnya">@endif

                <div class="relative min-w-40">
                    <label for="user-search" class="sr-only">Cari nama atau email</label>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500"><x-icon name="search" size="size-4" /></span>
                    <input id="user-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Search"
                           class="h-10 w-full rounded-lg border border-gray-300 pl-9 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                </div>

                @if ($filters['role'] !== 'lainnya')
                    <div class="relative">
                        <label for="user-role" class="sr-only">Peran</label>
                        <select id="user-role" name="role" @change="$el.form.requestSubmit()"
                                class="h-10 w-36 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">Semua Role</option>
                            @foreach (User::ROLES as $value => $label)
                                <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                    </div>
                @endif

                <div class="relative">
                    <label for="user-status" class="sr-only">Status</label>
                    <select id="user-status" name="status" @change="$el.form.requestSubmit()"
                            class="h-10 w-36 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Semua Status</option>
                        @foreach ($statusStyle as $value => [$label])
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                </div>

                <span class="flex h-10 items-center gap-2 rounded-lg border border-gray-200 px-3 text-xs text-gray-500" title="Jumlah filter aktif (peran, status, tanggal)">
                    <x-icon name="filter" size="size-4" /> {{ $activeFilters }}/3
                </span>

                <a href="{{ route('users.create') }}"
                   class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-500 px-4 text-xs font-medium text-white hover:bg-blue-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                    <x-icon name="plus" size="size-4" /> Buat Akun
                </a>
            </form>
        </div>

        {{-- Tab peran: bisa digeser di layar sempit --}}
        <nav aria-label="Filter peran" class="overflow-x-auto border-b border-gray-200 px-5 sm:px-6">
            <ul class="flex min-w-max gap-6">
                @foreach ($tabs as $role => $label)
                    @php $current = ($filters['role'] ?? null) === $role; @endphp
                    <li>
                        <a href="{{ $tabUrl($role) }}" @if ($current) aria-current="page" @endif
                           class="-mb-px block border-b-4 px-1 pb-3 text-sm focus-visible:outline-2 focus-visible:outline-blue-500 {{ $current ? 'border-blue-500 font-medium text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Tabel --}}
        <div class="p-5 sm:p-6">
            <div class="overflow-x-auto rounded-xl border border-gray-100">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-gray-900">
                            <th scope="col" class="rounded-tl-xl px-3 py-3 font-medium whitespace-nowrap">Employee Name</th>
                            <th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">Email</th>
                            <th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">Role</th>
                            <th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">Divisi</th>
                            <th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">Status</th>
                            <th scope="col" class="px-3 py-3 font-medium whitespace-nowrap">Terakhir Aktif</th>
                            <th scope="col" class="rounded-tr-xl px-3 py-3 text-center font-medium whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $u)
                            @php $status = $statusStyle[$u->display_status]; @endphp
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="hidden sm:inline-flex"><x-avatar :name="$u->nama_tampil" :photo="$photo($u)" size="size-10" /></span>
                                        <span class="block max-w-[150px] truncate whitespace-nowrap text-gray-900" title="{{ $u->nama_tampil }}">
                                            {{ $u->nama_tampil }}
                                            @if ($u->is(auth()->user()))<span class="ml-1 text-[11px] text-gray-400">(Anda)</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="max-w-[170px] truncate whitespace-nowrap px-3 py-3 text-gray-700" title="{{ $u->email }}">{{ $u->email }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex whitespace-nowrap rounded-md border px-2.5 py-1 text-xs font-medium {{ $roleStyle[$u->role] }}">{{ User::ROLES[$u->role] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-gray-700">{{ $u->employee?->department?->nama_departemen ?? '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex whitespace-nowrap rounded-md border px-2.5 py-1 text-xs font-medium {{ $status[1] }}">{{ $status[0] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs text-gray-700">{{ $u->terakhir_aktif_label }}</td>
                                <td class="px-3 py-3 text-center">
                                    {{-- Menu aksi ⋮: isi menyesuaikan status akun dan hak akses --}}
                                    <x-row-menu label="Aksi untuk {{ $u->nama_tampil }}" icon-only>
                                        <a href="{{ route('users.edit', $u) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                            <x-icon name="pencil" size="size-4" /> Edit akun
                                        </a>
                                        <a href="{{ route('users.edit', $u) }}#reset-password" class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                            <x-icon name="key" size="size-4" /> Reset kata sandi
                                        </a>

                                        @can('decide', $u)
                                            <form method="POST" action="{{ route('users.approve', $u) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-green-700 hover:bg-green-50"><x-icon name="check" size="size-4" /> Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('users.reject', $u) }}" onsubmit="return confirm('Tolak registrasi {{ e($u->nama_tampil) }}?')">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-red-700 hover:bg-red-50"><x-icon name="x" size="size-4" /> Tolak</button>
                                            </form>
                                        @endcan

                                        @if ($u->status_persetujuan === 'approved')
                                            @can('toggleStatus', $u)
                                                <form method="POST" action="{{ route('users.status', $u) }}"
                                                      @if ($u->status === 'aktif') onsubmit="return confirm('Nonaktifkan akun {{ e($u->nama_tampil) }}? Pengguna langsung keluar dan tidak bisa login.')" @endif>
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $u->status === 'aktif' ? 'nonaktif' : 'aktif' }}">
                                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs {{ $u->status === 'aktif' ? 'text-red-700 hover:bg-red-50' : 'text-green-700 hover:bg-green-50' }}">
                                                        <x-icon :name="$u->status === 'aktif' ? 'power' : 'check'" size="size-4" />
                                                        {{ $u->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </x-row-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-14 text-center text-sm text-gray-500">
                                    @if ($activeFilters > 0 || $filters['q'] !== '')
                                        Tidak ada akun yang cocok dengan filter ini.
                                        <a href="{{ route('users.index') }}" class="font-medium text-blue-600 hover:underline">Reset filter</a>
                                    @else
                                        Belum ada akun.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Ringkasan jumlah + pagination --}}
            <div class="mt-5 flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
                <p class="text-xs text-gray-500">
                    @if ($rows->total() > 0) Menampilkan {{ $rows->firstItem() }} - {{ $rows->lastItem() }} dari {{ $rows->total() }} data @else Tidak ada data @endif
                </p>
                {{ $rows->onEachSide(1)->links('components.pagination') }}
            </div>
        </div>
    </section>
</x-app-layout>
