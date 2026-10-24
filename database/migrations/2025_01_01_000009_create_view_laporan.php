<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SDD 2.3 Objek Database: v_karyawan_aktif, v_slip_gaji, v_rekap_absensi.
 *
 * Hanya dibuat di MySQL/MariaDB/PostgreSQL (SQL-nya portabel di ketiganya). Di SQLite (dipakai phpunit)
 * dilewati karena tidak mendukung EXTRACT; test tidak membutuhkan view.
 *
 * Function/Procedure/Trigger SDD (hitung_gaji_bersih, hitung_pph21, generate_penggajian, trg_kurangi_cuti,
 * trg_notif_izin) SENGAJA tidak dibuat di database: sintaksnya berbeda antar engine (SDD menulis PostgreSQL,
 * proyek berjalan di MySQL) dan sulit di-test. SDD sendiri menempatkan logikanya di Service
 * (PayrollService, ApprovalService, NotificationService).
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
            SELECT k.id_karyawan, k.nik, k.nama_lengkap, p.email, p.role, k.jabatan,
                   k.id_departemen, d.nama_departemen, k.tanggal_masuk, k.status_kepegawaian,
                   k.gaji_pokok, k.sisa_cuti
            FROM tbl_karyawan k
            JOIN tbl_pengguna p ON p.id_pengguna = k.id_pengguna
            LEFT JOIN tbl_departemen d ON d.id_departemen = k.id_departemen
            WHERE k.status = 'aktif' AND p.status = 'aktif' AND p.status_persetujuan = 'approved'
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_slip_gaji AS
            SELECT g.id_penggajian, g.id_karyawan, k.nik, k.nama_lengkap, k.jabatan, d.nama_departemen,
                   g.bulan, g.tahun, g.gaji_pokok, g.tunjangan, g.potongan_absensi,
                   g.pph21, g.bpjs_tk, g.bpjs_kes, g.gaji_bersih, g.status, g.tanggal_bayar
            FROM tbl_penggajian g
            JOIN tbl_karyawan k ON k.id_karyawan = g.id_karyawan
            LEFT JOIN tbl_departemen d ON d.id_departemen = k.id_departemen
        SQL);

        // Satu baris per karyawan per bulan. Hari izin/cuti/sakit dihitung pada bulan tanggal mulainya.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_rekap_absensi AS
            SELECT x.id_karyawan, x.tahun, x.bulan,
                   SUM(x.hadir) AS total_hadir, SUM(x.terlambat) AS total_terlambat, SUM(x.alpha) AS total_alpha,
                   SUM(x.izin) AS total_izin, SUM(x.cuti) AS total_cuti, SUM(x.sakit) AS total_sakit
            FROM (
                SELECT a.id_karyawan,
                       EXTRACT(YEAR FROM a.tanggal) AS tahun,
                       EXTRACT(MONTH FROM a.tanggal) AS bulan,
                       CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END AS hadir,
                       CASE WHEN a.status = 'terlambat' THEN 1 ELSE 0 END AS terlambat,
                       CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END AS alpha,
                       0 AS izin, 0 AS cuti, 0 AS sakit
                FROM tbl_absensi a
                WHERE a.status_persetujuan <> 'rejected'
                UNION ALL
                SELECT i.id_karyawan,
                       EXTRACT(YEAR FROM i.tanggal_mulai),
                       EXTRACT(MONTH FROM i.tanggal_mulai),
                       0, 0, 0,
                       CASE WHEN i.jenis = 'izin' THEN i.total_hari ELSE 0 END,
                       CASE WHEN i.jenis = 'cuti' THEN i.total_hari ELSE 0 END,
                       CASE WHEN i.jenis = 'sakit' THEN i.total_hari ELSE 0 END
                FROM tbl_izin_cuti i
                WHERE i.status = 'approved'
            ) x
            GROUP BY x.id_karyawan, x.tahun, x.bulan
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
