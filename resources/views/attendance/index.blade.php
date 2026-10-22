<x-app-layout title="Absensi">
    <x-slot:heading>
        <div class="flex items-center gap-3">
            <h1 class="text-[2rem] font-semibold tracking-tight text-slate-900">Absensi</h1>
        </div>
        <p class="mt-1 text-sm text-slate-500">Rekap kehadiran karyawan</p>
    </x-slot:heading>

    <div class="space-y-5 pb-2">
        <section class="rounded-[18px] border border-slate-200 bg-white p-4 shadow-[0_2px_12px_rgba(15,23,42,0.03)] sm:p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-[1.05rem] font-semibold text-slate-800">Kehadiran</h2>
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-500 transition hover:bg-slate-100" aria-label="Lihat detail">
                    <x-icon name="external" size="size-4" />
                </button>
            </div>

            <div class="mt-4 grid overflow-hidden rounded-2xl border border-slate-200 bg-[#f8fafc] md:grid-cols-3">
                <div class="px-4 py-4 text-center sm:px-5">
                    <div class="text-[0.9rem] font-semibold text-slate-700">Present</div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#2b8ef7]">{{ $summary['present']['on_time'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">On Time</div>
                        </div>
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#2b8ef7]">{{ $summary['present']['early_clock_in'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">Early Clock In</div>
                        </div>
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#2b8ef7]">{{ $summary['present']['late_clock_in'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">Late Clock In</div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-4 py-4 text-center sm:px-5 md:border-l md:border-t-0">
                    <div class="text-[0.9rem] font-semibold text-slate-700">Absent</div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#e85d5d]">{{ $summary['absent']['leaved'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">Leaved</div>
                        </div>
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#e85d5d]">{{ $summary['absent']['no_clock_in'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">No Clock In</div>
                        </div>
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#e85d5d]">{{ $summary['absent']['no_clock_out'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">No Clock Out</div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 px-4 py-4 text-center sm:px-5 md:border-l md:border-t-0">
                    <div class="text-[0.9rem] font-semibold text-slate-700">Away</div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#f0a93d]">{{ $summary['away']['total'] }}</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">Day Off</div>
                        </div>
                        <div>
                            <div class="text-[1.8rem] font-semibold leading-none text-[#f0a93d]">0</div>
                            <div class="mt-2 text-[0.7rem] text-slate-500">Time Off</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[18px] border border-slate-200 bg-white p-4 shadow-[0_2px_12px_rgba(15,23,42,0.03)] sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <h2 class="shrink-0 text-[1.05rem] font-semibold text-[#183153] xl:whitespace-nowrap">Persetujuan Absensi</h2>

                <div class="flex flex-wrap items-center justify-end gap-2 xl:flex-nowrap">
                    <div class="flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[#dce5f0] bg-[#f8fafc] px-3 text-xs font-medium text-[#8193ad]">
                        <x-icon name="filter" size="size-4" />
                        1/3
                    </div>

                    <form method="GET" action="{{ route('attendance.index') }}" class="flex flex-wrap items-center gap-2 xl:flex-nowrap">
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <x-icon name="search" size="size-4" />
                            </span>
                            <input type="search" name="search" value="{{ $search }}" placeholder="Search" class="h-10 w-36 rounded-xl border border-[#dce5f0] bg-[#f8fafc] pl-9 pr-3 text-sm text-[#183153] placeholder:text-[#8a9bb5] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                        </div>

                        <div class="relative">
                            <input type="date" name="date" value="{{ $date }}" class="h-10 w-[11.5rem] rounded-xl border border-[#dce5f0] bg-[#f8fafc] px-3 pr-9 text-sm text-[#183153] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <x-icon name="calendar" size="size-4" />
                            </span>
                        </div>

                        <div class="relative">
                            <select name="division" class="h-10 w-[8.5rem] appearance-none rounded-xl border border-[#dce5f0] bg-[#f8fafc] px-3 pr-9 text-sm text-[#183153] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                                @foreach ($divisions as $key => $label)
                                    <option value="{{ $key }}" @selected($division === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <x-icon name="chevron-down" size="size-4" class="text-[#8a9bb5]" />
                            </span>
                        </div>

                        <div class="relative">
                            <select name="status" class="h-10 w-[8.5rem] appearance-none rounded-xl border border-[#dce5f0] bg-[#f8fafc] px-3 pr-9 text-sm text-[#183153] focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                <x-icon name="chevron-down" size="size-4" class="text-[#8a9bb5]" />
                            </span>
                        </div>

                        <button type="submit" class="h-10 rounded-xl border border-[#dce5f0] bg-white px-4 text-sm font-medium text-[#183153] shadow-sm transition hover:bg-slate-50">Filter</button>
                    </form>

                    <div class="flex items-center gap-2">
                        <button type="button" class="h-10 inline-flex items-center gap-2 rounded-xl border border-[#d5ead9] bg-[#edf8ef] px-4 text-sm font-medium text-[#159447] transition hover:bg-[#e5f4e8]">
                            <x-icon name="plus" size="size-4" />
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-sm text-slate-700">
                        <thead>
                            <tr class="bg-[#f0f2f5] text-[#27364a]">
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Employee Name</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Divisi</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Check-in</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Check-out</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Evidence</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Status</th>
                                <th class="px-4 py-3 text-[0.8rem] font-semibold">Other</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($rows as $employee)
                                <tr class="align-middle">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-[#f2f4f7] to-[#dfe7f2] text-[0.72rem] font-semibold text-slate-700">{{ strtoupper(\Illuminate\Support\Str::substr($employee['name'], 0, 2)) }}</span>
                                            <span class="font-medium text-slate-800">{{ $employee['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $employee['division'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-slate-600">{{ $employee['check_in'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-slate-600">{{ $employee['check_out'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[0.74rem] text-slate-700">{{ $employee['evidence'] }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($employee['status_key'] === 'late')
                                            <span class="inline-flex rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[0.72rem] font-semibold text-red-500">Late</span>
                                        @else
                                            <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[0.72rem] font-semibold text-emerald-600">On-Time</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100">
                                            Action
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path d="M7 10l5 5 5-5"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-slate-500">Tidak ada data absensi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-center gap-2">
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm hover:bg-slate-50">‹</button>
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 text-blue-600 shadow-sm">1</button>
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50">2</button>
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50">3</button>
                <button type="button" class="flex size-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50">›</button>
            </div>
        </section>
    </div>
</x-app-layout>
