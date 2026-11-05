# Tracking Lokasi

Halaman **Tracking Lokasi** tersedia untuk akun yang sudah masuk. Admin, HRD, direktur, dan supervisor dapat melihat karyawan yang berada dalam cakupan akses mereka; karyawan hanya dapat melihat lokasinya sendiri.

## Berbagi lokasi live

Karyawan membuka halaman Tracking Lokasi, lalu memilih **Mulai berbagi lokasi** dan mengizinkan akses GPS pada browser. Lokasi dikirim berkala selama halaman tetap terbuka. Berbagi dapat dihentikan kapan saja dari tombol yang sama. Admin menerima pembaruan melalui polling berkala; status ditampilkan online bila pembaruan terakhir berumur kurang dari lima menit.

## Tujuan dan lokasi kustom

Admin dapat mencari alamat, memilih titik pada peta, menyimpan nama lokasi kustom, dan menetapkan tujuan untuk karyawan yang dipilih. Penetapan tujuan disimpan per karyawan. Garis riwayat menampilkan maksimal 300 titik lokasi terbaru.

Lokasi kustom yang sudah disimpan dapat dipilih dari dropdown dan difilter dengan mengetik nama atau keterangannya. Pencarian alamat menampilkan saran otomatis setelah pengguna mengetik tanpa perlu menekan tombol Cari. Peta menyediakan tombol untuk kembali ke lokasi perangkat (dengan izin GPS) dan mode layar penuh. Refresh data maupun pemilihan titik tidak mengubah tingkat zoom; kontrol zoom manual tetap tersedia dan pencocokan batas peta dilakukan hanya saat pengguna menekan **Lihat semua**.

## Simulasi

Panel **Simulasi lokasi** hanya tersedia untuk admin/atasan. Pilih karyawan lalu mulai simulasi untuk mengirim titik uji berkala. Data simulasi diberi label khusus di peta, aktivitas, detail, dan riwayat; simulasi tidak meminta atau menggunakan GPS perangkat karyawan.

## Sumber peta

- Peta dasar: OpenStreetMap, dengan atribusi pada peta.
- Pencarian dan alamat koordinat: Nominatim OpenStreetMap.
- Rute jalan: OSRM public routing service.
- Posisi perangkat: Geolocation API browser.

Layanan peta publik tidak memerlukan API key. Penggunaan pencarian, rute, dan tile tetap mengikuti kebijakan penggunaan serta ketersediaan layanan publik masing-masing.
