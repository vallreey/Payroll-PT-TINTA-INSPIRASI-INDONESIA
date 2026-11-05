<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserApprovalController extends Controller
{
    /**
     * Menampilkan daftar akun karyawan yang mendaftar (pending/approved/rejected).
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $users = User::with('employee')
            ->where('status', $status)
            ->where('id', '!=', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $counts = [
            'pending' => User::where('status', 'pending')->count(),
            'approved' => User::where('status', 'approved')->count(),
            'rejected' => User::where('status', 'rejected')->count(),
        ];

        return view('admin.user-approvals.index', [
            'users' => $users,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Menyetujui akun karyawan agar bisa login.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->guardAgainstSelfOrAlreadyDecided($user);

        $user->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', "Akun {$user->name} berhasil disetujui.");
    }

    /**
     * Menolak akun karyawan sehingga tidak bisa login.
     */
    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->guardAgainstSelfOrAlreadyDecided($user);

        $user->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', "Akun {$user->name} ditolak.");
    }

    private function guardAgainstSelfOrAlreadyDecided(User $user): void
    {
        abort_if($user->id === auth()->id(), 403, 'Tidak bisa memproses akun sendiri.');
    }
}
