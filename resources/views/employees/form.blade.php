@php
    use App\Models\Employee;

    // Mode: tambah (employee null) atau ubah
    $editing = $employee !== null;
    $action = $editing ? route('employees.update', $employee) : route('employees.store');

    // Nilai isian: input lama (setelah error validasi) > data karyawan > kosong
    $val = fn (string $field, $default = null) => old($field, $employee?->{$field} ?? $default);

    // Gaya isian seragam
    $input = 'h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
    $select = $input.' appearance-none pr-9';
    $card = 'rounded-2xl bg-white p-5 shadow-card sm:p-6';
    $legend = 'text-base font-semibold text-gray-900';
@endphp

<x-app-layout :title="$editing ? 'Ubah Karyawan' : 'Tambah Karyawan'">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ $editing ? 'Ubah Karyawan' : 'Tambah Karyawan' }}</h1>
        <p class="mt-0.5 text-xs text-gray-700">{{ $editing ? $employee->nama_lengkap : 'Data karyawan baru beserta akun login' }}</p>
    </x-slot:heading>

    {{-- Ringkasan error validasi di atas formulir --}}
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Ada isian yang perlu diperbaiki. Periksa kolom yang diberi tanda merah.
        </div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- ================= AKUN LOGIN (hanya saat tambah) ================= --}}
        @unless ($editing)
            <section class="{{ $card }}" aria-labelledby="sec-account">
                <h2 id="sec-account" class="{{ $legend }}">Akun login</h2>
                <p class="mt-1 text-xs text-gray-500">Akun langsung aktif dan disetujui. Berikan kata sandi awal kepada karyawan.</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-field name="email" label="Email" required>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="150" autocomplete="off" class="{{ $input }}">
                    </x-field>
                    <x-field name="password" label="Kata sandi awal" hint="Minimal 8 karakter, mengandung huruf dan angka." required>
                        <input id="password" name="password" type="password" autocomplete="new-password" maxlength="72" class="{{ $input }}">
                    </x-field>

                    {{-- Peran hanya bisa dipilih Super Admin; selain itu otomatis karyawan --}}
                    @if ($canChooseRole)
                        <x-field name="role" label="Peran akun">
                            <div class="relative">
                                <select id="role" name="role" class="{{ $select }}">
                                    @foreach (['karyawan' => 'Karyawan', 'supervisor' => 'Supervisor', 'hrd' => 'HRD', 'direktur' => 'CEO/Direktur'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('role', 'karyawan') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                            </div>
                        </x-field>
                    @endif
                </div>
            </section>
        @endunless

        {{-- ================= DATA PRIBADI ================= --}}
        <section class="{{ $card }}" aria-labelledby="sec-personal">
            <h2 id="sec-personal" class="{{ $legend }}">Data pribadi</h2>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-field name="nama_lengkap" label="Nama lengkap" required>
                    <input id="nama_lengkap" name="nama_lengkap" type="text" value="{{ $val('nama_lengkap') }}" maxlength="150" class="{{ $input }}">
                </x-field>
                <x-field name="nik" label="Employee ID (NIK)" hint="Unik; huruf, angka, titik, strip." required>
                    <input id="nik" name="nik" type="text" value="{{ $val('nik') }}" maxlength="20" class="{{ $input }}">
                </x-field>
                <x-field name="jenis_kelamin" label="Gender">
                    <div class="relative">
                        <select id="jenis_kelamin" name="jenis_kelamin" class="{{ $select }}">
                            <option value="">Belum diisi</option>
                            @foreach (Employee::GENDER as $value => $label)
                                <option value="{{ $value }}" @selected($val('jenis_kelamin') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                    </div>
                </x-field>
                <x-field name="tanggal_lahir" label="Tanggal lahir">
                    <input id="tanggal_lahir" name="tanggal_lahir" type="date" value="{{ $val('tanggal_lahir') instanceof \Carbon\CarbonInterface ? $val('tanggal_lahir')->toDateString() : $val('tanggal_lahir') }}" class="{{ $input }}">
                </x-field>
                <x-field name="no_telepon" label="No. telepon">
                    <input id="no_telepon" name="no_telepon" type="tel" value="{{ $val('no_telepon') }}" maxlength="20" class="{{ $input }}">
                </x-field>
                <x-field name="alamat" label="Alamat" class="md:col-span-2">
                    <textarea id="alamat" name="alamat" rows="3" maxlength="1000" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">{{ $val('alamat') }}</textarea>
                </x-field>
            </div>
        </section>

        {{-- ================= KEPEGAWAIAN ================= --}}
        <section class="{{ $card }}" aria-labelledby="sec-work">
            <h2 id="sec-work" class="{{ $legend }}">Kepegawaian</h2>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-field name="jabatan" label="Jabatan">
                    <input id="jabatan" name="jabatan" type="text" value="{{ $val('jabatan') }}" maxlength="100" class="{{ $input }}">
                </x-field>
                <x-field name="id_departemen" label="Divisi">
                    <div class="relative">
                        <select id="id_departemen" name="id_departemen" class="{{ $select }}">
                            <option value="">Belum diisi</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id_departemen }}" @selected((int) $val('id_departemen') === $d->id_departemen)>{{ $d->nama_departemen }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                    </div>
                </x-field>
                <x-field name="id_atasan" label="Atasan langsung" hint="Penerima notifikasi dan penyetuju absensi karyawan ini.">
                    <div class="relative">
                        <select id="id_atasan" name="id_atasan" class="{{ $select }}">
                            <option value="">Tanpa atasan (ditangani HRD)</option>
                            @foreach ($supervisors as $s)
                                <option value="{{ $s->id_karyawan }}" @selected((int) $val('id_atasan') === $s->id_karyawan)>{{ $s->nama_lengkap }}@if ($s->jabatan) ({{ $s->jabatan }})@endif</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                    </div>
                </x-field>
                <x-field name="status_kepegawaian" label="Status kepegawaian" required>
                    <div class="relative">
                        <select id="status_kepegawaian" name="status_kepegawaian" class="{{ $select }}">
                            @foreach (Employee::STATUS_KEPEGAWAIAN as $value => $label)
                                <option value="{{ $value }}" @selected($val('status_kepegawaian', 'tetap') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-500"><x-icon name="chevron-down" size="size-4" /></span>
                    </div>
                </x-field>
                <x-field name="tanggal_masuk" label="Tanggal masuk">
                    <input id="tanggal_masuk" name="tanggal_masuk" type="date" value="{{ $val('tanggal_masuk') instanceof \Carbon\CarbonInterface ? $val('tanggal_masuk')->toDateString() : $val('tanggal_masuk') }}" class="{{ $input }}">
                </x-field>
                <x-field name="sisa_cuti" label="Sisa cuti tahunan (hari)">
                    <input id="sisa_cuti" name="sisa_cuti" type="number" min="0" max="365" value="{{ $val('sisa_cuti', 12) }}" class="{{ $input }}">
                </x-field>
            </div>
        </section>

        {{-- ================= GAJI & REKENING ================= --}}
        <section class="{{ $card }}" aria-labelledby="sec-pay">
            <h2 id="sec-pay" class="{{ $legend }}">Gaji dan rekening</h2>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-field name="gaji_pokok" label="Gaji pokok per bulan (Rp)">
                    <input id="gaji_pokok" name="gaji_pokok" type="number" min="0" step="0.01" inputmode="decimal" value="{{ $val('gaji_pokok', 0) }}" class="{{ $input }}">
                </x-field>
                <x-field name="nama_bank" label="Nama bank">
                    <input id="nama_bank" name="nama_bank" type="text" value="{{ $val('nama_bank') }}" maxlength="100" class="{{ $input }}">
                </x-field>
                <x-field name="no_rekening" label="No. rekening">
                    <input id="no_rekening" name="no_rekening" type="text" inputmode="numeric" value="{{ $val('no_rekening') }}" maxlength="50" class="{{ $input }}">
                </x-field>
                <x-field name="nama_pemilik_rekening" label="Nama pemilik rekening">
                    <input id="nama_pemilik_rekening" name="nama_pemilik_rekening" type="text" value="{{ $val('nama_pemilik_rekening') }}" maxlength="150" class="{{ $input }}">
                </x-field>
            </div>
        </section>

        {{-- ================= DOKUMEN ================= --}}
        <section class="{{ $card }}" aria-labelledby="sec-doc">
            <h2 id="sec-doc" class="{{ $legend }}">Dokumen</h2>

            <div class="mt-5">
                <x-field name="dokumen_ktp" label="Scan KTP" hint="JPG, PNG, atau PDF, maksimal 4 MB. Disimpan privat.">
                    <input id="dokumen_ktp" name="dokumen_ktp" type="file" accept=".jpg,.jpeg,.png,.pdf,image/*,application/pdf"
                           class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                </x-field>
                @if ($editing && $employee->dokumen_ktp)
                    <p class="mt-2 text-xs text-gray-500">
                        Sudah ada scan KTP.
                        <a href="{{ route('employees.ktp', $employee) }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Lihat</a>
                        (mengunggah file baru akan menggantikannya).
                    </p>
                @endif
            </div>
        </section>

        {{-- Tombol aksi: tumpuk di layar kecil --}}
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ $editing ? route('employees.show', $employee) : route('employees.index') }}"
               class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-6 text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-blue-500">Batal</a>
            <button type="submit"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-700 px-6 text-sm font-medium text-white shadow-card hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                {{ $editing ? 'Simpan perubahan' : 'Tambah karyawan' }}
            </button>
        </div>
    </form>
</x-app-layout>
