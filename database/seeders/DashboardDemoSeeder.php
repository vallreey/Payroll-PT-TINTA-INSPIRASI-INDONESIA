<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobOrder;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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
            ->map(fn (string $name) => Department::firstOrCreate(['name' => $name]));

        $leaveType = LeaveType::firstOrCreate(
            ['name' => 'Cuti Tahunan'],
            ['default_quota' => 12, 'requires_attachment' => false],
        );

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
                ['name' => $name, 'username' => 'demo'.($i + 1), 'password' => 'password', 'role' => 'karyawan', 'status' => 'approved', 'approved_at' => now()],
            );

            $employee = Employee::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'department_id' => $departments[$i % $departments->count()]->id,
                    'nik' => '3302'.str_pad((string) ($i + 1), 12, '0', STR_PAD_LEFT),
                    'position' => ['Staff Marketing', 'Desainer', 'Teknisi Lapangan', 'Admin'][$i % 4],
                    'join_date' => $today->copy()->subMonths(6),
                    'employment_status' => 'tetap',
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
                $this->leaveOnce($employee, $leaveType, $today->toDateString(),
                    ['end_date' => $today->copy()->addDay()->toDateString(), 'reason' => 'Keperluan keluarga', 'status' => 'approved', 'approved_at' => now()]);
            }

            // Beberapa pengajuan pending untuk tab Submission
            if ($pattern === 2) {
                $this->leaveOnce($employee, $leaveType, $today->copy()->addDays(7)->toDateString(),
                    ['end_date' => $today->copy()->addDays(9)->toDateString(), 'total_days' => 3, 'reason' => 'Liburan', 'status' => 'pending']);
            }
        }

        $this->seedExtras($employees, $today);
    }

    private function seedExtras($employees, $today): void
    {
        $admin = User::where('role', 'super_admin')->first();

        $services = collect(['Desain Grafis', 'Percetakan', 'Konten Digital'])
            ->map(fn (string $name) => Service::firstOrCreate(['name' => $name]));

        foreach (['Cetak brosur', 'Desain logo', 'Video promosi', 'Banner event'] as $i => $title) {
            JobOrder::firstOrCreate(
                ['title' => $title],
                ['service_id' => $services[$i % $services->count()]->id, 'employee_id' => $employees[$i]->id, 'status' => 'in_progress'],
            );
        }

        Announcement::firstOrCreate(
            ['title' => 'Jadwal libur bersama'],
            ['body' => 'Libur bersama akan diinformasikan oleh HRD paling lambat satu minggu sebelumnya.', 'created_by' => $admin?->id, 'published_at' => now()->subHour()],
        );

        $schedule = Schedule::firstOrCreate(
            ['title' => 'Interview Kandidat - Customer Service', 'start_at' => $today->copy()->setTime(8, 0)],
            ['type' => 'meeting', 'end_at' => $today->copy()->setTime(10, 0), 'location' => 'Google Meet', 'meeting_url' => 'https://meet.google.com/', 'created_by' => $admin?->id],
        );
        $schedule->attendees()->syncWithoutDetaching($employees->take(3)->pluck('id')->all());

        if ($employees->isNotEmpty()) {
            Task::firstOrCreate(['employee_id' => $employees[0]->id, 'title' => 'Rekap absensi mingguan'], ['due_date' => $today->copy()->addDays(2)]);
        }
    }

    // whereDate dipakai (bukan firstOrCreate biasa) karena cast 'date' menyimpan jam 00:00:00 di SQLite.
    private function leaveOnce(Employee $employee, LeaveType $type, string $startDate, array $attributes): void
    {
        $exists = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereDate('start_date', $startDate)
            ->exists();

        if (! $exists) {
            LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => $startDate] + $attributes);
        }
    }

    private function checkIn(Employee $employee, $at): void
    {
        Attendance::firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $at->toDateString()],
            ['check_in_at' => $at, 'photo_path' => 'demo/placeholder.jpg', 'latitude' => -7.4243, 'longitude' => 109.2302, 'status' => 'pending',
             'presence' => $at->greaterThan($at->copy()->setTimeFromTimeString((string) config('attendance.work_start', '09:00'))) ? 'terlambat' : 'hadir'],
        );
    }
}
