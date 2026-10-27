<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** SDD "ApprovalService": siapa yang menyetujui absensi, dan proses persetujuannya. */
class ApprovalService
{
    public const APPROVER_ROLES = ['super_admin', 'hrd', 'direktur', 'supervisor'];

    public function __construct(private NotificationService $notifications)
    {
    }

    /**
     * Penerima notifikasi saat karyawan mengirim absensi: atasan langsung bila ada,
     * jika tidak ada maka seluruh HRD, jika HRD juga tidak ada maka Super Admin.
     *
     * @return Collection<int, User>
     */
    public function approversFor(Employee $employee): Collection
    {
        $active = fn ($query) => $query->where('status', 'aktif')->where('status_persetujuan', 'approved');

        $boss = $employee->supervisor?->user;
        if ($boss && $boss->isActive() && $boss->isApproved()) {
            return collect([$boss]);
        }

        foreach (['hrd', 'super_admin'] as $role) {
            $users = $active(User::query()->where('role', $role))->get();
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        return collect();
    }

    /**
     * Aturan: harus berperan penyetuju, absensi masih pending, tidak boleh menyetujui absensinya sendiri,
     * dan Supervisor hanya untuk bawahan langsungnya. Memakai relasi `employee` yang sudah dimuat.
     */
    public function canApprove(User $approver, Attendance $attendance): bool
    {
        if (! in_array($approver->role, self::APPROVER_ROLES, true)) {
            return false;
        }

        if ($attendance->status_persetujuan !== 'pending') {
            return false;
        }

        $ownId = $approver->employee?->id_karyawan;
        if ($ownId !== null && (int) $ownId === (int) $attendance->id_karyawan) {
            return false;
        }

        if ($approver->isSupervisor()) {
            return $ownId !== null && (int) $attendance->employee?->id_atasan === (int) $ownId;
        }

        return true;
    }

    public function approve(Attendance $attendance, User $approver): void
    {
        $this->decide($attendance, $approver, 'approved');
    }

    public function reject(Attendance $attendance, User $approver): void
    {
        $this->decide($attendance, $approver, 'rejected');
    }

    private function decide(Attendance $attendance, User $approver, string $decision): void
    {
        $attendance->loadMissing('employee.user');

        if (! $this->canApprove($approver, $attendance)) {
            throw new AttendanceException('Absensi ini tidak dapat Anda proses.');
        }

        // Update bersyarat: jika dua penyetuju menekan bersamaan, hanya satu yang berhasil.
        $affected = Attendance::query()
            ->whereKey($attendance->getKey())
            ->where('status_persetujuan', 'pending')
            ->update([
                'status_persetujuan' => $decision,
                'approved_by' => $approver->id_pengguna,
                'approved_at' => now(),
            ]);

        if ($affected === 0) {
            throw new AttendanceException('Absensi ini sudah diproses oleh penyetuju lain.');
        }

        $tanggal = CarbonImmutable::parse($attendance->tanggal)->locale('id')->translatedFormat('j F Y');
        $oleh = $approver->nama_tampil;
        $kata = $decision === 'approved' ? 'disetujui' : 'ditolak';

        if ($owner = $attendance->employee?->user) {
            $this->notifications->send(
                $owner,
                'Absensi '.$kata,
                "Absensi Anda tanggal {$tanggal} telah {$kata} oleh {$oleh}.",
                'absensi',
            );
        }
    }
}
