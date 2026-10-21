<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data awal WAJIB untuk production. Aman dijalankan berulang (idempotent):
 * php artisan db:seed --class=PayrollSystemSeeder
 */
class PayrollSystemSeeder extends Seeder
{
    public function run(): void
    {
        // Jenis cuti/izin dasar (category = SDD tbl_izin_cuti.jenis)
        $leaveTypes = [
            ['name' => 'Cuti Tahunan', 'category' => 'cuti',  'default_quota' => 12, 'requires_attachment' => false],
            ['name' => 'Izin',         'category' => 'izin',  'default_quota' => 0,  'requires_attachment' => false],
            ['name' => 'Sakit',        'category' => 'sakit', 'default_quota' => 0,  'requires_attachment' => true],
        ];
        foreach ($leaveTypes as $type) {
            LeaveType::updateOrCreate(['name' => $type['name']], $type);
        }

        Department::firstOrCreate(['name' => 'General']);

        // Akun Super Admin awal — WAJIB, karena semua registrasi karyawan butuh persetujuan
        // Super Admin. Ganti email & password setelah login pertama kali.
        // Password hanya di-set saat akun dibuat; seeder ulang tidak menimpa password yang sudah diganti.
        $admin = User::firstOrNew(['email' => 'superadmin@tintainspirasi.co.id']);
        if (! $admin->exists) {
            $admin->password = 'ChangeMe123!'; // di-hash otomatis oleh cast 'hashed'
        }
        $admin->fill([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'role' => 'super_admin',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => $admin->approved_at ?? now(),
        ])->save();
    }
}
