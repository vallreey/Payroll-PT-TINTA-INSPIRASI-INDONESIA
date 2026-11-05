<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Halaman Karyawan: daftar + ringkasan, tambah, ubah, detail, aktif/nonaktif, dan ekspor. */
class EmployeeController extends Controller
{
    private const PER_PAGE = 10;

    private const EXPORT_LIMIT = 5000;

    // Nilai filter Status: tiga jenis kepegawaian + karyawan nonaktif
    private const STATUS_FILTERS = ['tetap', 'kontrak', 'percobaan', 'nonaktif'];

    public function __construct(private EmployeeService $employees)
    {
    }

    // Daftar karyawan dengan kartu ringkasan Status dan Gender
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $user = $request->user();
        $filters = $this->filters($request);

        return view('employees.index', [
            'rows' => $this->query($user, $filters)->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'activeFilters' => collect([$filters['date'], $filters['division'], $filters['status']])->filter()->count(),
            'summary' => $this->summary($user),
            'departments' => Department::query()->orderBy('nama_departemen')->get(['id_departemen', 'nama_departemen']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Employee::class);

        return view('employees.form', $this->formData(null));
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Peran akun: hanya Super Admin yang boleh memilih; selain itu selalu "karyawan"
        $data['role'] = Gate::allows('chooseRole', Employee::class) ? ($data['role'] ?? 'karyawan') : 'karyawan';

        $employee = $this->employees->create($request->user(), $data, $request->file('dokumen_ktp'));

        return redirect()->route('employees.show', $employee)->with('status', 'Karyawan berhasil ditambahkan.');
    }

    public function show(Request $request, Employee $employee): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['user', 'department', 'supervisor']);

        return view('employees.show', [
            'employee' => $employee,
            'canUpdate' => $request->user()->can('update', $employee),
            'canToggle' => $request->user()->can('toggleStatus', $employee),
            'sensitive' => $request->user()->can('viewSensitive', Employee::class),
        ]);
    }

    public function edit(Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('employees.form', $this->formData($employee));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->validated(), $request->file('dokumen_ktp'));

        return redirect()->route('employees.show', $employee)->with('status', 'Data karyawan diperbarui.');
    }

    // Mengaktifkan / menonaktifkan karyawan (akun loginnya ikut berubah)
    public function updateStatus(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('toggleStatus', $employee);

        $status = $request->validate(['status' => ['required', 'in:aktif,nonaktif']])['status'];
        $this->employees->setStatus($employee, $status);

        return back()->with('status', $status === 'aktif' ? 'Karyawan diaktifkan.' : 'Karyawan dinonaktifkan.');
    }

    // Scan KTP bersifat privat: dibuka lewat sini setelah cek hak akses
    public function ktp(Request $request, Employee $employee)
    {
        Gate::authorize('view', $employee);
        abort_unless($request->user()->can('viewSensitive', Employee::class), 403);
        abort_if(! $employee->dokumen_ktp || ! Storage::disk('local')->exists($employee->dokumen_ktp), 404);

        return Storage::disk('local')->response($employee->dokumen_ktp, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // Ekspor Excel mengikuti filter aktif dan hak lihat pengguna
    public function exportExcel(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $filters = $this->filters($request);
        $rows = $this->exportRows($request->user(), $filters);

        return response(SimpleXlsx::build($this->exportHeaders(), $rows, 'Karyawan'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="karyawan-'.now()->format('Y-m-d').'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // Halaman siap cetak; pilih "Simpan sebagai PDF" di dialog cetak browser
    public function exportPrint(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        return view('exports.table', [
            'title' => 'Daftar Karyawan',
            'subtitle' => 'PT. Tinta Inspirasi Indonesia',
            'headers' => $this->exportHeaders(),
            'rows' => $this->exportRows($request->user(), $this->filters($request)),
            'printedBy' => $request->user()->nama_tampil,
        ]);
    }

    // ---------------------------------------------------------------------

    // Data untuk form tambah/ubah: pilihan divisi dan atasan
    private function formData(?Employee $employee): array
    {
        $supervisors = Employee::query()
            ->where('tbl_karyawan.status', 'aktif')
            ->whereHas('user', fn (Builder $q) => $q->whereIn('role', ['supervisor', 'hrd', 'direktur', 'super_admin']))
            ->when($employee, fn (Builder $q) => $q->whereKeyNot($employee->getKey()))
            ->orderBy('nama_lengkap')
            ->get(['id_karyawan', 'nama_lengkap', 'jabatan']);

        return [
            'employee' => $employee,
            'departments' => Department::query()->orderBy('nama_departemen')->get(['id_departemen', 'nama_departemen']),
            'supervisors' => $supervisors,
            'canChooseRole' => Gate::allows('chooseRole', Employee::class),
        ];
    }

    // Membersihkan input filter: nilai tidak valid diabaikan, bukan menimbulkan error
    private function filters(Request $request): array
    {
        $date = null;
        $raw = (string) $request->query('date', '');
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
        }

        $division = (int) $request->query('division');
        $division = $division > 0 && Department::query()->whereKey($division)->exists() ? $division : null;

        $status = $request->query('status');

        return [
            'date' => $date,
            'division' => $division,
            'status' => is_string($status) && in_array($status, self::STATUS_FILTERS, true) ? $status : null,
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
        ];
    }

    // Query daftar: hanya karyawan yang boleh dilihat pengguna; bawaan hanya yang aktif
    private function query(User $user, array $filters): Builder
    {
        $query = Employee::query()
            ->visibleTo($user)
            ->with(['user:id_pengguna,email,foto_profil', 'department:id_departemen,nama_departemen']);

        if ($filters['status'] === 'nonaktif') {
            $query->where('tbl_karyawan.status', 'nonaktif');
        } else {
            $query->where('tbl_karyawan.status', 'aktif');
            if ($filters['status']) {
                $query->where('tbl_karyawan.status_kepegawaian', $filters['status']);
            }
        }

        if ($filters['division']) {
            $query->where('tbl_karyawan.id_departemen', $filters['division']);
        }

        // Tanggal = "bergabung sampai tanggal ini"
        if ($filters['date']) {
            $query->where('tbl_karyawan.tanggal_masuk', '<=', $filters['date']->toDateString());
        }

        if ($filters['q'] !== '') {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('tbl_karyawan.nama_lengkap', 'like', $like)->orWhere('tbl_karyawan.nik', 'like', $like));
        }

        return $query->orderBy('tbl_karyawan.nama_lengkap')->orderBy('tbl_karyawan.id_karyawan');
    }

    // Ringkasan kartu atas: jumlah per jenis kepegawaian dan per gender (hanya karyawan aktif)
    private function summary(User $user): array
    {
        $active = Employee::query()->visibleTo($user)->where('tbl_karyawan.status', 'aktif');

        $byStatus = (clone $active)->selectRaw('status_kepegawaian, count(*) as total')->groupBy('status_kepegawaian')->pluck('total', 'status_kepegawaian');
        $byGender = (clone $active)->selectRaw('jenis_kelamin, count(*) as total')->groupBy('jenis_kelamin')->pluck('total', 'jenis_kelamin');

        return [
            'total' => (int) $byStatus->sum(),
            'status' => collect(Employee::STATUS_KEPEGAWAIAN)->map(fn ($label, $key) => (int) ($byStatus[$key] ?? 0))->all(),
            'gender' => collect(Employee::GENDER)->map(fn ($label, $key) => (int) ($byGender[$key] ?? 0))->all(),
            'gender_missing' => (int) ($byGender[''] ?? 0),
        ];
    }

    /** @return list<string> */
    private function exportHeaders(): array
    {
        return ['Employee ID', 'Nama', 'Email', 'Divisi', 'Gender', 'Jabatan', 'Jenis kepegawaian', 'Tanggal masuk', 'Telepon', 'Status'];
    }

    // Baris ekspor tidak memuat gaji, rekening, maupun KTP
    private function exportRows(User $user, array $filters)
    {
        return $this->query($user, $filters)->limit(self::EXPORT_LIMIT)->get()->map(fn (Employee $e) => [
            $e->nik,
            $e->nama_lengkap,
            $e->user?->email,
            $e->department?->nama_departemen,
            Employee::GENDER[$e->jenis_kelamin] ?? null,
            $e->jabatan,
            Employee::STATUS_KEPEGAWAIAN[$e->status_kepegawaian] ?? $e->status_kepegawaian,
            $e->tanggal_masuk?->format('d-m-Y'),
            $e->no_telepon,
            $e->status === 'aktif' ? 'Aktif' : 'Nonaktif',
        ]);
    }
}
