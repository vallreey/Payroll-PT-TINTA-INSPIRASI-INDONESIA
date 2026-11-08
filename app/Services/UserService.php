<?php

namespace App\Services;

use App\Exceptions\AccountException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Aturan manajemen akun: buat, ubah, reset kata sandi, aktif/nonaktif, dan persetujuan registrasi. */
class UserService
{
    public function __construct(private EmployeeService $employees)
    {
    }

    /** Super Admin: akun saja. Peran lain: akun + data karyawan dasar (NIK, nama), seperti registrasi. */
    public function create(User $actor, array $data): User
    {
        if ($data['role'] === 'super_admin') {
            return User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'super_admin',
                'status' => 'aktif',
                'status_persetujuan' => 'approved',
                'approved_by' => $actor->id_pengguna,
                'approved_at' => now(),
            ]);
        }

        // Akun + karyawan dibuat satu transaksi oleh EmployeeService
        return $this->employees->create($actor, $data)->user;
    }

    /** Ubah username, email, dan peran. Peran sendiri tidak boleh diubah. */
    public function update(User $actor, User $target, array $data): void
    {
        if ($data['role'] !== $target->role) {
            if ($actor->id_pengguna === $target->id_pengguna) {
                throw new AccountException('Anda tidak dapat mengubah peran akun Anda sendiri.');
            }
            $this->assertNotLastSuperAdmin($target, 'mengubah peran');
        }

        $target->update([
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'role' => $data['role'],
        ]);
    }

    /** Mengganti kata sandi dan memutus sesi lama (kecuali sesi yang sedang dipakai, bila akun sendiri). */
    public function resetPassword(User $target, string $password, ?string $keepSessionId = null): void
    {
        // forceFill: remember_token bukan kolom fillable, tanpa ini cookie "ingat saya" lama tetap berlaku
        $target->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        $this->revokeSessions($target, $keepSessionId);
    }

    /** Aktif/nonaktifkan akun; akun nonaktif langsung kehilangan sesinya. */
    public function setStatus(User $target, string $status): void
    {
        if ($status === 'nonaktif') {
            $this->assertNotLastSuperAdmin($target, 'menonaktifkan');
        }

        $target->update(['status' => $status]);

        if ($status === 'nonaktif') {
            $this->revokeSessions($target);
        }
    }

    /** Setujui/tolak registrasi. Update bersyarat: dua Super Admin tidak bisa memutuskan bersamaan. */
    public function decide(User $actor, User $target, string $decision): void
    {
        $affected = User::query()
            ->whereKey($target->getKey())
            ->where('status_persetujuan', 'pending')
            ->update(['status_persetujuan' => $decision, 'approved_by' => $actor->id_pengguna, 'approved_at' => now()]);

        if ($affected === 0) {
            throw new AccountException('Registrasi ini sudah diproses.');
        }
    }

    // Selalu sisakan minimal satu Super Admin aktif agar sistem tidak terkunci
    private function assertNotLastSuperAdmin(User $target, string $action): void
    {
        $isActiveAdmin = $target->role === 'super_admin' && $target->status === 'aktif' && $target->status_persetujuan === 'approved';
        if (! $isActiveAdmin) {
            return;
        }

        $others = User::query()
            ->where('role', 'super_admin')->where('status', 'aktif')->where('status_persetujuan', 'approved')
            ->whereKeyNot($target->getKey())
            ->exists();

        if (! $others) {
            throw new AccountException("Tidak dapat {$action}: ini satu-satunya Super Admin aktif.");
        }
    }

    // Hapus sesi tersimpan pengguna (hanya bila sesi disimpan di database)
    private function revokeSessions(User $user, ?string $except = null): void
    {
        $table = config('session.table', 'sessions');

        if (config('session.driver') !== 'database' || ! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('user_id', $user->id_pengguna)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->delete();
    }
}
