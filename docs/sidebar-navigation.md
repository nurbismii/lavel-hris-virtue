# Navigasi sidebar

Sidebar dikelompokkan berdasarkan pekerjaan. Hak akses, route, dan perhitungan
antrean tetap memakai implementasi yang ada. Kelompok tanpa tautan yang diizinkan
tidak ditampilkan. Hanya satu kelompok terbuka pada satu waktu; kelompok halaman
aktif terbuka saat halaman dimuat.

- Akses pribadi: Beranda Saya, Presensi Saya, Pengajuan Saya, Slip Gaji Saya, Kontrak Saya.
- Perlu Saya Setujui: persetujuan HOD, HR, dan delegasi, tetap dibedakan di dalam kelompok.
- Karyawan: data, CV, perubahan posisi, struktur organisasi, pelanggaran, pengunduran diri.
- Presensi & Jadwal: presensi, pemeriksaan, roster, shift, jadwal, libur, lembur, saldo cuti, penutupan periode.
- Kontrak & Gaji: kontrak, template, adendum, tanda tangan, perpanjangan, slip dan pembaruan gaji.
- Laporan & Pemantauan: monitor terpusat, ringkasan CV, batas waktu persetujuan, distribusi wilayah.
- Pengaturan: akun, perusahaan, lokasi presensi, peran, riwayat aktivitas/impor, Exit Portal.

`resources/views/partials/sidebar.blade.php` mempertahankan pemeriksaan akses setiap
tautan. `sidebar-group.blade.php` hanya menangani tampilan kelompok. Gaya berada di
`public/assets/css/custom.css`; accordion menggunakan Bootstrap yang sudah tersedia.
Label memakai `resources/lang/id/navigation.php` dan `resources/lang/zh_CN/navigation.php`.

## Verifikasi

```sh
php vendor/bin/phpunit tests/Feature/SidebarNavigationTest.php tests/Feature/HrDefaultMenuPermissionTest.php
```

Pemeriksaan manual setelah deployment:

1. Masuk sebagai Staff, Staff Roster, HOD, HR, dan akun dengan izin terbatas. Pastikan menu sesuai akses.
2. Buka pengajuan pribadi, persetujuan HOD/HR, halaman shift, template kontrak, dan pembaruan gaji. Pastikan tautan aktif serta kelompoknya tepat.
3. Periksa indikator jumlah antrean, termasuk akun penerima delegasi.
4. Buka kelompok dengan klik, Enter, dan Spasi. Kelompok sebelumnya harus tertutup.
5. Pada desktop, ringkas dan buka kembali sidebar. Pastikan menu tetap bisa dijangkau.
6. Pada ponsel, buka/tutup menu, gulir kelompok yang panjang, lalu tekan Escape jika memakai keyboard. Teks harus terbaca tanpa gulir horizontal.
7. Ganti bahasa Indonesia/Mandarin; pastikan label tidak menampilkan kunci terjemahan.

Tidak ada migrasi atau dependensi baru. Jika deployment memakai cache Blade,
jalankan `php artisan view:clear`. Stylesheet sudah memakai versi aset dari layout.
