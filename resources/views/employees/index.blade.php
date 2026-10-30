@php
    use App\Models\Employee;
    use Illuminate\Support\Facades\Route as RouteFacade;

    // Warna per jenis kepegawaian: kotak legenda dan lencana tabel (class ditulis utuh untuk Tailwind)
    $statusStyle = [
        'tetap' => ['bg-indigo-400', 'bg-indigo-100 text-indigo-600'],
        'kontrak' => ['bg-amber-300', 'bg-amber-100 text-amber-600'],
        'percobaan' => ['bg-sky-300', 'bg-sky-100 text-sky-500'],
    ];
    $genderStyle = [
        'L' => ['bg-emerald-300', 'bg-emerald-100 text-emerald-600'],
        'P' => ['bg-rose-300', 'bg-rose-100 text-rose-500'],
    ];

    // Isi dropdown Status: tiga jenis kepegawaian + nonaktif
    $statusOptions = Employee::STATUS_KEPEGAWAIAN + ['nonaktif' => 'Nonaktif'];

    $has = fn (string $name) => RouteFacade::has($name);
    $query = request()->query();
    $genderKinds = collect($summary['gender'])->filter(fn ($n) => $n > 0)->count();
@endphp

<x-app-layout title="Karyawan">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Karyawan</h1>
        <p class="mt-0.5 text-xs text-gray-700">Gambaran umum karyawan di perusahaan</p>
    </x-slot:heading>

    {{-- Pesan hasil aksi (tambah, ubah, nonaktifkan) --}}
    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    {{-- ================= KARTU RINGKASAN ================= --}}
    <section class="rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-labelledby="list-heading">
        <div class="flex items-start justify-between gap-3">
            <h2 id="list-heading" class="text-base font-semibold text-gray-900">List Karyawan</h2>

            {{-- Tombol tambah karyawan: hanya untuk yang berhak --}}
            @can('create', Employee::class)
                <a href="{{ route('employees.create') }}" aria-label="Tambah karyawan"
                   class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-blue-500 text-gray-800 shadow-sm hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                    <x-icon name="plus" size="size-5" />
                </a>
            @endcan
        </div>

        <div class="mt-5 grid divide-y divide-gray-200 rounded-lg border border-gray-200 md:grid-cols-2 md:divide-x md:divide-y-0">
            {{-- Status kepegawaian --}}
            <div class="p-4 sm:p-5">
                <p class="text-xs font-medium text-gray-900">Status</p>
                <p class="mt-3 text-lg text-gray-900"><span class="text-2xl font-semibold tabular-nums text-sky-600">{{ $summary['total'] }}</span> People</p>
                <ul class="mt-4 space-y-3 text-sm">
                    @foreach (Employee::STATUS_KEPEGAWAIAN as $key => $label)
                        <li class="flex items-center gap-3">
                            <span class="size-4 shrink-0 rounded {{ $statusStyle[$key][0] }}" aria-hidden="true"></span>
                            <span class="w-24 text-xs text-gray-900">{{ $label }}</span>
                            <span class="tabular-nums text-gray-800">{{ $summary['status'][$key] }} People</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Gender --}}
            <div class="p-4 sm:p-5">
                <p class="text-xs font-medium text-gray-900">Gender</p>
                <p class="mt-3 text-lg text-gray-900"><span class="text-2xl font-semibold tabular-nums text-sky-600">{{ $genderKinds }}</span> Gender</p>
                <ul class="mt-4 space-y-3 text-sm">
                    @foreach (Employee::GENDER as $key => $label)
                        <li class="flex items-center gap-3">
                            <span class="size-4 shrink-0 rounded {{ $genderStyle[$key][0] }}" aria-hidden="true"></span>
                            <span class="w-24 text-xs text-gray-900">{{ $label }}</span>
                            <span class="tabular-nums text-gray-800">{{ $summary['gender'][$key] }} People</span>
                        </li>
                    @endforeach
                    {{-- Data yang belum diisi ditampilkan agar jumlah total tetap bisa ditelusuri --}}
                    @if ($summary['gender_missing'] > 0)
                        <li class="flex items-center gap-3 text-gray-500">
                            <span class="size-4 shrink-0 rounded bg-gray-200" aria-hidden="true"></span>
                            <span class="w-24 text-xs">Belum diisi</span>
                            <span class="tabular-nums">{{ $summary['gender_missing'] }} People</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </section>

    {{-- ================= DAFTAR + FILTER ================= --}}
    <section class="mt-6 min-w-0 rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-label="Daftar karyawan">

        {{-- Toolbar: penghitung filter, pencarian, tanggal, divisi, status, ekspor --}}
        <form method="GET" action="{{ route('employees.index') }}" role="search" class="flex flex-wrap items-center gap-3">
            <span class="flex h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-xs text-gray-500" title="Jumlah filter aktif (tanggal, divisi, status)">
                <x-icon name="filter" size="size-4" /> {{ $activeFilters }}/3
            </span>

            <div class="relative min-w-40 flex-1">
                <label for="emp-search" class="sr-only">Cari nama atau Employee ID</label>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500"><x-icon name="search" size="size-4" /></span>
                <input id="emp-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Search"
                       class="h-11 w-full rounded-lg border border-gray-300 pl-9 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <div>
                <label for="emp-date" class="sr-only">Bergabung sampai tanggal</label>
                <input id="emp-date" type="date" name="date" value="{{ $filters['date']?->toDateString() }}" title="Bergabung sampai tanggal" @change="$el.form.requestSubmit()"
                       class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <div class="relative">
                <label for="emp-division" class="sr-only">Divisi</label>
                <select id="emp-division" name="division" @change="$el.form.requestSubmit()"
                        class="h-11 w-36 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Divisi</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id_departemen }}" @selected($filters['division'] === $d->id_departemen)>{{ $d->nama_departemen }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
            </div>

            <div class="relative">
                <label for="emp-status" class="sr-only">Status</label>
                <select id="emp-status" name="status" @change="$el.form.requestSubmit()"
                        class="h-11 w-36 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
            </div>

            {{-- Ekspor mengikuti filter yang sedang aktif --}}
            <a href="{{ route('employees.export.xlsx', $query) }}"
               class="flex h-11 items-center gap-2 rounded-lg bg-emerald-50 px-4 text-xs text-gray-800 shadow-card hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-emerald-600">
                <x-icon name="excel" size="size-4" class="text-emerald-700" /> Export <span class="sr-only">ke Excel</span>
            </a>
            <a href="{{ route('employees.export.print', $query) }}" target="_blank" rel="noopener"
               class="flex h-11 items-center gap-2 rounded-lg bg-red-50 px-4 text-xs text-gray-800 shadow-card hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-red-600">
                <x-icon name="pdf" size="size-4" class="text-red-600" /> Export <span class="sr-only">ke PDF (cetak)</span>
            </a>
        </form>

        {{-- Tabel: bisa digeser ke samping di layar sempit --}}
        <div class="mt-5 overflow-x-auto rounded-xl border border-gray-100">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead>
                    <tr class="bg-slate-100 text-gray-900">
                        <th scope="col" class="rounded-tl-xl px-4 py-3 font-medium whitespace-nowrap">Employee ID</th>
                        <th scope="col" class="px-4 py-3 font-medium whitespace-nowrap">Employee Name</th>
                        <th scope="col" class="px-4 py-3 font-medium whitespace-nowrap">Divisi</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Gender</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Lokasi</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Status</th>
                        <th scope="col" class="rounded-tr-xl px-4 py-3 text-center font-medium whitespace-nowrap">Other</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $e)
                        <tr class="{{ $e->status === 'nonaktif' ? 'bg-gray-50/70 text-gray-500' : '' }}">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-800">{{ $e->nik }}</td>

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="hidden sm:inline-flex"><x-avatar :name="$e->nama_lengkap" :photo="$e->photo_url" size="size-10" /></span>
                                    <span class="whitespace-nowrap">
                                        <span class="block text-gray-900">{{ $e->nama_lengkap }}</span>
                                        <span class="block text-[11px] text-gray-500">{{ $e->jabatan ?: '-' }}</span>
                                    </span>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $e->department?->nama_departemen ?? '-' }}</td>

                            <td class="px-4 py-3 text-center">
                                @if ($e->jenis_kelamin)
                                    <span class="inline-flex rounded-md px-3 py-1 text-xs font-medium {{ $genderStyle[$e->jenis_kelamin][1] }}">{{ Employee::GENDER[$e->jenis_kelamin] }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            {{-- Lokasi: tautan ke pelacakan; menu aktif otomatis saat halamannya tersedia --}}
                            <td class="whitespace-nowrap px-4 py-3 text-center text-xs">
                                @if ($has('tracking.index'))
                                    <a href="{{ route('tracking.index', ['employee' => $e->id_karyawan]) }}" class="text-blue-600 hover:underline">Track..</a>
                                @else
                                    <span class="text-gray-400" title="Halaman tracking belum tersedia">Track..</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex whitespace-nowrap rounded-md px-3 py-1 text-xs font-medium {{ $e->status === 'nonaktif' ? 'bg-gray-200 text-gray-600' : $statusStyle[$e->status_kepegawaian][1] }}">
                                    {{ $e->status === 'nonaktif' ? 'Nonaktif' : Employee::STATUS_KEPEGAWAIAN[$e->status_kepegawaian] }}
                                </span>
                            </td>

                            {{-- Aksi per baris --}}
                            <td class="px-4 py-3 text-center">
                                <x-row-menu>
                                    <a href="{{ route('employees.show', $e) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                        <x-icon name="eye" size="size-4" /> Lihat detail
                                    </a>
                                    @can('update', $e)
                                        <a href="{{ route('employees.edit', $e) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                            <x-icon name="pencil" size="size-4" /> Edit
                                        </a>
                                    @endcan
                                    @can('toggleStatus', $e)
                                        <form method="POST" action="{{ route('employees.status', $e) }}"
                                              @if ($e->status === 'aktif') onsubmit="return confirm('Nonaktifkan {{ e($e->nama_lengkap) }}? Akun login-nya juga tidak akan bisa dipakai.')" @endif>
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $e->status === 'aktif' ? 'nonaktif' : 'aktif' }}">
                                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs {{ $e->status === 'aktif' ? 'text-red-700 hover:bg-red-50' : 'text-green-700 hover:bg-green-50' }}">
                                                <x-icon :name="$e->status === 'aktif' ? 'power' : 'check'" size="size-4" />
                                                {{ $e->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    @endcan
                                </x-row-menu>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center text-sm text-gray-500">
                                @if ($activeFilters > 0 || $filters['q'] !== '')
                                    Tidak ada karyawan yang cocok dengan filter ini.
                                    <a href="{{ route('employees.index') }}" class="font-medium text-blue-600 hover:underline">Reset filter</a>
                                @else
                                    Belum ada data karyawan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Pagination bergaya pil --}}
    <div class="mt-6">{{ $rows->onEachSide(1)->links('components.pagination') }}</div>
</x-app-layout>
