<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_management_layout_for_authenticated_users(): void
    {
        $user = User::factory()->create([
            'name' => 'Surya Bintang',
            'role' => 'karyawan',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Attendance');
        $response->assertSee('Announcement');
        $response->assertSee('Schedule');
        $response->assertSee('images/DashboardIcon.png');
        $response->assertSee('images/AbsensiIcon.png');
        $response->assertSee('images/IzinCutiIcon.png');
        $response->assertSee('images/Notifications.png');
        $response->assertSee('images/Calendar.png');
    }

    public function test_attendance_page_renders_dummy_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Surya Bintang',
            'role' => 'hrd',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertOk();
        $response->assertSee('Absensi');
        $response->assertSee('Persetujuan Absensi');
        $response->assertSee('Ageng Saputro');
    }
}
