@php
    use App\Models\Employee;

    // Format rupiah untuk gaji
    $rupiah = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

    // Baris detail: label + nilai (kosong ditampilkan "-")
    $rows = [
        'Employee ID' => $employee->nik,
        'Email' => $employee->user?->email,
        'Gender' => Employee::GENDER[$employee->jenis_kelamin] ?? null,
        'Tanggal lahir' => $employee->tanggal_lahir?->locale('id')->translatedFormat('j F Y'),
        'No. telepon' => $employee->no_telepon,
        'Alamat' => $employee->alamat,
    ];
    $work = [
        'Jabatan' => $employee->jabatan,
        'Divisi' => $employee->department?->nama_departemen,
        'Atasan langsung' => $employee->supervisor?->nama_lengkap,
        'Status kepegawaian' => Employee::STATUS_KEPEGAWAIAN[$employee->status_kepegawaian] ?? null,
        'Tanggal masuk' => $employee->tanggal_masuk?->locale('id')->translatedFormat('j F Y'),
        'Sisa cuti tahunan' => $employee->sisa_cuti.' hari',
    ];
@endphp

<x-app-layout :title="$employee->nama_lengkap">

    <x-slot:heading>
        <h1 class="break-words text-xl font-semibold text-gray-900 sm:text-2xl">{{ $employee->nama_lengkap }}</h1>
        <p class="mt-0.5 text-xs text-gray-700">{{ $employee->jabatan ?: 'Detail karyawan' }}</p>
    </x-slot:heading>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    {{-- Kepala profil + tombol aksi --}}
    <section class="rounded-2xl bg-white p-5 shadow-card sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <x-avatar :name="$employee->nama_lengkap" :photo="$employee->photo_url" size="size-16" />
                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ $employee->nama_lengkap }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-md px-2.5 py-1 font-medium {{ $employee->status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                            {{ $employee->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        <span class="text-gray-500">{{ Employee::STATUS_KEPEGAWAIAN[$employee->status_kepegawaian] ?? '' }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('employees.index') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-xs text-gray-700 hover:bg-gray-50">Kembali</a>
                @if ($canUpdate)
                    <a href="{{ route('employees.edit', $employee) }}" class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-xs font-medium text-white hover:bg-blue-700">
                        <x-icon name="pencil" size="size-4" /> Edit
                    </a>
                @endif
                @if ($canToggle)
                    <form method="POST" action="{{ route('employees.status', $employee) }}"
                          @if ($employee->status === 'aktif') onsubmit="return confirm('Nonaktifkan karyawan ini? Akun login-nya juga tidak akan bisa dipakai.')" @endif>
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $employee->status === 'aktif' ? 'nonaktif' : 'aktif' }}">
                        <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-lg border px-4 text-xs font-medium {{ $employee->status === 'aktif' ? 'border-red-300 text-red-700 hover:bg-red-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                            <x-icon :name="$employee->status === 'aktif' ? 'power' : 'check'" size="size-4" />
                            {{ $employee->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    {{-- Data pribadi dan kepegawaian berdampingan di layar lebar --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach (['Data pribadi' => $rows, 'Kepegawaian' => $work] as $title => $items)
            <section class="rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-label="{{ $title }}">
                <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                <dl class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach ($items as $label => $value)
                        <div class="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="min-w-0 break-words text-gray-900">{{ $value !== null && $value !== '' ? $value : '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    {{-- Data sensitif: hanya untuk peran yang berhak (Super Admin, HRD, CEO) --}}
    @if ($sensitive)
        <section class="mt-6 rounded-2xl bg-white p-5 shadow-card sm:p-6" aria-label="Gaji dan dokumen">
            <h2 class="text-base font-semibold text-gray-900">Gaji, rekening, dan dokumen</h2>
            <dl class="mt-4 divide-y divide-gray-100 text-sm">
                @foreach ([
                    'Gaji pokok' => $rupiah($employee->gaji_pokok),
                    'Bank' => $employee->nama_bank,
                    'No. rekening' => $employee->no_rekening,
                    'Pemilik rekening' => $employee->nama_pemilik_rekening,
                ] as $label => $value)
                    <div class="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="min-w-0 break-words text-gray-900">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
                <div class="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
                    <dt class="text-gray-500">Scan KTP</dt>
                    <dd>
                        @if ($employee->dokumen_ktp)
                            <a href="{{ route('employees.ktp', $employee) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Lihat dokumen</a>
                        @else
                            <span class="text-gray-900">-</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    @endif
</x-app-layout>
