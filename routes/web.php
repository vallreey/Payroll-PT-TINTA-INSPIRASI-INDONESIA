<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredEmployeeController;
use App\Http\Controllers\Admin\UserApprovalController;

Route::get('/', function () {
    return view('welcome');
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

    // Placeholder dashboard — nanti diarahkan sesuai role (Karyawan/HRD/Supervisor/dst)
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // ---------- Admin: persetujuan pendaftaran akun karyawan ----------
    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/user-approvals', [UserApprovalController::class, 'index'])->name('user-approvals.index');
        Route::patch('/user-approvals/{user}/approve', [UserApprovalController::class, 'approve'])->name('user-approvals.approve');
        Route::patch('/user-approvals/{user}/reject', [UserApprovalController::class, 'reject'])->name('user-approvals.reject');
    });
});

