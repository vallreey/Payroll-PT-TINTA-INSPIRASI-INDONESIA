<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aturan Jam Kerja
    |--------------------------------------------------------------------------
    | Semua jam memakai zona waktu aplikasi (APP_TIMEZONE, default Asia/Jakarta) dan
    | format 24 jam "HH:MM". Ubah lewat .env tanpa mengubah kode.
    */

    // Check-in pada atau sebelum jam ini = tepat waktu ("hadir"); sesudahnya "terlambat".
    'work_start' => env('ATTENDANCE_WORK_START', '09:00'),

    // Check-out sebelum jam ini dihitung "pulang lebih awal" (Leaved). Karyawan yang belum check-out
    // baru dihitung "No Clock Out" setelah jam ini lewat.
    'work_end' => env('ATTENDANCE_WORK_END', '16:00'),

    // Check-in sekurang-kurangnya sekian menit sebelum work_start = "Early Clock In".
    'early_minutes' => (int) env('ATTENDANCE_EARLY_MINUTES', 30),

    // Hari kerja (ISO: 1 = Senin ... 7 = Minggu). Di luar hari ini tidak ada yang dihitung "No Clock In".
    'work_days' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('ATTENDANCE_WORK_DAYS', '1,2,3,4,5'))
    ))),

    // Batas ukuran foto bukti kehadiran (KB). Aplikasi mobile/web mengecilkan foto sebelum diunggah.
    'photo_max_kb' => (int) env('ATTENDANCE_PHOTO_MAX_KB', 8192),

    // Jumlah baris per halaman pada tabel Persetujuan Absensi.
    'per_page' => 10,

];
