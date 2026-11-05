@php
    use Illuminate\Support\Facades\Route as RouteFacade;

    $has = fn (string $name) => RouteFacade::has($name);
    $time = fn (?string $t) => $t ? substr(str_replace(':', '.', $t), 0, 5) : '-';
    $query = request()->query();

    $statusBadge = [
        'hadir' => ['On-Time', 'border-green-500 bg-green-50 text-green-600'],
        'terlambat' => ['Late', 'border-red-500 bg-red-50 text-red-600'],
        'alpha' => ['Absent', 'border-amber-400 bg-amber-50 text-amber-500'],
    ];

    $statusOptions = [
        'on_time' => 'On-Time',
        'late' => 'Late',
        'pending' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    $dateValue = $filters['date']?->toDateString() ?? '';
    $showDateColumn = $filters['date'] === null;
@endphp

<x-app-layout title="Absensi">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Absensi</h1>
        <p class="mt-0.5 text-xs text-gray-700">{{ $isManager ? 'Rekap kehadiran karyawan' : 'Riwayat kehadiran saya' }}</p>
    </x-slot:heading>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($canRecord)
        <div class="mb-4 flex justify-end">
            <a href="{{ route('attendance.record') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-700 px-4 py-2.5 text-sm font-medium text-white shadow-card hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                <x-icon name="camera" size="size-4" /> Catat Absensi
            </a>
        </div>
    @endif

    {{-- ================= KEHADIRAN (ringkasan) ================= --}}
    @if ($summary)
        <section class="rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-labelledby="presence-heading">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="presence-heading" class="text-base font-semibold text-gray-900">Kehadiran</h2>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ $summaryDate->locale('id')->translatedFormat('l, j F Y') }}
                        @unless ($summary['working_day'])
                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">Bukan hari kerja</span>
                        @endunless
                    </p>
                </div>
                @if ($has('reports.index'))
                    <a href="{{ route('reports.index') }}" aria-label="Buka laporan"
                       class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-blue-500 text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                        <x-icon name="external" size="size-4" />
                    </a>
                @else
                    <span aria-disabled="true" title="Halaman laporan belum tersedia"
                          class="flex size-9 shrink-0 cursor-default items-center justify-center rounded-lg border border-blue-200 text-gray-400">
                        <x-icon name="external" size="size-4" />
                    </span>
                @endif
            </div>

            @php
                $groups = [
                    'Present' => [['On Time', $summary['present']['on_time']], ['Early Clock In', $summary['present']['early']], ['Late Clock In', $summary['present']['late']]],
                    'Absent' => [['Leaved', $summary['absent']['leaved']], ['No Clock In', $summary['absent']['no_clock_in']], ['No Clock out', $summary['absent']['no_clock_out']]],
                    'Away' => [['Day Off', $summary['away']['day_off']], ['Time Off', $summary['away']['time_off']]],
                ];
            @endphp
            <div class="mt-5 grid divide-y divide-gray-200 rounded-lg border border-gray-200 md:grid-cols-[1.5fr_1.5fr_1fr] md:divide-x md:divide-y-0">
                @foreach ($groups as $group => $items)
                    <div class="p-4 sm:p-5">
                        <p class="text-xs font-medium text-gray-900">{{ $group }}</p>
                        <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-4">
                            @foreach ($items as [$label, $count])
                                <div>
                                    <dd class="text-xl font-semibold tabular-nums text-blue-700">{{ $count }}</dd>
                                    <dt class="mt-1 text-xs text-gray-400">{{ $label }}</dt>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ================= PERSETUJUAN ABSENSI ================= --}}
    <section class="{{ $summary ? 'mt-6' : '' }} min-w-0 rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-labelledby="approval-heading">
        <h2 id="approval-heading" class="text-base font-semibold text-gray-900">{{ $isManager ? 'Persetujuan Absensi' : 'Riwayat Absensi' }}</h2>

        <form method="GET" action="{{ route('attendance.index') }}" role="search" class="mt-5 flex flex-wrap items-center gap-3">
            <span class="flex h-11 items-center gap-2 rounded-lg border border-gray-200 px-3 text-xs text-gray-500" title="Jumlah filter aktif (tanggal, divisi, status)">
                <x-icon name="filter" size="size-4" /> {{ $activeFilters }}/{{ $isManager ? 3 : 2 }}
            </span>

            <div class="relative min-w-40 flex-1">
                <label for="att-search" class="sr-only">Cari nama atau NIK</label>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500"><x-icon name="search" size="size-4" /></span>
                <input id="att-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Search"
                       class="h-11 w-full rounded-lg border border-gray-300 pl-9 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            <div class="relative">
                <label for="att-date" class="sr-only">Tanggal</label>
                <input id="att-date" type="date" name="date" value="{{ $dateValue }}" @change="$el.form.requestSubmit()"
                       class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>

            @if ($isManager)
                <div class="relative">
                    <label for="att-division" class="sr-only">Divisi</label>
                    <select id="att-division" name="division" @change="$el.form.requestSubmit()"
                            class="h-11 w-36 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Divisi</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id_departemen }}" @selected($filters['division'] === $d->id_departemen)>{{ $d->nama_departemen }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                </div>
            @endif

            <div class="relative">
                <label for="att-status" class="sr-only">Status</label>
                <select id="att-status" name="status" @change="$el.form.requestSubmit()"
                        class="h-11 w-44 appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-9 text-xs text-gray-600 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
            </div>

            <a href="{{ route('attendance.export.xlsx', $query) }}"
               class="flex h-11 items-center gap-2 rounded-lg bg-emerald-50 px-4 text-xs text-gray-800 shadow-card hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-emerald-600">
                <x-icon name="excel" size="size-4" class="text-emerald-700" /> Export
                <span class="sr-only">ke Excel</span>
            </a>
            <a href="{{ route('attendance.export.print', $query) }}" target="_blank" rel="noopener"
               class="flex h-11 items-center gap-2 rounded-lg bg-red-50 px-4 text-xs text-gray-800 shadow-card hover:bg-red-100 focus-visible:outline-2 focus-visible:outline-red-600">
                <x-icon name="pdf" size="size-4" class="text-red-600" /> Export
                <span class="sr-only">ke PDF (cetak)</span>
            </a>
        </form>

        <div class="mt-5 overflow-x-auto rounded-xl border border-gray-100">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead>
                    <tr class="bg-slate-100 text-gray-900">
                        <th scope="col" class="rounded-tl-xl px-4 py-3 font-medium whitespace-nowrap">Employee Name</th>
                        <th scope="col" class="px-4 py-3 font-medium whitespace-nowrap">Divisi</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Check-in</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Check-out</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Evidance</th>
                        <th scope="col" class="px-4 py-3 text-center font-medium whitespace-nowrap">Status</th>
                        <th scope="col" class="rounded-tr-xl px-4 py-3 text-center font-medium whitespace-nowrap">Other</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($rows as $a)
                        @php
                            $badge = $statusBadge[$a->status] ?? $statusBadge['hadir'];
                            $nama = $a->employee?->nama_lengkap ?? '-';
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="hidden sm:inline-flex"><x-avatar :name="$nama" :photo="$a->employee?->photo_url" size="size-10" /></span>
                                    <span class="whitespace-nowrap">
                                        <span class="block text-gray-900">{{ $nama }}</span>
                                        @if ($showDateColumn)
                                            <span class="block text-[11px] text-gray-500">{{ \Carbon\CarbonImmutable::parse($a->tanggal)->locale('id')->translatedFormat('j M Y') }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $a->employee?->department?->nama_departemen ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center tabular-nums text-gray-900">{{ $time($a->jam_masuk) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center tabular-nums text-gray-900">{{ $time($a->jam_keluar) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-xs">
                                <span class="inline-flex items-center gap-3">
                                    <a href="{{ route('attendance.photo', [$a, 'masuk']) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Img 1</a>
                                    @if ($a->foto_keluar)
                                        <a href="{{ route('attendance.photo', [$a, 'keluar']) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Img 2</a>
                                    @endif
                                    <a href="https://www.google.com/maps?q={{ $a->lat_masuk }},{{ $a->long_masuk }}" target="_blank" rel="noopener noreferrer"
                                       aria-label="Lihat lokasi check-in di peta" class="text-gray-500 hover:text-blue-600"><x-icon name="pin" size="size-4" /></a>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex whitespace-nowrap rounded-md border px-3 py-1 text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($canApprove[$a->id_absensi] ?? false)
                                    <div x-data="{ open: false, top: 0, left: 0 }" @keydown.escape="open = false" @scroll.window="open = false" @resize.window="open = false" class="inline-block">
                                        <button type="button" aria-haspopup="true" :aria-expanded="open.toString()"
                                                @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 176); open = !open"
                                                class="inline-flex items-center gap-2 rounded-md border border-blue-600 bg-white px-3 py-1.5 text-xs text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                                            Action <x-icon name="chevron-down" size="size-4" />
                                        </button>
                                        <div x-cloak x-show="open" @click.outside="open = false" x-transition
                                             :style="'top:' + top + 'px; left:' + left + 'px'"
                                             class="fixed z-50 w-44 rounded-xl bg-white p-1 text-left shadow-lg ring-1 ring-gray-100">
                                            <form method="POST" action="{{ route('attendance.approve', $a) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-green-700 hover:bg-green-50">
                                                    <x-icon name="check" size="size-4" /> Setujui
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('attendance.reject', $a) }}" onsubmit="return confirm('Tolak absensi {{ e($nama) }}? Karyawan akan diberi tahu.')">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs text-red-700 hover:bg-red-50">
                                                    <x-icon name="x" size="size-4" /> Tolak
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $state = [
                                            'pending' => ['Menunggu', 'text-amber-600'],
                                            'approved' => ['Disetujui', 'text-green-600'],
                                            'rejected' => ['Ditolak', 'text-red-600'],
                                        ][$a->status_persetujuan];
                                    @endphp
                                    <span class="text-xs font-medium {{ $state[1] }}">{{ $state[0] }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center text-sm text-gray-500">
                                @if ($activeFilters > 0 || $filters['q'] !== '')
                                    Tidak ada data absensi yang cocok dengan filter ini.
                                    <a href="{{ route('attendance.index', ['date' => '']) }}" class="font-medium text-blue-600 hover:underline">Lihat semua tanggal</a>
                                @else
                                    Belum ada data absensi.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">{{ $rows->onEachSide(1)->links('components.pagination') }}</div>
</x-app-layout>
