<?php

namespace Database\Seeders;

use App\Models\Department;
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
        Department::firstOrCreate(['nama_departemen' => 'General']);

        // Akun Super Admin awal — WAJIB, karena semua registrasi karyawan butuh persetujuan
        // Super Admin. Ganti email & password setelah login pertama kali.
        // Password hanya di-set saat akun dibuat; seeder ulang tidak menimpa password yang sudah diganti.
        $admin = User::firstOrNew(['email' => 'superadmin@tintainspirasi.co.id']);
        if (! $admin->exists) {
            $admin->password = 'ChangeMe123!'; // di-hash otomatis oleh cast 'hashed'
        }
        $admin->fill([
            'username' => 'superadmin',
            'role' => 'super_admin',
            'status' => 'aktif',
            'status_persetujuan' => 'approved',
            'approved_at' => $admin->approved_at ?? now(),
        ])->save();
    }
}
