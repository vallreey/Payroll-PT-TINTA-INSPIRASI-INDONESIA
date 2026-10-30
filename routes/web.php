<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredEmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;

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

    // ---------- Absensi ----------
    // Pemantauan & persetujuan (web). URL lama /attendance tetap hidup sebagai alamat yang sama.
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/export/xlsx', [AttendanceController::class, 'exportExcel'])->name('attendance.export.xlsx');
    Route::get('/attendance/export/print', [AttendanceController::class, 'exportPrint'])->name('attendance.export.print');
    Route::get('/attendance/{attendance}/foto/{jenis}', [AttendanceController::class, 'photo'])
        ->whereIn('jenis', ['masuk', 'keluar'])->name('attendance.photo');
    Route::patch('/attendance/{attendance}/setujui', [AttendanceController::class, 'approve'])->name('attendance.approve');
    Route::patch('/attendance/{attendance}/tolak', [AttendanceController::class, 'reject'])->name('attendance.reject');

    // Pencatatan (dipakai dari ponsel): check-in / check-out dengan foto + GPS
    Route::get('/attendance/catat', [AttendanceRecordController::class, 'create'])->name('attendance.record');
    Route::post('/attendance/masuk', [AttendanceRecordController::class, 'checkIn'])->middleware('throttle:10,1')->name('attendance.check-in');
    Route::post('/attendance/keluar', [AttendanceRecordController::class, 'checkOut'])->middleware('throttle:10,1')->name('attendance.check-out');

    // ---------- Karyawan ----------
    // Daftar, ekspor, tambah, detail, ubah, aktif/nonaktif, dan scan KTP (hak akses: EmployeePolicy)
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/export/xlsx', [EmployeeController::class, 'exportExcel'])->name('employees.export.xlsx');
    Route::get('/employees/export/print', [EmployeeController::class, 'exportPrint'])->name('employees.export.print');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/status', [EmployeeController::class, 'updateStatus'])->name('employees.status');
    Route::get('/employees/{employee}/ktp', [EmployeeController::class, 'ktp'])->name('employees.ktp');
});


