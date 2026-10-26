<?php

namespace Tests\Concerns;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/** Pembantu test: akun + karyawan + absensi dengan nilai default yang masuk akal. */
trait CreatesAttendanceData
{
    private int $nikCounter = 0;

    protected function makeEmployee(string $role = 'karyawan', array $employee = [], array $user = []): Employee
    {
        $account = User::factory()->approved()->create(['role' => $role] + $user);

        return Employee::create([
            'id_pengguna' => $account->id_pengguna,
            'nik' => 'NIK'.(++$this->nikCounter),
            'nama_lengkap' => $employee['nama_lengkap'] ?? 'Karyawan '.$this->nikCounter,
        ] + $employee);
    }

    protected function makeAttendance(Employee $employee, string $date, string $jamMasuk, array $extra = []): Attendance
    {
        return Attendance::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => $date,
            'jam_masuk' => $jamMasuk,
            'foto_masuk' => 'absensi/test/masuk.jpg',
            'lat_masuk' => -7.4243,
            'long_masuk' => 109.2302,
            'status' => $jamMasuk > '09:00:00' ? 'terlambat' : 'hadir',
        ] + $extra);
    }

    /** Foto JPEG kecil yang sungguhan (terdeteksi dari isi file, tidak butuh ekstensi GD). */
    protected function photo(string $name = 'absensi.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAA0JCgsKCA0LCgsODg0PEyAVExISEyccHhcgLikxMC4pLSwzOko+MzZGNywtQFdBRkxOUlNSMj5aYVpQYEpRUk//2wBDAQ4ODhMREyYVFSZPNS01T09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0//wAARCAAIAAgDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwCWiiitzA//2Q=='));
    }

    protected function departmentNamed(string $name): Department
    {
        return Department::firstOrCreate(['nama_departemen' => $name]);
    }
}
