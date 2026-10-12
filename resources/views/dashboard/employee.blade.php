<x-app-layout title="Dashboard">
    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Selamat {{ $greeting }}, {{ $user->name }} <span aria-hidden="true">👋</span></h1>
        <p class="mt-0.5 text-xs text-gray-700">Hari ini {{ $dateLabel }}.</p>
    </x-slot:heading>

    <section class="max-w-md rounded-2xl bg-white p-6 shadow-card" aria-labelledby="today-heading">
        <h2 id="today-heading" class="text-base font-semibold text-gray-900">Absensi hari ini</h2>

        @if (! $employee)
            <p class="mt-3 text-sm text-gray-500">Data karyawan Anda belum dilengkapi. Hubungi HRD agar absensi dapat dicatat.</p>
        @elseif ($checkIn)
            <p class="mt-3 text-3xl font-semibold tabular-nums text-gray-900">{{ $checkIn }}</p>
            <p class="mt-1 text-sm text-gray-500">Check-in tercatat.</p>
            <span class="mt-3 inline-flex rounded-md border px-3 py-1 text-xs font-medium {{ $isLate ? 'border-red-500 bg-red-50 text-red-600' : 'border-green-500 bg-green-50 text-green-600' }}">
                {{ $isLate ? 'Late' : 'On-Time' }}
            </span>
        @else
            <p class="mt-3 text-sm text-gray-500">Anda belum check-in hari ini. Jam masuk {{ $workStart }}.</p>
        @endif
    </section>
</x-app-layout>
