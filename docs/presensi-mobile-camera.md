# Kamera presensi pada HP — 29 Oktober 2026

Keluhan: kamera patah-patah pada APK Android Samsung A54, juga terjadi pada browser Chrome/Safari, dan sebagian komponen kadang tidak terlihat.

## Perubahan

- Analisis wajah berikutnya dijadwalkan 320 ms setelah analisis sebelumnya selesai. Detector, landmark, dan recognition tetap dipakai, dengan ambang serta ukuran input yang sama.
- Kamera meminta 15 FPS sebagai preferensi perangkat. Resolusi permintaan dan kualitas selfie/bukti tetap sama; browser dapat memilih FPS yang didukung perangkat.
- Hanya satu permintaan akses kamera berlangsung. Hasil deteksi, stream, atau enkode selfie dari sesi lama dibuang setelah kamera dihentikan/diulang.
- Saat aplikasi/halaman disembunyikan, kamera aktif dijeda dan bukti pengambilan yang belum selesai direset. Gunakan tombol Aktifkan Ulang Kamera saat kembali. Selfie yang sudah selesai tidak dibuang oleh jeda ini.
- Efek blur dan bayangan besar di atas video dikurangi pada layar kecil. Peta mini di kamera dihapus; panel kamera menampilkan GPS live, jarak, akurasi, dan nama posisi presensi dari pembacaan terbaru. Peta utama dan pemeriksaan GPS tetap berjalan.
- Leaflet 1.9.4 disimpan lokal. Hash JS/CSS sama dengan SRI CDN sebelumnya, termasuk lisensi serta seluruh gambar runtime. Kegagalan library peta memiliki pesan cadangan dan tidak menghentikan persiapan kamera/GPS.
- Pesan placeholder kamera diperjelas dan tidak tertutup panduan sebelum video aktif.
- Panel kamera mengikuti palet template: bingkai putih, latar abu-abu gelap polos, dan aksen warna utama sistem. Gradasi biru serta pola kotak dekoratif dihapus.

## Deploy

Salin file berikut sesuai struktur aplikasi:

1. `resources/views/user/presensi/index.blade.php`
2. `resources/lang/zh_CN.json`
3. `public/assets/css/user-presensi.css`
4. Seluruh folder `public/vendor/leaflet/` beserta lima file dalam `images/` dan `LICENSE`.

Folder `public/` pada paket menunjukkan public path Laravel. Jika hosting memakai public path terpisah, tempatkan aset pada public path yang benar. Semua aset Leaflet harus ikut diunggah.

Jika `.env` menetapkan `ASSET_VERSION`, naikkan nilainya, misalnya `20261029-camera-3`, lalu perbarui cache konfigurasi. File Blade memuat aset dengan `versioned_asset()`; jika versi tidak diatur, helper memakai waktu perubahan file.

Jalankan pada Terminal cPanel/SSH untuk proyek ini:

```sh
/usr/local/bin/php /home/vdnisite/prod-hris/artisan view:clear
/usr/local/bin/php /home/vdnisite/prod-hris/artisan config:cache
```

Tutup dan buka kembali halaman presensi agar HTML dan aset baru dimuat. Perubahan halaman berlaku saat APK membuka URL HRIS yang diperbarui; sumber APK tidak tersedia pada workspace ini.

## Verifikasi

- 21 tes JavaScript mencakup pengambilan tiga frame, timer serial, jeda render, perubahan sesi, stream/hasil selfie terlambat, pause, informasi GPS tanpa peta mini, fallback peta utama, dan integritas aset lokal.
- 14 tes Laravel terkait presensi, lokalisasi, dan versi aset lolos. Blade serta tiga script inline hasil render lolos pemeriksaan sintaks.
- Pratinjau statis 390 × 844 px dan 900 × 844 px diperiksa tanpa overflow horizontal. Latar netral, teks GPS, dan pesan kamera terbaca. Kamera, GPS, data karyawan, dan API biometrik tidak dipakai pada pratinjau tersebut.
- FPS, penggunaan CPU/GPU, kompatibilitas WebView, dan kualitas biometrik belum diukur pada perangkat nyata.

Uji Samsung A54 dan perangkat Safari setelah deploy: izin kamera, pengambilan frontal, retake berulang, pindah aplikasi lalu kembali, GPS, hasil API, serta keadaan library peta tidak tersedia. Catat apakah masalah terjadi saat memuat model, sepanjang video, atau hanya ketika selfie disimpan. Jika tetap lambat, pengukuran browser/perangkat diperlukan sebelum menentukan perubahan pada APK.
