<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceException;
use App\Http\Requests\AttendanceCaptureRequest;
use App\Services\AttendanceService;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman PENCATATAN absensi.
 */
class AttendanceRecordController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    public function create(Request $request): View
    {
        $employee = $request->user()->employee;
        $now = CarbonImmutable::now();
        $today = $employee ? $this->attendance->today($employee, $now) : null;

        return view('attendance.record', [
            'employee' => $employee,
            'today' => $today,
            'now' => $now,
            'isWorkingDay' => WorkCalendar::isWorkingDay($now),
            'workStart' => WorkCalendar::workStart($now)->format('H.i'),
            // check-in bila belum ada baris hari ini; check-out bila sudah check-in & belum check-out
            'mode' => ! $today ? 'masuk' : ($today->jam_keluar === null && $today->status_persetujuan !== 'rejected' ? 'keluar' : 'selesai'),
        ]);
    }

    public function checkIn(AttendanceCaptureRequest $request): RedirectResponse
    {
        return $this->handle($request, 'checkIn', 'Check-in berhasil dicatat.');
    }

    public function checkOut(AttendanceCaptureRequest $request): RedirectResponse
    {
        return $this->handle($request, 'checkOut', 'Check-out berhasil dicatat.');
    }

    private function handle(AttendanceCaptureRequest $request, string $method, string $success): RedirectResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return back()->withErrors(['absensi' => 'Akun Anda belum terhubung dengan data karyawan. Hubungi HRD.']);
        }

        try {
            $this->attendance->{$method}(
                $employee,
                $request->file('foto'),
                (float) $request->input('latitude'),
                (float) $request->input('longitude'),
            );
        } catch (AttendanceException $e) {
            return back()->withErrors(['absensi' => $e->getMessage()]);
        }

        return redirect()->route('attendance.record')->with('status', $success);
    }
}
