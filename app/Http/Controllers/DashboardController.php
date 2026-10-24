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
        $cutoff = $dayStart->setTimeFromTimeString((string) config('attendance.work_start', '09:00'));

        $scopes = $this->statusScopes($dayStart, $cutoff);
        $scope = $this->scopedEmployees($user);

        // ---- Kartu statistik ----
        $quarterStart = $now->startOfQuarter();

        $totalEmployees = (clone $scope)->count();
        $newEmployees = (clone $scope)->where('tbl_karyawan.created_at', '>=', $quarterStart)->count();

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
            ->select('jam_masuk')
            ->whereColumn('tbl_absensi.id_karyawan', 'tbl_karyawan.id_karyawan');
        $scopes['present']($firstCheckIn);
        $firstCheckIn->orderBy('jam_masuk')->limit(1);

        $employees = (clone $scope)
            ->select('tbl_karyawan.*')
            ->addSelect(['first_check_in' => $firstCheckIn])
            ->with(['user:id_pengguna,username,foto_profil', 'department:id_departemen,nama_departemen'])
            ->orderByRaw('first_check_in IS NULL')
            ->orderByDesc('first_check_in')
            ->orderBy('tbl_karyawan.id_karyawan')
            ->limit(self::ROWS)
            ->get();

        $leaveIds = LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $dayStart->toDateString())
            ->whereDate('tanggal_selesai', '>=', $dayStart->toDateString())
            ->whereIn('id_karyawan', $employees->pluck('id_karyawan')->all())
            ->pluck('id_karyawan')
            ->flip();

        $cutoffTime = $cutoff->format('H:i:s');

        $rows = $employees->map(function (Employee $employee) use ($cutoffTime, $leaveIds) {
            $firstIn = $employee->first_check_in; // TIME 'HH:MM:SS' atau null

            return [
                'name' => $employee->nama_lengkap,
                'photo' => $employee->photo_url,
                'status' => $firstIn
                    ? ($firstIn <= $cutoffTime ? 'on_time' : 'late')
                    : ($leaveIds->has($employee->id_karyawan) ? 'leave' : 'absent'),
                'check_in' => $firstIn ? CarbonImmutable::parse($firstIn)->format('H.i') : null,
            ];
        });

        // ---- Announcement (search + filter periode) ----
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $period = $request->query('period');
        $period = in_array($period, ['week', 'month'], true) ? $period : null;

        $announcementQuery = Announcement::query()
            ->whereNotNull('tanggal_terbit')
            ->where('tanggal_terbit', '<=', $now);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $announcementQuery->where(fn (Builder $q) => $q->where('judul', 'like', $like)->orWhere('isi', 'like', $like));
        }
        if ($period === 'week') {
            $announcementQuery->where('tanggal_terbit', '>=', $now->startOfWeek());
        } elseif ($period === 'month') {
            $announcementQuery->where('tanggal_terbit', '>=', $now->startOfMonth());
        }

        $announcements = $announcementQuery->latest('tanggal_terbit')->limit(10)->get();

        // ---- Submission: pengajuan izin/cuti pending ----
        $pendingQuery = LeaveRequest::query()
            ->pending()
            ->whereIn('id_karyawan', (clone $scope)->select('tbl_karyawan.id_karyawan'));
        $pendingTotal = (clone $pendingQuery)->count();
        $submissions = $pendingQuery
            ->with('employee:id_karyawan,nama_lengkap')
            ->latest()
            ->limit(5)
            ->get();

        // ---- Task milik user yang login ----
        $tasks = Task::query()
            ->where('id_karyawan', $user->employee?->id_karyawan ?? 0)
            ->where('status', 'terbuka')
            ->orderBy('tenggat')
            ->limit(10)
            ->get();

        // ---- Schedule mendatang ----
        // Super Admin / HRD / Direktur melihat semua; role lain hanya yang dibuat atau diikutinya.
        $scheduleQuery = Schedule::query()->where('waktu_mulai', '>=', $dayStart);
        if (! ($user->isSuperAdmin() || $user->isHrd() || $user->isDirektur())) {
            $employeeId = $user->employee?->id_karyawan ?? 0;
            $scheduleQuery->where(fn (Builder $q) => $q
                ->where('dibuat_oleh', $user->id_pengguna)
                ->orWhereHas('attendees', fn (Builder $a) => $a->where('tbl_karyawan.id_karyawan', $employeeId)));
        }
        $scheduleTotal = (clone $scheduleQuery)->count();
        $schedules = $scheduleQuery
            ->with('attendees.user:id_pengguna,username,foto_profil')
            ->orderBy('waktu_mulai')
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
    // Dashboard Karyawan
    // =====================================================================

    private function employeeDashboard(User $user): View
    {
        $now = CarbonImmutable::now();
        $employee = $user->employee;
        $cutoffTime = $now->startOfDay()
            ->setTimeFromTimeString((string) config('attendance.work_start', '09:00'))
            ->format('H:i:s');

        $attendance = $employee?->attendances()
            ->where('tanggal', $now->toDateString())
            ->where('status_persetujuan', '!=', 'rejected')
            ->first();

        $jamMasuk = $attendance?->jam_masuk;

        return view('dashboard.employee', [
            'user' => $user,
            'photo' => $employee?->photo_url,
            'employee' => $employee,
            'greeting' => $this->greeting($now),
            'dateLabel' => $now->locale('id')->translatedFormat('l, j F Y'),
            'checkIn' => $jamMasuk ? CarbonImmutable::parse($jamMasuk)->format('H.i') : null,
            'isLate' => $jamMasuk ? $jamMasuk > $cutoffTime : false,
            'workStart' => substr($cutoffTime, 0, 5),
        ]);
    }

    // =====================================================================
    // Helper
    // =====================================================================

    /**
     * Karyawan yang boleh dilihat user ini:
     * Super Admin / HRD / Direktur = semua karyawan aktif (akun aktif & disetujui);
     * Supervisor = hanya bawahan langsungnya.
     */
    private function scopedEmployees(User $user): Builder
    {
        $query = Employee::query()
            ->where('tbl_karyawan.status', 'aktif')
            ->whereHas('user', fn (Builder $q) => $q
                ->where('status_persetujuan', 'approved')
                ->where('status', 'aktif'));

        if ($user->isSupervisor()) {
            $query->where('tbl_karyawan.id_atasan', $user->employee?->id_karyawan ?? 0);
        }

        return $query;
    }

    /**
     * Kondisi reusable (dipakai whereHas / whereDoesntHave).
     * "present" = ada absensi hari ini yang bukan alpha dan belum ditolak atasan.
     *
     * @return array{present: Closure, onTime: Closure, onLeave: Closure}
     */
    private function statusScopes(CarbonImmutable $day, CarbonImmutable $cutoff): array
    {
        $date = $day->toDateString();
        $cutoffTime = $cutoff->format('H:i:s');

        $present = fn (Builder $q) => $q
            ->where('tanggal', $date)
            ->where('status', '!=', 'alpha')
            ->where('status_persetujuan', '!=', 'rejected');

        return [
            'present' => $present,
            'onTime' => fn (Builder $q) => $present($q)->where('jam_masuk', '<=', $cutoffTime),
            'onLeave' => fn (Builder $q) => $q
                ->where('status', 'approved')
                ->whereDate('tanggal_mulai', '<=', $date)
                ->whereDate('tanggal_selesai', '>=', $date),
        ];
    }

    /**
     * Prioritas status: ada absensi > sedang cuti/izin disetujui > absent.
     * "Late" = jam masuk hari itu melewati jam masuk kerja (config/attendance.php).
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
