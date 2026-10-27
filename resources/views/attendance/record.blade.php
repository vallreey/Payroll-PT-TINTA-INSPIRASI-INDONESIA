@php
    $time = fn (?string $t) => $t ? substr(str_replace(':', '.', $t), 0, 5) : '-';
    $isOut = $mode === 'keluar';
    $stateLabels = ['pending' => ['Menunggu persetujuan', 'text-amber-600'], 'approved' => ['Disetujui', 'text-green-600'], 'rejected' => ['Ditolak', 'text-red-600']];
@endphp

<x-app-layout title="Catat Absensi">

    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Catat Absensi</h1>
        <p class="mt-0.5 text-xs text-gray-700">{{ $now->locale('id')->translatedFormat('l, j F Y') }}, jam masuk {{ $workStart }}</p>
    </x-slot:heading>

    <div class="mx-auto max-w-md space-y-4">

        @if (session('status'))
            <div role="status" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
            </div>
        @endif

        @if (! $employee)
            <section class="rounded-2xl bg-white p-6 text-center shadow-card">
                <p class="text-sm text-gray-700">Akun Anda belum terhubung dengan data karyawan, sehingga absensi tidak dapat dicatat.</p>
                <p class="mt-1 text-xs text-gray-500">Hubungi HRD untuk melengkapi data karyawan Anda.</p>
            </section>
        @else
            {{-- Status hari ini --}}
            <section class="rounded-2xl bg-white p-5 shadow-card" aria-labelledby="today-heading">
                <h2 id="today-heading" class="text-sm font-semibold text-gray-900">Absensi hari ini</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-gray-500">Check-in</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $time($today?->jam_masuk) }}</dd>
                        @if ($today)
                            <dd class="mt-1 text-xs {{ $today->status === 'terlambat' ? 'text-red-600' : 'text-green-600' }}">{{ $today->status === 'terlambat' ? 'Terlambat' : 'Tepat waktu' }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Check-out</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $time($today?->jam_keluar) }}</dd>
                    </div>
                </dl>
                @if ($today)
                    <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                        Persetujuan atasan:
                        <span class="font-medium {{ $stateLabels[$today->status_persetujuan][1] }}">{{ $stateLabels[$today->status_persetujuan][0] }}</span>
                    </p>
                @endif
                @unless ($isWorkingDay)
                    <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">Hari ini bukan hari kerja.</p>
                @endunless
            </section>

            @if ($mode === 'selesai')
                <section class="rounded-2xl bg-white p-6 text-center shadow-card">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100 text-green-600"><x-icon name="check" size="size-6" /></span>
                    <p class="mt-3 text-sm font-medium text-gray-900">
                        {{ $today?->status_persetujuan === 'rejected' ? 'Absensi hari ini ditolak atasan.' : 'Absensi hari ini sudah lengkap.' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $today?->status_persetujuan === 'rejected' ? 'Hubungi HRD untuk koreksi.' : 'Terima kasih. Sampai jumpa besok.' }}
                    </p>
                    <a href="{{ route('attendance.index') }}" class="mt-4 inline-block text-xs font-medium text-blue-600 hover:underline">Lihat riwayat absensi</a>
                </section>
            @else
                {{-- Form check-in / check-out --}}
                <form method="POST" enctype="multipart/form-data" novalidate
                      action="{{ route($isOut ? 'attendance.check-out' : 'attendance.check-in') }}"
                      x-data="attendanceCapture()" @submit="onSubmit($event)"
                      class="space-y-4">
                    @csrf
                    <input type="hidden" name="latitude" :value="lat">
                    <input type="hidden" name="longitude" :value="lng">

                    {{-- Langkah 1: lokasi --}}
                    <section class="rounded-2xl bg-white p-5 shadow-card" aria-labelledby="loc-heading">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="loc-heading" class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <span class="flex size-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">1</span> Lokasi
                            </h2>
                            <button type="button" @click="getLocation()" :disabled="loc === 'loading'"
                                    class="flex items-center gap-1 rounded-md border border-gray-200 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                                <x-icon name="refresh" size="size-3.5" /> Ulangi
                            </button>
                        </div>
                        <p class="mt-3 text-sm" role="status" aria-live="polite"
                           :class="{ 'text-gray-500': loc === 'idle' || loc === 'loading', 'text-green-700': loc === 'ok', 'text-red-700': loc === 'error' }"
                           x-text="locMessage">Mengambil lokasi...</p>
                    </section>

                    {{-- Langkah 2: foto --}}
                    <section class="rounded-2xl bg-white p-5 shadow-card" aria-labelledby="photo-heading">
                        <h2 id="photo-heading" class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="flex size-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">2</span> Foto bukti kehadiran
                        </h2>

                        <template x-if="preview">
                            <img :src="preview" alt="Foto yang akan dikirim" class="mt-3 aspect-[4/3] w-full rounded-xl object-cover">
                        </template>

                        <label for="foto"
                               class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-blue-400 bg-blue-50 px-4 py-4 text-sm font-medium text-blue-700 focus-within:outline-2 focus-within:outline-blue-500 hover:bg-blue-100">
                            <x-icon name="camera" />
                            <span x-text="preview ? 'Ambil ulang foto' : 'Ambil foto'">Ambil foto</span>
                        </label>
                        <input id="foto" name="foto" type="file" accept="image/*" capture="user" class="sr-only" @change="onPhoto($event)" x-ref="foto">
                        <p class="mt-2 text-xs text-red-700" x-show="photoError" x-text="photoError" x-cloak></p>
                    </section>

                    {{-- Langkah 3: kirim --}}
                    <button type="submit" :disabled="!ready"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-indigo-700 px-4 py-4 text-base font-medium text-white shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 disabled:cursor-not-allowed disabled:opacity-40">
                        <span x-text="busy ? 'Mengirim...' : '{{ $isOut ? 'Check-out sekarang' : 'Check-in sekarang' }}'">{{ $isOut ? 'Check-out sekarang' : 'Check-in sekarang' }}</span>
                    </button>
                    <p class="text-center text-xs text-gray-500">Waktu dicatat oleh server saat Anda menekan tombol kirim.</p>
                </form>

                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('attendanceCapture', () => ({
                            lat: null, lng: null, loc: 'idle', locMessage: 'Mengambil lokasi...',
                            preview: null, photoError: '', busy: false,

                            init() { this.getLocation(); },

                            get ready() { return this.loc === 'ok' && !!this.preview && !this.busy; },

                            getLocation() {
                                if (!window.isSecureContext) {
                                    this.loc = 'error';
                                    this.locMessage = 'Lokasi hanya bisa diambil lewat HTTPS atau localhost. Buka aplikasi dari alamat https://.';
                                    return;
                                }
                                if (!navigator.geolocation) {
                                    this.loc = 'error';
                                    this.locMessage = 'Perangkat ini tidak mendukung GPS.';
                                    return;
                                }
                                this.loc = 'loading';
                                this.locMessage = 'Mengambil lokasi...';
                                navigator.geolocation.getCurrentPosition(
                                    (pos) => {
                                        this.lat = pos.coords.latitude.toFixed(7);
                                        this.lng = pos.coords.longitude.toFixed(7);
                                        this.loc = 'ok';
                                        this.locMessage = 'Lokasi ditemukan (akurasi ±' + Math.round(pos.coords.accuracy) + ' m).';
                                    },
                                    (err) => {
                                        this.loc = 'error';
                                        this.lat = this.lng = null;
                                        this.locMessage = err.code === 1
                                            ? 'Izin lokasi ditolak. Aktifkan GPS dan izinkan lokasi untuk situs ini, lalu tekan Ulangi.'
                                            : 'Lokasi tidak ditemukan. Aktifkan GPS lalu tekan Ulangi.';
                                    },
                                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                                );
                            },

                            async onPhoto(event) {
                                const file = event.target.files[0];
                                this.photoError = '';
                                if (!file) { return; }
                                if (!file.type.startsWith('image/')) {
                                    this.photoError = 'File harus berupa foto.';
                                    event.target.value = '';
                                    this.preview = null;
                                    return;
                                }
                                // Perkecil foto (maks 1280px) agar unggahan cepat dan hemat kuota.
                                try {
                                    const blob = await this.shrink(file);
                                    if (blob) {
                                        const small = new File([blob], 'absensi.jpg', { type: 'image/jpeg' });
                                        const transfer = new DataTransfer();
                                        transfer.items.add(small);
                                        event.target.files = transfer.files;
                                        this.preview = URL.createObjectURL(small);
                                        return;
                                    }
                                } catch (e) { /* pakai foto asli */ }
                                this.preview = URL.createObjectURL(file);
                            },

                            shrink(file) {
                                return new Promise((resolve) => {
                                    const img = new Image();
                                    const url = URL.createObjectURL(file);
                                    img.onload = () => {
                                        const scale = Math.min(1, 1280 / Math.max(img.width, img.height));
                                        const canvas = document.createElement('canvas');
                                        canvas.width = Math.round(img.width * scale);
                                        canvas.height = Math.round(img.height * scale);
                                        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                                        URL.revokeObjectURL(url);
                                        canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.8);
                                    };
                                    img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
                                    img.src = url;
                                });
                            },

                            onSubmit(event) {
                                if (!this.ready) { event.preventDefault(); return; }
                                this.busy = true;
                            },
                        }));
                    });
                </script>
            @endif
        @endif
    </div>
</x-app-layout>
