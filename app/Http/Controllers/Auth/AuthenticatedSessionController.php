<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['auth' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // Akun dinonaktifkan Super Admin (AC-003)
        if (! $user->isActive()) {
            Auth::logout();

            return back()
                ->withErrors(['auth' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.'])
                ->onlyInput('email');
        }

        // Akun belum di-approve Super Admin (use case 001 & FR-001 di SRS)
        if (! $user->isApproved()) {
            Auth::logout();

            return back()->withErrors([
                'auth' => match ($user->status_persetujuan) {
                    'pending' => 'Akun Anda masih menunggu persetujuan Super Admin.',
                    'rejected' => 'Registrasi akun Anda ditolak. Hubungi HRD.',
                    default => 'Akun tidak dapat digunakan.',
                },
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Arahkan ke dashboard sesuai role (dashboard-nya menyusul dibuat)
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
