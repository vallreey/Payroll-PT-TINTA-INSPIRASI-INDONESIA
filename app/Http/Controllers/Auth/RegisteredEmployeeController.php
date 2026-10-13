<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredEmployeeController extends Controller
{
    /**
     * Menampilkan halaman Form Register.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Memproses Registrasi:
     * - Membuat akun User dengan role "karyawan" & status "pending"
     * - Membuat data Employee terkait (menyimpan NIK)
     * - Akun baru menunggu persetujuan Super Admin (sesuai use case 001 SRS)
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'string', 'max:32', 'unique:employees,nik'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'nik.unique' => 'NIK ini sudah terdaftar.',
            'email.unique' => 'Email ini sudah terdaftar.',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'], // otomatis di-hash lewat cast 'hashed' di model
                'role' => 'karyawan',
                'status' => 'pending',
            ]);

            Employee::create([
                'user_id' => $user->id,
                'nik' => $validated['nik'],
            ]);
        });

        return redirect()
            ->route('login')
            ->with('status', 'Registrasi berhasil. Akun Anda menunggu persetujuan Super Admin sebelum bisa login.');
    }
}
