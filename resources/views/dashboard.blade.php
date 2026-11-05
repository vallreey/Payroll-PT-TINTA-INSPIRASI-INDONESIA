<x-app-layout title="Dashboard">

    <h1 class="text-2xl font-bold text-gray-900">
        Selamat datang, {{ auth()->user()->name }}
    </h1>
    <p class="mt-1 text-gray-500">
        Role: <span class="font-medium capitalize">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
    </p>

    @if (auth()->user()->role === 'super_admin')
        <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="font-semibold text-gray-800">Persetujuan Pendaftaran Akun</h2>
            <p class="mt-1 text-sm text-gray-500">
                Kelola akun karyawan baru yang mendaftar dan menunggu persetujuan Anda.
            </p>
            <a href="{{ route('admin.user-approvals.index') }}"
               class="mt-4 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-cyan-400 to-indigo-600 px-5 py-2.5 text-white text-sm font-semibold shadow-md hover:opacity-95 transition">
                Buka Halaman Persetujuan
            </a>
        </div>
    @else
        <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-6 text-gray-500 text-sm">
            Halaman dashboard karyawan akan menyusul dibuat.
        </div>
    @endif

</x-app-layout>
