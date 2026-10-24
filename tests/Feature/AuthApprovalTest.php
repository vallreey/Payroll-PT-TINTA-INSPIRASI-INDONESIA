<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Database\Seeders\PayrollSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alur registrasi -> persetujuan Super Admin -> login (SRS use case 001/002/061, AC-001/002/003). */
class AuthApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_pending_account_and_employee_with_name(): void
    {
        $this->post('/register', [
            'name' => 'Adhi Puspito', 'nik' => '3302000000000001',
            'email' => 'adhi@example.com', 'password' => 'rahasia123',
        ])->assertRedirect(route('login'));

        $user = User::where('email', 'adhi@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status_persetujuan);
        $this->assertSame('aktif', $user->status);
        $this->assertSame('karyawan', $user->role);

        $employee = Employee::where('id_pengguna', $user->id_pengguna)->firstOrFail();
        $this->assertSame('Adhi Puspito', $employee->nama_lengkap);
        $this->assertSame('3302000000000001', $employee->nik);
    }

    public function test_pending_account_cannot_login(): void
    {
        User::factory()->create(['email' => 'p@example.com', 'password' => 'rahasia123']);

        $this->post('/login', ['email' => 'p@example.com', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('auth');
        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_login_even_if_approved(): void
    {
        User::factory()->approved()->create(['email' => 'n@example.com', 'password' => 'rahasia123', 'status' => 'nonaktif']);

        $this->post('/login', ['email' => 'n@example.com', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('auth');
        $this->assertGuest();
    }

    public function test_approved_active_account_can_login(): void
    {
        User::factory()->approved()->create(['email' => 'ok@example.com', 'password' => 'rahasia123']);

        $this->post('/login', ['email' => 'ok@example.com', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_super_admin_seeder_account_logs_in_and_dashboard_shows_demo_data(): void
    {
        $this->seed(PayrollSystemSeeder::class);
        $this->seed(DashboardDemoSeeder::class);

        $this->post('/login', ['email' => 'superadmin@tintainspirasi.co.id', 'password' => 'ChangeMe123!'])
            ->assertRedirect(route('dashboard'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Selamat')
            ->assertSee('superadmin')          // Admin murni: nama tampil = username
            ->assertSee('Rizky Pratama')       // nama dari tbl_karyawan.nama_lengkap (check-in terbaru 09.30)
            ->assertSee('Pengajuan cuti baru')   // tbl_notifikasi milik Super Admin
            ->assertSee('Leave Schedule');
    }

    public function test_demo_seeders_are_idempotent(): void
    {
        $this->seed(PayrollSystemSeeder::class);
        $this->seed(DashboardDemoSeeder::class);
        $counts = [Employee::count(), \App\Models\Attendance::count(), \App\Models\LeaveRequest::count()];

        $this->seed(PayrollSystemSeeder::class);
        $this->seed(DashboardDemoSeeder::class);

        $this->assertSame($counts, [Employee::count(), \App\Models\Attendance::count(), \App\Models\LeaveRequest::count()]);
    }
}
