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
        $user = User::factory()->approved()->create(['role' => 'karyawan']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Attendance');
        $response->assertSee('Announcement');
        $response->assertSee('Schedule');
    }

    public function test_attendance_page_renders_dummy_data(): void
    {
        $user = User::factory()->approved()->create(['role' => 'hrd']);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertOk();
        $response->assertSee('Absensi');
        $response->assertSee('Persetujuan Absensi');
        $response->assertSee('Ageng Saputro');
    }
}
