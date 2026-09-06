# Dashboard CV Maker

Menu mandiri Dashboard CV Maker tersedia di /admin/cv-maker-dashboard, endpoint /admin/cv-maker-dashboard/data, dengan izin cv_maker_dashboard. Halaman Compare tidak memuat dashboard maupun menjalankan agregasinya.

Seluruh agregasi dan prioritas dibatasi di server ke employees.area_kerja VDNI/VDNIP, kemudian dipersempit lagi oleh cakupan akses pengguna dan filter. Memilih semua perusahaan tetap hanya mencakup kedua perusahaan tersebut. Filter Status karyawan default Aktif, termasuk saat parameter tidak dikirim ke endpoint dan setelah Reset filter. Aktif berarti status_resign = AKTIF; Tidak aktif berarti status_resign selain AKTIF (misalnya resign/PHK/putus kontrak). Semua status mencakup keduanya. Status null tetap dikecualikan mengikuti query dasar yang sudah ada.

Dashboard membaca snapshot lokal tanpa mengakses API CV Maker. Muat ulang tidak menjalankan sinkronisasi. Snapshot diperbarui melalui mekanisme cv-maker:sync-progress yang sudah tersedia.

## Indikator

- Belum tersinkronisasi: snapshot tidak ada; status pengisian belum diketahui.
- Akun tidak ditemukan: snapshot tersedia tanpa user ID.
- Belum membuat profil: user ID tersedia, profile ID kosong.
- Dalam pengisian/lengkap: profile ID tersedia; mengikuti is_complete.
- Status review terpisah dari kelengkapan CV.
- Tahap tertahan: tahap pertama yang belum lengkap, bukan seluruh missing_steps.
- Departemen: sepuluh persentase kelengkapan terendah dalam filter aktif.
- Prioritas: maksimal delapan karyawan perlu reminder/konfirmasi. Tautan Detail tersedia hanya jika pengguna juga memiliki izin Compare.

## Deployment

Jalankan hanya migrasi penambahan izin berikut jika belum diterapkan:

```sh
php artisan migrate --path=database/migrations/2026_09_06_000001_append_cv_maker_dashboard_menu_to_roles.php --force
php artisan config:clear
php artisan view:clear
```

Migrasi menambahkan izin dashboard untuk role yang sudah secara eksplisit memiliki Compare, mempertahankan akses yang dibatasi admin, dan aman dijalankan ulang. Role dengan menu null mengikuti default konfigurasi. Tidak ada perubahan data karyawan. Rollback migrasi mempertahankan izin yang mungkin sudah disunting admin; pencabutan izin dapat dilakukan melalui pengaturan role.

## Pengujian

```sh
php vendor/bin/phpunit tests/Feature/CvMakerDashboardServiceTest.php
php vendor/bin/phpunit tests/Unit/CvMakerCompareServiceTest.php
```

Periksa menu baru dan menu Compare secara terpisah. Ubah filter perusahaan/departemen/status serta klik kartu dan grafik; pastikan seluruh panel konsisten. Pilihan seluruh perusahaan harus tetap hanya VDNI/VDNIP. Periksa role dengan scope terbatas, hasil kosong, koneksi gagal, reset filter, dan tampilan mobile. Pastikan halaman Compare tidak lagi menampilkan ringkasan dashboard.
