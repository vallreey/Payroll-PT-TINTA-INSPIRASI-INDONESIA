<?php

namespace Tests\Feature;

use App\Exceptions\AccountException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

/** Manajemen Pengguna (khusus Super Admin): akses, daftar, buat/ubah, reset kata sandi, status, persetujuan. */
class UserManagementTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    // Super Admin tanpa data karyawan (akun administrasi)
    private function admin(array $over = []): User
    {
        return User::factory()->approved()->create(['role' => 'super_admin', 'username' => 'admin'.uniqid()] + $over);
    }

    public function test_only_super_admin_can_open_user_management(): void
    {
        $this->actingAs($this->admin())->get(route('users.index'))->assertOk();

        foreach (['hrd', 'direktur', 'supervisor', 'karyawan'] as $role) {
            $user = $this->makeEmployee($role)->user;
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)->get(route('users.create'))->assertForbidden();
            $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();
        }

        auth()->logout();
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_summary_cards_count_all_accounts_by_status(): void
    {
        $admin = $this->admin();
        $this->makeEmployee('karyawan');                                    // aktif
        $this->makeEmployee('karyawan', [], ['status' => 'nonaktif']);       // nonaktif
        User::factory()->create(['status_persetujuan' => 'pending']);       // menunggu
        User::factory()->create(['status_persetujuan' => 'pending']);       // menunggu
        User::factory()->create(['status_persetujuan' => 'rejected']);      // ditolak

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s['total'] === 6 && $s['aktif'] === 2 && $s['nonaktif'] === 1
                && $s['menunggu'] === 2 && $s['ditolak'] === 1 && $s['aktif_pct'] === 33.3);
    }

    public function test_list_filters_by_role_tab_status_search_and_registration_date(): void
    {
        $admin = $this->admin(['email' => 'root@example.com']);
        $this->makeEmployee('hrd', ['nama_lengkap' => 'Hana Hrd']);
        $this->makeEmployee('supervisor', ['nama_lengkap' => 'Sinta Supervisor']);
        $this->makeEmployee('direktur', ['nama_lengkap' => 'Dedi Direktur']);
        $old = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Orang Lama'], ['status' => 'nonaktif']);
        $old->user->forceFill(['created_at' => '2025-01-15 10:00:00'])->save();
        User::factory()->create(['email' => 'pending@example.com', 'status_persetujuan' => 'pending']);

        $get = fn (array $q) => $this->actingAs($admin)->get(route('users.index', $q));

        $get(['role' => 'hrd'])->assertSee('Hana Hrd')->assertDontSee('Sinta Supervisor');
        $get(['role' => 'lainnya'])->assertSee('Sinta Supervisor')->assertSee('Dedi Direktur')->assertDontSee('Hana Hrd');
        $get(['status' => 'nonaktif'])->assertSee('Orang Lama')->assertDontSee('Hana Hrd');
        $get(['status' => 'menunggu'])->assertSee('pending@example.com')->assertDontSee('Hana Hrd');
        $get(['q' => 'sinta'])->assertSee('Sinta Supervisor')->assertDontSee('Hana Hrd');
        $get(['q' => 'root@example'])->assertSee('root@example.com')->assertDontSee('Hana Hrd');
        $get(['from' => '2025-01-01', 'to' => '2025-01-31'])->assertSee('Orang Lama')->assertDontSee('Hana Hrd');
        $get(['from' => '2025-01-31', 'to' => '2025-01-01'])->assertSee('Orang Lama');          // tertukar dibetulkan otomatis
        $get(['q' => '%'])->assertOk()->assertDontSee('Hana Hrd');                              // wildcard dianggap teks biasa
        $get(['role' => 'hack', 'status' => 'x', 'from' => 'bukan-tanggal'])->assertOk()->assertSee('Hana Hrd'); // nilai aneh diabaikan
    }

    public function test_pending_accounts_come_first_and_pagination_works(): void
    {
        $admin = $this->admin();
        for ($i = 1; $i <= 12; $i++) {
            User::factory()->approved()->create(['email' => sprintf('user%02d@example.com', $i)]);
        }
        User::factory()->create(['email' => 'menunggu@example.com', 'status_persetujuan' => 'pending']);

        $this->actingAs($admin)->get(route('users.index'))->assertSeeInOrder(['menunggu@example.com', 'user12@example.com'])->assertDontSee('user01@example.com');
        $this->actingAs($admin)->get(route('users.index', ['page' => 2]))->assertOk()->assertSee('user01@example.com');
    }

    public function test_create_administrator_account_without_employee_data(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), [
            'role' => 'super_admin', 'email' => 'root2@example.com', 'username' => 'root2', 'password' => 'rahasia123',
        ])->assertRedirect(route('users.index'))->assertSessionHas('status');

        $user = User::where('email', 'root2@example.com')->firstOrFail();
        $this->assertSame('super_admin', $user->role);
        $this->assertSame('approved', $user->status_persetujuan);
        $this->assertSame($admin->id_pengguna, $user->approved_by);
        $this->assertNull($user->employee);
        $this->assertSame('root2', $user->nama_tampil);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_create_account_for_other_roles_also_creates_the_employee(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), [
            'role' => 'hrd', 'email' => 'hana@example.com', 'password' => 'rahasia123', 'nama_lengkap' => 'Hana Baru', 'nik' => 'HR-001',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'hana@example.com')->firstOrFail();
        $this->assertSame('hrd', $user->role);
        $this->assertSame('Hana Baru', $user->employee->nama_lengkap);
        $this->assertSame('HR-001', $user->employee->nik);
        $this->assertSame('Hana Baru', $user->nama_tampil);

        // langsung bisa login
        auth()->logout();
        $this->post('/login', ['email' => 'hana@example.com', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    }

    public function test_create_validation_rejects_bad_input_and_stores_nothing(): void
    {
        $admin = $this->admin();
        $existing = $this->makeEmployee('karyawan', ['nik' => 'DUP-1']);
        $before = User::count();
        $ok = ['role' => 'karyawan', 'email' => 'baru@example.com', 'password' => 'rahasia123', 'nama_lengkap' => 'Baru', 'nik' => 'N-1'];

        $bad = [
            ['role' => 'dewa'],
            ['email' => $existing->user->email],
            ['email' => 'bukan-email'],
            ['password' => 'pendek1'],
            ['password' => 'hurufsajaaa'],
            ['username' => 'ada spasi!'],
            ['username' => '', 'role' => 'super_admin'],     // username wajib untuk Super Admin (kunci pertama = bidang yang error)
            ['nik' => 'DUP-1'],
            ['nama_lengkap' => ''],
        ];

        foreach ($bad as $override) {
            $this->actingAs($admin)->post(route('users.store'), $override + $ok)->assertSessionHasErrors(array_key_first($override));
        }

        $this->assertSame($before, User::count());
    }

    public function test_update_changes_role_email_and_username_with_uniqueness_checks(): void
    {
        $admin = $this->admin();
        $target = $this->makeEmployee('karyawan')->user;
        $other = $this->makeEmployee('karyawan')->user;

        $this->actingAs($admin)->put(route('users.update', $target), ['role' => 'supervisor', 'email' => 'baru@example.com', 'username' => 'baru'])
            ->assertRedirect(route('users.index'))->assertSessionHas('status');

        $target->refresh();
        $this->assertSame('supervisor', $target->role);
        $this->assertSame('baru@example.com', $target->email);
        $this->assertSame('baru', $target->username);

        $this->actingAs($admin)->put(route('users.update', $target), ['role' => 'supervisor', 'email' => $other->email])->assertSessionHasErrors('email');
        // email sendiri boleh dipakai ulang
        $this->actingAs($admin)->put(route('users.update', $target), ['role' => 'supervisor', 'email' => 'baru@example.com'])->assertSessionHasNoErrors();
    }

    public function test_own_role_cannot_be_changed_but_another_admin_can_be_demoted(): void
    {
        $admin = $this->admin();
        $second = $this->admin();

        $this->actingAs($admin)->put(route('users.update', $admin), ['role' => 'hrd', 'email' => $admin->email, 'username' => $admin->username])
            ->assertSessionHas('error');
        $this->assertSame('super_admin', $admin->fresh()->role);

        $this->actingAs($admin)->put(route('users.update', $second), ['role' => 'hrd', 'email' => $second->email, 'username' => $second->username])
            ->assertSessionHasNoErrors();
        $this->assertSame('hrd', $second->fresh()->role);
    }

    // Penjaga "Super Admin terakhir" diuji langsung pada layanan (lewat web pelaku selalu Super Admin aktif lain)
    public function test_the_last_active_super_admin_can_never_be_deactivated_or_demoted(): void
    {
        $only = $this->admin();
        $service = app(UserService::class);

        try {
            $service->setStatus($only, 'nonaktif');
            $this->fail('seharusnya ditolak');
        } catch (AccountException $e) {
            $this->assertStringContainsString('satu-satunya Super Admin', $e->getMessage());
        }

        try {
            $service->update($this->admin(['status' => 'nonaktif']), $only, ['role' => 'hrd', 'email' => $only->email, 'username' => $only->username]);
            $this->fail('seharusnya ditolak');
        } catch (AccountException $e) {
            $this->assertStringContainsString('satu-satunya Super Admin', $e->getMessage());
        }

        $this->assertSame('aktif', $only->fresh()->status);
        $this->assertSame('super_admin', $only->fresh()->role);

        // dengan Super Admin aktif lain, boleh
        $this->admin();
        $service->setStatus($only, 'nonaktif');
        $this->assertSame('nonaktif', $only->fresh()->status);
    }

    public function test_reset_password_changes_credentials_and_revokes_remember_token(): void
    {
        $admin = $this->admin();
        $target = $this->makeEmployee('karyawan', [], ['password' => 'lamaaja123'])->user;
        $token = $target->remember_token;

        $this->actingAs($admin)->put(route('users.password', $target), ['password' => 'baruaja456', 'password_confirmation' => 'baruaja456'])
            ->assertSessionHas('status');

        $target->refresh();
        $this->assertTrue(Hash::check('baruaja456', $target->password));
        $this->assertFalse(Hash::check('lamaaja123', $target->password));
        $this->assertNotSame($token, $target->remember_token);

        $bad = [['password' => 'pendek1', 'password_confirmation' => 'pendek1'], ['password' => 'hurufsajaaa', 'password_confirmation' => 'hurufsajaaa'], ['password' => 'baruaja456', 'password_confirmation' => 'beda12345']];
        foreach ($bad as $payload) {
            $this->actingAs($admin)->put(route('users.password', $target), $payload)->assertSessionHasErrors('password');
        }

        // hanya Super Admin
        $this->actingAs($this->makeEmployee('hrd')->user)->put(route('users.password', $target), ['password' => 'xxxxxxx123', 'password_confirmation' => 'xxxxxxx123'])->assertForbidden();
    }

    public function test_reset_password_deletes_stored_sessions_of_that_user_only(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $target = $this->makeEmployee('karyawan')->user;
        $other = $this->makeEmployee('karyawan')->user;

        foreach ([[$target->id_pengguna, 's-target-1'], [$target->id_pengguna, 's-target-2'], [$other->id_pengguna, 's-other']] as [$uid, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '', 'last_activity' => time()]);
        }

        $this->actingAs($admin)->put(route('users.password', $target), ['password' => 'baruaja456', 'password_confirmation' => 'baruaja456']);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id_pengguna)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id_pengguna)->count(), 'sesi orang lain tidak boleh ikut terhapus');
    }

    public function test_deactivating_blocks_login_and_cuts_the_active_session(): void
    {
        $admin = $this->admin();
        $target = $this->makeEmployee('karyawan', [], ['password' => 'rahasia123'])->user;

        // Pengguna sedang login lalu dinonaktifkan: permintaan berikutnya diputus
        $this->actingAs($target)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->patch(route('users.status', $target), ['status' => 'nonaktif'])->assertSessionHas('status');
        $this->assertSame('nonaktif', $target->fresh()->status);

        $this->actingAs($target->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => $target->email, 'password' => 'rahasia123'])->assertSessionHasErrors('auth');

        // diaktifkan kembali -> bisa login lagi
        $this->actingAs($admin)->patch(route('users.status', $target), ['status' => 'aktif']);
        auth()->logout();
        $this->post('/login', ['email' => $target->email, 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    }

    public function test_status_change_rules(): void
    {
        $admin = $this->admin();
        $target = $this->makeEmployee('karyawan')->user;

        $this->actingAs($admin)->patch(route('users.status', $admin), ['status' => 'nonaktif'])->assertForbidden();          // akun sendiri
        $this->actingAs($admin)->patch(route('users.status', $target), ['status' => 'diblokir'])->assertSessionHasErrors('status');
        $this->actingAs($this->makeEmployee('hrd')->user)->patch(route('users.status', $target), ['status' => 'nonaktif'])->assertForbidden();

        $this->assertSame('aktif', $admin->fresh()->status);
        $this->assertSame('aktif', $target->fresh()->status);
    }

    public function test_registration_approval_flow(): void
    {
        $admin = $this->admin();
        $this->post('/register', ['name' => 'Calon Baru', 'nik' => 'REG-001', 'email' => 'calon@example.com', 'password' => 'rahasia123']);
        $pending = User::where('email', 'calon@example.com')->firstOrFail();
        $this->assertSame('pending', $pending->status_persetujuan);

        // belum disetujui: tidak bisa login
        $this->post('/login', ['email' => 'calon@example.com', 'password' => 'rahasia123'])->assertSessionHasErrors('auth');
        auth()->logout();

        $this->actingAs($admin)->patch(route('users.approve', $pending))->assertSessionHas('status');
        $pending->refresh();
        $this->assertSame('approved', $pending->status_persetujuan);
        $this->assertSame($admin->id_pengguna, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);

        auth()->logout();
        $this->post('/login', ['email' => 'calon@example.com', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));

        // keputusan hanya sekali
        $this->actingAs($admin)->patch(route('users.reject', $pending))->assertForbidden();
        $this->assertSame('approved', $pending->fresh()->status_persetujuan);
    }

    public function test_rejected_registration_cannot_log_in(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['email' => 'tolak@example.com', 'password' => 'rahasia123', 'status_persetujuan' => 'pending']);

        $this->actingAs($admin)->patch(route('users.reject', $user))->assertSessionHas('status');
        $this->assertSame('rejected', $user->fresh()->status_persetujuan);

        auth()->logout();
        $this->post('/login', ['email' => 'tolak@example.com', 'password' => 'rahasia123'])->assertSessionHasErrors('auth');
        $this->assertGuest();
    }

    public function test_last_active_is_tracked_at_most_every_five_minutes_without_touching_updated_at(): void
    {
        Carbon::setTestNow('2026-06-22 09:15:00');
        $user = $this->makeEmployee('karyawan')->user;
        $updatedAt = $user->fresh()->updated_at;

        $this->actingAs($user)->get(route('dashboard'));
        $this->assertSame('2026-06-22 09:15:00', $user->fresh()->terakhir_aktif->format('Y-m-d H:i:s'));
        $this->assertEquals($updatedAt, $user->fresh()->updated_at, 'updated_at tidak boleh berubah');

        Carbon::setTestNow('2026-06-22 09:17:00');                       // < 5 menit: tidak dicatat ulang
        $this->actingAs($user->fresh())->get(route('dashboard'));
        $this->assertSame('09:15:00', $user->fresh()->terakhir_aktif->format('H:i:s'));

        Carbon::setTestNow('2026-06-22 09:21:00');                       // > 5 menit: dicatat
        $this->actingAs($user->fresh())->get(route('dashboard'));
        $this->assertSame('09:21:00', $user->fresh()->terakhir_aktif->format('H:i:s'));

        // Label tampilan
        $this->assertSame('Hari ini, 09.21', $user->fresh()->terakhir_aktif_label);
        Carbon::setTestNow('2026-06-23 08:00:00');
        $this->assertSame('Kemarin, 09.21', $user->fresh()->terakhir_aktif_label);
        Carbon::setTestNow('2026-07-30 08:00:00');
        $this->assertSame('22 Jun 2026, 09.21', $user->fresh()->terakhir_aktif_label);
        $this->assertSame('Belum pernah', $this->makeEmployee('karyawan')->user->fresh()->terakhir_aktif_label);

        Carbon::setTestNow();
    }

    public function test_edit_page_shows_info_and_employee_link(): void
    {
        $admin = $this->admin();
        $employee = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Santoso']);

        $this->actingAs($admin)->get(route('users.edit', $employee->user))->assertOk()
            ->assertSee('Budi Santoso')->assertSee(route('employees.show', $employee), false)->assertSee('Reset kata sandi')
            ->assertSee('value="'.$employee->user->email.'"', false);

        // akun administrasi tanpa karyawan
        $this->actingAs($admin)->get(route('users.edit', $this->admin()))->assertOk()->assertSee('akun administrasi');
    }
}
