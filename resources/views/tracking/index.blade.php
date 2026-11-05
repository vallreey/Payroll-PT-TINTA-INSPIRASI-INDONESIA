<x-app-layout title="Tracking Lokasi">
    <x-slot:heading>
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Tracking Lokasi</h1>
        <p class="mt-0.5 text-xs text-gray-600">Pantau lokasi dan pergerakan karyawan secara real-time.</p>
    </x-slot:heading>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <style>
            #tracking-map { min-height: 390px; height: clamp(390px, 52vh, 560px); }
            #journey-map { min-height: 300px; height: 45vh; }
            #map-shell:fullscreen { display: flex; flex-direction: column; padding: 1rem; background: #f5f6fb; }
            #map-shell:fullscreen #tracking-map { flex: 1; min-height: 0; height: auto; }
            #map-shell:fullscreen > .map-legend { flex: none; }
            .employee-pin { display: grid; width: 34px; height: 34px; place-items: center; border: 3px solid white; border-radius: 9999px; background: #2563eb; box-shadow: 0 2px 8px #0f172a55; color: white; font: 600 12px Poppins, sans-serif; }
            .employee-pin.is-offline { background: #94a3b8; }
            .leaflet-popup-content-wrapper { border-radius: 12px; }
            .leaflet-popup-content { margin: 10px 14px; font: 12px Poppins, sans-serif; }
            .destination-tooltip { max-width: 180px; overflow: hidden; text-overflow: ellipsis; font: 600 11px Poppins, sans-serif; }
        </style>
    @endpush

    <script>
        window.trackingConfig = {{ Illuminate\Support\Js::from([
            'isManager' => $isManager,
            'selfEmployeeId' => $user->employee?->id_karyawan,
            'employees' => $employees,
            'places' => $places,
            'routes' => [
                'data' => route('tracking.data'),
                'historyPrefix' => url('/tracking-lokasi/karyawan').'/',
                'position' => route('tracking.position'),
                'assignment' => route('tracking.assignment'),
                'assignmentPrefix' => url('/tracking-lokasi/assignment').'/',
                'places' => route('tracking.places.store'),
                'search' => route('tracking.search'),
                'reverse' => route('tracking.reverse'),
                'route' => route('tracking.route'),
                'simulationPrefix' => url('/tracking-lokasi/simulation').'/',
                'simulationReset' => route('tracking.simulation.reset'),
            ],
        ]) }};
    </script>
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @vite('resources/js/tracking.js')
    @endpush

    <div id="tracking-page" class="space-y-5">
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Ringkasan tracking">
            <article class="rounded-2xl bg-white p-4 shadow-card sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-indigo-100"><img src="{{ asset('images/ic_baseline-people.png') }}" alt="" class="size-5"></span>
                    <div><p class="text-xs text-gray-500">Karyawan dipantau</p><p id="employee-count" class="text-xl font-semibold">0</p></div>
                </div>
            </article>
            <article class="rounded-2xl bg-white p-4 shadow-card sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-green-100"><span class="size-3 rounded-full bg-green-500"></span></span>
                    <div><p class="text-xs text-gray-500">Sedang online</p><p id="live-count" class="text-xl font-semibold">0</p></div>
                </div>
            </article>
            <article class="rounded-2xl bg-white p-4 shadow-card sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-amber-100"><img src="{{ asset('images/lokasi2.png') }}" alt="" class="size-5"></span>
                    <div><p class="text-xs text-gray-500">Tujuan aktif</p><p id="assignment-count" class="text-xl font-semibold">0</p></div>
                </div>
            </article>
            <article class="rounded-2xl bg-white p-4 shadow-card sm:p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-blue-100"><img src="{{ asset('images/buildinglokasi.png') }}" alt="" class="size-5"></span>
                    <div><p class="text-xs text-gray-500">Lokasi tersimpan</p><p id="place-count" class="text-xl font-semibold">0</p></div>
                </div>
            </article>
        </section>

        <section class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,.8fr)]">
            <div class="min-w-0 overflow-hidden rounded-2xl bg-white shadow-card">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-900">Peta lokasi karyawan</h2>
                            <p id="map-status" class="mt-1 text-xs text-gray-500">Memuat peta OpenStreetMap…</p>
                        </div>
                        <div class="flex gap-2">
                            <button id="refresh-map" type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Refresh</button>
                            <button id="fit-map" type="button" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">Lihat semua</button>
                        </div>
                    </div>

                    @if ($isManager)
                        <div class="grid gap-2 md:grid-cols-[minmax(150px,.8fr)_minmax(200px,1.1fr)_minmax(180px,1.2fr)_auto]">
                            <label class="sr-only" for="employee-select">Pilih karyawan</label>
                            <select id="employee-select" class="min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs text-gray-700 focus:border-blue-500 focus:outline-none">
                                <option value="">Pilih karyawan</option>
                            </select>
                            <div class="relative min-w-0">
                                <label class="sr-only" for="saved-place-search">Cari lokasi tersimpan</label>
                                <div class="flex">
                                    <input id="saved-place-search" type="search" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="saved-place-results" placeholder="Cari lokasi tersimpan…" class="min-w-0 flex-1 rounded-l-lg border border-gray-200 px-3 py-2.5 text-xs focus:border-blue-500 focus:outline-none">
                                    <button id="saved-place-toggle" type="button" aria-label="Tampilkan lokasi tersimpan" class="rounded-r-lg border border-l-0 border-gray-200 bg-white px-3 text-gray-600 hover:bg-gray-50">⌄</button>
                                </div>
                                <div id="saved-place-results" role="listbox" class="absolute left-0 right-0 top-full z-[500] mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg"></div>
                            </div>
                            <div class="relative min-w-0">
                                <label class="sr-only" for="place-search">Cari alamat atau tempat</label>
                                <input id="place-search" type="search" autocomplete="off" maxlength="120" role="combobox" aria-expanded="false" aria-controls="search-results" placeholder="Cari alamat atau tempat…" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-xs focus:border-blue-500 focus:outline-none">
                                <div id="search-results" role="listbox" class="absolute left-0 right-0 top-full z-[500] mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg"></div>
                            </div>
                            <button id="save-place-open" type="button" class="rounded-lg border border-blue-200 px-3 py-2.5 text-xs font-medium text-blue-700 hover:bg-blue-50">+ Simpan lokasi</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button id="assign-route" type="button" class="rounded-lg bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">Tambah tujuan karyawan</button>
                            <span id="assignment-message" class="text-xs text-gray-500" aria-live="polite">Pilih karyawan dan tujuan, atau klik peta untuk memilih titik.</span>
                        </div>
                        <div id="employee-destinations" class="space-y-2" aria-live="polite"></div>
                    @else
                        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-blue-50 p-3">
                            <span class="size-2.5 rounded-full bg-blue-500"></span>
                            <p id="tracking-consent" class="flex-1 text-xs text-blue-900">Berbagi lokasi hanya berjalan jika Anda mengaktifkannya dan memberikan izin GPS.</p>
                            <button id="toggle-tracking" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">Mulai berbagi lokasi</button>
                        </div>
                    @endif
                </div>

                <div id="map-shell">
                    <div class="flex flex-wrap items-center justify-end gap-2 px-4 pb-3 pt-1 sm:px-5">
                        <button id="recenter-map" type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50" title="Tampilkan lokasi perangkat ini">Lokasi saya</button>
                        <button id="fullscreen-map" type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50" aria-label="Buka peta layar penuh">Layar penuh</button>
                    </div>
                    <div id="tracking-map" role="application" aria-label="Peta interaktif OpenStreetMap"></div>
                <div class="map-legend flex flex-wrap items-center gap-4 border-t border-gray-100 px-4 py-3 text-[11px] text-gray-500 sm:px-5">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-green-600"></span>Mulai</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-500"></span>Kunjungan</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-red-600"></span>Terakhir tercatat</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-violet-600"></span>Live</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-gray-400"></span>Offline</span>
                    <span class="inline-flex items-center gap-1.5"><span class="grid size-4 place-items-center rounded-full bg-indigo-600 text-[10px] text-white">🎯</span>Tujuan admin</span>
                    <span class="inline-flex items-center gap-1.5"><img src="{{ asset('images/lokasi2.png') }}" alt="" class="size-3.5">Lokasi tersimpan</span>
                    <span class="ml-auto">Peta © OpenStreetMap</span>
                </div>
                </div>
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl bg-white p-4 shadow-card sm:p-5" aria-labelledby="activity-heading">
                    <div class="flex items-center justify-between">
                        <div><h2 id="activity-heading" class="font-semibold text-gray-900">Aktivitas lokasi</h2><p class="mt-1 text-xs text-gray-500">Pembaruan terbaru</p></div>
                        <span class="flex items-center gap-1.5 text-[11px] font-medium text-green-600"><span class="size-2 rounded-full bg-green-500"></span>LIVE</span>
                    </div>
                    <ul id="activity-list" class="mt-4 max-h-[250px] divide-y divide-gray-100 overflow-y-auto"></ul>
                    <p id="activity-empty" class="hidden py-8 text-center text-xs text-gray-500">Belum ada lokasi yang dibagikan.</p>
                </section>

                @if ($isManager)
                    <section class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/60 p-4 sm:p-5" aria-labelledby="simulation-heading">
                        <div class="flex items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-blue-700 shadow-sm">▶</span>
                            <div class="min-w-0 flex-1">
                                <h2 id="simulation-heading" class="font-semibold text-gray-900">Simulasi lokasi</h2>
                                <p class="mt-1 text-xs leading-5 text-gray-600">Uji pergerakan pada peta tanpa GPS. Titik simulasi selalu diberi label khusus.</p>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <label class="sr-only" for="simulation-employee">Karyawan simulasi</label>
                            <select id="simulation-employee" class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"></select>
                            <button id="toggle-simulation" type="button" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700">Mulai simulasi</button>
                            <button id="reset-simulation" type="button" class="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Reset Simulasi</button>
                        </div>
                        <p id="simulation-status" class="mt-2 text-[11px] text-gray-500" aria-live="polite">Pilih karyawan untuk memulai.</p>
                    </section>
                @endif
            </aside>
        </section>

        <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,.8fr)]">
            <section class="min-w-0 overflow-hidden rounded-2xl bg-white shadow-card" aria-labelledby="employees-heading">
                <div class="flex flex-col gap-3 px-4 pb-3 pt-5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div><h2 id="employees-heading" class="font-semibold text-gray-900">Daftar karyawan</h2><p class="mt-1 text-xs text-gray-500">Pilih nama untuk melihat jejak lokasinya.</p></div>
                    <div class="flex items-center gap-2">
                        <label class="sr-only" for="employee-search">Cari karyawan berdasarkan nama</label>
                        <input id="employee-search" type="search" autocomplete="off" placeholder="Cari nama karyawan…" class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-xs focus:border-blue-500 focus:outline-none sm:w-56 sm:flex-none">
                        <span id="table-count" class="shrink-0 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">0 karyawan</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-xs">
                        <thead class="bg-slate-100 text-gray-700">
                            <tr><th class="px-4 py-3 font-medium sm:px-5">Karyawan</th><th class="px-3 py-3 font-medium">Divisi</th><th class="px-3 py-3 font-medium">Lokasi</th><th class="px-3 py-3 font-medium">Status</th><th class="px-3 py-3 font-medium">Update terakhir</th></tr>
                        </thead>
                        <tbody id="employee-list" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
                <p id="employee-empty" class="hidden px-5 py-10 text-center text-sm text-gray-500">Belum ada data karyawan untuk ditampilkan.</p>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-card sm:p-5" aria-labelledby="detail-heading">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 id="detail-heading" class="font-semibold text-gray-900">Detail lokasi</h2><p class="mt-1 text-xs text-gray-500">Karyawan yang dipilih</p></div>
                    <span id="detail-status" class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-medium text-gray-500">Belum dipilih</span>
                </div>
                <div id="employee-detail" class="mt-4"></div>
            </section>
        </section>
    </div>

    <div id="journey-dialog" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-slate-900/50 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="journey-dialog-title">
        <section class="flex max-h-[95vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-gray-100 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 id="journey-dialog-title" class="truncate text-base font-semibold text-gray-900">Riwayat perjalanan</h2>
                    <p id="journey-dialog-employee" class="mt-1 truncate text-xs text-gray-500">Pilih karyawan untuk melihat riwayat.</p>
                </div>
                <button id="close-journey-dialog" type="button" aria-label="Tutup riwayat perjalanan" class="grid size-9 shrink-0 place-items-center rounded-lg text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-700">&times;</button>
            </header>
            <div class="overflow-y-auto p-4 sm:p-6">
                <div class="mb-4 flex flex-wrap items-end gap-2">
                    <div>
                        <label for="journey-day" class="mb-1 block text-[10px] font-medium text-gray-600">Tanggal</label>
                        <select id="journey-day" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-blue-500 focus:outline-none"></select>
                    </div>
                    <div>
                        <label for="journey-month" class="mb-1 block text-[10px] font-medium text-gray-600">Bulan</label>
                        <select id="journey-month" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-blue-500 focus:outline-none"></select>
                    </div>
                    <div>
                        <label for="journey-year" class="mb-1 block text-[10px] font-medium text-gray-600">Tahun</label>
                        <select id="journey-year" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-700 focus:border-blue-500 focus:outline-none"></select>
                    </div>
                    <button id="journey-today" type="button" class="rounded-lg border border-blue-100 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">Hari ini</button>
                    <p id="history-status" class="ml-auto self-center text-[10px] text-gray-500" aria-live="polite">Pilih tanggal untuk memuat riwayat.</p>
                </div>

                <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(280px,.8fr)]">
                    <div class="min-w-0 overflow-hidden rounded-xl border border-gray-100">
                        <div id="journey-map" role="application" aria-label="Peta riwayat perjalanan karyawan"></div>
                        <div class="flex flex-wrap gap-x-4 gap-y-2 border-t border-gray-100 px-3 py-2.5 text-[10px] text-gray-500">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-green-600"></span>Awal</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-500"></span>Kunjungan</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-red-600"></span>Akhir</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-violet-600"></span>Live</span>
                            <span class="inline-flex items-center gap-1.5"><span class="grid size-4 place-items-center rounded-full bg-indigo-600 text-[10px] text-white">🎯</span>Tujuan admin</span>
                            <span class="ml-auto">Polyline GPS tersimpan</span>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div id="journey-summary" class="grid grid-cols-2 gap-2"></div>
                        <div id="journey-last-status" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-[10px] text-gray-600"></div>
                        <div class="mt-4 flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-gray-800">Timeline perjalanan</h3>
                            <span id="journey-count" class="text-[10px] text-gray-400"></span>
                        </div>
                        <div id="journey-timeline" class="mt-2 max-h-[35vh] space-y-2 overflow-y-auto"></div>
                        <div id="journey-point-detail" class="mt-3 hidden rounded-xl border border-blue-100 bg-blue-50/70 p-3"></div>
                    </div>
                </div>
                <p class="mt-4 border-t border-gray-100 pt-3 text-[10px] leading-4 text-gray-400">Jarak dihitung dari garis lurus antar-sampel GPS yang tersimpan. Tujuan admin ditandai dengan ikon khusus dan dianggap dikunjungi jika titik GPS berada dalam radius 100 m; waktu kunjungan mengikuti sampel GPS. Data simulasi diberi label terpisah.</p>
            </div>
        </section>
    </div>

    @if ($isManager)
        <div id="place-dialog" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="place-dialog-title">
            <form id="place-form" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 id="place-dialog-title" class="font-semibold text-gray-900">Simpan lokasi baru</h2><p id="place-coordinates" class="mt-1 text-xs text-gray-500">Pilih titik di peta terlebih dahulu.</p></div>
                    <button id="close-place-dialog" type="button" aria-label="Tutup" class="rounded-lg px-2 text-xl leading-6 text-gray-400 hover:bg-gray-100">&times;</button>
                </div>
                <label class="block text-xs font-medium text-gray-700">Nama lokasi
                    <input id="place-name" name="name" required maxlength="120" class="mt-1.5 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                </label>
                <label class="block text-xs font-medium text-gray-700">Keterangan (opsional)
                    <textarea id="place-description" name="description" maxlength="500" rows="2" class="mt-1.5 w-full resize-y rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none"></textarea>
                </label>
                <p id="place-error" class="text-xs text-red-600" aria-live="polite"></p>
                <div class="flex justify-end gap-2">
                    <button id="cancel-place" type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700">Batal</button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">Simpan di peta</button>
                </div>
            </form>
        </div>

        <div id="destination-dialog" class="fixed inset-0 z-[1000] hidden items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true" aria-labelledby="destination-dialog-title">
            <form id="destination-form" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 id="destination-dialog-title" class="font-semibold text-gray-900">Tambah tujuan karyawan</h2><p id="destination-coordinates" class="mt-1 text-xs text-gray-500"></p></div>
                    <button id="close-destination-dialog" type="button" aria-label="Tutup" class="rounded-lg px-2 text-xl leading-6 text-gray-400 hover:bg-gray-100">&times;</button>
                </div>
                <input id="destination-id" type="hidden">
                <input id="destination-latitude" name="latitude" type="hidden">
                <input id="destination-longitude" name="longitude" type="hidden">
                <div id="destination-preview" class="hidden rounded-xl bg-slate-50 p-4">
                    <p id="destination-preview-name" class="text-sm font-semibold text-gray-900"></p>
                    <p id="destination-preview-employee" class="mt-1 text-xs text-gray-600"></p>
                    <p id="destination-preview-description" class="mt-2 hidden text-xs leading-5 text-gray-600"></p>
                    <p id="destination-preview-coordinates" class="mt-2 text-[10px] tabular-nums text-gray-400"></p>
                </div>
                <div id="destination-fields" class="hidden space-y-4">
                    <label class="block text-xs font-medium text-gray-700">Nama lokasi
                        <input id="destination-name" name="name" required maxlength="120" class="mt-1.5 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                    </label>
                    <label class="block text-xs font-medium text-gray-700">Ikon lokasi
                        <select id="destination-icon" name="icon" class="mt-1.5 w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="pin">📍 Pin</option>
                            <option value="office">🏢 Kantor</option>
                            <option value="warehouse">🏭 Gudang</option>
                            <option value="store">🏪 Toko</option>
                            <option value="meeting">🤝 Pertemuan</option>
                            <option value="target">🎯 Target</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-gray-700">Catatan (opsional)
                        <textarea id="destination-description" name="description" maxlength="500" rows="2" class="mt-1.5 w-full resize-y rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none"></textarea>
                    </label>
                    <p id="destination-edit-coordinate-hint" class="hidden rounded-lg bg-blue-50 px-3 py-2 text-[10px] text-blue-700"></p>
                    <button id="move-destination" type="button" class="hidden rounded-lg border border-blue-200 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">Pilih titik baru di peta</button>
                </div>
                <p id="destination-assigned-employee" class="hidden text-xs text-gray-600"></p>
                <p id="destination-error" class="text-xs text-red-600" aria-live="polite"></p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button id="delete-destination-dialog" type="button" class="hidden mr-auto rounded-lg border border-red-200 px-4 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Hapus</button>
                    <button id="edit-destination-dialog" type="button" class="hidden rounded-lg border border-blue-200 px-4 py-2 text-xs font-medium text-blue-700 hover:bg-blue-50">Edit</button>
                    <button id="cancel-destination" type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700">Tutup</button>
                    <button id="save-destination" type="submit" class="hidden rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700">Simpan tujuan</button>
                </div>
            </form>
        </div>
    @endif
</x-app-layout>
