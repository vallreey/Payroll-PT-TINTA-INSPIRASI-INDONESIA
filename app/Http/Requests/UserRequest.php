<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Validasi form Buat Akun (tanpa {user}) dan Ubah Akun (dengan {user}). */
class UserRequest extends FormRequest
{
    // Akun yang sedang diubah (null saat membuat)
    private function target(): ?User
    {
        return $this->route('user');
    }

    public function authorize(): bool
    {
        $target = $this->target();

        return $target ? Gate::allows('update', $target) : Gate::allows('create', User::class);
    }

    public function rules(): array
    {
        $target = $this->target();
        $isAdminRole = $this->input('role') === 'super_admin';

        $rules = [
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('tbl_pengguna', 'email')->ignore($target?->id_pengguna, 'id_pengguna')],
            // Super Admin tidak punya data karyawan, jadi username dipakai sebagai nama tampil
            'username' => [$isAdminRole ? 'required' : 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('tbl_pengguna', 'username')->ignore($target?->id_pengguna, 'id_pengguna')],
        ];

        if (! $target) {
            $rules['password'] = ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()];

            // Peran selain Super Admin dibuatkan data karyawan dasar
            if (! $isAdminRole) {
                $rules['nama_lengkap'] = ['required', 'string', 'max:150'];
                $rules['nik'] = ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-\/]+$/', Rule::unique('tbl_karyawan', 'nik')];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'username.required' => 'Username wajib diisi untuk Super Admin.',
            'username.unique' => 'Username sudah dipakai.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, strip, dan garis bawah.',
            'nik.unique' => 'Employee ID (NIK) sudah dipakai karyawan lain.',
            'nik.regex' => 'Employee ID hanya boleh berisi huruf, angka, titik, strip, dan garis miring.',
        ];
    }
}
