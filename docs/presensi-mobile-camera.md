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

## Perbaikan bingkai kuning/merah dan identitas — 13 November 2026

- Pengukuran arah wajah memakai garis mata dan hidung, tanpa garis rahang. Pada uji sintetis, rumus lama salah membaca wajah frontal sebagai menoleh saat kamera sedikit miring; hasil juga berubah jika posisi landmark rahang berubah. Rumus baru menjaga hasil tetap sama pada kedua kondisi tersebut. Ini belum membuktikan tingkat keberhasilan pengguna berkerudung pada kamera nyata.
- Deteksi yang kosong atau kurang jelas dicoba ulang pada input 512 setelah 320, dengan ambang kualitas/matching yang sama. Foto referensi yang tetap kurang jelas memerlukan pemeriksaan HR dan tidak dipakai untuk menyetujui identitas. Deteksi lebih dari satu wajah langsung diblokir.
- Bingkai kuning menjelaskan apakah wajah kurang jelas, terlalu jauh/dekat, miring, atau belum frontal. Bingkai merah karena matching meminta pemeriksaan foto referensi bila masalah menetap. Kerudung boleh dipakai selama area mata, hidung, dan mulut terlihat.
- Selfie yang akan disimpan diperiksa lagi terhadap referensi akun. Hasil matching frame sebelumnya tidak dipakai untuk menyetujui foto lain. Pada mode aktif, pengguna kembali menghadap tengah setelah gerakan kiri/kanan agar selfie akhir tetap frontal.
- Bingkai hijau menandakan bukti kamera siap; keputusan identitas dan liveness tetap dari server. Normalisasi respons aktif kini membutuhkan keputusan wajah eksplisit, confidence dan skor liveness yang memenuhi ambang; status umum `verified` tidak dapat mengalahkan hasil wajah negatif. Indikasi layar/foto tidak boleh menjadi terverifikasi meski provider mengirim status yang bertentangan. Hasil meragukan tetap mengikuti review HR.

Deploy `resources/views/user/presensi/index.blade.php` dan `app/Services/Presensi/ServerSideFaceVerificationService.php`, jalankan `php artisan view:clear`, lalu `php artisan queue:restart` bila menggunakan worker yang menetap. Tidak ada migrasi, paket baru, atau perubahan ambang. Data presensi dan foto referensi lama tidak diubah otomatis.

Verifikasi kode: tes JavaScript memakai wajah/landmark sintetis dan respons detektor simulasi; tes Laravel memakai data sintetis/SQLite. Uji perangkat perlu membandingkan pemilik akun berkerudung dengan orang lain menggunakan akun yang sama, retake/pindah aplikasi, referensi buram, beberapa wajah, serta hasil akhir di riwayat setelah worker berjalan. Jangan menyimpulkan identitas lolos hanya dari warna hijau. Ukur juga waktu deteksi ulang pada HP yang bermasalah. Database lokal tidak terhubung saat diagnosis, sehingga foto referensi dan hasil presensi akun yang dilaporkan belum diperiksa.

### Lanjutan: detektor belum membaca wajah berkerudung

Untuk pesan “Wajah belum terdeteksi / belum terbaca jelas”, menaikkan input Tiny saja belum menambahkan kemampuan detektor lain. Pembacaan landmark sekarang memakai model penuh; deteksi mencoba SSD Mobilenet V1 bila Tiny pada 320 dan 512 tetap kosong atau lemah. SSD hanya dimuat saat dibutuhkan dan dipakai kembali agar tiap frame tidak terus menjalankan tiga percobaan. Ambang deteksi/kecocokan identitas serta keputusan akhir server tetap berlaku. Beberapa wajah diblokir, dan kegagalan memuat model menampilkan instruksi mencoba kembali tanpa meloloskan capture.

Sesuai pilihan pengguna, alur tetap pasif tanpa instruksi menoleh. UI mempersiapkan bukti kamera dan menunjukkan kesiapan capture; skor deteksi atau kestabilan wajah tidak dinyatakan sebagai hasil anti-spoofing yang sah. Model server tetap menentukan liveness pasif dan identitas.

Tambahan deploy pada perbaikan sebelumnya:

- `resources/views/user/presensi/index.blade.php` versi terbaru.
- `public/vendor/face-api/weights/face_landmark_68_model-weights_manifest.json` dan `face_landmark_68_model-shard1`.
- `public/vendor/face-api/weights/ssd_mobilenetv1_model-weights_manifest.json`, `ssd_mobilenetv1_model-shard1`, dan `ssd_mobilenetv1_model-shard2`.
- `public/vendor/face-api/LICENSE` dan catatan sumber/hash pada `MODELS.md`.

Tambahan aset sekitar 6 MB. Setelah mengunggah semua berkas, jalankan `php artisan view:clear`, lalu buka kembali halaman presensi. Aplikasi harus bisa mengambil manifest beserta shard pada public path yang benar; jika hanya Blade yang diunggah, model baru gagal dimuat. Tidak diperlukan perubahan `.env`, paket PHP/Node, atau migrasi database. Perlu menguji kembali pengguna berkerudung dan wajah orang lain pada HP sebenarnya; kualitas biometrik dan biaya CPU/browser belum terukur pada perangkat tersebut.
