<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

/** Persetujuan absensi (SRS use case 015/028/041/054, AC-008). */
class AttendanceApprovalTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hrd_approves_and_employee_is_notified(): void
    {
        $hrd = $this->makeEmployee('hrd', ['nama_lengkap' => 'Hana HRD']);
        $staff = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($staff, '2026-06-22', '08:40:00');

        $this->actingAs($hrd->user)->patch(route('attendance.approve', $a))->assertSessionHas('status');

        $a->refresh();
        $this->assertSame('approved', $a->status_persetujuan);
        $this->assertSame($hrd->id_pengguna, $a->approved_by);
        $this->assertNotNull($a->approved_at);

        $n = Notifikasi::where('id_pengguna', $staff->id_pengguna)->firstOrFail();
        $this->assertSame('absensi', $n->tipe);
        $this->assertStringContainsString('disetujui', $n->pesan);
        $this->assertStringContainsString('Hana HRD', $n->pesan);
        $this->assertFalse($n->is_read);
    }

    public function test_rejecting_notifies_the_employee(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $staff = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($staff, '2026-06-22', '08:40:00');

        $this->actingAs($hrd->user)->patch(route('attendance.reject', $a))->assertSessionHas('status');

        $this->assertSame('rejected', $a->fresh()->status_persetujuan);
        $this->assertStringContainsString('ditolak', Notifikasi::where('id_pengguna', $staff->id_pengguna)->value('pesan'));
    }

    public function test_supervisor_can_decide_only_for_direct_reports(): void
    {
        $boss = $this->makeEmployee('supervisor');
        $mine = $this->makeEmployee('karyawan', ['id_atasan' => $boss->id_karyawan]);
        $notMine = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($mine, '2026-06-22', '08:40:00');
        $b = $this->makeAttendance($notMine, '2026-06-22', '08:41:00');

        $this->actingAs($boss->user)->patch(route('attendance.approve', $a))->assertSessionHas('status');
        $this->actingAs($boss->user)->patch(route('attendance.approve', $b))->assertForbidden();

        $this->assertSame('approved', $a->fresh()->status_persetujuan);
        $this->assertSame('pending', $b->fresh()->status_persetujuan);
    }

    public function test_nobody_can_decide_their_own_attendance(): void
    {
        foreach (['hrd', 'direktur', 'super_admin', 'supervisor'] as $role) {
            $me = $this->makeEmployee($role);
            $a = $this->makeAttendance($me, '2026-06-22', '08:40:00');

            $this->actingAs($me->user)->patch(route('attendance.approve', $a))->assertForbidden();
            $this->assertSame('pending', $a->fresh()->status_persetujuan, "role {$role}");
        }
    }

    public function test_regular_employee_cannot_approve_anything(): void
    {
        $colleague = $this->makeEmployee('karyawan');
        $other = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($other, '2026-06-22', '08:40:00');

        $this->actingAs($colleague->user)->patch(route('attendance.approve', $a))->assertForbidden();
        $this->actingAs($colleague->user)->patch(route('attendance.reject', $a))->assertForbidden();
        $this->assertSame('pending', $a->fresh()->status_persetujuan);
    }

    public function test_decision_cannot_be_made_twice(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $director = $this->makeEmployee('direktur');
        $staff = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($staff, '2026-06-22', '08:40:00');

        $this->actingAs($hrd->user)->patch(route('attendance.approve', $a))->assertSessionHas('status');
        $this->actingAs($director->user)->patch(route('attendance.reject', $a))->assertSessionHas('error');

        $this->assertSame('approved', $a->fresh()->status_persetujuan);
        $this->assertSame($hrd->id_pengguna, $a->fresh()->approved_by);
        $this->assertSame(1, Notifikasi::where('id_pengguna', $staff->id_pengguna)->count());
    }

    public function test_action_menu_is_shown_only_when_the_user_can_decide(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $staff = $this->makeEmployee('karyawan');
        $own = $this->makeAttendance($hrd, '2026-06-22', '08:30:00');
        $theirs = $this->makeAttendance($staff, '2026-06-22', '08:40:00');

        $html = $this->actingAs($hrd->user)->get(route('attendance.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('attendance.approve', $theirs), $html, 'HRD boleh menyetujui absensi karyawan');
        $this->assertStringNotContainsString(route('attendance.approve', $own), $html, 'tidak ada tombol untuk absensi sendiri');
    }

    public function test_guest_cannot_decide(): void
    {
        $staff = $this->makeEmployee('karyawan');
        $a = $this->makeAttendance($staff, '2026-06-22', '08:40:00');

        $this->patch(route('attendance.approve', $a))->assertRedirect(route('login'));
    }
}
