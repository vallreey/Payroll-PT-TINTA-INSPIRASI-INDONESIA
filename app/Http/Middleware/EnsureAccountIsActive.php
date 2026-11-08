<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang pada semua route yang butuh login:
 * 1) memutus sesi akun yang dinonaktifkan atau belum/tidak disetujui (sebelumnya hanya dicek saat login);
 * 2) mencatat "terakhir aktif" paling sering sekali per 5 menit.
 */
class EnsureAccountIsActive
{
    // Selang minimum antar pencatatan aktivitas (menit)
    private const TRACK_EVERY_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || ! $user->isApproved())) {
            // Akun tidak lagi boleh dipakai: keluarkan dan kembali ke halaman login
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['auth' => 'Akun Anda tidak aktif. Hubungi Super Admin.']);
        }

        if ($user && (! $user->terakhir_aktif || $user->terakhir_aktif->lt(now()->subMinutes(self::TRACK_EVERY_MINUTES)))) {
            // Query langsung agar updated_at akun tidak ikut berubah
            DB::table($user->getTable())->where($user->getKeyName(), $user->getKey())->update(['terakhir_aktif' => now()]);
        }

        return $next($request);
    }
}
