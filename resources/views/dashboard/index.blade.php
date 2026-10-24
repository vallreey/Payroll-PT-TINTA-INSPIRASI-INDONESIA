@php
    use Illuminate\Support\Facades\Route as RouteFacade;
    use Illuminate\Support\Str;

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

    $safeUrl = fn ($url) => is_string($url) && Str::startsWith($url, ['https://', 'http://']);
    $has = fn (string $name) => RouteFacade::has($name);
@endphp

<x-app-layout title="Dashboard">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Selamat {{ $greeting }}, {{ $user->name }} <span aria-hidden="true">👋</span></h1>
        <p class="mt-0.5 text-xs text-gray-700">Hari ini {{ $dateLabel }}.</p>
    </x-slot:heading>

    {{-- ================= KARTU STATISTIK ================= --}}
    <section aria-label="Ringkasan perusahaan" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl bg-white p-4 shadow-card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-2xl font-semibold tracking-tight text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-800">{{ $stat['label'] }}</p>
                    </div>
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $tones[$stat['tone']] }}">
                        <x-icon :name="$stat['icon']" size="size-5" />
                    </span>
                </div>
                <div class="mt-3 flex min-h-6 items-center justify-between gap-2 border-t border-gray-200 pt-2 text-[10px] text-gray-600">
                    <p class="flex items-center gap-1.5">
                        @if ($stat['caption'])
                            <x-icon name="arrow-up" size="size-3.5" class="text-green-500" />
                            {{ $stat['caption'] }}
                        @endif
                    </p>
                    @if ($stat['route'] && $has($stat['route']))
                        <a href="{{ route($stat['route']) }}" aria-label="Lihat {{ $stat['label'] }}" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="play" size="size-3.5" />
                        </a>
                    @else
                        <x-icon name="play" size="size-3.5" class="text-gray-300" />
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-2 lg:gap-3.5">

        {{-- ================= ATTENDANCE ================= --}}
        <section class="rounded-2xl bg-white p-4 shadow-card" aria-labelledby="attendance-heading">
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

            <ul class="mt-4 flex flex-wrap items-center gap-y-2 px-1 text-xs">
                <li class="flex items-center gap-2 pr-4">
                    <span class="size-2.5 rounded-full bg-green-500" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['on_time'] }}</strong>
                    <span class="font-medium text-gray-400">On-Time</span>
                </li>
                <li class="flex items-center gap-2 border-l border-gray-200 px-4">
                    <span class="size-2.5 rounded-full bg-red-600" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['late'] }}</strong>
                    <span class="font-medium text-gray-400">Late</span>
                </li>
                <li class="flex items-center gap-2 border-l border-gray-200 px-4">
                    <span class="size-2.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                    <strong class="font-semibold">{{ $summary['absent'] }}</strong>
                    <span class="font-medium text-gray-400">Absent</span>
                </li>
                @if ($summary['leave'] > 0)
                    <li class="flex items-center gap-2 border-l border-gray-200 pl-4">
                        <span class="size-2.5 rounded-full bg-sky-400" aria-hidden="true"></span>
                        <strong class="font-semibold">{{ $summary['leave'] }}</strong>
                        <span class="font-medium text-gray-400">On Leave</span>
                    </li>
                @endif
            </ul>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-gray-100">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-gray-900">
                            <th scope="col" class="rounded-tl-2xl px-5 py-3 font-medium">Employee Name</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="rounded-tr-2xl px-5 py-3 font-medium">Check-in</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$row['name']" :photo="$row['photo']" size="size-10" />
                                        <span class="font-normal text-gray-900">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-md border px-3 py-1 text-xs font-medium {{ $badges[$row['status']][1] }}">{{ $badges[$row['status']][0] }}</span>
                                </td>
                                <td class="px-5 py-3 tabular-nums text-gray-900">{{ $row['check_in'] ?? '-' }}</td>
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
        <div class="space-y-4">

            {{-- Announcement / Submission / Task --}}
            <section x-data="{ tab: 'announcement' }" class="rounded-2xl bg-white shadow-card" aria-label="Pengumuman, pengajuan, dan tugas">
                <div role="tablist" class="flex gap-6 border-b border-gray-200 px-5 pt-5">
                    @foreach (['announcement' => 'Announcement', 'submission' => 'Submission', 'task' => 'Task'] as $key => $label)
                        <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                                :aria-selected="(tab === '{{ $key }}').toString()"
                                @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-400 hover:text-gray-700'"
                                class="-mb-px flex items-center gap-1.5 border-b-4 pb-2.5 text-xs font-medium focus-visible:outline-2 focus-visible:outline-blue-500">
                            {{ $label }}
                            @if ($key === 'submission' && $pendingTotal > 0)
                                <span class="rounded-full bg-blue-600 px-1.5 text-[11px] font-semibold leading-5 text-white">{{ $pendingTotal }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Announcement --}}
                <div role="tabpanel" id="panel-announcement" aria-labelledby="tab-announcement" x-show="tab === 'announcement'" class="p-5">
                    <form method="GET" action="{{ route('dashboard') }}" role="search" class="flex gap-3">
                        <div class="relative min-w-0 flex-1">
                            <label for="announcement-search" class="sr-only">Cari pengumuman</label>
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500"><x-icon name="search" size="size-4" /></span>
                            <input id="announcement-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search"
                                   class="w-full rounded-md border border-gray-300 py-2.5 pl-9 pr-3 text-xs text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div class="relative w-36 shrink-0">
                            <label for="announcement-period" class="sr-only">Filter periode</label>
                            <select id="announcement-period" name="period" @change="$el.form.requestSubmit()"
                                    class="w-full appearance-none rounded-md border border-gray-300 bg-white py-2.5 pl-3 pr-9 text-xs text-gray-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">Filter</option>
                                <option value="week" @selected($period === 'week')>This week</option>
                                <option value="month" @selected($period === 'month')>This month</option>
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                        </div>
                    </form>

                    <div class="min-h-24">
                        @if ($announcements->isNotEmpty())
                            <ul class="mt-4 space-y-4">
                                @foreach ($announcements as $a)
                                    <li>
                                        <p class="text-sm font-medium text-gray-900">{{ $a->title }}</p>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-gray-600">{{ $a->body }}</p>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ $a->published_at->locale('id')->translatedFormat('j F Y') }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex min-h-24 flex-col items-center justify-center text-center text-xs text-gray-900">
                                @if ($search !== '' || $period)
                                    <p>No announcement matches your search</p>
                                    <a href="{{ route('dashboard') }}" class="mt-1 font-medium text-blue-600 hover:underline">Reset</a>
                                @else
                                    <p>There is no announcement</p>
                                    <p class="mt-2">at this time</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($canAnnounce)
                        <div class="mt-4 flex justify-end">
                            @if ($has('announcements.create'))
                                <a href="{{ route('announcements.create') }}"
                                   class="flex items-center gap-3 rounded-full bg-sky-100 py-1.5 pl-1.5 pr-5 text-[11px] text-gray-800 shadow-md hover:bg-sky-200 focus-visible:outline-2 focus-visible:outline-blue-500">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-blue-500 text-white"><x-icon name="plus" size="size-4" /></span>
                                    Add Announcement
                                </a>
                            @else
                                <button type="button" disabled aria-disabled="true" title="Form pengumuman belum tersedia"
                                        class="flex cursor-not-allowed items-center gap-3 rounded-full bg-sky-100 py-1.5 pl-1.5 pr-5 text-[11px] text-gray-500 shadow-md">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-blue-400 text-white"><x-icon name="plus" size="size-4" /></span>
                                    Add Announcement
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Submission: pengajuan izin/cuti yang menunggu persetujuan --}}
                <div role="tabpanel" id="panel-submission" aria-labelledby="tab-submission" x-cloak x-show="tab === 'submission'" class="min-h-72 p-5">
                    @if ($submissions->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($submissions as $s)
                                <li class="py-3 first:pt-0 last:pb-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $s->employee?->user?->name ?? '-' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $s->leaveType?->name }},
                                        {{ $s->start_date->locale('id')->translatedFormat('j M') }}
                                        @if (! $s->start_date->isSameDay($s->end_date))
                                            – {{ $s->end_date->locale('id')->translatedFormat('j M') }}
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                        @if ($pendingTotal > $submissions->count())
                            <p class="mt-3 text-xs text-gray-400">+{{ $pendingTotal - $submissions->count() }} pengajuan lainnya menunggu persetujuan.</p>
                        @endif
                    @else
                        <p class="pt-24 text-center text-xs text-gray-900">There is no submission at this time</p>
                    @endif
                </div>

                {{-- Task --}}
                <div role="tabpanel" id="panel-task" aria-labelledby="tab-task" x-cloak x-show="tab === 'task'" class="min-h-72 p-5">
                    @if ($tasks->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($tasks as $t)
                                <li class="py-3 first:pt-0 last:pb-0">
                                    <p class="text-sm font-medium text-gray-900">{{ $t->title }}</p>
                                    @if ($t->due_date)
                                        <p class="text-xs text-gray-500">Tenggat {{ $t->due_date->locale('id')->translatedFormat('j F Y') }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="pt-24 text-center text-xs text-gray-900">There is no task at this time</p>
                    @endif
                </div>
            </section>

            {{-- Schedule --}}
            <section x-data="{ filter: 'all' }" class="rounded-2xl bg-white p-5 shadow-card" aria-labelledby="schedule-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="schedule-heading" class="text-base font-semibold text-gray-900">Schedule ({{ $scheduleTotal }})</h2>
                    <div class="flex items-center gap-3">
                        <div class="flex rounded-xl bg-sky-100 p-0.5 text-sm" role="group" aria-label="Filter jadwal">
                            @foreach (['all' => 'All', 'meeting' => 'Meetings', 'event' => 'Event'] as $key => $label)
                                <button type="button" @click="filter = '{{ $key }}'"
                                        :aria-pressed="(filter === '{{ $key }}').toString()"
                                        :class="filter === '{{ $key }}' ? 'bg-gradient-to-r from-cyan-500 to-indigo-700 text-white' : 'text-gray-800 hover:bg-sky-200'"
                                        class="rounded-[10px] px-4 py-1.5 font-normal focus-visible:outline-2 focus-visible:outline-blue-500">{{ $label }}</button>
                            @endforeach
                        </div>
                        @if ($has('schedules.index'))
                            <a href="{{ route('schedules.index') }}" aria-label="Buka kalender"
                               class="flex size-9 items-center justify-center rounded-lg border border-blue-500 text-gray-800 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                                <img src="{{ asset('images/Calendar.png') }}" alt="" aria-hidden="true" class="size-4">
                            </a>
                        @else
                            <span aria-disabled="true" title="Kalender belum tersedia"
                                  class="flex size-9 cursor-not-allowed items-center justify-center rounded-lg border border-blue-200 text-gray-400">
                                <img src="{{ asset('images/Calendar.png') }}" alt="" aria-hidden="true" class="size-4">
                            </span>
                        @endif
                    </div>
                </div>

                @if ($schedules->isNotEmpty())
                    <ul class="mt-5 space-y-4">
                        @foreach ($schedules as $e)
                            @php $extra = $e->attendees->count() - 3; @endphp
                            <li x-show="filter === 'all' || filter === '{{ $e->type }}'"
                                class="flex items-center justify-between gap-3 rounded-xl border border-blue-600 p-4">
                                <div class="min-w-0">
                                    <p class="flex items-center gap-2 text-xs font-medium text-gray-900">
                                        <x-icon :name="$e->type === 'meeting' ? 'employees' : 'calendar'" size="size-4" />
                                        <span class="truncate">{{ $e->title }}</span>
                                    </p>
                                    <p class="mt-2 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500">
                                        <span>{{ $e->start_at->locale('id')->translatedFormat('j F, Y') }}</span>
                                        <span class="size-2 rounded-full bg-gray-300" aria-hidden="true"></span>
                                        <span class="tabular-nums">{{ $e->start_at->format('H.i') }} - {{ $e->end_at->format('H.i') }}</span>
                                    </p>
                                    @if ($e->location)
                                        <p class="mt-2 flex items-center gap-2 text-[11px] text-gray-900">
                                            <x-icon name="video" size="size-4" class="text-blue-600" />
                                            {{ $e->location }}
                                            @if ($safeUrl($e->meeting_url))
                                                <a href="{{ $e->meeting_url }}" target="_blank" rel="noopener noreferrer"
                                                   aria-label="Buka tautan {{ $e->title }}" class="hover:text-blue-600">
                                                    <x-icon name="arrow-right" size="size-4" />
                                                </a>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                @if ($e->attendees->isNotEmpty())
                                    <div class="flex shrink-0 -space-x-3" aria-label="{{ $e->attendees->count() }} peserta">
                                        @foreach ($e->attendees->take(3) as $p)
                                            <x-avatar :name="$p->user?->name ?? ''" :photo="$p->photo_url" size="size-10" class="border border-blue-600 bg-white" />
                                        @endforeach
                                        @if ($extra > 0)
                                            <span class="flex size-10 items-center justify-center rounded-full border border-blue-600 bg-blue-50 text-xs font-medium text-blue-700">+{{ $extra }}</span>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="py-12 text-center text-xs text-gray-900">There is no schedule at this time</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
