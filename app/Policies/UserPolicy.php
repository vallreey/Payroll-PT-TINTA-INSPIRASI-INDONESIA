<?php

namespace App\Policies;

use App\Models\User;

/** Hak akses Manajemen Pengguna: hanya Super Admin. Ditemukan otomatis (User -> UserPolicy). */
class UserPolicy
{
    // Semua aksi di bawah ini khusus Super Admin
    private function admin(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function viewAny(User $actor): bool
    {
        return $this->admin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->admin($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->admin($actor);
    }

    // Boleh mereset kata sandi siapa pun, termasuk akunnya sendiri
    public function resetPassword(User $actor, User $target): bool
    {
        return $this->admin($actor);
    }

    // Aktif/nonaktifkan: tidak boleh untuk akun sendiri
    public function toggleStatus(User $actor, User $target): bool
    {
        return $this->admin($actor) && $actor->id_pengguna !== $target->id_pengguna;
    }

    // Setujui/tolak registrasi: hanya akun yang masih menunggu
    public function decide(User $actor, User $target): bool
    {
        return $this->admin($actor) && $target->status_persetujuan === 'pending';
    }
}
