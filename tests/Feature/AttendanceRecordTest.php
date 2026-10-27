<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

/** Pencatatan absensi dari ponsel: check-in / check-out (SRS use case 006, AC-005). */
class AttendanceRecordTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['attendance.work_start' => '09:00', 'attendance.work_end' => '16:00']);
        Carbon::setTestNow('2026-06-22 08:50:00'); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function checkIn($user, array $data = [])
    {
        return $this->actingAs($user)->post(route('attendance.check-in'), $data + [
            'foto' => $this->photo(), 'latitude' => '-7.4243', 'longitude' => '109.2302',
        ]);
    }

    public function test_check_in_on_time_stores_time_photo_and_coordinates(): void
    {
        $employee = $this->makeEmployee();

        $this->checkIn($employee->user)->assertRedirect(route('attendance.record'));

        $row = Attendance::firstOrFail();
        $this->assertSame($employee->id_karyawan, $row->id_karyawan);
        $this->assertSame('2026-06-22', $row->tanggal);
        $this->assertSame('08:50:00', $row->jam_masuk);
        $this->assertSame('hadir', $row->status);
        $this->assertSame('pending', $row->status_persetujuan);
        $this->assertEquals(-7.4243, (float) $row->lat_masuk);
        $this->assertEquals(109.2302, (float) $row->long_masuk);
        Storage::disk('local')->assertExists($row->foto_masuk);
    }

    public function test_check_in_exactly_at_start_is_on_time_and_one_second_later_is_late(): void
    {
        Carbon::setTestNow('2026-06-22 09:00:00');
        $a = $this->makeEmployee();
        $this->checkIn($a->user);
        $this->assertSame('hadir', Attendance::where('id_karyawan', $a->id_karyawan)->value('status'));

        Carbon::setTestNow('2026-06-22 09:00:01');
        $b = $this->makeEmployee();
        $this->checkIn($b->user);
        $this->assertSame('terlambat', Attendance::where('id_karyawan', $b->id_karyawan)->value('status'));
    }

    public function test_time_comes_from_the_server_not_from_the_request(): void
    {
        $employee = $this->makeEmployee();

        $this->checkIn($employee->user, ['jam_masuk' => '06:00:00', 'status' => 'hadir', 'tanggal' => '2020-01-01', 'id_karyawan' => 999]);

        $row = Attendance::firstOrFail();
        $this->assertSame('08:50:00', $row->jam_masuk);
        $this->assertSame('2026-06-22', $row->tanggal);
        $this->assertSame($employee->id_karyawan, $row->id_karyawan);
    }

    public function test_cannot_check_in_twice_on_the_same_day(): void
    {
        $employee = $this->makeEmployee();

        $this->checkIn($employee->user)->assertSessionHasNoErrors();
        $this->checkIn($employee->user)->assertSessionHasErrors('absensi');

        $this->assertSame(1, Attendance::count());
        $this->assertCount(1, Storage::disk('local')->allFiles(), 'foto kedua tidak boleh tersimpan');
    }

    public function test_check_out_updates_the_same_row(): void
    {
        $employee = $this->makeEmployee();
        $this->checkIn($employee->user);

        Carbon::setTestNow('2026-06-22 16:10:00');
        $this->actingAs($employee->user)->post(route('attendance.check-out'), [
            'foto' => $this->photo('keluar.jpg'), 'latitude' => '-7.5', 'longitude' => '109.3',
        ])->assertRedirect(route('attendance.record'))->assertSessionHasNoErrors();

        $row = Attendance::firstOrFail();
        $this->assertSame(1, Attendance::count());
        $this->assertSame('08:50:00', $row->jam_masuk);
        $this->assertSame('16:10:00', $row->jam_keluar);
        $this->assertEquals(-7.5, (float) $row->lat_keluar);
        Storage::disk('local')->assertExists($row->foto_keluar);
        $this->assertNotSame($row->foto_masuk, $row->foto_keluar);
    }

    public function test_check_out_without_check_in_or_twice_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $payload = ['foto' => $this->photo(), 'latitude' => '-7.4', 'longitude' => '109.2'];

        $this->actingAs($employee->user)->post(route('attendance.check-out'), $payload)->assertSessionHasErrors('absensi');
        $this->assertSame(0, Attendance::count());

        $this->checkIn($employee->user);
        Carbon::setTestNow('2026-06-22 16:00:00');
        $this->actingAs($employee->user)->post(route('attendance.check-out'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($employee->user)->post(route('attendance.check-out'), ['foto' => $this->photo()] + $payload)->assertSessionHasErrors('absensi');
    }

    public function test_photo_and_gps_are_required_and_validated(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->post(route('attendance.check-in'), ['latitude' => '-7.4', 'longitude' => '109.2'])
            ->assertSessionHasErrors('foto');
        $this->actingAs($employee->user)->post(route('attendance.check-in'), ['foto' => $this->photo()])
            ->assertSessionHasErrors(['latitude', 'longitude']);
        $this->actingAs($employee->user)->post(route('attendance.check-in'), ['foto' => $this->photo(), 'latitude' => '0', 'longitude' => '0'])
            ->assertSessionHasErrors('latitude');
        $this->actingAs($employee->user)->post(route('attendance.check-in'), ['foto' => $this->photo(), 'latitude' => '123', 'longitude' => '109'])
            ->assertSessionHasErrors('latitude');
        $this->actingAs($employee->user)->post(route('attendance.check-in'), ['foto' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'latitude' => '-7.4', 'longitude' => '109.2'])
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Attendance::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_approvers_are_notified_boss_first_then_hrd_then_super_admin(): void
    {
        // 1) punya atasan -> hanya atasan
        $boss = $this->makeEmployee('supervisor');
        $hrd = $this->makeEmployee('hrd');
        $staff = $this->makeEmployee('karyawan', ['id_atasan' => $boss->id_karyawan, 'nama_lengkap' => 'Budi Santoso']);
        $this->checkIn($staff->user);

        $this->assertSame(1, Notifikasi::where('id_pengguna', $boss->id_pengguna)->where('tipe', 'absensi')->count());
        $this->assertSame(0, Notifikasi::where('id_pengguna', $hrd->id_pengguna)->count());
        $this->assertStringContainsString('Budi Santoso', Notifikasi::where('id_pengguna', $boss->id_pengguna)->value('pesan'));

        // 2) tanpa atasan -> HRD
        $loner = $this->makeEmployee();
        $this->checkIn($loner->user);
        $this->assertSame(1, Notifikasi::where('id_pengguna', $hrd->id_pengguna)->count());

        // 3) tanpa atasan dan tanpa HRD -> Super Admin
        $hrd->user->update(['status' => 'nonaktif']);
        $admin = User::factory()->approved()->create(['role' => 'super_admin']);
        $another = $this->makeEmployee();
        $this->checkIn($another->user);
        $this->assertSame(1, Notifikasi::where('id_pengguna', $admin->id_pengguna)->count());
    }

    public function test_account_without_employee_record_cannot_record(): void
    {
        $admin = User::factory()->approved()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('attendance.record'))->assertOk()->assertSee('belum terhubung dengan data karyawan');
        $this->checkIn($admin)->assertSessionHasErrors('absensi');
        $this->assertSame(0, Attendance::count());
    }

    public function test_inactive_employee_cannot_record(): void
    {
        $employee = $this->makeEmployee('karyawan', ['status' => 'nonaktif']);

        $this->checkIn($employee->user)->assertSessionHasErrors('absensi');
        $this->assertSame(0, Attendance::count());
    }

    public function test_rejected_attendance_blocks_recording_the_same_day(): void
    {
        $employee = $this->makeEmployee();
        $this->makeAttendance($employee, '2026-06-22', '08:40:00', ['status_persetujuan' => 'rejected']);

        $this->checkIn($employee->user)->assertSessionHasErrors('absensi');
        $this->assertSame(1, Attendance::count());
    }

    public function test_record_page_shows_the_right_step_and_guests_are_redirected(): void
    {
        $employee = $this->makeEmployee();

        $this->actingAs($employee->user)->get(route('attendance.record'))->assertOk()->assertSee('Check-in sekarang');

        $this->checkIn($employee->user);
        $this->actingAs($employee->user)->get(route('attendance.record'))->assertOk()->assertSee('Check-out sekarang');

        Carbon::setTestNow('2026-06-22 16:05:00');
        $this->actingAs($employee->user)->post(route('attendance.check-out'), ['foto' => $this->photo(), 'latitude' => '-7.4', 'longitude' => '109.2']);
        $this->actingAs($employee->user)->get(route('attendance.record'))->assertOk()->assertSee('sudah lengkap');

        auth()->logout();
        $this->get(route('attendance.record'))->assertRedirect(route('login'));
        $this->post(route('attendance.check-in'))->assertRedirect(route('login'));
    }
}
