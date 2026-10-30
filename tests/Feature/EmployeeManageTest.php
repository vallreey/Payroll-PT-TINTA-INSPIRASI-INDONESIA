<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

/** Tambah, ubah, aktif/nonaktif karyawan, data sensitif, dan scan KTP. */
class EmployeeManageTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // Data form tambah yang valid; bisa ditimpa per test
    private function payload(array $over = []): array
    {
        return $over + [
            'nama_lengkap' => 'Dewi Baru', 'nik' => 'E-2026-001', 'email' => 'dewi@example.com', 'password' => 'rahasia123',
            'jenis_kelamin' => 'P', 'status_kepegawaian' => 'percobaan', 'jabatan' => 'Desainer',
            'tanggal_masuk' => '2026-06-01', 'gaji_pokok' => '4500000', 'sisa_cuti' => '12',
        ];
    }

    public function test_hrd_creates_employee_with_a_ready_to_use_account(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $dept = $this->departmentNamed('Creative');

        $this->actingAs($hrd->user)->post(route('employees.store'), $this->payload(['id_departemen' => $dept->id_departemen]))
            ->assertRedirect()->assertSessionHas('status');

        $employee = Employee::where('nik', 'E-2026-001')->firstOrFail();
        $user = $employee->user;

        $this->assertSame('Dewi Baru', $employee->nama_lengkap);
        $this->assertSame('percobaan', $employee->status_kepegawaian);
        $this->assertSame($dept->id_departemen, $employee->id_departemen);
        $this->assertSame('karyawan', $user->role);
        $this->assertSame('aktif', $user->status);
        $this->assertSame('approved', $user->status_persetujuan);
        $this->assertSame($hrd->id_pengguna, $user->approved_by);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotSame('rahasia123', $user->getRawOriginal('password'));

        // Karyawan baru langsung bisa login
        auth()->logout();
        $this->post('/login', ['email' => 'dewi@example.com', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    }

    public function test_role_can_only_be_chosen_by_super_admin(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $this->actingAs($hrd->user)->post(route('employees.store'), $this->payload(['role' => 'super_admin']));
        $this->assertSame('karyawan', User::where('email', 'dewi@example.com')->value('role'), 'HRD tidak boleh menentukan peran');

        $admin = $this->makeEmployee('super_admin');
        $this->actingAs($admin->user)->post(route('employees.store'), $this->payload(['nik' => 'E-2', 'email' => 'sv@example.com', 'role' => 'supervisor']));
        $this->assertSame('supervisor', User::where('email', 'sv@example.com')->value('role'));

        // super_admin baru tidak boleh dibuat dari form ini
        $this->actingAs($admin->user)->post(route('employees.store'), $this->payload(['nik' => 'E-3', 'email' => 'x@example.com', 'role' => 'super_admin']))
            ->assertSessionHasErrors('role');
        $this->assertNull(User::where('email', 'x@example.com')->first());
    }

    public function test_only_hrd_and_super_admin_can_create_or_edit(): void
    {
        $target = $this->makeEmployee('karyawan');

        foreach (['direktur', 'supervisor', 'karyawan'] as $role) {
            $user = $this->makeEmployee($role)->user;
            $this->actingAs($user)->get(route('employees.create'))->assertForbidden();
            $this->actingAs($user)->post(route('employees.store'), $this->payload())->assertForbidden();
            $this->actingAs($user)->get(route('employees.edit', $target))->assertForbidden();
            $this->actingAs($user)->put(route('employees.update', $target), $this->payload())->assertForbidden();
        }

        $this->assertNull(Employee::where('nik', 'E-2026-001')->first());
    }

    public function test_validation_rejects_bad_input_and_stores_nothing(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $existing = $this->makeEmployee('karyawan', ['nik' => 'DUP-1']);
        $before = User::count();

        $bad = [
            ['nik' => 'DUP-1'],                       // NIK ganda
            ['email' => $existing->user->email],      // email ganda
            ['password' => 'pendek1'],                // < 8 karakter
            ['password' => 'hurufsajaaa'],            // tanpa angka
            ['nik' => 'ada spasi & simbol!'],         // format NIK
            ['no_telepon' => 'abc'],                  // telepon
            ['status_kepegawaian' => 'lapangan'],     // bukan jenis yang dikenal
            ['jenis_kelamin' => 'X'],
            ['tanggal_lahir' => '2999-01-01'],        // di masa depan
            ['gaji_pokok' => '-5'],
            ['id_departemen' => '99999'],
            ['nama_lengkap' => ''],
            ['dokumen_ktp' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')],
            ['dokumen_ktp' => UploadedFile::fake()->create('besar.pdf', 5000, 'application/pdf')],
        ];

        // Tiap kasus harus ditolak dengan error pada bidang yang salah
        foreach ($bad as $override) {
            $this->actingAs($hrd->user)->post(route('employees.store'), $this->payload($override))
                ->assertSessionHasErrors(array_key_first($override));
        }

        $this->assertSame($before, User::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_ktp_is_stored_privately_and_only_shown_to_sensitive_roles(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $boss = $this->makeEmployee('supervisor');
        $ceo = $this->makeEmployee('direktur');

        $this->actingAs($hrd->user)->post(route('employees.store'), $this->payload([
            'id_atasan' => $boss->id_karyawan,
            'dokumen_ktp' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $employee = Employee::where('nik', 'E-2026-001')->firstOrFail();
        Storage::disk('local')->assertExists($employee->dokumen_ktp);
        $this->assertStringStartsWith('karyawan/ktp/', $employee->dokumen_ktp);

        $url = route('employees.ktp', $employee);
        $this->actingAs($hrd->user)->get($url)->assertOk();
        $this->actingAs($ceo->user)->get($url)->assertOk();
        $this->actingAs($boss->user)->get($url)->assertForbidden();   // atasan langsung pun tidak boleh melihat KTP
        $this->actingAs($employee->user)->get($url)->assertForbidden();

        // Karyawan tanpa KTP -> 404
        $other = $this->makeEmployee('karyawan');
        $this->actingAs($hrd->user)->get(route('employees.ktp', $other))->assertNotFound();
    }

    public function test_update_changes_data_replaces_ktp_and_never_touches_account_or_role(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan', ['nik' => 'OLD-1', 'nama_lengkap' => 'Nama Lama']);
        $hash = $employee->user->getRawOriginal('password');

        $this->actingAs($hrd->user)->put(route('employees.update', $employee), [
            'nama_lengkap' => 'Nama Baru', 'nik' => 'NEW-1', 'status_kepegawaian' => 'tetap', 'gaji_pokok' => '6000000',
            'jabatan' => 'Manajer', 'dokumen_ktp' => UploadedFile::fake()->create('ktp1.pdf', 50, 'application/pdf'),
            // upaya menyusup: ganti peran/kata sandi/email lewat form ubah harus diabaikan
            'role' => 'super_admin', 'password' => 'dihack12345', 'email' => 'hack@example.com',
        ])->assertRedirect(route('employees.show', $employee))->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame('Nama Baru', $employee->nama_lengkap);
        $this->assertSame('NEW-1', $employee->nik);
        $this->assertEquals(6000000, (float) $employee->gaji_pokok);
        $this->assertSame('karyawan', $employee->user->role);
        $this->assertSame($hash, $employee->user->getRawOriginal('password'));
        $this->assertNotSame('hack@example.com', $employee->user->email);

        // Mengganti KTP: file lama dihapus, file baru tersimpan
        $first = $employee->dokumen_ktp;
        $this->actingAs($hrd->user)->put(route('employees.update', $employee), [
            'nama_lengkap' => 'Nama Baru', 'nik' => 'NEW-1', 'status_kepegawaian' => 'tetap',
            'dokumen_ktp' => UploadedFile::fake()->create('ktp2.pdf', 50, 'application/pdf'),
        ]);
        $employee->refresh();
        $this->assertNotSame($first, $employee->dokumen_ktp);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($employee->dokumen_ktp);
        $this->assertCount(1, Storage::disk('local')->allFiles());

        // Mengubah tanpa mengunggah KTP tidak menghapus KTP yang ada
        $this->actingAs($hrd->user)->put(route('employees.update', $employee), ['nama_lengkap' => 'Nama Akhir', 'nik' => 'NEW-1', 'status_kepegawaian' => 'kontrak']);
        $this->assertNotNull($employee->fresh()->dokumen_ktp);
        $this->assertSame('Nama Akhir', $employee->fresh()->nama_lengkap);

        // NIK sendiri boleh dipakai ulang, NIK orang lain tidak
        $other = $this->makeEmployee('karyawan', ['nik' => 'OTHER-1']);
        $this->actingAs($hrd->user)->put(route('employees.update', $employee), ['nama_lengkap' => 'X', 'nik' => 'OTHER-1', 'status_kepegawaian' => 'tetap'])->assertSessionHasErrors('nik');
    }

    public function test_supervisor_chain_cannot_loop_or_point_to_self(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeEmployee('supervisor');
        $b = $this->makeEmployee('supervisor', ['id_atasan' => $a->id_karyawan]);
        $c = $this->makeEmployee('karyawan', ['id_atasan' => $b->id_karyawan]);

        $form = fn (Employee $e, ?int $boss) => ['nama_lengkap' => $e->nama_lengkap, 'nik' => $e->nik, 'status_kepegawaian' => 'tetap', 'id_atasan' => $boss];

        $this->actingAs($hrd->user)->put(route('employees.update', $a), $form($a, $a->id_karyawan))->assertSessionHasErrors('id_atasan');   // diri sendiri
        $this->actingAs($hrd->user)->put(route('employees.update', $a), $form($a, $b->id_karyawan))->assertSessionHasErrors('id_atasan');   // A -> B -> A
        $this->actingAs($hrd->user)->put(route('employees.update', $a), $form($a, $c->id_karyawan))->assertSessionHasErrors('id_atasan');   // A -> C -> B -> A
        $this->actingAs($hrd->user)->put(route('employees.update', $c), $form($c, $a->id_karyawan))->assertSessionHasNoErrors();           // C boleh langsung ke A
        $this->assertNull($a->fresh()->id_atasan);
    }

    public function test_detail_page_hides_sensitive_data_from_supervisors(): void
    {
        $boss = $this->makeEmployee('supervisor');
        $hrd = $this->makeEmployee('hrd');
        $ceo = $this->makeEmployee('direktur');
        $mine = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Anak Buah', 'id_atasan' => $boss->id_karyawan, 'gaji_pokok' => 8765432, 'nama_bank' => 'Bank Rahasia']);
        $stranger = $this->makeEmployee('karyawan');

        foreach ([$hrd, $ceo] as $viewer) {
            $this->actingAs($viewer->user)->get(route('employees.show', $mine))->assertOk()->assertSee('Rp 8.765.432')->assertSee('Bank Rahasia');
        }

        $this->actingAs($boss->user)->get(route('employees.show', $mine))->assertOk()->assertSee('Anak Buah')
            ->assertDontSee('8.765.432')->assertDontSee('Bank Rahasia')->assertDontSee(route('employees.edit', $mine), false);

        $this->actingAs($boss->user)->get(route('employees.show', $stranger))->assertForbidden();
        $this->actingAs($this->makeEmployee('karyawan')->user)->get(route('employees.show', $mine))->assertForbidden();
    }

    public function test_deactivating_blocks_login_and_reactivating_restores_it(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $employee = $this->makeEmployee('karyawan', [], ['password' => 'rahasia123']);

        $this->actingAs($hrd->user)->patch(route('employees.status', $employee), ['status' => 'nonaktif'])->assertSessionHas('status');
        $this->assertSame('nonaktif', $employee->fresh()->status);
        $this->assertSame('nonaktif', $employee->user->fresh()->status);

        auth()->logout();
        $this->post('/login', ['email' => $employee->user->email, 'password' => 'rahasia123'])->assertSessionHasErrors('auth');
        $this->assertGuest();

        $this->actingAs($hrd->user)->patch(route('employees.status', $employee), ['status' => 'aktif']);
        $this->assertSame('aktif', $employee->fresh()->status);
        $this->assertSame('aktif', $employee->user->fresh()->status);

        auth()->logout();
        $this->post('/login', ['email' => $employee->user->email, 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    }

    public function test_status_change_rules(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $boss = $this->makeEmployee('supervisor');
        $employee = $this->makeEmployee('karyawan');

        $this->actingAs($hrd->user)->patch(route('employees.status', $hrd), ['status' => 'nonaktif'])->assertForbidden();         // akun sendiri
        $this->actingAs($boss->user)->patch(route('employees.status', $employee), ['status' => 'nonaktif'])->assertForbidden();   // bukan pengelola
        $this->actingAs($hrd->user)->patch(route('employees.status', $employee), ['status' => 'cuti'])->assertSessionHasErrors('status');

        $this->assertSame('aktif', $hrd->fresh()->status);
        $this->assertSame('aktif', $employee->fresh()->status);
    }

    public function test_edit_form_is_prefilled_and_excludes_the_person_from_the_boss_list(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $boss = $this->makeEmployee('supervisor', ['nama_lengkap' => 'Sinta Boss', 'nik' => 'BOSS-1', 'jabatan' => 'Kepala Tim']);
        $this->makeEmployee('supervisor', ['nama_lengkap' => 'Rina Lain']);

        $page = $this->actingAs($hrd->user)->get(route('employees.edit', $boss))->assertOk();
        $page->assertSee('value="BOSS-1"', false)->assertSee('value="Kepala Tim"', false);
        $page->assertSee('Rina Lain')->assertDontSee('Sinta Boss (Kepala Tim)');
    }
}
