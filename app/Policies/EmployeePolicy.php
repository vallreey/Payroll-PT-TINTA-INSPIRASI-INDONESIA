<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/** Hak akses data karyawan. Ditemukan otomatis oleh Laravel (nama Employee -> EmployeePolicy). */
class EmployeePolicy
{
    // Peran yang boleh membuka halaman Karyawan
    private const VIEWERS = ['super_admin', 'hrd', 'direktur', 'supervisor'];

    // Peran yang boleh mengubah data (termasuk gaji dan akun login)
    private const MANAGERS = ['super_admin', 'hrd'];

    // Peran yang boleh melihat data sensitif (gaji, rekening, scan KTP)
    private const SENSITIVE = ['super_admin', 'hrd', 'direktur'];

    public function viewAny(User $user): bool
    {
        return in_array($user->role, self::VIEWERS, true);
    }

    // Boleh melihat bila peran sesuai dan karyawan ada dalam jangkauannya (Supervisor: bawahan + dirinya)
    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user)
            && Employee::query()->visibleTo($user)->whereKey($employee->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    public function update(User $user, Employee $employee): bool
    {
        return in_array($user->role, self::MANAGERS, true);
    }

    // Aktif/nonaktifkan: hanya pengelola, dan tidak boleh untuk akun sendiri
    public function toggleStatus(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee) && $employee->id_pengguna !== $user->id_pengguna;
    }

    public function viewSensitive(User $user): bool
    {
        return in_array($user->role, self::SENSITIVE, true);
    }

    // Hanya Super Admin yang boleh menentukan peran akun saat membuat karyawan baru
    public function chooseRole(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
