<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobOrder;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh HANYA untuk melihat tampilan dashboard saat development.
 * Jalankan: php artisan db:seed --class=DashboardDemoSeeder
 * Aman dijalankan ulang (tidak menggandakan data), dan menolak jalan di production.
 */
class DashboardDemoSeeder extends Seeder
{
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
                    'tanggal_masuk' => $today->copy()->subMonths(6)->toDateString(),
                    'status_kepegawaian' => 'tetap',
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

        $this->seedExtras($employees, $today);
    }

    private function seedExtras($employees, $today): void
    {
        $admin = User::where('role', 'super_admin')->first();

        $services = collect(['Desain Grafis', 'Percetakan', 'Konten Digital'])
            ->map(fn (string $name) => Service::firstOrCreate(['nama_layanan' => $name]));

        foreach (['Cetak brosur', 'Desain logo', 'Video promosi', 'Banner event'] as $i => $judul) {
            JobOrder::firstOrCreate(
                ['judul' => $judul],
                ['id_layanan' => $services[$i % $services->count()]->id_layanan, 'id_karyawan' => $employees[$i]->id_karyawan, 'status' => 'berjalan'],
            );
        }

        Announcement::firstOrCreate(
            ['judul' => 'Jadwal libur bersama'],
            ['isi' => 'Libur bersama akan diinformasikan oleh HRD paling lambat satu minggu sebelumnya.', 'dibuat_oleh' => $admin?->id_pengguna, 'tanggal_terbit' => now()->subHour()],
        );

        $schedule = Schedule::firstOrCreate(
            ['judul' => 'Interview Kandidat - Customer Service', 'waktu_mulai' => $today->copy()->setTime(8, 0)],
            ['tipe' => 'meeting', 'waktu_selesai' => $today->copy()->setTime(10, 0), 'lokasi' => 'Google Meet', 'url_meeting' => 'https://meet.google.com/', 'dibuat_oleh' => $admin?->id_pengguna],
        );
        $schedule->attendees()->syncWithoutDetaching($employees->take(3)->pluck('id_karyawan')->all());

        if ($employees->isNotEmpty()) {
            Task::firstOrCreate(['id_karyawan' => $employees[0]->id_karyawan, 'judul' => 'Rekap absensi mingguan'], ['tenggat' => $today->copy()->addDays(2)->toDateString()]);
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
                'foto_masuk' => 'demo/placeholder.jpg',
                'lat_masuk' => -7.4243,
                'long_masuk' => 109.2302,
                'status' => $late ? 'terlambat' : 'hadir',
                'status_persetujuan' => 'pending',
            ],
        );
    }
}
