<x-app-layout title="Persetujuan Akun">

    <h1 class="text-2xl font-bold text-gray-900">Persetujuan Pendaftaran Akun</h1>
    <p class="mt-1 text-gray-500">Setujui atau tolak akun karyawan yang baru mendaftar.</p>

    {{-- Tabs status --}}
    <div class="mt-6 flex gap-2 border-b border-gray-200">
        @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
            <a href="{{ route('admin.user-approvals.index', ['status' => $key]) }}"
               class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition
                      {{ $status === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                <span class="ml-1 text-xs text-gray-400">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>

    {{-- Tabel --}}
    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-5 py-3">Nama</th>
                    <th class="px-5 py-3">NIK</th>
                    <th class="px-5 py-3">Email</th>
                    <th class="px-5 py-3">Tanggal Daftar</th>
                    @if ($status !== 'pending')
                        <th class="px-5 py-3">Diproses Oleh</th>
                    @endif
                    @if ($status === 'pending')
                        <th class="px-5 py-3 text-right">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-gray-800">{{ $user->name }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $user->employee->nik ?? '-' }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $user->email }}</td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $user->created_at->format('d M Y H:i') }}</td>

                        @if ($status !== 'pending')
                            <td class="px-5 py-3.5 text-gray-500">
                                {{ $user->approvedBy?->name ?? '-' }}
                                @if ($user->approved_at)
                                    <span class="block text-xs text-gray-400">{{ $user->approved_at->format('d M Y H:i') }}</span>
                                @endif
                            </td>
                        @endif

                        @if ($status === 'pending')
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.user-approvals.approve', $user) }}"
                                          onsubmit="return confirm('Setujui akun {{ $user->name }}?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="rounded-lg bg-green-50 border border-green-200 px-3.5 py-1.5 text-green-700 font-medium hover:bg-green-100 transition">
                                            Setujui
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.user-approvals.reject', $user) }}"
                                          onsubmit="return confirm('Tolak akun {{ $user->name }}?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="rounded-lg bg-red-50 border border-red-200 px-3.5 py-1.5 text-red-700 font-medium hover:bg-red-100 transition">
                                            Tolak
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-gray-400">
                            Tidak ada akun dengan status ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

</x-app-layout>
