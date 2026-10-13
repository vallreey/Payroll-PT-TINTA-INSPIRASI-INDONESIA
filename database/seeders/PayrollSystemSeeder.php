<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PayrollSystemSeeder extends Seeder
{
    public function run(): void
    {
        // Jenis cuti/izin dasar
        LeaveType::insert([
            ['name' => 'Cuti Tahunan', 'default_quota' => 12, 'requires_attachment' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Izin', 'default_quota' => 0, 'requires_attachment' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sakit', 'default_quota' => 0, 'requires_attachment' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Departemen contoh
        Department::create(['name' => 'General']);

        // Akun Super Admin awal — WAJIB, karena semua registrasi karyawan
        // butuh persetujuan Super Admin (tanpa akun ini tidak ada yang bisa approve).
        // Ganti email & password setelah login pertama kali.
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@tintainspirasi.co.id',
            'password' => Hash::make('ChangeMe123!'),
            'role' => 'super_admin',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
