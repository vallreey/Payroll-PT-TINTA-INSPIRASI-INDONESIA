<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobOrder;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    private const STATUSES = ['on_time', 'late', 'absent', 'leave'];

    private const ROWS = 6;

    public function index(Request $request): View
    {
        $user = $request->user();

        // Tampilan utama dashboard mengikuti mockup manajemen (attendance, announcement,
        // schedule, dan ringkasan KPI). Dashboard karyawan tetap bisa dipindah ke route
        // terpisah bila nanti ingin dibuat versi khusus karyawan.
        return $this->managementDashboard($request, $user);
    }

    // =====================================================================
    // Dashboard Super Admin / HRD / Supervisor / Direktur
    // =====================================================================

    private function managementDashboard(Request $request, User $user): View
    {
        $now = CarbonImmutable::now();
        $dayStart = $now->startOfDay();
        $dayEnd = $now->endOfDay();
        $cutoff = $dayStart->setTimeFromTimeString((string) config('attendance.work_start', '09:00'));

        $scopes = $this->statusScopes($dayStart, $dayEnd, $cutoff);
        $scope = $this->scopedEmployees($user);

        // ---- Kartu statistik ----
        $quarterStart = $now->startOfQuarter();

        $totalEmployees = (clone $scope)->count();
        $newEmployees = (clone $scope)->where('employees.created_at', '>=', $quarterStart)->count();

        $totalServices = Service::query()->count();
        $newServices = Service::query()->where('created_at', '>=', $quarterStart)->count();

        $totalJobs = JobOrder::query()->count();
        $newJobs = JobOrder::query()->where('created_at', '>=', $quarterStart)->count();

        $totalDepartments = Department::query()->count();
        $newDepartments = Department::query()->where('created_at', '>=', $quarterStart)->count();

        $stats = [
            ['label' => 'Total karyawan', 'value' => $totalEmployees, 'icon' => 'employees', 'tone' => 'pink', 'route' => 'employees.index',
                'caption' => $newEmployees > 0 ? "{$newEmployees} more than last quarter" : null],
            ['label' => 'Total service', 'value' => $totalServices, 'icon' => 'code', 'tone' => 'violet', 'route' => null,
                'caption' => $this->growthCaption($totalServices, $newServices)],
            ['label' => 'Total job', 'value' => $totalJobs, 'icon' => 'documents', 'tone' => 'blue', 'route' => null,
                'caption' => $this->growthCaption($totalJobs, $newJobs)],
            ['label' => 'Total divisi', 'value' => $totalDepartments, 'icon' => 'org', 'tone' => 'green', 'route' => null,
                'caption' => $newDepartments > 0 ? "{$newDepartments} more than last quarter" : null],
        ];

        // ---- Ringkasan absensi hari ini ----
        $summary = [];
        foreach (self::STATUSES as $key) {
            $summary[$key] = $this->applyStatus(clone $scope, $key, $scopes)->count();
        }

        // ---- 6 aktivitas check-in terbaru hari ini (daftar lengkap ada di halaman Absensi) ----
        $firstCheckIn = Attendance::query()
            ->select('check_in_at')
            ->whereColumn('attendances.employee_id', 'employees.id');
        $scopes['present']($firstCheckIn);
        $firstCheckIn->orderBy('check_in_at')->limit(1);

        $employees = (clone $scope)
            ->select('employees.*')
            ->addSelect(['first_check_in' => $firstCheckIn])
            ->with(['user:id,name', 'department:id,name'])
            ->orderByRaw('first_check_in IS NULL')
            ->orderByDesc('first_check_in')
            ->orderBy('employees.id')
            ->limit(self::ROWS)
            ->get();

        $leaveIds = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dayStart->toDateString())
            ->whereDate('end_date', '>=', $dayStart->toDateString())
            ->whereIn('employee_id', $employees->pluck('id')->all())
            ->pluck('employee_id')
            ->flip();

        $rows = $employees->map(function (Employee $employee) use ($cutoff, $leaveIds) {
            $firstIn = $employee->first_check_in ? CarbonImmutable::parse($employee->first_check_in) : null;

            return [
                'name' => $employee->user?->name ?? '-',
                'photo' => $employee->photo_url,
                'status' => $firstIn
                    ? ($firstIn->lessThanOrEqualTo($cutoff) ? 'on_time' : 'late')
                    : ($leaveIds->has($employee->id) ? 'leave' : 'absent'),
                'check_in' => $firstIn?->format('H.i'),
            ];
        });

        // ---- Announcement (search + filter periode) ----
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $period = $request->query('period');
        $period = in_array($period, ['week', 'month'], true) ? $period : null;

        $announcementQuery = Announcement::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $announcementQuery->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like));
        }
        if ($period === 'week') {
            $announcementQuery->where('published_at', '>=', $now->startOfWeek());
        } elseif ($period === 'month') {
            $announcementQuery->where('published_at', '>=', $now->startOfMonth());
        }

        $announcements = $announcementQuery->latest('published_at')->limit(10)->get();

        // ---- Submission: pengajuan izin/cuti pending ----
        $pendingQuery = LeaveRequest::query()
            ->pending()
            ->whereIn('employee_id', (clone $scope)->select('employees.id'));
        $pendingTotal = (clone $pendingQuery)->count();
        $submissions = $pendingQuery
            ->with(['employee.user:id,name', 'leaveType:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        // ---- Task milik user yang login ----
        $tasks = Task::query()
            ->where('employee_id', $user->employee?->id ?? 0)
            ->where('status', 'open')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        // ---- Schedule mendatang ----
        // Super Admin / HRD / Direktur melihat semua; role lain hanya yang dibuat atau diikutinya.
        $scheduleQuery = Schedule::query()->where('start_at', '>=', $dayStart);
        if (! ($user->isSuperAdmin() || $user->isHrd() || $user->isDirektur())) {
            $employeeId = $user->employee?->id ?? 0;
            $scheduleQuery->where(fn (Builder $q) => $q
                ->where('created_by', $user->id)
                ->orWhereHas('attendees', fn (Builder $a) => $a->where('employees.id', $employeeId)));
        }
        $scheduleTotal = (clone $scheduleQuery)->count();
        $schedules = $scheduleQuery
            ->with('attendees.user:id,name')
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        return view('dashboard.index', [
            'user' => $user,
            'photo' => $user->employee?->photo_url,
            'greeting' => $this->greeting($now),
            'dateLabel' => $now->locale('id')->translatedFormat('l, j F Y'),
            'stats' => $stats,
            'summary' => $summary,
            'rows' => $rows,
            'search' => $search,
            'period' => $period,
            'announcements' => $announcements,
            'pendingTotal' => $pendingTotal,
            'submissions' => $submissions,
            'tasks' => $tasks,
            'schedules' => $schedules,
            'scheduleTotal' => $scheduleTotal,
            'canAnnounce' => $user->isSuperAdmin() || $user->isHrd() || $user->isDirektur(),
        ]);
    }

    // =====================================================================
    // Dashboard Karyawan (tidak melihat data karyawan lain)
    // =====================================================================

    private function employeeDashboard(User $user): View
    {
        $now = CarbonImmutable::now();
        $employee = $user->employee;
        $cutoff = $now->startOfDay()->setTimeFromTimeString((string) config('attendance.work_start', '09:00'));

        $attendance = $employee?->attendances()
            ->whereBetween('check_in_at', [$now->startOfDay(), $now->endOfDay()])
            ->where('status', '!=', 'rejected')
            ->orderBy('check_in_at')
            ->first();

        $checkIn = $attendance?->check_in_at;

        return view('dashboard.employee', [
            'user' => $user,
            'photo' => $employee?->photo_url,
            'employee' => $employee,
            'greeting' => $this->greeting($now),
            'dateLabel' => $now->locale('id')->translatedFormat('l, j F Y'),
            'checkIn' => $checkIn?->format('H.i'),
            'isLate' => $checkIn ? $checkIn->greaterThan($cutoff) : false,
            'workStart' => $cutoff->format('H.i'),
        ]);
    }

    // =====================================================================
    // Helper
    // =====================================================================

    /**
     * Karyawan yang boleh dilihat user ini:
     * Super Admin / HRD / Direktur = semua karyawan (akun approved);
     * Supervisor = hanya bawahan langsungnya.
     */
    private function scopedEmployees(User $user): Builder
    {
        $query = Employee::query()
            ->whereHas('user', fn (Builder $q) => $q->where('status', 'approved'));

        if ($user->isSupervisor()) {
            $query->where('employees.supervisor_id', $user->employee?->id ?? 0);
        }

        return $query;
    }

    /**
     * Kondisi reusable (dipakai whereHas / whereDoesntHave).
     * "present" = ada check-in hari ini yang belum ditolak approver.
     *
     * @return array{present: Closure, onTime: Closure, onLeave: Closure}
     */
    private function statusScopes(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $cutoff): array
    {
        $present = fn (Builder $q) => $q
            ->whereBetween('check_in_at', [$start, $end])
            ->where('status', '!=', 'rejected');

        return [
            'present' => $present,
            'onTime' => fn (Builder $q) => $present($q)->where('check_in_at', '<=', $cutoff),
            'onLeave' => fn (Builder $q) => $q
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $start->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString()),
        ];
    }

    /**
     * Prioritas status: ada check-in > sedang cuti/izin disetujui > absent.
     * "Late" = check-in pertama hari itu melewati jam masuk.
     */
    private function applyStatus(Builder $query, string $status, array $scopes): Builder
    {
        return match ($status) {
            'on_time' => $query->whereHas('attendances', $scopes['onTime']),
            'late' => $query->whereHas('attendances', $scopes['present'])
                ->whereDoesntHave('attendances', $scopes['onTime']),
            'leave' => $query->whereDoesntHave('attendances', $scopes['present'])
                ->whereHas('leaveRequests', $scopes['onLeave']),
            'absent' => $query->whereDoesntHave('attendances', $scopes['present'])
                ->whereDoesntHave('leaveRequests', $scopes['onLeave']),
        };
    }

    private function growthCaption(int $total, int $added): ?string
    {
        if ($added <= 0) {
            return null;
        }

        $before = $total - $added;

        return $before > 0
            ? round($added / $before * 100).'% more than last quarter'
            : "{$added} new this quarter";
    }

    private function greeting(CarbonImmutable $now): string
    {
        return match (true) {
            $now->hour < 11 => 'Pagi',
            $now->hour < 15 => 'Siang',
            $now->hour < 18 => 'Sore',
            default => 'Malam',
        };
    }
}
