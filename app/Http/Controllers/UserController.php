<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountException;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Manajemen Pengguna (khusus Super Admin): daftar akun, buat, ubah, reset kata sandi, aktif/nonaktif, persetujuan. */
class UserController extends Controller
{
    private const PER_PAGE = 10;

    // Tab/filter peran; "lainnya" = Supervisor + CEO/Direktur
    private const ROLE_FILTERS = ['super_admin', 'hrd', 'supervisor', 'direktur', 'karyawan', 'lainnya'];

    private const STATUS_FILTERS = ['aktif', 'nonaktif', 'menunggu', 'ditolak'];

    public function __construct(private UserService $users)
    {
    }

    // Daftar akun + kartu ringkasan
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $this->filters($request);

        return view('users.index', [
            'rows' => $this->query($filters)->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'activeFilters' => collect([$filters['role'], $filters['status'], $filters['from'] || $filters['to']])->filter()->count(),
            'stats' => $this->stats(),
            'rangeLabel' => $this->rangeLabel($filters),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', ['account' => null]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->create($request->user(), $request->validated());

        return redirect()->route('users.index')->with('status', 'Akun berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', ['account' => $user->load('employee:id_karyawan,id_pengguna,nama_lengkap')]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->users->update($request->user(), $user, $request->validated());
        } catch (AccountException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')->with('status', 'Akun diperbarui.');
    }

    // Reset kata sandi oleh Super Admin (FR-002); sesi lama akun itu diputus
    public function password(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $isSelf = $user->is($request->user());
        $this->users->resetPassword($user, $data['password'], $isSelf ? $request->session()->getId() : null);

        return back()->with('status', 'Kata sandi diganti.');
    }

    // Aktif/nonaktifkan akun
    public function status(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $status = $request->validate(['status' => ['required', 'in:aktif,nonaktif']])['status'];

        try {
            $this->users->setStatus($user, $status);
        } catch (AccountException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $status === 'aktif' ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, 'approved', 'Registrasi disetujui.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, 'rejected', 'Registrasi ditolak.');
    }

    // ---------------------------------------------------------------------

    private function decide(Request $request, User $user, string $decision, string $message): RedirectResponse
    {
        Gate::authorize('decide', $user);

        try {
            $this->users->decide($request->user(), $user, $decision);
        } catch (AccountException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $message);
    }

    // Membersihkan input filter: nilai tidak valid diabaikan, bukan menimbulkan error
    private function filters(Request $request): array
    {
        $date = function (string $key) use ($request): ?CarbonImmutable {
            $raw = (string) $request->query($key, '');

            return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay()
                : null;
        };

        $from = $date('from');
        $to = $date('to');
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from]; // tertukar: perbaiki urutan
        }

        $role = $request->query('role');
        $status = $request->query('status');

        return [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'role' => is_string($role) && in_array($role, self::ROLE_FILTERS, true) ? $role : null,
            'status' => is_string($status) && in_array($status, self::STATUS_FILTERS, true) ? $status : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function query(array $f): Builder
    {
        $query = User::query()->with(['employee:id_karyawan,id_pengguna,id_departemen,nama_lengkap', 'employee.department:id_departemen,nama_departemen']);

        if ($f['role'] === 'lainnya') {
            $query->whereIn('role', ['supervisor', 'direktur']);
        } elseif ($f['role']) {
            $query->where('role', $f['role']);
        }

        // Status mengikuti label tampilan (User::display_status)
        match ($f['status']) {
            'menunggu' => $query->where('status_persetujuan', 'pending'),
            'ditolak' => $query->where('status_persetujuan', 'rejected'),
            'aktif' => $query->where('status_persetujuan', 'approved')->where('status', 'aktif'),
            'nonaktif' => $query->where('status_persetujuan', 'approved')->where('status', 'nonaktif'),
            default => null,
        };

        // Rentang tanggal = tanggal akun terdaftar
        if ($f['from']) {
            $query->where('tbl_pengguna.created_at', '>=', $f['from']);
        }
        if ($f['to']) {
            $query->where('tbl_pengguna.created_at', '<=', $f['to']->endOfDay());
        }

        if ($f['q'] !== '') {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('email', 'like', $like)
                ->orWhere('username', 'like', $like)
                ->orWhereHas('employee', fn (Builder $e) => $e->where('nama_lengkap', 'like', $like)));
        }

        // Yang menunggu persetujuan tampil lebih dulu, lalu akun terbaru
        return $query->orderByRaw("CASE WHEN status_persetujuan = 'pending' THEN 0 ELSE 1 END")->orderByDesc('id_pengguna');
    }

    // Angka kartu ringkasan: seluruh akun, tidak terpengaruh filter
    private function stats(): array
    {
        $row = User::query()->selectRaw(
            "count(*) as total,
             sum(case when status_persetujuan = 'approved' and status = 'aktif' then 1 else 0 end) as aktif,
             sum(case when status_persetujuan = 'approved' and status = 'nonaktif' then 1 else 0 end) as nonaktif,
             sum(case when status_persetujuan = 'pending' then 1 else 0 end) as menunggu,
             sum(case when status_persetujuan = 'rejected' then 1 else 0 end) as ditolak"
        )->first();

        $total = (int) $row->total;
        $percent = fn ($n) => $total > 0 ? round($n / $total * 100, 1) : 0.0;

        return [
            'total' => $total,
            'aktif' => (int) $row->aktif, 'aktif_pct' => $percent((int) $row->aktif),
            'nonaktif' => (int) $row->nonaktif, 'nonaktif_pct' => $percent((int) $row->nonaktif),
            'menunggu' => (int) $row->menunggu, 'menunggu_pct' => $percent((int) $row->menunggu),
            'ditolak' => (int) $row->ditolak,
        ];
    }

    // Teks tombol rentang tanggal di pojok kanan atas
    private function rangeLabel(array $f): string
    {
        if (! $f['from'] && ! $f['to']) {
            return 'Semua tanggal';
        }

        $fmt = fn (?CarbonImmutable $d) => $d?->locale('id')->translatedFormat('j M Y');

        return ($fmt($f['from']) ?? '...').' - '.($fmt($f['to']) ?? '...');
    }
}
