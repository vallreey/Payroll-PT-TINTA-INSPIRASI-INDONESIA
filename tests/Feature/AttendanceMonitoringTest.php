<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\AttendanceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;

/** Halaman pemantauan absensi (web): hak lihat, filter, ringkasan Kehadiran, foto bukti. */
class AttendanceMonitoringTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'attendance.work_start' => '09:00', 'attendance.work_end' => '16:00',
            'attendance.early_minutes' => 30, 'attendance.work_days' => [1, 2, 3, 4, 5],
        ]);
        Carbon::setTestNow('2026-06-22 10:00:00'); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function leave(Employee $e, string $jenis, string $status = 'approved'): void
    {
        LeaveRequest::create([
            'id_karyawan' => $e->id_karyawan, 'jenis' => $jenis, 'tanggal_mulai' => '2026-06-22',
            'tanggal_selesai' => '2026-06-23', 'total_hari' => 2, 'alasan' => 'x', 'status' => $status,
        ]);
    }

    public function test_hrd_sees_everyone_supervisor_sees_team_and_self_employee_sees_only_self(): void
    {
        $hrd = $this->makeEmployee('hrd', ['nama_lengkap' => 'Hana HRD']);
        $boss = $this->makeEmployee('supervisor', ['nama_lengkap' => 'Sinta Supervisor']);
        $mine = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Anak Buah', 'id_atasan' => $boss->id_karyawan]);
        $other = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Orang Lain']);
        foreach ([$hrd, $boss, $mine, $other] as $e) {
            $this->makeAttendance($e, '2026-06-22', '08:40:00');
        }

        $this->actingAs($hrd->user)->get(route('attendance.index'))
            ->assertOk()->assertSee('Anak Buah')->assertSee('Orang Lain')->assertSee('Sinta Supervisor');

        $this->actingAs($boss->user)->get(route('attendance.index'))
            ->assertOk()->assertSee('Anak Buah')->assertSee('Sinta Supervisor')->assertDontSee('Orang Lain')->assertDontSee('Hana HRD');

        $this->actingAs($mine->user)->get(route('attendance.index'))
            ->assertOk()->assertSee('Riwayat Absensi')->assertSee('Anak Buah')
            ->assertDontSee('Orang Lain')->assertDontSee('Persetujuan Absensi')->assertDontSee('Kehadiran');
    }

    public function test_default_date_is_today_for_managers_and_empty_date_shows_all(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Hari Ini']);
        $b = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Kemarin']);
        $this->makeAttendance($a, '2026-06-22', '08:40:00');
        $this->makeAttendance($b, '2026-06-19', '08:40:00');

        $this->actingAs($hrd->user)->get(route('attendance.index'))->assertSee('Hari Ini')->assertDontSee('Kemarin');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['date' => '2026-06-19']))->assertSee('Kemarin')->assertDontSee('Hari Ini');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['date' => '']))->assertSee('Hari Ini')->assertSee('Kemarin');
        // tanggal tidak valid tidak boleh menyebabkan error
        $this->actingAs($hrd->user)->get(route('attendance.index', ['date' => '31-02-xx']))->assertOk();
    }

    public function test_filters_by_division_status_and_search(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Andi Marketing', 'id_departemen' => $this->departmentNamed('Marketing')->id_departemen]);
        $b = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Kreatif', 'id_departemen' => $this->departmentNamed('Kreatif')->id_departemen]);
        $this->makeAttendance($a, '2026-06-22', '08:40:00');
        $this->makeAttendance($b, '2026-06-22', '09:30:00', ['status_persetujuan' => 'approved']);

        $marketing = $this->departmentNamed('Marketing')->id_departemen;
        $this->actingAs($hrd->user)->get(route('attendance.index', ['division' => $marketing]))->assertSee('Andi Marketing')->assertDontSee('Budi Kreatif');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['status' => 'late']))->assertSee('Budi Kreatif')->assertDontSee('Andi Marketing');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['status' => 'pending']))->assertSee('Andi Marketing')->assertDontSee('Budi Kreatif');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['q' => 'kreatif']))->assertSee('Budi Kreatif')->assertDontSee('Andi Marketing');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['q' => $a->nik]))->assertSee('Andi Marketing')->assertDontSee('Budi Kreatif');
        // karakter LIKE khusus diperlakukan sebagai teks biasa
        $this->actingAs($hrd->user)->get(route('attendance.index', ['q' => '%']))->assertOk()->assertDontSee('Andi Marketing');
        // nilai filter tidak dikenal diabaikan
        $this->actingAs($hrd->user)->get(route('attendance.index', ['status' => 'hack', 'division' => '999999']))->assertOk()->assertSee('Andi Marketing');
    }

    public function test_pending_rows_come_first_and_pagination_works(): void
    {
        $hrd = $this->makeEmployee('hrd');
        for ($i = 1; $i <= 12; $i++) {
            $e = $this->makeEmployee('karyawan', ['nama_lengkap' => sprintf('Pegawai %02d', $i)]);
            $this->makeAttendance($e, '2026-06-22', '08:'.sprintf('%02d', 10 + $i).':00', ['status_persetujuan' => $i === 12 ? 'pending' : 'approved']);
        }

        $first = $this->actingAs($hrd->user)->get(route('attendance.index'));
        $first->assertOk()->assertSeeInOrder(['Pegawai 12', 'Pegawai 01'])->assertDontSee('Pegawai 11');
        $this->actingAs($hrd->user)->get(route('attendance.index', ['page' => 2]))->assertOk()->assertSee('Pegawai 11');
    }

    public function test_summary_counts_follow_the_documented_rules(): void
    {
        $scope = fn () => Employee::query();
        $early = $this->makeEmployee('karyawan');   // 08:20 -> early
        $onTime = $this->makeEmployee('karyawan');  // 08:45 -> on time
        $late = $this->makeEmployee('karyawan');    // 09:30 -> late
        $none = $this->makeEmployee('karyawan');    // tidak ada absensi -> no clock in
        $cuti = $this->makeEmployee('karyawan');
        $izin = $this->makeEmployee('karyawan');
        $rejected = $this->makeEmployee('karyawan'); // absensi ditolak -> tidak dihitung hadir
        $resigned = $this->makeEmployee('karyawan', ['status' => 'nonaktif']);

        $this->makeAttendance($early, '2026-06-22', '08:20:00', ['jam_keluar' => '15:00:00']); // pulang awal
        $this->makeAttendance($onTime, '2026-06-22', '08:45:00');
        $this->makeAttendance($late, '2026-06-22', '09:30:00');
        $this->makeAttendance($rejected, '2026-06-22', '08:50:00', ['status_persetujuan' => 'rejected']);
        $this->leave($cuti, 'cuti');
        $this->leave($izin, 'izin');

        $s = app(AttendanceSummary::class)->forDate($scope(), CarbonImmutable::parse('2026-06-22'));

        $this->assertTrue($s['working_day']);
        $this->assertSame(['on_time' => 1, 'early' => 1, 'late' => 1], $s['present']);
        // none + rejected = 2 orang tanpa absensi valid; nonaktif tidak dihitung
        $this->assertSame(2, $s['absent']['no_clock_in']);
        $this->assertSame(0, $s['absent']['no_clock_out'], 'jam pulang belum lewat (sekarang 10.00)');
        $this->assertSame(1, $s['absent']['leaved']);
        $this->assertSame(['day_off' => 1, 'time_off' => 1], $s['away']);

        // setelah jam pulang, yang belum check-out dihitung "No Clock Out"
        Carbon::setTestNow('2026-06-22 17:00:00');
        $s = app(AttendanceSummary::class)->forDate($scope(), CarbonImmutable::parse('2026-06-22'));
        $this->assertSame(2, $s['absent']['no_clock_out']); // onTime + late belum check-out
    }

    public function test_summary_ignores_unapproved_leave_and_weekends(): void
    {
        $e = $this->makeEmployee('karyawan');
        $this->leave($e, 'cuti', 'pending'); // belum disetujui -> tidak dianggap sedang cuti

        $s = app(AttendanceSummary::class)->forDate(Employee::query(), CarbonImmutable::parse('2026-06-22'));
        $this->assertSame(1, $s['absent']['no_clock_in']);
        $this->assertSame(0, $s['away']['day_off']);

        // Sabtu: bukan hari kerja -> tidak ada yang "No Clock In"
        $weekend = app(AttendanceSummary::class)->forDate(Employee::query(), CarbonImmutable::parse('2026-06-20'));
        $this->assertFalse($weekend['working_day']);
        $this->assertSame(0, $weekend['absent']['no_clock_in']);
    }

    public function test_summary_card_is_rendered_for_managers(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $this->makeAttendance($hrd, '2026-06-22', '08:20:00');

        $this->actingAs($hrd->user)->get(route('attendance.index'))
            ->assertOk()->assertSee('Kehadiran')->assertSee('Early Clock In')->assertSee('No Clock In')->assertSee('Day Off');
    }

    public function test_evidence_photo_is_private_and_access_controlled(): void
    {
        Storage::disk('local')->put('absensi/test/masuk.jpg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP/bAEMA'));

        $boss = $this->makeEmployee('supervisor');
        $mine = $this->makeEmployee('karyawan', ['id_atasan' => $boss->id_karyawan]);
        $stranger = $this->makeEmployee('karyawan');
        $otherBoss = $this->makeEmployee('supervisor');
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeAttendance($mine, '2026-06-22', '08:40:00');

        $url = route('attendance.photo', [$a, 'masuk']);
        $this->actingAs($mine->user)->get($url)->assertOk();            // pemilik
        $this->actingAs($boss->user)->get($url)->assertOk();            // atasan
        $this->actingAs($hrd->user)->get($url)->assertOk();             // HRD
        $this->actingAs($stranger->user)->get($url)->assertForbidden(); // karyawan lain
        $this->actingAs($otherBoss->user)->get($url)->assertForbidden(); // supervisor lain
        $this->actingAs($hrd->user)->get(route('attendance.photo', [$a, 'keluar']))->assertNotFound(); // belum ada foto keluar
        $this->actingAs($hrd->user)->get('/attendance/'.$a->id_absensi.'/foto/../../.env')->assertNotFound();

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }
}
