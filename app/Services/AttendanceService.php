<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * SDD "AttendanceService": check-in / check-out.
 *
 * Aturan yang dijaga di sini (bukan di controller):
 *  - waktu SELALU dari server, tidak pernah dari perangkat pengguna
 *  - akun & karyawan harus aktif dan sudah disetujui
 *  - satu baris per karyawan per hari (juga dijaga unique index di database)
 *  - foto + koordinat wajib untuk check-in maupun check-out (SRS FR-007, AC-005)
 *  - status "terlambat" bila check-in melewati jam masuk di config/attendance.php
 *  - atasan/HRD diberi notifikasi saat ada absensi baru yang menunggu persetujuan
 */
class AttendanceService
{
    public const DISK = 'local'; // privat: foto hanya bisa dibuka lewat route yang memeriksa hak akses

    public function __construct(private ApprovalService $approvals, private NotificationService $notifications)
    {
    }

    public function today(Employee $employee, ?CarbonImmutable $now = null): ?Attendance
    {
        $now ??= CarbonImmutable::now();

        return Attendance::query()
            ->where('id_karyawan', $employee->id_karyawan)
            ->where('tanggal', $now->toDateString())
            ->first();
    }

    /** 'hadir' bila tepat waktu, 'terlambat' bila melewati jam masuk. */
    public function statusFor(CarbonImmutable $checkIn): string
    {
        return $checkIn->greaterThan(WorkCalendar::workStart($checkIn)) ? 'terlambat' : 'hadir';
    }

    public function checkIn(Employee $employee, UploadedFile $photo, float $latitude, float $longitude): Attendance
    {
        $this->assertCanRecord($employee);

        $now = CarbonImmutable::now();

        if ($existing = $this->today($employee, $now)) {
            throw new AttendanceException(
                $existing->status_persetujuan === 'rejected'
                    ? 'Absensi hari ini sudah ditolak oleh atasan. Hubungi HRD untuk koreksi.'
                    : 'Anda sudah check-in hari ini pukul '.substr($existing->jam_masuk, 0, 5).'.'
            );
        }

        $path = $this->storePhoto($photo, $now);

        try {
            $attendance = DB::transaction(fn () => Attendance::create([
                'id_karyawan' => $employee->id_karyawan,
                'tanggal' => $now->toDateString(),
                'jam_masuk' => $now->format('H:i:s'),
                'foto_masuk' => $path,
                'lat_masuk' => $latitude,
                'long_masuk' => $longitude,
                'status' => $this->statusFor($now),
                'status_persetujuan' => 'pending',
            ]));
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan bersamaan (mis. tombol ditekan dua kali): yang kedua ditolak.
            Storage::disk(self::DISK)->delete($path);
            throw new AttendanceException('Absensi hari ini sudah tercatat.');
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }

        $this->notifyApprovers($employee, $attendance);

        return $attendance;
    }

    public function checkOut(Employee $employee, UploadedFile $photo, float $latitude, float $longitude): Attendance
    {
        $this->assertCanRecord($employee);

        $now = CarbonImmutable::now();
        $attendance = $this->today($employee, $now);

        if (! $attendance) {
            throw new AttendanceException('Anda belum check-in hari ini.');
        }
        if ($attendance->status_persetujuan === 'rejected') {
            throw new AttendanceException('Absensi hari ini sudah ditolak oleh atasan. Hubungi HRD untuk koreksi.');
        }
        if ($attendance->jam_keluar !== null) {
            throw new AttendanceException('Anda sudah check-out hari ini pukul '.substr($attendance->jam_keluar, 0, 5).'.');
        }

        $path = $this->storePhoto($photo, $now);

        // Update bersyarat: hanya jika jam_keluar masih kosong (aman dari penekanan ganda).
        $affected = Attendance::query()
            ->whereKey($attendance->getKey())
            ->whereNull('jam_keluar')
            ->update([
                'jam_keluar' => $now->format('H:i:s'),
                'foto_keluar' => $path,
                'lat_keluar' => $latitude,
                'long_keluar' => $longitude,
                'updated_at' => $now,
            ]);

        if ($affected === 0) {
            Storage::disk(self::DISK)->delete($path);
            throw new AttendanceException('Anda sudah check-out hari ini.');
        }

        return $attendance->refresh();
    }

    private function assertCanRecord(Employee $employee): void
    {
        $user = $employee->user;

        if ($employee->status !== 'aktif' || ! $user || ! $user->isActive() || ! $user->isApproved()) {
            throw new AttendanceException('Akun atau data karyawan Anda tidak aktif. Hubungi HRD.');
        }
    }

    private function storePhoto(UploadedFile $photo, CarbonImmutable $now): string
    {
        $path = $photo->store('absensi/'.$now->format('Y/m'), self::DISK);

        if (! $path) {
            throw new AttendanceException('Foto gagal disimpan. Silakan coba lagi.');
        }

        return $path;
    }

    private function notifyApprovers(Employee $employee, Attendance $attendance): void
    {
        $approvers = $this->approvals->approversFor($employee);
        if ($approvers->isEmpty()) {
            return;
        }

        $status = $attendance->status === 'terlambat' ? 'terlambat' : 'tepat waktu';

        $this->notifications->send(
            $approvers,
            'Absensi menunggu persetujuan',
            "{$employee->nama_lengkap} check-in pukul ".substr($attendance->jam_masuk, 0, 5)." ({$status}).",
            'absensi',
        );
    }
}
