<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hitungan untuk kartu "Kehadiran" di halaman Absensi.
 *
 *  Present: on_time (hadir, tidak awal) | early (hadir, >= early_minutes sebelum jam masuk) | late (terlambat)
 *  Absent : no_clock_in (tidak ada absensi valid, tidak sedang izin/cuti, pada hari kerja)
 *           no_clock_out (sudah check-in tetapi belum check-out; baru dihitung setelah jam pulang)
 *           leaved (check-out sebelum jam pulang)
 *  Away   : day_off (cuti + sakit yang disetujui) | time_off (izin yang disetujui)
 *
 * Absensi yang DITOLAK atasan tidak dihitung sebagai kehadiran.
 */
class AttendanceSummary
{
    /**
     * @param  Builder  $employees  query Employee yang sudah dibatasi hak lihat pengguna
     * @return array{present: array<string,int>, absent: array<string,int>, away: array<string,int>, working_day: bool}
     */
    public function forDate(Builder $employees, CarbonImmutable $date): array
    {
        $workingDay = WorkCalendar::isWorkingDay($date);
        $day = $date->toDateString();

        // Hanya karyawan aktif (akun aktif & disetujui) yang sudah masuk kerja pada tanggal itu.
        $activeIds = (clone $employees)
            ->where('tbl_karyawan.status', 'aktif')
            ->whereHas('user', fn (Builder $q) => $q->where('status', 'aktif')->where('status_persetujuan', 'approved'))
            ->where(fn (Builder $q) => $q->whereNull('tbl_karyawan.tanggal_masuk')->orWhere('tbl_karyawan.tanggal_masuk', '<=', $day))
            ->select('tbl_karyawan.id_karyawan');

        $records = Attendance::query()
            ->whereIn('id_karyawan', $activeIds)
            ->where('tanggal', $day)
            ->where('status_persetujuan', '!=', 'rejected')
            ->where('status', '!=', 'alpha');

        $early = WorkCalendar::earlyCutoff($date)->format('H:i:s');
        $end = WorkCalendar::workEnd($date)->format('H:i:s');

        $late = (clone $records)->where('status', 'terlambat')->count();
        $onTimeAll = (clone $records)->where('status', 'hadir');
        $earlyCount = (clone $onTimeAll)->where('jam_masuk', '<=', $early)->count();
        $onTime = (clone $onTimeAll)->where('jam_masuk', '>', $early)->count();

        // Izin/cuti/sakit yang disetujui dan mencakup tanggal ini (satu orang dihitung sekali per jenis).
        $leaves = fn (array $kinds) => LeaveRequest::query()
            ->where('status', 'approved')
            ->whereIn('jenis', $kinds)
            ->whereDate('tanggal_mulai', '<=', $day)
            ->whereDate('tanggal_selesai', '>=', $day)
            ->whereIn('id_karyawan', $activeIds);

        $dayOff = $workingDay ? $leaves(['cuti', 'sakit'])->distinct()->count('id_karyawan') : 0;
        $timeOff = $workingDay ? $leaves(['izin'])->distinct()->count('id_karyawan') : 0;

        $noClockIn = 0;
        if ($workingDay) {
            $onLeave = $leaves(['cuti', 'sakit', 'izin'])->select('id_karyawan');
            $noClockIn = Employee::query()
                ->whereIn('tbl_karyawan.id_karyawan', $activeIds)
                ->whereNotIn('tbl_karyawan.id_karyawan', (clone $records)->select('id_karyawan'))
                ->whereNotIn('tbl_karyawan.id_karyawan', $onLeave)
                ->count();
        }

        $isToday = $date->isSameDay(CarbonImmutable::now());
        $afterClosing = ! $isToday || CarbonImmutable::now()->format('H:i:s') >= $end;
        $noClockOut = $workingDay && $afterClosing ? (clone $records)->whereNull('jam_keluar')->count() : 0;
        $leaved = (clone $records)->whereNotNull('jam_keluar')->where('jam_keluar', '<', $end)->count();

        return [
            'working_day' => $workingDay,
            'present' => ['on_time' => $onTime, 'early' => $earlyCount, 'late' => $late],
            'absent' => ['leaved' => $leaved, 'no_clock_in' => $noClockIn, 'no_clock_out' => $noClockOut],
            'away' => ['day_off' => $dayOff, 'time_off' => $timeOff],
        ];
    }
}
