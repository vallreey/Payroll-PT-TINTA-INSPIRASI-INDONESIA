<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_management_layout_for_authenticated_users(): void
    {
        $user = User::factory()->approved()->create(['role' => 'karyawan']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Attendance');
        $response->assertSee('Notification');
        $response->assertSee('Schedule');
    }

    public function test_attendance_page_renders_real_attendance_data(): void
    {
        $hrd = User::factory()->approved()->create(['role' => 'hrd']);
        $employee = Employee::create([
            'id_pengguna' => User::factory()->approved()->create()->id_pengguna,
            'nik' => '3302000000000099',
            'nama_lengkap' => 'Ageng Saputro',
        ]);
        Attendance::create([
            'id_karyawan' => $employee->id_karyawan,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '08:55:00',
            'foto_masuk' => 'absensi/test/masuk.jpg',
            'lat_masuk' => -7.4243,
            'long_masuk' => 109.2302,
        ]);

        $response = $this->actingAs($hrd)->get('/attendance');

        $response->assertOk();
        $response->assertSee('Absensi');
        $response->assertSee('Persetujuan Absensi');
        $response->assertSee('Ageng Saputro');
    }
}
