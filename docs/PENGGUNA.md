# Fitur Manajemen Pengguna

Halaman `/users` (menu **Manajemen Pengguna**), khusus **Super Admin** (`UserPolicy`). Memakai `tbl_pengguna`.

## Yang bisa dilakukan

| Aksi | Keterangan |
|---|---|
| Lihat daftar | kartu ringkasan, tab peran, filter peran/status/tanggal terdaftar, pencarian nama/email/username |
| Buat akun | Super Admin: akun saja (username wajib, dipakai sebagai nama tampil). Peran lain: akun **dan** data karyawan dasar (NIK + nama) dalam satu transaksi |
| Edit akun | username, email, peran |
| Reset kata sandi | sandi baru (min. 8, huruf + angka); `remember_token` diganti dan sesi tersimpan akun itu dihapus |
| Aktif / Nonaktifkan | akun nonaktif tidak bisa login dan **sesi yang sedang berjalan langsung diputus** |
| Setujui / Tolak registrasi | untuk akun berstatus `pending` (SRS use case 061); keputusan hanya sekali |

## Aturan

- Tidak bisa menonaktifkan akun sendiri dan tidak bisa mengubah peran sendiri.
- Selalu tersisa minimal satu Super Admin aktif (penjaga di `UserService`).
- Akun yang belum disetujui, ditolak, atau nonaktif ditolak saat login **dan di setiap permintaan berikutnya**
  (`EnsureAccountIsActive`). Sebelumnya status hanya dicek saat login, sehingga akun yang dinonaktifkan masih bisa memakai
  sesi lama sampai kedaluwarsa (120 menit).
- Middleware yang sama mengisi kolom **Terakhir Aktif** (`tbl_pengguna.terakhir_aktif`) paling sering sekali per 5 menit.

## Beda dari prototype (dan alasannya)

| Prototype | Implementasi | Alasan |
|---|---|---|
| Kartu "Akun diblokir" | **Menunggu Persetujuan** (+ jumlah registrasi ditolak) | SDD tidak punya status "diblokir"; yang ada alur persetujuan registrasi (SRS use case 060/061) |
| Rentang tanggal di pojok kanan atas | memfilter **tanggal akun terdaftar** (`created_at`) | prototype tidak menjelaskan tanggal apa yang dimaksud |
| Kolom "Terakhir Aktif" | kolom baru `terakhir_aktif` | tidak ada di SDD; tanpa kolom ini nilainya tidak bisa diketahui |
| Menu ⋮ tiap baris | Edit akun, Reset kata sandi, Setujui/Tolak, Aktifkan/Nonaktifkan | sesuai SRS use case 060/061 dan AC-003 |

## Perlu diketahui

- **Nonaktifkan di sini hanya menutup akses login.** Status kerja karyawan di tabel `tbl_karyawan` tidak berubah. Untuk
  karyawan yang resign, gunakan **Karyawan → Nonaktifkan** (mengubah keduanya).
- Pendaftar yang belum disetujui tidak tampil dan tidak dihitung di halaman Karyawan, dashboard, maupun absensi.
- Belum ada hapus akun dan belum ada notifikasi hasil persetujuan (tipe `tbl_notifikasi` hanya izin/cuti/absensi/gaji).
- "Terakhir Aktif" bernilai kosong ("Belum pernah") sampai akun itu membuka halaman setelah fitur ini dipasang.
