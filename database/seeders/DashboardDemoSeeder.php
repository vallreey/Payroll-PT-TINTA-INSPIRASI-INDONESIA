<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Notifikasi;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh HANYA untuk melihat tampilan dashboard saat development.
 * Jalankan: php artisan db:seed --class=DashboardDemoSeeder
 * Aman dijalankan ulang (tidak menggandakan data), dan menolak jalan di production.
 */
class DashboardDemoSeeder extends Seeder
{
    private const PLACEHOLDER = 'absensi/demo/placeholder.png';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Dilewati: seeder demo tidak boleh dijalankan di production.');

            return;
        }

        $departments = collect(['Marketing', 'Creative', 'Teknis Lapangan', 'Administrasi'])
            ->map(fn (string $name) => Department::firstOrCreate(['nama_departemen' => $name]));

        $names = [
            'Ageng Saputro', 'Eka Bayu Wiguna', 'Dewi Lestari', 'Rizky Pratama',
            'Siti Nurhaliza', 'Bagas Wicaksono', 'Putri Anggraini', 'Fajar Nugroho',
            'Rina Kusuma', 'Dimas Aditya', 'Lia Permata', 'Yoga Firmansyah',
        ];

        $today = now()->startOfDay();
        $employees = collect();
        $this->placeholderPhoto();

        foreach ($names as $i => $name) {
            $user = User::firstOrCreate(
                ['email' => 'demo'.($i + 1).'@tintainspirasi.test'],
                [
                    'username' => 'demo'.($i + 1),
                    'password' => 'password',
                    'role' => 'karyawan',
                    'status' => 'aktif',
                    'status_persetujuan' => 'approved',
                    'approved_at' => now(),
                ],
            );

            $employee = Employee::firstOrCreate(
                ['id_pengguna' => $user->id_pengguna],
                [
                    'id_departemen' => $departments[$i % $departments->count()]->id_departemen,
                    'nik' => '3302'.str_pad((string) ($i + 1), 12, '0', STR_PAD_LEFT),
                    'nama_lengkap' => $name,
                    'jabatan' => ['Staff Marketing', 'Desainer', 'Teknisi Lapangan', 'Admin'][$i % 4],
                    // Variasi data agar ringkasan halaman Karyawan terlihat realistis
                    'jenis_kelamin' => $i % 2 === 0 ? 'L' : 'P',
                    'tanggal_masuk' => $today->copy()->subMonths(6 + $i)->toDateString(),
                    'status_kepegawaian' => ['tetap', 'tetap', 'tetap', 'kontrak', 'percobaan', 'tetap'][$i % 6],
                    'gaji_pokok' => 4500000,
                ],
            );
            $employees->push($employee);

            // Pola: 0-2 tepat waktu, 3 terlambat, 4 tidak hadir, 5 cuti disetujui (berulang)
            $pattern = $i % 6;

            if ($pattern <= 2) {
                $this->checkIn($employee, $today->copy()->setTime(8, 45 + $pattern * 5));
            } elseif ($pattern === 3) {
                $this->checkIn($employee, $today->copy()->setTime(9, 30));
            }

            if ($pattern === 0) {
                $this->leaveOnce($employee, $today->copy()->addDay()->toDateString(), [
                    'jenis' => 'izin', 'tanggal_selesai' => $today->copy()->addDay()->toDateString(), 'total_hari' => 1,
                    'alasan' => 'Keperluan administrasi', 'status' => 'approved', 'approved_at' => now(),
                ]);
            } elseif ($pattern === 1) {
                $this->leaveOnce($employee, $today->copy()->addDays(2)->toDateString(), [
                    'jenis' => 'sakit', 'tanggal_selesai' => $today->copy()->addDays(3)->toDateString(), 'total_hari' => 2,
                    'alasan' => 'Sakit, istirahat sesuai anjuran dokter', 'status' => 'approved', 'approved_at' => now(),
                ]);
            } elseif ($pattern === 5) {
                $this->leaveOnce($employee, $today->toDateString(), [
                    'jenis' => 'cuti', 'tanggal_selesai' => $today->copy()->addDay()->toDateString(), 'total_hari' => 2,
                    'alasan' => 'Keperluan keluarga', 'status' => 'approved', 'approved_at' => now(),
                ]);
            }

            // Beberapa pengajuan pending untuk tab Submission
            if ($pattern === 2) {
                $this->leaveOnce($employee, $today->copy()->addDays(7)->toDateString(), [
                    'jenis' => 'cuti', 'tanggal_selesai' => $today->copy()->addDays(9)->toDateString(), 'total_hari' => 3,
                    'alasan' => 'Liburan', 'status' => 'pending',
                ]);
            }
        }

        $this->seedHistory($employees, $today);
        $this->seedExtras($employees, $today);
    }

    /** Foto contoh di disk privat, agar tautan "Img 1" di halaman Absensi bisa dibuka. */
    private function placeholderPhoto(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::PLACEHOLDER)) {
            $disk->put(self::PLACEHOLDER, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAUAAAADwCAIAAAD+Tyo8AAADnklEQVR42u3cMY6rWhBFUWMxGVIS5h8iJGfIMTPxFCxhZE7VWvEPHrfupuhW6w/bfjyATE9HAAIGBAwIGAQMCBgQMCBgEDAgYEDAIGBAwICAAQGDgAEBAwIGBAwCBgQMCBgEDAgYEDAgYBAwIGBAwICAQcCAgAEBg4ABAQMCBgQMAgYEDPzC6AjKWF/v7//jZZ6cWAHDth9OoUOxehYw1bpVsoCp0K2SBUyRdGUsYOLTlbGAiU9XxgImPl0Z35Y/5FBvtX+hDYwwrGIbmNJrzSoWMNklaNgntHQr8DltA6vXsyBgN94TCRh33XMJmHa3XMMCVq9nRMAgYKwmTypg2t1pDQvYbfbUCBgEjEXk2QXsBjsBBAwI2PJxDgIGBGztOA0EDALGwnEmAgYEbNU4GQQMAgYE7CvR+SBgEDAgYHwfOiUBAwIGAQMCBgR8e34346wEDAgYBAwIGBAwIGAQMCBgQMAgYEDAgIABAYOAAQGXt8yTQ3BWAgYEDAIGBAwI+Pb8bsYpCRgQMAgY34fOR8CAgAEB+0p0MgIGBGzVOBMEDALGwnEaAgYEbO04BwQMAsbycQICdoM9OwIGBGwReWoB4zZ7XgG7054UAYOAsZo8o4DpeL/VK2ANey4E7K57IgHjxqv3zoZtP5zCf62vt3SxgTXgXy5glKBen9AU/pyWrg1MahvqtYGJXMXSFTCRGUtXwERmLF0BE5mxdAVMXsm6FTB5JetWwCT1rFgBA3/mDzlAwICAAQGDgAEBAwIGBAy1jI7gatH/z7rz/MnXpfwllmL1LGDRilbMAtatkhGwdGUsYKQrYwFLFxkLWLoyRsDSlXEt/hJLvaZgA7s0WMU2sHoxFwG7JabjE9rlwOe0DaxezEvAbgOmJmD3wOwE7AZgggI2e8xRwKZO42n6GRgE7IWNmQrYpDFZAZuxhgUMCNjrGVMWsLli1j6hQcBeyZi4gAEBexnTeO42MAjYaxjTFzAgYOuXLnfABgYBe/XiJggYEDAI2FcT7oOAAQEDAgYB+4EHt0LAgIBBwICAAQGf4zdY9LkbNjAIGBAwIGAQMCBgQMCAgEHAgIABAYOAAQEDAgbaBrzMk7nS5G7YwCBgQMCAgEHAyfweiya3wgYGAQMCBgTsx2C63AcbGAQMCNhXE26CgMEG9urFHRAwIGAvYEzfBgYBew1j7gIGBOxlTOOJ28AgYK9kzFrA5oopCxgE7PWM+QrYjDHZ63wA2+eXjOT4DMEAAAAASUVORK5CYII='));
        }
    }

    /** Riwayat 3 hari kerja terakhir, lengkap dengan check-out dan status persetujuan yang bervariasi. */
    private function seedHistory($employees, $today): void
    {
        $days = collect();
        $cursor = $today->copy();
        while ($days->count() < 3) {
            $cursor = $cursor->copy()->subDay();
            if (\App\Support\WorkCalendar::isWorkingDay($cursor)) {
                $days->push($cursor);
            }
        }

        $approver = User::where('role', 'super_admin')->first();

        foreach ($days as $d => $date) {
            foreach ($employees as $i => $employee) {
                $pattern = ($i + $d) % 6;
                if ($pattern === 4) {
                    continue; // tidak hadir
                }

                $late = $pattern === 3;
                $masuk = $late ? $date->copy()->setTime(9, 10 + $i % 20) : $date->copy()->setTime(8, 40 + ($i * 3) % 20);
                $keluar = $date->copy()->setTime(16, $i % 3 === 0 ? 5 : 20);

                $attendance = Attendance::firstOrCreate(
                    ['id_karyawan' => $employee->id_karyawan, 'tanggal' => $date->toDateString()],
                    [
                        'jam_masuk' => $masuk->format('H:i:s'),
                        'jam_keluar' => $keluar->format('H:i:s'),
                        'foto_masuk' => self::PLACEHOLDER,
                        'foto_keluar' => self::PLACEHOLDER,
                        'lat_masuk' => -7.4243, 'long_masuk' => 109.2302,
                        'lat_keluar' => -7.4243, 'long_keluar' => 109.2302,
                        'status' => $late ? 'terlambat' : 'hadir',
                        'status_persetujuan' => $pattern === 5 ? 'rejected' : 'approved',
                        'approved_by' => $approver?->id_pengguna,
                        'approved_at' => $keluar,
                    ],
                );
            }
        }
    }

    private function seedExtras($employees, $today): void
    {
        $admin = User::where('role', 'super_admin')->first();

        // Notifikasi untuk Super Admin (tbl_notifikasi)
        if ($admin) {
            foreach ([
                ['Pengajuan cuti baru', 'Dewi Lestari mengajukan cuti tahunan 3 hari.', 'cuti', false],
                ['Absensi menunggu persetujuan', '8 absensi hari ini menunggu persetujuan.', 'absensi', false],
                ['Penggajian siap disetujui', 'Draft penggajian bulan ini sudah dibuat.', 'gaji', true],
            ] as [$judul, $pesan, $tipe, $read]) {
                Notifikasi::firstOrCreate(
                    ['id_pengguna' => $admin->id_pengguna, 'judul' => $judul],
                    ['pesan' => $pesan, 'tipe' => $tipe, 'is_read' => $read],
                );
            }
        }

        // Dua slip gaji menunggu persetujuan (tbl_penggajian)
        foreach ($employees->take(2) as $employee) {
            $payroll = Payroll::firstOrNew([
                'id_karyawan' => $employee->id_karyawan,
                'bulan' => (int) $today->format('n'),
                'tahun' => (int) $today->format('Y'),
            ]);

            if (! $payroll->exists) {
                $payroll->fill([
                    'gaji_pokok' => 4500000, 'tunjangan' => 500000, 'potongan_absensi' => 0,
                    'pph21' => 50000, 'bpjs_tk' => 90000, 'bpjs_kes' => 45000, 'status' => 'pending_approval',
                ]);
                $payroll->recalculate();
                $payroll->save();
            }
        }
    }

    // whereDate dipakai (bukan firstOrCreate biasa) karena cast 'date' menyimpan jam 00:00:00 di SQLite.
    private function leaveOnce(Employee $employee, string $tanggalMulai, array $attributes): void
    {
        $exists = LeaveRequest::where('id_karyawan', $employee->id_karyawan)
            ->whereDate('tanggal_mulai', $tanggalMulai)
            ->exists();

        if (! $exists) {
            LeaveRequest::create(['id_karyawan' => $employee->id_karyawan, 'tanggal_mulai' => $tanggalMulai] + $attributes);
        }
    }

    private function checkIn(Employee $employee, $at): void
    {
        $late = $at->greaterThan($at->copy()->setTimeFromTimeString((string) config('attendance.work_start', '09:00')));

        Attendance::firstOrCreate(
            ['id_karyawan' => $employee->id_karyawan, 'tanggal' => $at->toDateString()],
            [
                'jam_masuk' => $at->format('H:i:s'),
                'foto_masuk' => self::PLACEHOLDER,
                'lat_masuk' => -7.4243,
                'long_masuk' => 109.2302,
                'status' => $late ? 'terlambat' : 'hadir',
                'status_persetujuan' => 'pending',
            ],
        );
    }
}
