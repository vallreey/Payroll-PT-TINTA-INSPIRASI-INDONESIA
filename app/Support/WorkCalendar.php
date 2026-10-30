<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Aturan jam & hari kerja dari config/attendance.php. Satu-satunya tempat aturan ini dihitung. */
final class WorkCalendar
{
    public static function isWorkingDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeekIso, config('attendance.work_days', [1, 2, 3, 4, 5]), true);
    }

    /** Jam masuk pada tanggal tersebut. */
    public static function workStart(CarbonInterface $date): CarbonImmutable
    {
        return self::at($date, (string) config('attendance.work_start', '09:00'));
    }

    public static function workEnd(CarbonInterface $date): CarbonImmutable
    {
        return self::at($date, (string) config('attendance.work_end', '16:00'));
    }

    /** Check-in pada atau sebelum waktu ini dianggap "Early Clock In". */
    public static function earlyCutoff(CarbonInterface $date): CarbonImmutable
    {
        return self::workStart($date)->subMinutes((int) config('attendance.early_minutes', 30));
    }

    private static function at(CarbonInterface $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString())->setTimeFromTimeString($time);
    }
}
