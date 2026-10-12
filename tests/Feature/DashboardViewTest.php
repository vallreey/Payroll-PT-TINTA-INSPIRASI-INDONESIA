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
    }
}
