# Fitur Absensi

Pencatatan dilakukan lewat **ponsel** (`/attendance/catat`); web desktop dipakai untuk **memantau dan menyetujui**
(`/attendance`). Seluruh data memakai tabel SDD `tbl_absensi`, `tbl_karyawan`, `tbl_izin_cuti`, `tbl_notifikasi`.

## Alur

1. Karyawan membuka `/attendance/catat` di ponsel, mengizinkan lokasi, mengambil foto, lalu menekan **Check-in**.
2. Server mencatat `jam_masuk` (waktu server, bukan waktu perangkat), foto, dan koordinat. Status `hadir` bila
   `jam_masuk <= ATTENDANCE_WORK_START`, selain itu `terlambat`. Status persetujuan awal `pending`.
3. Atasan langsung (`id_atasan`) mendapat notifikasi `tbl_notifikasi` (tipe `absensi`). Bila tidak punya atasan:
   semua HRD; bila tidak ada HRD: Super Admin.
4. Sore hari: **Check-out** dengan foto + lokasi (memperbarui baris yang sama).
5. Penyetuju membuka `/attendance`, memilih **Action → Setujui / Tolak**. Karyawan mendapat notifikasi hasilnya.

## Aturan penting

| Aturan | Keterangan |
|---|---|
| Satu baris per karyawan per hari | `unique(id_karyawan, tanggal)` + pemeriksaan di `AttendanceService` |
| Waktu dari server | field `jam_masuk`/`tanggal`/`status` pada request diabaikan |
| Foto & GPS wajib | check-in dan check-out; koordinat (0,0) ditolak; foto JPG/PNG/WEBP maks 8 MB |
| Foto bersifat privat | disimpan di `storage/app/private/absensi/…`, dibuka lewat `/attendance/{id}/foto/{masuk\|keluar}` setelah cek hak akses (tidak butuh `storage:link`) |
| Siapa melihat siapa | Super Admin/HRD/Direktur: semua; Supervisor: bawahan + dirinya; Karyawan: dirinya (`Employee::visibleTo`) |
| Siapa menyetujui | Super Admin/HRD/Direktur: semua kecuali absensinya sendiri; Supervisor: hanya bawahan langsung; tidak ada yang boleh menyetujui absensinya sendiri |
| Keputusan sekali saja | update bersyarat `WHERE status_persetujuan = 'pending'`; penyetuju kedua mendapat pesan "sudah diproses" |
| Absensi ditolak | tidak dihitung hadir; karyawan tidak bisa check-in ulang di hari yang sama (hubungi HRD) |

## Kartu "Kehadiran" (halaman Absensi)

Dihitung oleh `AttendanceSummary` untuk tanggal yang dipilih, hanya karyawan aktif (akun aktif & disetujui):

| Kartu | Arti |
|---|---|
| On Time | `hadir`, check-in setelah batas "early" |
| Early Clock In | `hadir`, check-in `ATTENDANCE_EARLY_MINUTES` menit atau lebih sebelum jam masuk |
| Late Clock In | `terlambat` |
| No Clock In | tidak ada absensi valid, tidak sedang izin/cuti/sakit, **pada hari kerja** |
| No Clock out | sudah check-in tetapi belum check-out; baru dihitung setelah `ATTENDANCE_WORK_END` |
| Leaved | check-out sebelum `ATTENDANCE_WORK_END` (pulang lebih awal) |
| Day Off | cuti + sakit yang disetujui dan mencakup tanggal itu |
| Time Off | izin yang disetujui dan mencakup tanggal itu |

> SDD tidak mendefinisikan Early, Leaved, maupun Time Off. Definisi di atas adalah asumsi dan seluruhnya
> diatur dari `config/attendance.php` / `.env`; ubah di sana atau di `AttendanceSummary` bila kebijakan PT berbeda.

## Konfigurasi (`.env`)

```
APP_TIMEZONE=Asia/Jakarta
ATTENDANCE_WORK_START=09:00
ATTENDANCE_WORK_END=16:00
ATTENDANCE_EARLY_MINUTES=30
ATTENDANCE_WORK_DAYS=1,2,3,4,5      # 1=Senin ... 7=Minggu
```

## Menjalankan di ponsel

- **GPS hanya bekerja lewat HTTPS atau `localhost`.** Membuka `http://192.168.x.x:8000` dari ponsel akan memunculkan pesan
  "Lokasi hanya bisa diambil lewat HTTPS". Gunakan domain ber-HTTPS (server), Laragon dengan SSL aktif, atau tunnel
  (mis. Cloudflare Tunnel / ngrok) saat menguji dari ponsel.
- Tombol **Ambil foto** membuka kamera depan di ponsel (`capture="user"`); di desktop hanya memunculkan pemilih file.
- Foto diperkecil di perangkat (maks 1280 px) sebelum diunggah.

## Ekspor

- **Excel**: file `.xlsx` sungguhan (`SimpleXlsx`, memakai ekstensi PHP `zip`); mengikuti filter yang aktif. Isi sel selalu
  teks sehingga nama seperti `=HYPERLINK(...)` tidak dijalankan Excel.
- **PDF**: halaman cetak (`/attendance/export/print`); pilih "Simpan sebagai PDF" di dialog cetak browser.

## Keterbatasan yang diketahui

- `tbl_absensi` tidak punya kolom catatan persetujuan, sehingga alasan penolakan tidak disimpan (hanya jadi notifikasi).
- Satu tanggal per baris: shift yang melewati tengah malam belum didukung.
- Belum ada kalender hari libur nasional; hanya `ATTENDANCE_WORK_DAYS`.
- Penyetuju dapat menyetujui sebelum karyawan check-out; check-out sesudahnya tidak mengubah status persetujuan.
