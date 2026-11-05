<?php

namespace App\Http\Controllers;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AttendanceSummary;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman PEMANTAUAN absensi (web). Pencatatan absensi dilakukan lewat ponsel:
 * lihat AttendanceRecordController.
 */
class AttendanceController extends Controller
{
    private const STATUS_FILTERS = ['on_time', 'late', 'pending', 'approved', 'rejected'];

    private const EXPORT_LIMIT = 5000;

    public function __construct(private ApprovalService $approvals)
    {
    }

    public function index(Request $request, AttendanceSummary $summary): View
    {
        $user = $request->user();
        $filters = $this->filters($request, $user);

        $rows = $this->query($user, $filters)->paginate(config('attendance.per_page', 10))->withQueryString();

        $canApprove = [];
        foreach ($rows as $attendance) {
            $canApprove[$attendance->id_absensi] = $this->approvals->canApprove($user, $attendance);
        }

        $isManager = $user->isApprover();
        $summaryDate = $filters['date'] ?? CarbonImmutable::now()->startOfDay();

        return view('attendance.index', [
            'user' => $user,
            'isManager' => $isManager,
            'rows' => $rows,
            'canApprove' => $canApprove,
            'filters' => $filters,
            'activeFilters' => collect([$filters['date'], $filters['division'], $filters['status']])->filter()->count(),
            'departments' => $isManager ? Department::query()->orderBy('nama_departemen')->get(['id_departemen', 'nama_departemen']) : collect(),
            'summary' => $isManager ? $summary->forDate(Employee::query()->visibleTo($user), $summaryDate) : null,
            'summaryDate' => $summaryDate,
            'canRecord' => $user->employee !== null,
        ]);
    }

    public function approve(Request $request, Attendance $attendance): RedirectResponse
    {
        return $this->decide($request, $attendance, 'approve');
    }

    public function reject(Request $request, Attendance $attendance): RedirectResponse
    {
        return $this->decide($request, $attendance, 'reject');
    }

    /** Foto bukti kehadiran. Disimpan privat; hanya yang berhak melihat karyawan tersebut yang boleh membukanya. */
    public function photo(Request $request, Attendance $attendance, string $jenis)
    {
        abort_unless(in_array($jenis, ['masuk', 'keluar'], true), 404);
        $this->authorizeView($request->user(), $attendance);

        $path = $jenis === 'masuk' ? $attendance->foto_masuk : $attendance->foto_keluar;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportExcel(Request $request): Response|StreamedResponse
    {
        $user = $request->user();
        $filters = $this->filters($request, $user);

        $rows = $this->query($user, $filters)->limit(self::EXPORT_LIMIT)->get()
            ->map(fn (Attendance $a) => $this->exportRow($a));

        $binary = SimpleXlsx::build($this->exportHeaders(), $rows, 'Absensi');
        $name = 'absensi-'.($filters['date']?->toDateString() ?? 'semua-tanggal').'.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Halaman siap cetak; pengguna memilih "Simpan sebagai PDF" di dialog cetak browser. */
    public function exportPrint(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request, $user);

        $rows = $this->query($user, $filters)->limit(self::EXPORT_LIMIT)->get()
            ->map(fn (Attendance $a) => $this->exportRow($a));

        // Memakai halaman cetak umum (exports.table)
        return view('exports.table', [
            'title' => 'Rekap Absensi Karyawan',
            'subtitle' => 'PT. Tinta Inspirasi Indonesia, tanggal: '
                .($filters['date'] ? $filters['date']->locale('id')->translatedFormat('j F Y') : 'semua tanggal'),
            'headers' => $this->exportHeaders(),
            'rows' => $rows,
            'printedBy' => $user->nama_tampil,
        ]);
    }

    // ---------------------------------------------------------------------

    private function decide(Request $request, Attendance $attendance, string $action): RedirectResponse
    {
        $user = $request->user();
        $attendance->loadMissing('employee');

        // 403: bukan penyetuju, tidak berhak melihat karyawan ini, atau mencoba menyetujui absensinya sendiri
        // / absensi non-bawahan (Supervisor).
        abort_unless(in_array($user->role, ApprovalService::APPROVER_ROLES, true), 403);
        $this->authorizeView($user, $attendance);

        if ($attendance->status_persetujuan !== 'pending') {
            return back()->with('error', 'Absensi ini sudah diproses.');
        }

        abort_unless($this->approvals->canApprove($user, $attendance), 403);

        try {
            $action === 'approve'
                ? $this->approvals->approve($attendance, $user)
                : $this->approvals->reject($attendance, $user);
        } catch (AttendanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $action === 'approve' ? 'Absensi disetujui.' : 'Absensi ditolak.');
    }

    private function authorizeView(User $user, Attendance $attendance): void
    {
        $allowed = Employee::query()->visibleTo($user)->whereKey($attendance->id_karyawan)->exists();
        abort_unless($allowed, 403);
    }

    /** @return array{date: ?CarbonImmutable, division: ?int, status: ?string, q: string} */
    private function filters(Request $request, User $user): array
    {
        // Tanggal: bila parameter tidak ada, Super Admin/HRD/Supervisor/Direktur melihat HARI INI;
        // karyawan melihat riwayatnya. Parameter kosong (date=) berarti semua tanggal.
        $date = null;
        if ($request->has('date')) {
            $raw = (string) $request->query('date');
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $date = CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            }
        } elseif ($user->isApprover()) {
            $date = CarbonImmutable::now()->startOfDay();
        }

        $division = (int) $request->query('division');
        $division = $division > 0 && Department::query()->whereKey($division)->exists() ? $division : null;

        $status = $request->query('status');
        $status = is_string($status) && in_array($status, self::STATUS_FILTERS, true) ? $status : null;

        return [
            'date' => $date,
            'division' => $division,
            'status' => $status,
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
        ];
    }

    private function query(User $user, array $filters): Builder
    {
        $query = Attendance::query()
            ->with(['employee.user:id_pengguna,foto_profil', 'employee.department:id_departemen,nama_departemen'])
            ->whereIn('tbl_absensi.id_karyawan', Employee::query()->visibleTo($user)->select('tbl_karyawan.id_karyawan'));

        if ($filters['date']) {
            $query->where('tbl_absensi.tanggal', $filters['date']->toDateString());
        }
        if ($filters['division']) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('id_departemen', $filters['division']));
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->whereHas('employee', fn (Builder $q) => $q
                ->where('nama_lengkap', 'like', $like)
                ->orWhere('nik', 'like', $like));
        }

        match ($filters['status']) {
            'on_time' => $query->where('tbl_absensi.status', 'hadir'),
            'late' => $query->where('tbl_absensi.status', 'terlambat'),
            'pending', 'approved', 'rejected' => $query->where('tbl_absensi.status_persetujuan', $filters['status']),
            default => null,
        };

        // Penyetuju melihat yang menunggu persetujuan lebih dulu.
        if ($user->isApprover()) {
            $query->orderByRaw("CASE WHEN tbl_absensi.status_persetujuan = 'pending' THEN 0 ELSE 1 END");
        }

        return $query->orderByDesc('tbl_absensi.tanggal')->orderBy('tbl_absensi.jam_masuk')->orderBy('tbl_absensi.id_absensi');
    }

    /** @return list<string> */
    private function exportHeaders(): array
    {
        return ['Tanggal', 'NIK', 'Nama', 'Divisi', 'Check-in', 'Check-out', 'Status', 'Persetujuan', 'Lat masuk', 'Long masuk'];
    }

    /** @return list<string|null> */
    private function exportRow(Attendance $a): array
    {
        $time = fn (?string $t) => $t ? substr(str_replace(':', '.', $t), 0, 5) : null;

        return [
            CarbonImmutable::parse($a->tanggal)->format('d-m-Y'),
            $a->employee?->nik,
            $a->employee?->nama_lengkap,
            $a->employee?->department?->nama_departemen,
            $time($a->jam_masuk),
            $time($a->jam_keluar),
            $a->status === 'terlambat' ? 'Late' : 'On-Time',
            ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$a->status_persetujuan] ?? $a->status_persetujuan,
            (string) $a->lat_masuk,
            (string) $a->long_masuk,
        ];
    }
}
