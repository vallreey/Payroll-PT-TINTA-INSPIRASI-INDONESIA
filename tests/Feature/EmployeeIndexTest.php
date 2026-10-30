<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;
use ZipArchive;

/** Halaman daftar Karyawan: akses, jangkauan data, ringkasan, filter, dan ekspor. */
class EmployeeIndexTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    // Isi sheet pertama dari respons .xlsx
    private function sheetXml($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $response->baseResponse->getContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    public function test_only_management_roles_can_open_the_page(): void
    {
        $staff = $this->makeEmployee('karyawan');
        $this->actingAs($staff->user)->get(route('employees.index'))->assertForbidden();

        foreach (['super_admin', 'hrd', 'direktur', 'supervisor'] as $role) {
            $this->actingAs($this->makeEmployee($role)->user)->get(route('employees.index'))->assertOk();
        }

        auth()->logout();
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    }

    public function test_hrd_sees_everyone_and_supervisor_only_the_team(): void
    {
        $hrd = $this->makeEmployee('hrd', ['nama_lengkap' => 'Hana HRD']);
        $boss = $this->makeEmployee('supervisor', ['nama_lengkap' => 'Sinta Boss']);
        $mine = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Anak Buah', 'id_atasan' => $boss->id_karyawan]);
        $other = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Orang Lain']);

        $this->actingAs($hrd->user)->get(route('employees.index'))
            ->assertSee('Anak Buah')->assertSee('Orang Lain')->assertSee('Sinta Boss');

        $this->actingAs($boss->user)->get(route('employees.index'))
            ->assertSee('Anak Buah')->assertSee('Sinta Boss')->assertDontSee('Orang Lain')->assertDontSee('Hana HRD');
    }

    public function test_summary_counts_active_people_by_status_and_gender(): void
    {
        $hrd = $this->makeEmployee('hrd', ['status_kepegawaian' => 'tetap', 'jenis_kelamin' => 'P']);
        $this->makeEmployee('karyawan', ['status_kepegawaian' => 'tetap', 'jenis_kelamin' => 'L']);
        $this->makeEmployee('karyawan', ['status_kepegawaian' => 'kontrak', 'jenis_kelamin' => 'L']);
        $this->makeEmployee('karyawan', ['status_kepegawaian' => 'percobaan']); // gender belum diisi
        $this->makeEmployee('karyawan', ['status_kepegawaian' => 'tetap', 'status' => 'nonaktif', 'jenis_kelamin' => 'L']); // tidak dihitung

        $response = $this->actingAs($hrd->user)->get(route('employees.index'))->assertOk();
        $response->assertViewHas('summary', fn ($s) => $s['total'] === 4
            && $s['status'] === ['tetap' => 2, 'kontrak' => 1, 'percobaan' => 1]
            && $s['gender'] === ['L' => 2, 'P' => 1]
            && $s['gender_missing'] === 1);
        $response->assertSee('Permanent')->assertSee('Contract')->assertSee('Probation')->assertSee('Belum diisi');
    }

    public function test_filters_by_status_division_search_and_join_date(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $marketing = $this->departmentNamed('Marketing')->id_departemen;
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Andi Tetap', 'status_kepegawaian' => 'tetap', 'id_departemen' => $marketing, 'tanggal_masuk' => '2024-01-10']);
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Kontrak', 'status_kepegawaian' => 'kontrak', 'tanggal_masuk' => '2026-03-01']);
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Cici Resign', 'status' => 'nonaktif']);

        $get = fn (array $q) => $this->actingAs($hrd->user)->get(route('employees.index', $q));

        $get([])->assertSee('Andi Tetap')->assertSee('Budi Kontrak')->assertDontSee('Cici Resign');   // bawaan: hanya aktif
        $get(['status' => 'kontrak'])->assertSee('Budi Kontrak')->assertDontSee('Andi Tetap');
        $get(['status' => 'nonaktif'])->assertSee('Cici Resign')->assertDontSee('Andi Tetap');
        $get(['division' => $marketing])->assertSee('Andi Tetap')->assertDontSee('Budi Kontrak');
        $get(['q' => 'budi'])->assertSee('Budi Kontrak')->assertDontSee('Andi Tetap');
        $get(['date' => '2025-01-01'])->assertSee('Andi Tetap')->assertDontSee('Budi Kontrak');     // bergabung sampai tanggal
        $get(['q' => '%'])->assertOk()->assertDontSee('Andi Tetap');                                 // wildcard LIKE dianggap teks biasa
        $get(['status' => 'hack', 'division' => '99999', 'date' => 'bukan-tanggal'])->assertOk()->assertSee('Andi Tetap'); // nilai aneh diabaikan
    }

    public function test_pagination_and_add_button_visibility(): void
    {
        $hrd = $this->makeEmployee('hrd', ['nama_lengkap' => 'AAA Hrd']);
        for ($i = 1; $i <= 12; $i++) {
            $this->makeEmployee('karyawan', ['nama_lengkap' => sprintf('Pegawai %02d', $i)]);
        }

        $this->actingAs($hrd->user)->get(route('employees.index'))->assertSee(route('employees.create'), false)->assertSee('Pegawai 08')->assertDontSee('Pegawai 11');
        $this->actingAs($hrd->user)->get(route('employees.index', ['page' => 2]))->assertSee('Pegawai 11');

        // Direktur boleh melihat tetapi tidak boleh menambah
        $ceo = $this->makeEmployee('direktur');
        $this->actingAs($ceo->user)->get(route('employees.index'))->assertOk()->assertDontSee(route('employees.create'), false);
    }

    public function test_excel_export_has_no_salary_or_bank_data_and_respects_filters(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Andi Tetap', 'status_kepegawaian' => 'tetap', 'gaji_pokok' => 7654321, 'no_rekening' => '1234567890']);
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Kontrak', 'status_kepegawaian' => 'kontrak']);

        $response = $this->actingAs($hrd->user)->get(route('employees.export.xlsx', ['status' => 'tetap']));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));

        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Andi Tetap', $xml);
        $this->assertStringContainsString('Permanent', $xml);
        $this->assertStringNotContainsString('Budi Kontrak', $xml);
        $this->assertStringNotContainsString('7654321', $xml, 'gaji tidak boleh ikut diekspor');
        $this->assertStringNotContainsString('1234567890', $xml, 'rekening tidak boleh ikut diekspor');
    }

    public function test_exports_only_include_visible_people_and_require_a_management_role(): void
    {
        $boss = $this->makeEmployee('supervisor');
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Anak Buah', 'id_atasan' => $boss->id_karyawan]);
        $this->makeEmployee('karyawan', ['nama_lengkap' => 'Orang Lain']);

        $xml = $this->sheetXml($this->actingAs($boss->user)->get(route('employees.export.xlsx')));
        $this->assertStringContainsString('Anak Buah', $xml);
        $this->assertStringNotContainsString('Orang Lain', $xml);

        $this->actingAs($boss->user)->get(route('employees.export.print'))->assertOk()->assertSee('Daftar Karyawan')->assertSee('Anak Buah');

        $staff = $this->makeEmployee('karyawan');
        $this->actingAs($staff->user)->get(route('employees.export.xlsx'))->assertForbidden();
        $this->actingAs($staff->user)->get(route('employees.export.print'))->assertForbidden();
    }
}
