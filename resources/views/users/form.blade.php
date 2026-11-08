@php
    use App\Models\User;

    // Mode: buat akun (account null) atau ubah akun
    $editing = $account !== null;
    $isSelf = $editing && $account->is(auth()->user());

    // Nilai isian: input lama (setelah error validasi) > data akun > bawaan
    $val = fn (string $field, $default = null) => old($field, $account?->{$field} ?? $default);

    // Gaya isian seragam
    $input = 'h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
    $select = $input.' appearance-none pr-9';
    $card = 'rounded-2xl bg-white p-5 shadow-card sm:p-6';
    $legend = 'text-base font-semibold text-gray-900';

    $statusLabel = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'menunggu' => 'Menunggu persetujuan', 'ditolak' => 'Ditolak'];
@endphp

<x-app-layout :title="$editing ? 'Edit Akun' : 'Buat Akun'">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ $editing ? 'Edit Akun' : 'Buat Akun' }}</h1>
        <p class="mt-0.5 break-all text-xs text-gray-700">{{ $editing ? $account->email : 'Akun baru langsung aktif dan disetujui' }}</p>
    </x-slot:heading>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Ada isian yang perlu diperbaiki. Periksa kolom yang diberi tanda merah.</div>
    @endif

    <div class="mx-auto max-w-3xl space-y-6">

        {{-- ================= DATA AKUN ================= --}}
        {{-- x-data: kolom karyawan hanya tampil bila peran bukan Super Admin (saat membuat akun) --}}
        <form method="POST" action="{{ $editing ? route('users.update', $account) : route('users.store') }}" novalidate
              x-data="{ role: '{{ old('role', $account?->role ?? 'karyawan') }}' }" class="space-y-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <section class="{{ $card }}" aria-labelledby="sec-account">
                <h2 id="sec-account" class="{{ $legend }}">Akun</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-field name="role" label="Peran" required>
                        @if ($isSelf)
                            {{-- Peran sendiri tidak bisa diubah: tampil saja, dikirim lewat input tersembunyi --}}
                            <input type="hidden" name="role" value="{{ $account->role }}">
                            <p class="flex h-11 items-center rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm text-gray-600">{{ User::ROLES[$account->role] }}</p>
                        @else
                            <div class="relative">
                                <select id="role" name="role" x-model="role" class="{{ $select }}">
                                    @foreach (User::ROLES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                            </div>
                        @endif
                    </x-field>

                    <x-field name="email" label="Email" required>
                        <input id="email" name="email" type="email" value="{{ $val('email') }}" maxlength="150" autocomplete="off" class="{{ $input }}">
                    </x-field>

                    <x-field name="username" label="Username" hint="Wajib untuk Super Admin (dipakai sebagai nama tampil).">
                        <input id="username" name="username" type="text" value="{{ $val('username') }}" maxlength="100" autocomplete="off" class="{{ $input }}">
                    </x-field>

                    @unless ($editing)
                        <x-field name="password" label="Kata sandi awal" hint="Minimal 8 karakter, mengandung huruf dan angka." required>
                            <input id="password" name="password" type="password" autocomplete="new-password" maxlength="72" class="{{ $input }}">
                        </x-field>
                    @endunless
                </div>
            </section>

            {{-- Data karyawan dasar: hanya saat membuat akun non-Super Admin --}}
            @unless ($editing)
                <section class="{{ $card }}" x-show="role !== 'super_admin'" x-cloak aria-labelledby="sec-employee">
                    <h2 id="sec-employee" class="{{ $legend }}">Data karyawan</h2>
                    <p class="mt-1 text-xs text-gray-500">Data lengkap (jabatan, divisi, gaji, dll.) dapat dilengkapi lewat menu Karyawan.</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <x-field name="nama_lengkap" label="Nama lengkap" required>
                            <input id="nama_lengkap" name="nama_lengkap" type="text" value="{{ old('nama_lengkap') }}" maxlength="150" class="{{ $input }}">
                        </x-field>
                        <x-field name="nik" label="Employee ID (NIK)" hint="Unik; huruf, angka, titik, strip." required>
                            <input id="nik" name="nik" type="text" value="{{ old('nik') }}" maxlength="20" class="{{ $input }}">
                        </x-field>
                    </div>
                </section>
            @endunless

            {{-- Tombol aksi: tumpuk di layar kecil --}}
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('users.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-6 text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-blue-500">Batal</a>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-700 px-6 text-sm font-medium text-white shadow-card hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                    {{ $editing ? 'Simpan perubahan' : 'Buat akun' }}
                </button>
            </div>
        </form>

        @if ($editing)
            {{-- ================= INFO AKUN ================= --}}
            <section class="{{ $card }}" aria-labelledby="sec-info">
                <h2 id="sec-info" class="{{ $legend }}">Informasi</h2>
                <dl class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach ([
                        'Status' => $statusLabel[$account->display_status],
                        'Terdaftar' => $account->created_at?->locale('id')->translatedFormat('j F Y'),
                        'Terakhir aktif' => $account->terakhir_aktif_label,
                    ] as $label => $value)
                        <div class="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ $value ?: '-' }}</dd>
                        </div>
                    @endforeach
                    <div class="grid grid-cols-[9rem_1fr] gap-3 py-2.5">
                        <dt class="text-gray-500">Data karyawan</dt>
                        <dd>
                            @if ($account->employee)
                                <a href="{{ route('employees.show', $account->employee) }}" class="text-blue-600 hover:underline">{{ $account->employee->nama_lengkap }}</a>
                            @else
                                <span class="text-gray-900">Tidak ada (akun administrasi)</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- ================= RESET KATA SANDI ================= --}}
            <form id="reset-password" method="POST" action="{{ route('users.password', $account) }}" class="{{ $card }} scroll-mt-24" aria-labelledby="sec-password" novalidate>
                @csrf @method('PUT')
                <h2 id="sec-password" class="{{ $legend }}">Reset kata sandi</h2>
                <p class="mt-1 text-xs text-gray-500">Setelah diganti, sesi login lama akun ini langsung diputus.</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-field name="password" label="Kata sandi baru" hint="Minimal 8 karakter, mengandung huruf dan angka." required>
                        <input id="password" name="password" type="password" autocomplete="new-password" maxlength="72" class="{{ $input }}">
                    </x-field>
                    <x-field name="password_confirmation" label="Ulangi kata sandi baru" required>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="72" class="{{ $input }}">
                    </x-field>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl border border-blue-600 bg-white px-6 text-sm font-medium text-blue-700 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-500">
                        <x-icon name="key" size="size-4" /> Ganti kata sandi
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
