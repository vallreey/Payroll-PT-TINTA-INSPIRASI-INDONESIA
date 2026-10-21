<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SDD 2.3 Objek Database: v_karyawan_aktif, v_slip_gaji, v_rekap_absensi.
 *
 * Hanya dibuat di MySQL/MariaDB/PostgreSQL (SQL-nya portabel di ketiganya). Di SQLite (dipakai
 * phpunit) dilewati karena tidak mendukung EXTRACT; test tidak butuh view.
 *
 * Function/Procedure/Trigger SDD (hitung_gaji_bersih, hitung_pph21, generate_penggajian,
 * trg_kurangi_cuti, trg_notif_izin) SENGAJA tidak dibuat di database: sintaksnya berbeda antar
 * engine (SDD menulis PostgreSQL, proyek berjalan di MySQL), sulit di-test, dan SDD sendiri sudah
 * menempatkan logikanya di Service (PayrollService, ApprovalService, NotificationService).
 */
return new class extends Migration
{
    private function supported(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_karyawan_aktif AS
            SELECT e.id AS employee_id, e.nik, u.name AS employee_name, u.email, u.role,
                   e.position, e.department_id, d.name AS department_name,
                   e.join_date, e.employment_status, e.base_salary
            FROM employees e
            JOIN users u ON u.id = e.user_id
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE e.is_active = TRUE AND u.is_active = TRUE AND u.status = 'approved'
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_slip_gaji AS
            SELECT p.id AS payroll_id, p.employee_id, e.nik, u.name AS employee_name,
                   e.position, d.name AS department_name,
                   pp.id AS payroll_period_id, pp.name AS period_name, pp.start_date, pp.end_date,
                   p.basic_salary, p.allowance, p.gross_salary,
                   p.attendance_deduction, p.pph21_deduction,
                   p.bpjs_tk_deduction, p.bpjs_kes_deduction, p.other_deduction,
                   p.net_salary, p.status, p.paid_at
            FROM payrolls p
            JOIN payroll_periods pp ON pp.id = p.payroll_period_id
            JOIN employees e ON e.id = p.employee_id
            JOIN users u ON u.id = e.user_id
            LEFT JOIN departments d ON d.id = e.department_id
        SQL);

        // Satu baris per karyawan per bulan. Hari izin/cuti/sakit dihitung pada bulan tanggal mulainya.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_rekap_absensi AS
            SELECT x.employee_id, x.period_year, x.period_month,
                   SUM(x.hadir) AS total_hadir, SUM(x.terlambat) AS total_terlambat, SUM(x.alpha) AS total_alpha,
                   SUM(x.izin) AS total_izin, SUM(x.cuti) AS total_cuti, SUM(x.sakit) AS total_sakit
            FROM (
                SELECT a.employee_id,
                       EXTRACT(YEAR FROM a.work_date) AS period_year,
                       EXTRACT(MONTH FROM a.work_date) AS period_month,
                       CASE WHEN a.presence = 'hadir' THEN 1 ELSE 0 END AS hadir,
                       CASE WHEN a.presence = 'terlambat' THEN 1 ELSE 0 END AS terlambat,
                       CASE WHEN a.presence = 'alpha' THEN 1 ELSE 0 END AS alpha,
                       0 AS izin, 0 AS cuti, 0 AS sakit
                FROM attendances a
                WHERE a.status <> 'rejected'
                UNION ALL
                SELECT lr.employee_id,
                       EXTRACT(YEAR FROM lr.start_date),
                       EXTRACT(MONTH FROM lr.start_date),
                       0, 0, 0,
                       CASE WHEN lt.category = 'izin' THEN lr.total_days ELSE 0 END,
                       CASE WHEN lt.category = 'cuti' THEN lr.total_days ELSE 0 END,
                       CASE WHEN lt.category = 'sakit' THEN lr.total_days ELSE 0 END
                FROM leave_requests lr
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE lr.status = 'approved'
            ) x
            GROUP BY x.employee_id, x.period_year, x.period_month
        SQL);
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS v_rekap_absensi');
        DB::statement('DROP VIEW IF EXISTS v_slip_gaji');
        DB::statement('DROP VIEW IF EXISTS v_karyawan_aktif');
    }
};
