# Tracking Lokasi

Halaman **Tracking Lokasi** tersedia untuk akun yang sudah masuk. Admin, HRD, direktur, dan supervisor dapat melihat karyawan yang berada dalam cakupan akses mereka; karyawan hanya dapat melihat lokasinya sendiri.

## Berbagi lokasi live

Karyawan membuka halaman Tracking Lokasi, lalu memilih **Mulai berbagi lokasi** dan mengizinkan akses GPS pada browser. Lokasi dikirim berkala selama halaman tetap terbuka. Berbagi dapat dihentikan kapan saja dari tombol yang sama. Admin menerima pembaruan melalui polling berkala; status ditampilkan online bila pembaruan terakhir berumur kurang dari lima menit.

## Tujuan dan lokasi kustom

Admin dapat mencari alamat atau memilih titik pada peta; koordinat tujuan diambil otomatis dari titik tersebut, tanpa mengisi latitude/longitude secara manual. Admin dapat menyimpan nama lokasi kustom dan menetapkan beberapa tujuan untuk satu karyawan. Setiap tujuan dapat dikelola terpisah dengan nama, koordinat otomatis, ikon, dan catatan opsional. Menekan marker tujuan menampilkan nama, karyawan yang ditugaskan, dan catatan, dengan aksi edit/hapus. Edit koordinat dilakukan dengan memilih titik baru di peta. Ikon dan nama tujuan ditampilkan pada peta dan timeline riwayat. Tujuan dianggap dikunjungi pada tanggal yang dipilih jika titik GPS berada dalam radius geofence 100 m; waktu kunjungan mengikuti titik sampel pertama yang memenuhi radius, dan sumber simulasi tetap ditandai.

Lokasi kustom yang sudah disimpan dapat dipilih dari dropdown dan difilter dengan mengetik nama atau keterangannya. Pencarian alamat menampilkan saran otomatis setelah pengguna mengetik tanpa perlu menekan tombol Cari. Peta menyediakan tombol untuk kembali ke lokasi perangkat (dengan izin GPS) dan mode layar penuh. Refresh data maupun pemilihan titik tidak mengubah tingkat zoom; kontrol zoom manual tetap tersedia dan pencocokan batas peta dilakukan hanya saat pengguna menekan **Lihat semua**.

## Detail aktivitas dan riwayat harian

Admin memilih karyawan lalu menekan **Lihat Detail** untuk membuka popup riwayat GPS. Tanggal, bulan, dan tahun dapat dipilih terpisah, atau gunakan pintasan **Hari ini**. Peta menampilkan polyline dari titik tersimpan dengan penanda awal (hijau), lokasi kunjungan (kuning), titik terakhir pada riwayat (merah), serta lokasi live terbaru (ungu) yang terpisah dari jejak historis. Timeline atau penanda peta dapat dipilih untuk melihat koordinat, waktu sampel, dan detail kunjungan.

Jarak merupakan jumlah garis lurus antar-sampel GPS yang tersimpan; durasi aktivitas adalah selisih cap waktu titik pertama dan terakhir. Kunjungan diperkirakan dari titik berurutan yang berada dalam radius 100 m dan membentang minimal 5 menit. Waktu tiba/pergi adalah waktu sampel pertama/terakhir pada kelompok tersebut, bukan waktu presisi memasuki/meninggalkan bangunan. Nama tempat hanya dicocokkan dengan lokasi kustom tersimpan dalam radius 200 m; titik lain ditampilkan sebagai koordinat. Riwayat yang diminta dibatasi 5.000 titik per hari dan UI menyatakan bila hasil terpotong. Data simulasi tetap berlabel simulasi. Hari tanpa titik GPS menampilkan empty state.

## Simulasi

Panel **Simulasi lokasi** hanya tersedia untuk admin/atasan. Pilih karyawan lalu mulai simulasi untuk mengirim titik uji berkala. Data simulasi diberi label khusus di peta, aktivitas, detail, dan riwayat; simulasi tidak meminta atau menggunakan GPS perangkat karyawan. **Reset Simulasi** meminta konfirmasi lalu menghapus hanya titik GPS dengan penanda `is_simulated`; titik GPS asli, tujuan, lokasi kustom, akun, dan data master tidak dihapus.

## Sumber peta

- Peta dasar: OpenStreetMap, dengan atribusi pada peta.
- Pencarian dan alamat koordinat: Nominatim OpenStreetMap.
- Rute jalan: OSRM public routing service.
- Posisi perangkat: Geolocation API browser.

Layanan peta publik tidak memerlukan API key. Penggunaan pencarian, rute, dan tile tetap mengikuti kebijakan penggunaan serta ketersediaan layanan publik masing-masing.
