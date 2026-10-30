<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/** Validasi form tambah/ubah karyawan. Route tanpa {employee} = tambah; dengan {employee} = ubah. */
class EmployeeRequest extends FormRequest
{
    // Karyawan yang sedang diubah (null saat menambah)
    private function target(): ?Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        $target = $this->target();

        return $target
            ? Gate::allows('update', $target)
            : Gate::allows('create', Employee::class);
    }

    public function rules(): array
    {
        $target = $this->target();

        $rules = [
            'nik' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-\/]+$/',
                Rule::unique('tbl_karyawan', 'nik')->ignore($target?->id_karyawan, 'id_karyawan')],
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['nullable', Rule::in(array_keys(Employee::GENDER))],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'no_telepon' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'id_departemen' => ['nullable', 'integer', 'exists:tbl_departemen,id_departemen'],
            'id_atasan' => ['nullable', 'integer', 'exists:tbl_karyawan,id_karyawan'],
            'tanggal_masuk' => ['nullable', 'date'],
            'status_kepegawaian' => ['required', Rule::in(array_keys(Employee::STATUS_KEPEGAWAIAN))],
            'sisa_cuti' => ['nullable', 'integer', 'min:0', 'max:365'],
            'gaji_pokok' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'no_rekening' => ['nullable', 'string', 'max:50', 'regex:/^[0-9\-\s]+$/'],
            'nama_pemilik_rekening' => ['nullable', 'string', 'max:150'],
            'dokumen_ktp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];

        // Akun login hanya dibuat saat menambah karyawan baru
        if (! $target) {
            $rules['email'] = ['required', 'email:rfc', 'max:150', Rule::unique('tbl_pengguna', 'email')];
            $rules['password'] = ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()];

            // Peran hanya dibaca bila pengguna berhak memilihnya (Super Admin); selain itu diabaikan
            if (Gate::allows('chooseRole', Employee::class)) {
                $rules['role'] = ['nullable', Rule::in(['karyawan', 'supervisor', 'hrd', 'direktur'])];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nik.unique' => 'Employee ID (NIK) sudah dipakai karyawan lain.',
            'nik.regex' => 'Employee ID hanya boleh berisi huruf, angka, titik, strip, dan garis miring.',
            'email.unique' => 'Email sudah terdaftar.',
            'no_telepon.regex' => 'Nomor telepon hanya boleh berisi angka dan tanda + - ( ).',
            'no_rekening.regex' => 'Nomor rekening hanya boleh berisi angka dan strip.',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            'dokumen_ktp.mimes' => 'Scan KTP harus berformat JPG, PNG, atau PDF.',
            'dokumen_ktp.max' => 'Ukuran scan KTP maksimal 4 MB.',
        ];
    }

    // Mencegah atasan melingkar (A atasan B, B atasan A) dan menjadi atasan diri sendiri
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->target();
                $bossId = (int) $this->input('id_atasan');

                if (! $target || ! $bossId) {
                    return;
                }

                if ($bossId === $target->id_karyawan) {
                    $validator->errors()->add('id_atasan', 'Karyawan tidak bisa menjadi atasan dirinya sendiri.');

                    return;
                }

                // Telusuri rantai atasan ke atas; bila bertemu karyawan ini, berarti melingkar
                $cursor = Employee::query()->find($bossId);
                for ($depth = 0; $cursor && $depth < 50; $depth++) {
                    if ((int) $cursor->id_atasan === $target->id_karyawan) {
                        $validator->errors()->add('id_atasan', 'Atasan yang dipilih berada di bawah karyawan ini (rantai atasan melingkar).');

                        return;
                    }
                    $cursor = $cursor->id_atasan ? Employee::query()->find($cursor->id_atasan) : null;
                }
            },
        ];
    }
}
