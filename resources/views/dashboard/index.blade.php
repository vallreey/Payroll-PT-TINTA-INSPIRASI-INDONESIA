@php
    use Illuminate\Support\Facades\Route as RouteFacade;

    // Badge status absensi (class ditulis utuh agar terdeteksi Tailwind)
    $badges = [
        'on_time' => ['On-Time',  'border-green-500 bg-green-50 text-green-600'],
        'late'    => ['Late',     'border-red-500 bg-red-50 text-red-600'],
        'absent'  => ['Absent',   'border-amber-400 bg-amber-50 text-amber-500'],
        'leave'   => ['On Leave', 'border-sky-400 bg-sky-50 text-sky-600'],
    ];

    $tones = [
        'pink'   => 'bg-pink-100 text-fuchsia-600',
        'violet' => 'bg-purple-100 text-purple-600',
        'blue'   => 'bg-blue-100 text-blue-600',
        'green'  => 'bg-green-100 text-green-600',
    ];

    $has = fn (string $name) => RouteFacade::has($name);
@endphp

<x-app-layout title="Dashboard">

    <x-slot:heading>
        <h1 class="break-words text-xl font-semibold text-gray-900 sm:text-2xl">Selamat {{ $greeting }}, {{ $user->nama_tampil }} <span aria-hidden="true">👋</span></h1>
        <p class="mt-0.5 text-xs text-gray-700">Hari ini {{ $dateLabel }}.</p>
    </x-slot:heading>

    {{-- ================= KARTU STATISTIK ================= --}}
    <section aria-label="Ringkasan perusahaan" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl bg-white p-5 shadow-card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-3xl font-semibold tracking-tight text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-sm text-gray-800">{{ $stat['label'] }}</p>
                    </div>
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full {{ $tones[$stat['tone']] }}">
                        <x-icon :name="$stat['icon']" size="size-6" />
                    </span>
                </div>
                <div class="mt-4 flex min-h-8 items-center justify-between gap-2 border-t border-gray-200 pt-3 text-[11px] text-gray-600">
                    <p class="flex items-center gap-1.5 whitespace-nowrap">
                        @if ($stat['caption'])
                            @if ($stat['caption']['trend'])
                                <x-icon name="arrow-up" size="size-4" class="text-green-500" />
                            @endif
                            {{ $stat['caption']['text'] }}
                        @endif
                    </p>
                    @if ($stat['route'] && $has($stat['route']))
                        <a href="{{ route($stat['route']) }}" aria-label="Lihat {{ $stat['label'] }}" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="play" size="size-4" />
                        </a>
                    @else
                        <x-icon name="play" size="size-4" class="text-gray-300" />
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[1.15fr_1fr]">

        {{-- ================= ATTENDANCE ================= --}}
        <section class="min-w-0 rounded-2xl bg-white p-5 shadow-card" aria-labelledby="attendance-heading">
            <div class="flex items-center justify-between">
                <h2 id="attendance-heading" class="text-base font-semibold text-gray-900">Attendance</h2>
                @if ($has('attendance.index'))
                    <a href="{{ route('attendance.index') }}" aria-label="Lihat semua absensi"
                       class="flex size-9 items-center justify-center rounded-lg border border-blue-500 text-gray-800 shadow-sm hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                        <x-icon name="external" size="size-4" />
                    </a>
                @else
                    <span aria-disabled="true" title="Halaman absensi belum tersedia"
                          class="flex size-9 cursor-not-allowed items-center justify-center rounded-lg border border-blue-200 text-gray-400">
                        <x-icon name="external" size="size-4" />
                    </span>
                @endif
            </div>

            <ul class="mt-5 flex flex-wrap items-center gap-y-2 text-sm">
                <li class="flex items-center gap-2 pr-3">
                    <span class="size-2.5 rounded-full bg-green-500" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['on_time'] }}</strong>
                    <span class="font-medium text-gray-400">On-Time</span>
                </li>
                <li class="flex items-center gap-2 border-l border-gray-200 px-3">
                    <span class="size-2.5 rounded-full bg-red-600" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['late'] }}</strong>
                    <span class="font-medium text-gray-400">Late</span>
                </li>
                <li class="flex items-center gap-2 border-l border-gray-200 px-3">
                    <span class="size-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['absent'] }}</strong>
                    <span class="font-medium text-gray-400">Absent</span>
                </li>
                @if ($summary['leave'] > 0)
                    <li class="flex items-center gap-2 border-l border-gray-200 pl-3">
                        <span class="size-2.5 rounded-full bg-sky-400" aria-hidden="true"></span>
                        <strong class="font-semibold">{{ $summary['leave'] }}</strong>
                        <span class="font-medium text-gray-400">On Leave</span>
                    </li>
                @endif
            </ul>

            <div class="mt-5 overflow-x-auto rounded-2xl border border-gray-100">
                <table class="w-full min-w-[320px] text-left text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-gray-900">
                            <th scope="col" class="rounded-tl-2xl px-2 py-3 font-medium whitespace-nowrap sm:px-4">Employee Name</th>
                            <th scope="col" class="px-2 py-3 font-medium whitespace-nowrap sm:px-4">Status</th>
                            <th scope="col" class="rounded-tr-2xl px-2 py-3 font-medium whitespace-nowrap sm:px-4">Check-in</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-2 py-3 sm:px-4">
                                    <div class="flex items-center gap-3">
                                        <span class="hidden sm:inline-flex"><x-avatar :name="$row['name']" :photo="$row['photo']" size="size-10" /></span>
                                        <span class="font-normal text-gray-900">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-3 sm:px-4">
                                    <span class="inline-flex whitespace-nowrap rounded-md border px-2 py-1 text-xs font-medium sm:px-3 {{ $badges[$row['status']][1] }}">{{ $badges[$row['status']][0] }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-3 tabular-nums text-gray-900 sm:px-4">{{ $row['check_in'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-12 text-center text-sm text-gray-500">Belum ada karyawan yang terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ================= KOLOM KANAN ================= --}}
        <div class="min-w-0 space-y-6">

            {{-- Notification / Submission / Approval --}}
            <section x-data="{ tab: 'notification' }" class="rounded-2xl bg-white shadow-card" aria-label="Notifikasi, pengajuan, dan persetujuan">
                <div role="tablist" class="flex gap-6 border-b border-gray-200 px-5 pt-5">
                    @foreach (['notification' => 'Notification', 'submission' => 'Submission', 'approval' => 'Approval'] as $key => $label)
                        @php
                            $badge = match ($key) {
                                'notification' => $unreadTotal,
                                'submission' => $pendingLeaveTotal,
                                default => $approvalTotal,
                            };
                        @endphp
                        <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                                :aria-selected="(tab === '{{ $key }}').toString()"
                                @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-400 hover:text-gray-700'"
                                class="-mb-px flex items-center gap-1.5 border-b-4 pb-2.5 text-xs font-medium focus-visible:outline-2 focus-visible:outline-blue-500">
                            {{ $label }}
                            @if ($badge > 0)
                                <span class="rounded-full bg-blue-600 px-1.5 text-[11px] font-semibold leading-5 text-white">{{ $badge }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Notification (tbl_notifikasi milik user yang login) --}}
                <div role="tabpanel" id="panel-notification" aria-labelledby="tab-notification" x-show="tab === 'notification'" class="p-5">
                    <form method="GET" action="{{ route('dashboard') }}" role="search" class="flex flex-col gap-3 sm:flex-row">
                        <div class="relative min-w-0 flex-1">
                            <label for="notification-search" class="sr-only">Cari notifikasi</label>
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500"><x-icon name="search" size="size-4" /></span>
                            <input id="notification-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search"
                                   class="w-full rounded-md border border-gray-300 py-2.5 pl-9 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div class="relative w-full shrink-0 sm:w-36">
                            <label for="notification-type" class="sr-only">Filter jenis notifikasi</label>
                            <select id="notification-type" name="tipe" @change="$el.form.requestSubmit()"
                                    class="w-full appearance-none rounded-md border border-gray-300 bg-white py-2.5 pl-3 pr-9 text-xs text-gray-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">Filter</option>
                                @foreach (['izin' => 'Izin', 'cuti' => 'Cuti', 'absensi' => 'Absensi', 'gaji' => 'Gaji'] as $value => $label)
                                    <option value="{{ $value }}" @selected($tipe === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                        </div>
                    </form>

                    <div class="min-h-56">
                        @if ($notifications->isNotEmpty())
                            <ul class="mt-4 divide-y divide-gray-100">
                                @foreach ($notifications as $n)
                                    <li class="flex gap-3 py-3 first:pt-0 last:pb-0">
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->is_read ? 'bg-gray-200' : 'bg-blue-500' }}" aria-hidden="true"></span>
                                        <div class="min-w-0">
                                            <p class="text-sm {{ $n->is_read ? 'font-normal text-gray-700' : 'font-medium text-gray-900' }}">
                                                {{ $n->judul }}
                                                @unless ($n->is_read)<span class="sr-only">(belum dibaca)</span>@endunless
                                            </p>
                                            <p class="mt-0.5 line-clamp-2 text-xs text-gray-600">{{ $n->pesan }}</p>
                                            <p class="mt-1 text-[11px] text-gray-400">{{ ucfirst($n->tipe) }}, {{ $n->created_at->locale('id')->diffForHumans() }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex min-h-56 flex-col items-center justify-center text-center text-xs text-gray-900">
                                @if ($search !== '' || $tipe)
                                    <p>No notification matches your search</p>
                                    <a href="{{ route('dashboard') }}" class="mt-1 font-medium text-blue-600 hover:underline">Reset</a>
                                @else
                                    <p>There is no notification</p>
                                    <p class="mt-2">at this time</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Submission: pengajuan izin/cuti yang menunggu persetujuan --}}
                <div role="tabpanel" id="panel-submission" aria-labelledby="tab-submission" x-cloak x-show="tab === 'submission'" class="min-h-72 p-5">
                    @if ($submissions->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($submissions as $s)
                                <li class="py-3 first:pt-0 last:pb-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $s->employee?->nama_lengkap ?? '-' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ ucfirst($s->jenis) }}@if ($s->sub_jenis) ({{ $s->sub_jenis }})@endif,
                                        {{ $s->tanggal_mulai->locale('id')->translatedFormat('j M') }}
                                        @if (! $s->tanggal_mulai->isSameDay($s->tanggal_selesai))
                                            – {{ $s->tanggal_selesai->locale('id')->translatedFormat('j M') }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                        @if ($pendingLeaveTotal > $submissions->count())
                            <p class="mt-3 text-xs text-gray-400">+{{ $pendingLeaveTotal - $submissions->count() }} pengajuan lainnya menunggu persetujuan.</p>
                        @endif
                    @else
                        <p class="pt-24 text-center text-xs text-gray-900">There is no submission at this time</p>
                    @endif
                </div>

                {{-- Approval: absensi dan penggajian yang menunggu persetujuan --}}
                <div role="tabpanel" id="panel-approval" aria-labelledby="tab-approval" x-cloak x-show="tab === 'approval'" class="min-h-72 p-5">
                    @if ($approvals->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($approvals as $a)
                                <li class="py-3 first:pt-0 last:pb-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $a['title'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $a['subtitle'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                        @if ($approvalTotal > $approvals->count())
                            <p class="mt-3 text-xs text-gray-400">+{{ $approvalTotal - $approvals->count() }} persetujuan lainnya.</p>
                        @endif
                    @else
                        <p class="pt-24 text-center text-xs text-gray-900">There is no approval at this time</p>
                    @endif
                </div>
            </section>

            {{-- Leave Schedule: izin/cuti/sakit yang disetujui dan belum berakhir --}}
            <section x-data="{ filter: 'all' }" class="rounded-2xl bg-white p-5 shadow-card" aria-labelledby="schedule-heading">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-3">
                    <h2 id="schedule-heading" class="text-base font-semibold text-gray-900">Leave Schedule ({{ $scheduleTotal }})</h2>
                    <div class="flex items-center gap-3">
                        <div class="flex rounded-xl bg-sky-100 p-0.5 text-sm" role="group" aria-label="Filter jenis">
                            @foreach (['all' => 'All', 'cuti' => 'Cuti', 'izin' => 'Izin', 'sakit' => 'Sakit'] as $key => $label)
                                <button type="button" @click="filter = '{{ $key }}'"
                                        :aria-pressed="(filter === '{{ $key }}').toString()"
                                        :class="filter === '{{ $key }}' ? 'bg-gradient-to-r from-cyan-500 to-indigo-700 text-white' : 'text-gray-800 hover:bg-sky-200'"
                                        class="rounded-[10px] px-3.5 py-1.5 font-normal focus-visible:outline-2 focus-visible:outline-blue-500">{{ $label }}</button>
                            @endforeach
                        </div>
                        @if ($has('leaves.index'))
                            <a href="{{ route('leaves.index') }}" aria-label="Buka halaman izin dan cuti"
                               class="flex size-9 items-center justify-center rounded-lg border border-blue-500 text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                                <x-icon name="calendar" size="size-4" />
                            </a>
                        @else
                            <span aria-disabled="true" title="Halaman izin dan cuti belum tersedia"
                                  class="flex size-9 cursor-default items-center justify-center rounded-lg border border-blue-200 text-gray-400">
                                <x-icon name="calendar" size="size-4" />
                            </span>
                        @endif
                    </div>
                </div>

                @if ($schedules->isNotEmpty())
                    <ul class="mt-5 space-y-4">
                        @foreach ($schedules as $e)
                            <li x-show="filter === 'all' || filter === '{{ $e->jenis }}'"
                                class="flex items-center justify-between gap-3 rounded-xl border border-blue-600 p-4">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 text-xs font-medium text-gray-900">
                                        <x-icon name="calendar" size="size-4" />
                                        <span class="truncate">{{ $e->employee?->nama_lengkap ?? '-' }}, {{ ucfirst($e->jenis) }}@if ($e->sub_jenis) ({{ $e->sub_jenis }})@endif</span>
                                    </p>
                                    <p class="mt-2 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500">
                                        <span>{{ $e->tanggal_mulai->locale('id')->translatedFormat('j F, Y') }}</span>
                                        @if (! $e->tanggal_mulai->isSameDay($e->tanggal_selesai))
                                            <span aria-hidden="true">–</span>
                                            <span>{{ $e->tanggal_selesai->locale('id')->translatedFormat('j F, Y') }}</span>
                                        @endif
                                        <span class="size-2 rounded-full bg-gray-300" aria-hidden="true"></span>
                                        <span>{{ $e->total_hari }} hari</span>
                                    </p>
                                </div>
                                <x-avatar :name="$e->employee?->nama_lengkap ?? ''" :photo="$e->employee?->photo_url" size="size-10" class="border border-blue-600" />
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="py-12 text-center text-xs text-gray-900">There is no leave schedule at this time</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
