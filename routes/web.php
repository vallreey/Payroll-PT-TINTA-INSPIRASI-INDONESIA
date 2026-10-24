<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredEmployeeController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect()->route('login');
});
// ---------- Guest routes (belum login) ----------
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredEmployeeController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredEmployeeController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // Placeholder sementara — belum diimplementasikan.
    // Supaya link "Lupa Password?" di halaman login/register tidak error 404.
    Route::get('/forgot-password', function () {
        return redirect()->route('login')->with('status', 'Fitur reset password belum tersedia.');
    })->name('password.request');

    // Placeholder sementara untuk tombol "Register dengan Google".
    // Nanti diganti Laravel Socialite kalau fitur ini mau benar-benar dibangun.
    Route::get('/auth/google', function () {
        return redirect()->route('register')->with('status', 'Login dengan Google belum tersedia.');
    })->name('auth.google');
});

// ---------- Authenticated routes (sudah login & approved) ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Dashboard — tampilan menyesuaikan role (lihat DashboardController)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Absensi — versi dummy untuk UI sesuai mockup
    Route::get('/attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
});


