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

        $this->seedExtras($employees, $today);
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
                'foto_masuk' => 'demo/placeholder.jpg',
                'lat_masuk' => -7.4243,
                'long_masuk' => 109.2302,
                'status' => $late ? 'terlambat' : 'hadir',
                'status_persetujuan' => 'pending',
            ],
        );
    }
}
