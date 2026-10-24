<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Notifikasi;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menjaga agar skema database tetap sesuai SDD/SRS (lihat docs/DATABASE.md).
 * Jika test ini gagal setelah mengubah migrasi, berarti ada tabel/kolom SDD yang hilang.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sdd_tables_and_columns_exist(): void
    {
        $expected = [
            'tbl_pengguna' => ['id_pengguna', 'username', 'email', 'password', 'role', 'status', 'status_persetujuan', 'foto_profil', 'approved_by', 'approved_at', 'created_at', 'updated_at'],
            'tbl_karyawan' => ['id_karyawan', 'id_pengguna', 'id_departemen', 'id_atasan', 'nik', 'nama_lengkap', 'jabatan', 'tanggal_masuk',
                'no_telepon', 'alamat', 'gaji_pokok', 'sisa_cuti', 'status', 'dokumen_ktp', 'created_at'],
            'tbl_departemen' => ['id_departemen', 'nama_departemen', 'deskripsi', 'created_at'],
            'tbl_absensi' => ['id_absensi', 'id_karyawan', 'tanggal', 'jam_masuk', 'jam_keluar', 'foto_masuk', 'foto_keluar',
                'lat_masuk', 'long_masuk', 'lat_keluar', 'long_keluar', 'status', 'status_persetujuan', 'keterangan'],
            'tbl_izin_cuti' => ['id_izin', 'id_karyawan', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'total_hari', 'alasan', 'dokumen',
                'status', 'approved_by', 'catatan_approval', 'created_at', 'updated_at'],
            'tbl_penggajian' => ['id_penggajian', 'id_karyawan', 'bulan', 'tahun', 'gaji_pokok', 'tunjangan', 'potongan_absensi',
                'pph21', 'bpjs_tk', 'bpjs_kes', 'gaji_bersih', 'status', 'tanggal_bayar', 'created_at'],
            'tbl_tracking_lokasi' => ['id_tracking', 'id_karyawan', 'tanggal', 'waktu', 'latitude', 'longitude', 'keterangan', 'created_at'],
            'tbl_notifikasi' => ['id_notifikasi', 'id_pengguna', 'judul', 'pesan', 'tipe', 'is_read', 'created_at'],
            'tbl_dokumen_karyawan' => ['id_dokumen', 'id_karyawan', 'jenis_dokumen', 'path_file'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ada");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Tabel {$table} kehilangan kolom SDD");
        }

        // Tabel nama lama tidak boleh tersisa
        foreach (['users', 'employees', 'attendances', 'leave_requests', 'payrolls', 'departments', 'notifications',
            'tbl_layanan', 'tbl_pekerjaan', 'tbl_pengumuman', 'tbl_jadwal', 'tbl_peserta_jadwal', 'tbl_tugas'] as $old) {
            $this->assertFalse(Schema::hasTable($old), "Tabel {$old} seharusnya tidak ada (bukan bagian SDD)");
        }
    }

    public function test_one_attendance_row_per_employee_per_day(): void
    {
        $employee = $this->makeEmployee('1');

        $row = [
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => '2026-06-21',
            'jam_masuk' => '08:55:00',
            'foto_masuk' => 'a.jpg', 'lat_masuk' => -7.4, 'long_masuk' => 109.2,
        ];

        $first = Attendance::create($row);
        $this->assertSame('2026-06-21', $first->tanggal);
        $this->assertSame('hadir', $first->fresh()->status);
        $this->assertSame('pending', $first->fresh()->status_persetujuan);

        $this->expectException(UniqueConstraintViolationException::class);
        Attendance::create($row);
    }

    public function test_same_employee_can_attend_on_different_days(): void
    {
        $employee = $this->makeEmployee('2');

        foreach (['2026-06-21', '2026-06-22'] as $tanggal) {
            Attendance::create(['id_karyawan' => $employee->id_karyawan, 'tanggal' => $tanggal, 'jam_masuk' => '08:50:00',
                'foto_masuk' => 'a.jpg', 'lat_masuk' => 1, 'long_masuk' => 1]);
        }

        $this->assertSame(2, Attendance::count());
    }

    public function test_user_defaults_follow_sdd_and_registration_flow(): void
    {
        $user = User::factory()->create(['username' => 'budi']);
        $user = $user->fresh();

        $this->assertSame('aktif', $user->status);
        $this->assertSame('pending', $user->status_persetujuan);
        $this->assertSame('karyawan', $user->role);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isApproved());
    }

    public function test_username_must_be_unique_but_may_be_empty(): void
    {
        User::factory()->create(['username' => 'budi']);
        User::factory()->count(2)->create(['username' => null]); // username null boleh lebih dari satu

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['username' => 'budi']);
    }

    public function test_display_name_comes_from_employee_or_falls_back_to_username(): void
    {
        $employee = $this->makeEmployee('3', 'Adhi Puspito');
        $this->assertSame('Adhi Puspito', $employee->user->fresh()->nama_tampil);

        $admin = User::factory()->create(['username' => 'superadmin', 'role' => 'super_admin']);
        $this->assertSame('superadmin', $admin->nama_tampil);
    }

    public function test_payroll_is_unique_per_employee_per_month_and_net_is_calculated(): void
    {
        $employee = $this->makeEmployee('4');

        $payroll = new Payroll([
            'id_karyawan' => $employee->id_karyawan, 'bulan' => 6, 'tahun' => 2026,
            'gaji_pokok' => 5000000, 'tunjangan' => 500000, 'potongan_absensi' => 100000,
            'pph21' => 50000, 'bpjs_tk' => 100000, 'bpjs_kes' => 50000,
        ]);
        $payroll->recalculate();
        $payroll->save();

        $this->assertEquals(5200000.00, $payroll->fresh()->gaji_bersih);
        $this->assertSame('draft', $payroll->fresh()->status);

        $this->expectException(UniqueConstraintViolationException::class);
        Payroll::create(['id_karyawan' => $employee->id_karyawan, 'bulan' => 6, 'tahun' => 2026]);
    }

    public function test_notifications_table_matches_sdd(): void
    {
        $user = User::factory()->create();

        Notifikasi::create(['id_pengguna' => $user->id_pengguna, 'judul' => 'Pengajuan izin baru', 'pesan' => 'Ada pengajuan izin.', 'tipe' => 'izin']);

        $this->assertSame(1, $user->notifikasi()->belumDibaca()->count());

        $user->notifikasi()->first()->update(['is_read' => true]);
        $this->assertSame(0, $user->notifikasi()->belumDibaca()->count());
    }

    private function makeEmployee(string $nik, string $nama = 'Karyawan Uji'): Employee
    {
        return Employee::create([
            'id_pengguna' => User::factory()->create()->id_pengguna,
            'nik' => $nik,
            'nama_lengkap' => $nama,
        ]);
    }
}
