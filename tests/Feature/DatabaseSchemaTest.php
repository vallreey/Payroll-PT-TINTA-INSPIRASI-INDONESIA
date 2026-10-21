<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Menjaga agar skema database tetap sesuai SDD/SRS (lihat docs/DATABASE.md).
 * Jika test ini gagal setelah mengubah migrasi, berarti ada kolom/aturan SDD yang hilang.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_and_columns_from_sdd_exist(): void
    {
        $expected = [
            'users' => ['name', 'username', 'email', 'password', 'role', 'status', 'is_active', 'profile_photo_path', 'approved_by', 'approved_at'],
            'departments' => ['name', 'description'],
            'employees' => ['user_id', 'department_id', 'supervisor_id', 'nik', 'position', 'phone', 'address', 'join_date', 'base_salary', 'is_active'],
            'employee_documents' => ['employee_id', 'document_type', 'file_path'],
            'attendances' => ['employee_id', 'work_date', 'presence', 'check_in_at', 'check_out_at', 'photo_path', 'check_out_photo_path',
                'latitude', 'longitude', 'check_out_latitude', 'check_out_longitude', 'status', 'notes'],
            'location_trackings' => ['employee_id', 'latitude', 'longitude', 'recorded_at', 'note'],
            'leave_types' => ['name', 'category', 'default_quota', 'requires_attachment'],
            'leave_quotas' => ['employee_id', 'leave_type_id', 'year', 'quota', 'used'],
            'leave_requests' => ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'total_days', 'reason', 'attachment_path',
                'status', 'approved_by', 'approval_note', 'rejection_reason'],
            'payroll_periods' => ['name', 'start_date', 'end_date'],
            'payrolls' => ['employee_id', 'payroll_period_id', 'basic_salary', 'allowance', 'attendance_deduction', 'pph21_deduction',
                'bpjs_deduction', 'bpjs_tk_deduction', 'bpjs_kes_deduction', 'other_deduction', 'gross_salary', 'net_salary', 'status', 'paid_at'],
            'notifications' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ada");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Tabel {$table} kehilangan kolom SDD");
        }
    }

    public function test_one_attendance_row_per_employee_per_day(): void
    {
        $employee = $this->makeEmployee('1');

        $row = [
            'employee_id' => $employee->id,
            'photo_path' => 'a.jpg', 'latitude' => -7.4, 'longitude' => 109.2,
            'check_in_at' => '2026-06-21 08:55:00',
        ];

        $first = Attendance::create($row);
        $this->assertSame('2026-06-21', $first->work_date, 'work_date harus terisi otomatis dari check_in_at');
        $this->assertSame('hadir', $first->fresh()->presence);

        $this->expectException(UniqueConstraintViolationException::class);
        Attendance::create($row + ['check_out_at' => null]);
    }

    public function test_same_employee_can_attend_on_different_days(): void
    {
        $employee = $this->makeEmployee('2');

        foreach (['2026-06-21 08:55:00', '2026-06-22 08:50:00'] as $at) {
            Attendance::create(['employee_id' => $employee->id, 'photo_path' => 'a.jpg', 'latitude' => 1, 'longitude' => 1, 'check_in_at' => $at]);
        }

        $this->assertSame(2, Attendance::count());
    }

    public function test_username_must_be_unique_but_may_be_empty(): void
    {
        User::factory()->create(['username' => 'budi']);
        User::factory()->count(2)->create(); // username null boleh lebih dari satu

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['username' => 'budi']);
    }

    public function test_laravel_database_notifications_work_with_the_table(): void
    {
        $user = User::factory()->create();
        $user->notify(new SchemaTestNotification());

        $this->assertSame(1, $user->unreadNotifications()->count());

        $notification = $user->unreadNotifications()->first();
        $this->assertSame('izin', $notification->data['tipe']);

        $notification->markAsRead();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    private function makeEmployee(string $nik): Employee
    {
        return Employee::create(['user_id' => User::factory()->create()->id, 'nik' => $nik]);
    }
}

class SchemaTestNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['judul' => 'Pengajuan izin baru', 'pesan' => 'Ada pengajuan izin.', 'tipe' => 'izin'];
    }
}
