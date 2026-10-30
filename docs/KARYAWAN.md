# Fitur Karyawan

Halaman `/employees` (menu **Karyawan**) memakai tabel SDD `tbl_karyawan` (+ `tbl_pengguna` untuk akun login).

## Hak akses (`EmployeePolicy`)

| Aksi | Super Admin | HRD | CEO/Direktur | Supervisor | Karyawan |
|---|---|---|---|---|---|
| Lihat daftar & detail | semua | semua | semua | bawahan + dirinya | tidak |
| Tambah / ubah / aktif-nonaktif | ya | ya | tidak | tidak | tidak |
| Lihat gaji, rekening, scan KTP | ya | ya | ya | tidak | tidak |
| Menentukan peran akun baru | ya | tidak (selalu "karyawan") | tidak | tidak | tidak |

> SRS menyebut Supervisor dan CEO juga "mengelola data karyawan". Hak ubah sengaja dibatasi ke Super Admin dan HRD
> karena form ini memuat gaji dan membuat akun login. Longgarkan di `EmployeePolicy` bila kebijakan PT berbeda.

## Aturan

- **Tambah karyawan** membuat akun login (langsung `approved`, `aktif`) dan data karyawan dalam satu transaksi. Email unik,
  kata sandi min. 8 karakter berisi huruf dan angka.
- **Employee ID** = `tbl_karyawan.nik` (unik, maks. 20 karakter).
- **Jenis kepegawaian**: `tetap` (Permanent), `kontrak` (Contract), `percobaan` (Probation).
- **Nonaktifkan** mengubah `status` karyawan *dan* akun login menjadi `nonaktif`, sehingga tidak bisa login dan tidak bisa
  mencatat absensi. Akun sendiri tidak bisa dinonaktifkan.
- **Atasan langsung** tidak boleh diri sendiri atau berada di bawah karyawan itu (rantai melingkar ditolak). Atasan
  menerima notifikasi dan menyetujui absensi (lihat `ABSENSI.md`).
- **Scan KTP** (JPG/PNG/PDF, maks. 4 MB) disimpan privat di `storage/app/private/karyawan/ktp/` dan dibuka lewat
  `/employees/{id}/ktp` setelah cek hak akses. Mengganti KTP menghapus file lama.
- **Filter tanggal** berarti "bergabung sampai tanggal ini" (`tanggal_masuk <=`). Bawaan menampilkan karyawan aktif saja;
  pilih Status = Nonaktif untuk melihat yang nonaktif.
- **Ekspor Excel/PDF** mengikuti filter dan tidak memuat gaji, rekening, maupun KTP.

## Belum tercakup

- CRUD Divisi/Departemen (pilihan Divisi memakai `tbl_departemen` yang sudah ada).
- Upload dokumen selain KTP (`tbl_dokumen_karyawan`) dan foto profil.
- Kolom "Lokasi / Track.." menjadi tautan otomatis begitu route `tracking.index` dibuat.
