<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAttendanceData;
use Tests\TestCase;
use ZipArchive;

/** Ekspor Excel (.xlsx) dan halaman cetak (PDF) mengikuti filter dan hak lihat. */
class AttendanceExportTest extends TestCase
{
    use CreatesAttendanceData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.work_start' => '09:00']);
        Carbon::setTestNow('2026-06-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return string isi sheet1.xml dari respons .xlsx */
    private function sheetXml($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $response->baseResponse->getContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'respons harus berupa file zip/xlsx yang valid');
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return (string) $xml;
    }

    public function test_excel_export_is_a_valid_xlsx_that_respects_filters(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Andi Hadir', 'id_departemen' => $this->departmentNamed('Marketing')->id_departemen]);
        $b = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Budi Kemarin']);
        $this->makeAttendance($a, '2026-06-22', '09:30:00');
        $this->makeAttendance($b, '2026-06-19', '08:40:00');

        $response = $this->actingAs($hrd->user)->get(route('attendance.export.xlsx'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('absensi-2026-06-22.xlsx', $response->headers->get('Content-Disposition'));

        $xml = $this->sheetXml($response);
        $this->assertStringContainsString('Andi Hadir', $xml);
        $this->assertStringContainsString('Marketing', $xml);
        $this->assertStringContainsString('Late', $xml);
        $this->assertStringContainsString('09.30', $xml);
        $this->assertStringNotContainsString('Budi Kemarin', $xml, 'default tanggal = hari ini');

        $all = $this->sheetXml($this->actingAs($hrd->user)->get(route('attendance.export.xlsx', ['date' => ''])));
        $this->assertStringContainsString('Budi Kemarin', $all);
    }

    public function test_excel_export_only_contains_rows_the_user_may_see(): void
    {
        $me = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Saya Sendiri']);
        $other = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Orang Lain']);
        $this->makeAttendance($me, '2026-06-22', '08:40:00');
        $this->makeAttendance($other, '2026-06-22', '08:41:00');

        $xml = $this->sheetXml($this->actingAs($me->user)->get(route('attendance.export.xlsx', ['date' => ''])));

        $this->assertStringContainsString('Saya Sendiri', $xml);
        $this->assertStringNotContainsString('Orang Lain', $xml);
    }

    public function test_excel_cells_are_text_so_formulas_in_names_never_run(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $evil = $this->makeEmployee('karyawan', ['nama_lengkap' => '=HYPERLINK("http://evil.test","klik") <script>']);
        $this->makeAttendance($evil, '2026-06-22', '08:40:00');

        $xml = $this->sheetXml($this->actingAs($hrd->user)->get(route('attendance.export.xlsx')));

        $this->assertStringContainsString('t="inlineStr"', $xml);
        $this->assertStringNotContainsString('<f>', $xml, 'tidak boleh ada elemen rumus');
        $this->assertStringContainsString('&lt;script&gt;', $xml, 'karakter XML di-escape');
    }

    public function test_print_page_lists_rows_and_requires_login(): void
    {
        $hrd = $this->makeEmployee('hrd');
        $a = $this->makeEmployee('karyawan', ['nama_lengkap' => 'Andi Cetak']);
        $this->makeAttendance($a, '2026-06-22', '08:40:00');

        $this->actingAs($hrd->user)->get(route('attendance.export.print'))
            ->assertOk()->assertSee('Rekap Absensi Karyawan')->assertSee('Andi Cetak')->assertSee('Simpan sebagai PDF');

        auth()->logout();
        $this->get(route('attendance.export.print'))->assertRedirect(route('login'));
        $this->get(route('attendance.export.xlsx'))->assertRedirect(route('login'));
    }
}
