<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** SDD "Karyawan": membuat, mengubah, dan mengaktifkan/menonaktifkan karyawan beserta akun loginnya. */
class EmployeeService
{
    // Disk privat: scan KTP hanya dibuka lewat route yang memeriksa hak akses
    private const DISK = 'local';

    // Kolom tbl_karyawan yang boleh diisi dari form (kolom lain tidak pernah diambil dari request)
    private const FIELDS = [
        'nik', 'nama_lengkap', 'jabatan', 'id_departemen', 'id_atasan', 'tanggal_masuk', 'no_telepon', 'alamat',
        'jenis_kelamin', 'tanggal_lahir', 'status_kepegawaian', 'gaji_pokok', 'sisa_cuti',
        'nama_bank', 'no_rekening', 'nama_pemilik_rekening',
    ];

    // Kolom NOT NULL yang bila kosong dibiarkan memakai nilai bawaan database
    private const NOT_NULL = ['gaji_pokok', 'sisa_cuti', 'status_kepegawaian'];

    /** Membuat akun login (langsung disetujui) dan data karyawan dalam satu transaksi. */
    public function create(User $actor, array $data, ?UploadedFile $ktp = null): Employee
    {
        $path = $ktp ? $this->storeKtp($ktp) : null;

        try {
            return DB::transaction(function () use ($actor, $data, $path) {
                // Akun dibuat oleh pengelola, jadi tidak perlu antre persetujuan registrasi
                $user = User::create([
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => $data['role'],
                    'status' => 'aktif',
                    'status_persetujuan' => 'approved',
                    'approved_by' => $actor->id_pengguna,
                    'approved_at' => now(),
                ]);

                return Employee::create($this->attributes($data) + [
                    'id_pengguna' => $user->id_pengguna,
                    'dokumen_ktp' => $path,
                ]);
            });
        } catch (Throwable $e) {
            // Gagal menyimpan: jangan tinggalkan file yatim
            $this->deleteFile($path);
            throw $e;
        }
    }

    /** Mengubah data karyawan; scan KTP lama dihapus hanya setelah data baru tersimpan. */
    public function update(Employee $employee, array $data, ?UploadedFile $ktp = null): Employee
    {
        $old = $employee->dokumen_ktp;
        $new = $ktp ? $this->storeKtp($ktp) : null;

        try {
            DB::transaction(fn () => $employee->update($this->attributes($data) + ($new ? ['dokumen_ktp' => $new] : [])));
        } catch (Throwable $e) {
            $this->deleteFile($new);
            throw $e;
        }

        if ($new) {
            $this->deleteFile($old);
        }

        return $employee->refresh();
    }

    /** Karyawan nonaktif juga tidak bisa login: status akun ikut diubah. */
    public function setStatus(Employee $employee, string $status): void
    {
        DB::transaction(function () use ($employee, $status) {
            $employee->update(['status' => $status]);
            $employee->user?->update(['status' => $status]);
        });
    }

    /** Hanya mengambil kolom yang diizinkan; kolom NOT NULL yang kosong tidak ikut diubah. */
    private function attributes(array $data): array
    {
        $attributes = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if ($data[$field] === null && in_array($field, self::NOT_NULL, true)) {
                continue;
            }
            $attributes[$field] = $data[$field];
        }

        return $attributes;
    }

    private function storeKtp(UploadedFile $file): string
    {
        $path = $file->store('karyawan/ktp', self::DISK);

        if (! $path) {
            throw new \RuntimeException('Scan KTP gagal disimpan.');
        }

        return $path;
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
