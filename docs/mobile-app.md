# Aplikasi V-People Android/iOS dan akses mobile

## Status hasil

- Android: proyek native Java, package `com.vdni.vpeople`, versi 1.0.10 (kode 10), Android 9/API 28 ke atas. Presensi GPS memerlukan hardware key attestation yang lolos verifikasi. APK release bertanda tangan tersedia di `public/downloads/vpeople-android.apk` dan berkas versi `public/downloads/vpeople-android-1.0.10.apk`.
- iOS: proyek UIKit/WKWebView di `mobile/ios/VPeople.xcodeproj`, iOS 15 ke atas, iPhone/iPad. Source disiapkan; **belum dikompilasi, ditandatangani, atau diterbitkan**. Memerlukan Mac, Xcode, akun Apple Developer, bundle ID yang tersedia, dan distribusi TestFlight/App Store. Tidak ada tautan iOS palsu.
- Aplikasi menggunakan fitur HRIS yang sudah ada melalui WebView. Memerlukan internet; tidak menyimpan salinan offline data karyawan.
- Domain awal `https://hris.vdnisite.com` mengikuti konfigurasi aplikasi V-People sebelumnya. Pastikan domain ini benar untuk lingkungan tujuan sebelum membagikan APK.
- Android memakai package baru, sehingga tidak menggantikan APK lama `com.example.v_people`. Pengguna yang memasang keduanya mungkin melihat pemilih aplikasi saat membuka deep link. Akun dan data HRIS tetap sama; sesi login aplikasi terpisah.

## Alur web

1. Browser Android yang dikenali membuka root/login/area HRIS diarahkan ke `/download-app`. Untuk sementara, browser iPhone/iPad dapat login tanpa memasang aplikasi.
2. Pada perangkat yang diwajibkan menggunakan aplikasi, POST login password, login passkey, registrasi yang diaktifkan, serta akses area terproteksi diblokir oleh middleware. JSON mendapat HTTP 403 dengan `code=mobile_app_required` dan tautan unduh. Guest pada route yang mendahulukan auth dapat mendapat 401 atau diarahkan ke login.
3. Laptop/desktop dan WebView dengan marker `VPEOPLE_APP` dapat memakai login normal. Marker tidak memberi akses akun; password, CSRF, session, role, dan permission tetap diperlukan.
4. Halaman unduh dan pemulihan password tetap tersedia tanpa aplikasi. Pemulihan password tidak otomatis login.
5. Tidak ada logout paksa hanya karena menerima permintaan mobile; data sesi tidak dihapus.
6. Browser iPad mode desktop juga dikecualikan oleh skrip pengalihan. Setelah aplikasi iOS siap, pengalihan dapat diaktifkan melalui `MOBILE_APP_IOS_REQUIRED=true`.

Deteksi browser untuk login memakai user-agent/client hints. Presensi GPS memiliki pemeriksaan tambahan berupa hardware key attestation dan tanda tangan pembacaan GPS. Server menolak pencatatan GPS dan absen tanpa bukti tersebut, termasuk permintaan yang memakai identitas browser desktop.

## Konfigurasi Laravel

```dotenv
MOBILE_APP_REQUIRED=true
MOBILE_APP_IOS_REQUIRED=false
MOBILE_APP_ANDROID_DOWNLOAD_URL=
MOBILE_APP_IOS_DOWNLOAD_URL=
MOBILE_APP_MINIMUM_VERSION_CODE=2
MOBILE_APP_LATEST_VERSION_CODE=10
MOBILE_APP_LATEST_VERSION_NAME=1.0.10
MOBILE_APP_GPS_SIGNER_SHA256=006b134a51a3f84db456c3a3ff4b9411c143055f4de9a33a5978f643992af576
MOBILE_APP_IOS_MINIMUM_VERSION_CODE=1
MOBILE_APP_IOS_LATEST_VERSION_CODE=1
MOBILE_APP_IOS_LATEST_VERSION_NAME=1.0.0
```

- Kosongkan tautan Android agar menggunakan `public/downloads/vpeople-android.apk` yang benar-benar ada. Tautan legacy `MOBILE_APP_DOWNLOAD_URL` masih didukung dan didahulukan sebelum fallback berkas lokal; kosongkan bila masih menunjuk APK lama.
- Tautan khusus platform harus HTTPS. Android dapat memakai URL APK perusahaan/Play Store. iOS memakai tautan App Store/TestFlight yang sudah aktif.
- Bila berkas/tautan tidak tersedia, halaman menampilkan status belum tersedia dan arahan ke HR/desktop.
- `MOBILE_APP_REQUIRED=true` berlaku juga pada local/staging. `MOBILE_APP_IOS_REQUIRED=false` membuka login web iPhone/iPad selama aplikasi iOS belum tersedia. Pengecualian login ini tidak menggantikan syarat bukti GPS pada endpoint presensi.
- Untuk rollout yang belum siap, `MOBILE_APP_REQUIRED=false` membuka kembali login web mobile tanpa menghapus data.
- Endpoint `GET /api/mobile/app-version?platform=android|ios&version_code=N` memakai versi masing-masing platform. Kedua klien memeriksa minimum versi sebelum membuka login; kegagalan koneksi menampilkan Coba lagi/Muat ulang.
- Pertahankan minimum Android selama rollout. Naikkan hanya setelah APK terbaru tersedia untuk semua pengguna. Versi iOS memiliki minimum tersendiri.
- Verifikasi sertifikat memakai `phpseclib/phpseclib` seri 3 dari Composer. Upload vendor yang mengikuti `composer.lock` atau jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` pada lingkungan deployment. Fitur ini memakai cache persisten yang mendukung lock dan atomic add, seperti file atau Redis. Pastikan cache dibagi antar-instance bila memakai beberapa server. Tidak ada migration atau worker tambahan. Runtime repository saat pengujian adalah Laravel 13/PHP 8.5.

## Build Android

Buka `mobile/android` dengan Android Studio. Toolchain yang dipakai: Gradle 8.9, Android Gradle Plugin 8.7.2, SDK 35, JDK bawaan Android Studio (JDK 17+). Tidak ada dependency runtime aplikasi tambahan.

```powershell
cd mobile/android
.\gradlew.bat :app:testReleaseUnitTest :app:lintRelease :app:assembleRelease
```

Untuk server staging HTTPS yang berbeda, tambahkan `-PhrisUrl=https://staging.example.com`. Nilainya harus origin HTTPS tanpa path, query, fragment, atau credential. Jangan memakai APK staging untuk distribusi production.

APK hasil build: `app/build/outputs/apk/release/app-release.apk`. Build release tanpa `keystore.properties` menghasilkan APK unsigned; gunakan Android Studio **Generate Signed Bundle / APK** atau pulihkan signing material resmi terlebih dahulu.

Kunci release lokal yang digunakan untuk APK awal:

- `mobile/android/signing/vpeople-release.p12`
- `mobile/android/keystore.properties`

**Cadangkan keduanya ke penyimpanan perusahaan yang terbatas aksesnya.** Jangan commit, kirim lewat chat, atau unggah ke hosting. Kehilangan kunci akan menghalangi pembaruan instalasi yang sudah ada. Kedua berkas diabaikan Git; seluruh direktori `mobile` juga ditolak oleh Apache melalui `.htaccess` sebagai proteksi tambahan jika root repository keliru terekspos. Hosting tetap harus memakai document root `public`.

APK publik diabaikan Git karena merupakan build artifact. Saat deploy manual, salin APK terverifikasi secara terpisah ke `public/downloads/vpeople-android.apk`; jangan mengunggah folder source/build/signing.

Jika runtime Windows memiliki temp path terlalu panjang dan Java gagal dengan `Unable to establish loopback connection`, set `JAVA_TOOL_OPTIONS=-Djdk.net.unixdomain.tmpdir=<folder-temp-pendek>` hanya pada shell build agar properti berlaku juga pada daemon/compiler Java. Gunakan folder yang memang dimiliki untuk pekerjaan ini, tanpa mengubah konfigurasi Java global.

## Build iOS

1. Pada Mac dengan Xcode yang mendukung distribusi Apple saat ini, buka `mobile/ios/VPeople.xcodeproj`.
2. Pilih target VPeople, isi Development Team dan bundle identifier perusahaan. Sesuaikan `HRISBaseURL` di `VPeople/Info.plist` bila domain berbeda.
3. Build ke simulator/iPhone uji. Verifikasi kamera, lokasi, unggah, unduh, cookie sesi, dan navigasi.
4. Jalankan pemeriksaan origin pada Mac:

   ```sh
   swiftc VPeople/OriginPolicy.swift Tests/OriginPolicyCheck.swift -o /tmp/vpeople-origin-check
   /tmp/vpeople-origin-check
   ```

5. Product → Archive, lalu distribusikan melalui App Store Connect/TestFlight. Lengkapi informasi privasi, screenshot, dukungan, serta kebutuhan review Apple. Persetujuan App Store tidak dapat dijamin; aplikasi harus memenuhi ketentuan Apple, termasuk kecukupan fungsi aplikasi.
6. Setelah tautan distribusi aktif, isi `MOBILE_APP_IOS_DOWNLOAD_URL` dan perbarui cache config.

iOS tidak dapat dipasang pengguna umum dengan sekadar mengunggah file IPA tanpa signing/provisioning/distribusi yang sesuai. Dokumentasi resmi: [metode distribusi Apple](https://help.apple.com/xcode/mac/current/en.lproj/dev31de635e5.html).

## Perilaku aplikasi dan data

- Navigasi internal, permission kamera, dan unduhan dibatasi ke **origin HTTPS yang sama** (host/port harus cocok). URL eksternal HTTPS, telepon, dan email dibuka lewat aplikasi sistem; tidak memperoleh cookie HRIS.
- Validasi TLS tetap aktif. HTTP, mixed content, file URL, URL dengan kredensial, dan intent asing tidak dimuat di WebView.
- Tidak ada JavaScript bridge atau token rahasia bersama yang ditanam dalam aplikasi.
- Kamera presensi menggunakan WebRTC dan izin OS sesuai permintaan. Lokasi Android memerlukan izin akurat; penolakan memberi pesan dan dapat dipulihkan lewat Pengaturan.
- Unggah Android memakai pemilih dokumen/foto sistem; pengambilan foto presensi menggunakan kamera web. iOS memakai pemilih bawaan WKWebView.
- Unduhan Android disalin ke cache privat (maksimum 100 MiB, timeout 20 detik baca), lalu pengguna memilih lokasi penyimpanan. Redirect unduhan ke origin lain ditolak sebelum cookie dikirim. Berkas cache dihapus setelah selesai/batal; unduhan terputus dibersihkan saat pembukaan berikutnya.
- Unduhan iOS memakai WKDownload dan menu Simpan ke File/Bagikan. Berkas sementara dibersihkan setelah selesai/batal atau saat peluncuran berikutnya.
- Backup cloud dan transfer data Android dibatasi agar sesi/dokumen aplikasi tidak ikut backup.
- Aplikasi menggunakan satu header dengan identitas V-People, bahasa, dan lonceng notifikasi. Lonceng menampilkan lima notifikasi terbaru, jumlah belum dibaca, Refresh, serta tautan kotak masuk. Clear menghapus seluruh notifikasi milik akun tersebut (termasuk yang belum dibaca) setelah konfirmasi. Panel diperbarui saat dibuka, melalui polling 60 detik, dan melalui realtime bila broadcasting dikonfigurasi. Ini adalah notifikasi di dalam aplikasi; push Android saat aplikasi tertutup belum tersedia.
- Tab Layanan di bawah membuka daftar fitur sesuai hak akses. Ruang bawah konten mengikuti tinggi tab, termasuk perubahan ukuran teks dan safe area. Tombol kembali Android pada 1.0.10 langsung menuju Beranda; dua tekanan dalam dua detik di Beranda menutup aplikasi. Gestur geser iOS tetap mengikuti riwayat halaman. Tombol Coba lagi tampil ketika diperlukan.
- Login password adalah alur yang didukung sebagai dasar. Passkey dalam WebView belum diverifikasi dan dapat memerlukan integrasi platform tambahan; jangan menjanjikan Face ID/Windows Hello di aplikasi hanya karena web mendukung passkey.

## Deploy cPanel

1. Upload perubahan Laravel/Blade/aset. Document root tetap folder `public`. Perapian UI/notifikasi memerlukan `public/assets/css/native-app.css`, `public/assets/js/app-layout.js`, hasil bundle `public/js/app.js` beserta map/license, terjemahan, controller notifikasi, dan Blade terbaru. Perubahan ini tampil pada APK 1.0.7 setelah aset web diperbarui; tidak memerlukan APK baru.
2. Upload APK rilis ke `public/downloads`, termasuk `.htaccess` untuk MIME/attachment. Jangan upload signing material atau folder `mobile`.
3. Atur variabel di atas dan pastikan domain memakai HTTPS serta `APP_DEBUG=false`. Jika `.env` menetapkan `ASSET_VERSION`, naikkan misalnya ke `20261103-ui-2` agar browser memuat aset terbaru. Deploy route, view, aset, `resources/security`, vendor, dan APK bersama. Pengguna 1.0.5–1.0.9 dapat memasang APK 1.0.10 sebagai pembaruan memakai sertifikat yang sama. Atur `MOBILE_APP_LATEST_VERSION_CODE=10` dan `MOBILE_APP_LATEST_VERSION_NAME=1.0.10` pada `.env` hosting; minimum versi tidak perlu dinaikkan untuk perubahan navigasi ini.
4. Jalankan sesuai PHP production:

   ```sh
   php artisan config:cache
   php artisan route:clear
   php artisan view:clear
   ```

5. Coba unduh dari ponsel. Android dapat meminta pengguna mengizinkan pemasangan dari browser sumber unduhan. Distribusi Play Store menjadi alternatif bila tersedia.
6. Hindari caching bersama/CDN pada login/halaman pengguna. Respons blokir mobile dan halaman unduh menggunakan `Cache-Control: no-store, private`.

## Verifikasi

Otomatis:

```sh
php artisan test --compact --filter="NativeGpsProofTest|UserPresensiLocationSafetyTest|MobileApp|PasskeyAuthenticationTest|EmailUrlTest|SidebarNavigationTest|UserNotificationDeletionTest"
node tests/Frontend/native-gps.test.cjs
node tests/Frontend/mobile-app.test.cjs
node tests/Frontend/notifications.test.cjs
```

Android: unit test origin, build debug/release, lint release, serta `apksigner verify --verbose`. Peringatan lint JavaScript ditinjau: fitur formulir/kamera web memerlukan JS, dengan pembatasan origin dan tanpa native JS bridge.

Halaman unduh dicek melalui browser pada lebar 390px: tidak ada overflow horizontal, tombol memiliki tinggi sekitar 49px, dan tautan login desktop tetap mencapai formulir login. Struktur plist iOS divalidasi; build Xcode belum dilakukan.

Uji perangkat fisik/staging **masih diperlukan**:

- Android Chrome, iPhone Safari, iPad mode desktop, laptop Windows/macOS; root, login, POST login, passkey, dan sesi lama.
- Akun uji: login/logout, ingat saya, password salah, sesi kedaluwarsa; jangan gunakan akun karyawan produksi untuk tes otomatis.
- Lonceng menampilkan daftar dan badge; Refresh mencoba kembali setelah koneksi gagal. Batal pada Clear tidak menghapus apa pun; konfirmasi Clear menghapus hanya notifikasi akun uji tersebut, mengosongkan daftar dan badge. Periksa bagian akhir dashboard dan navigasi bawah pada layar kecil serta teks diperbesar.
- Izin kamera/lokasi diberikan dan ditolak, lokasi tidak akurat, foto presensi, unggah PDF/foto, unduh dokumen, pembatalan penyimpanan.
- Jaringan mati/lambat, TLS tidak valid, HTTP 500, versi di bawah minimum, deep link saat aplikasi terpasang/tidak terpasang.
- iOS belum tersedia harus menampilkan informasi yang benar tanpa tautan unduh kosong.
- Jika APK lama dan baru terpasang bersamaan, pastikan pengguna memilih aplikasi baru.

Tidak ada deployment ke hosting, pemasangan pada perangkat fisik, atau publikasi store yang dilakukan otomatis.

## Tombol kembali Android pada 1.0.10

Alur: Slip Gaji/menu lain → tombol kembali → Beranda → kembali sekali (petunjuk muncul) → kembali lagi dalam dua detik → aplikasi ditutup. Tekanan untuk menuju Beranda tidak dihitung sebagai tekanan pertama untuk keluar. Berpindah halaman, berpindah aplikasi, atau melewati dua detik membatalkan hitungan. Tombol dan gestur Back memakai penanganan yang sama, termasuk callback Android 13+. Tidak menelusuri riwayat WebView, tidak logout, dan tidak mematikan proses secara paksa.

Deploy APK dan `resources/views/layouts/app.blade.php`, lalu jalankan `php artisan view:clear` di hosting. Layout memasok URL Beranda sesuai `preferredHomeRouteName()` melalui metadata `vpeople-home-url`; aplikasi hanya menerima tujuan HTTPS dari origin HRIS. Ini mendukung Beranda karyawan, admin, dan fallback akun tanpa akses dashboard. Pada hosting lama tanpa metadata, aplikasi memakai dashboard yang sudah dikunjungi atau `/login` yang mengarahkan akun aktif ke halaman awalnya. Halaman login dan pemeriksaan versi juga dapat ditutup dengan dua tekanan.

Unit test `BackNavigationTest` mencakup navigasi menu, tujuan sesuai peran, batas dua detik, reset, pergantian akun, metadata dari origin lain, dan kompatibilitas hosting lama. Uji manual: kunjungi beberapa menu sebelum menekan Back; pastikan langsung Beranda. Uji dua tekanan cepat, tekanan kedua setelah lebih dari dua detik, berpindah aplikasi setelah tekanan pertama, sesi berakhir, dan Back saat keyboard terbuka. Android 13+ memerlukan verifikasi gestur pada perangkat yang mendukungnya.

Verifikasi lokal 1.0.10: sembilan unit test Android dan 12 tes Laravel (150 assertion) lolos; lint release tidak memiliki error; APK release bertanda tangan resmi yang sama terverifikasi. Instrumentation Android 9/API 28 memeriksa bahwa tekanan pertama tidak menutup Activity dan tekanan kedua menutup task, sekaligus mempertahankan pemeriksaan insets. Navigasi menu/peran diuji pada unit/fitur dengan URL sintetis, bukan akun karyawan produksi. Belum ada pengujian langsung pada HP atau runtime gestur Android 13+.

## Ikon aplikasi pada 1.0.9

Logo asli dari pengguna disimpan tanpa perubahan di `mobile/branding/vpeople-logo.png`. Android memakai ikon adaptif berlatar putih dan foreground 108 dp untuk seluruh perangkat yang didukung (Android 9+). Seluruh logo, termasuk tulisan PEOPLE, ditempatkan di area aman agar tidak terpotong ketika launcher memakai mask bulat atau persegi membulat; lihat [panduan ikon adaptif Android](https://developer.android.com/develop/ui/compose/system/icon_design_adaptive). Manifest memakai ikon yang sama untuk `icon` dan `roundIcon`. Alpha logo yang sama dipakai untuk varian bertema pada launcher yang mendukungnya.

Aset iOS `AppIcon.png` juga diperbarui menjadi PNG 1024 x 1024 RGB berlatar putih. Source iOS belum dibangun dengan Xcode. Perubahan ikon launcher memerlukan pemasangan APK 1.0.9 sebagai pembaruan aplikasi, terpisah dari pembaruan aset web di hosting.

## Status bar dan jarak layar Android pada 1.0.8

Versi ini memerlukan pembaruan APK. Pengaturan jendela membuat konten memenuhi layar, lalu root layout menambahkan ruang untuk bar sistem, cutout, dan keyboard satu kali. Insets yang sudah ditangani dikirim sebagai nol ke WebView, sehingga `safe-area-inset-bottom` pada CSS tidak menambahkan ruang yang sama lagi. Saat keyboard ditutup, WebView tetap menerima pembaruan ukuran nol agar tidak menyisakan ruang kosong. Ikon jam/sinyal/baterai dan navigasi sistem menggunakan warna gelap di atas latar putih; status bar tetap terlihat.

Implementasi mengikuti [panduan Android untuk insets WebView](https://developer.android.com/develop/ui/views/layout/webapps/understand-window-insets) dan [pengaturan edge-to-edge](https://developer.android.com/develop/ui/views/layout/edge-to-edge-manually). Android 9/10 memakai API inset legacy; Android 11+ memakai API bertipe, termasuk resize keyboard. Tidak menambahkan dependensi runtime.

Tes regresi di perangkat/emulator uji yang terisolasi:

```powershell
.\gradlew.bat :app:connectedDebugAndroidTest
```

`WindowInsetsCheck` memeriksa warna ikon, status bar tidak fullscreen, root layout tidak diberi offset kedua, insets tombol/gestur, pembukaan/penutupan keyboard, insets sisi pada landscape, dan cutout pada Android 11+. Tes tidak memerlukan akun karyawan. Uji ulang pada Samsung dengan navigasi tiga tombol dan gestur, keyboard terbuka/tertutup, portrait/landscape, serta ukuran teks diperbesar sebelum rollout.

Verifikasi rilis lokal 1.0.8: dua unit test Android, lint release tanpa error, APK release, tanda tangan v2 dengan sertifikat resmi yang sama, 21 tes Laravel (137 assertion), dan instrumentation pada emulator Android 9/API 28 lolos. Instrumentation memvariasikan ukuran inset secara sintetis. Jalur Android 11+ belum diuji runtime: image API 34 yang tersedia adalah Android XR dan gagal boot dengan renderer emulator yang tersedia. Pengujian langsung pada HP Samsung dan Android 15 masih diperlukan; tidak ada deployment hosting pada perbaikan ini.

## Perlindungan Fake GPS pada 1.0.7

Sesuai pilihan penerapan, perangkat Android didaftarkan otomatis melalui hardware key attestation. Pengguna memerlukan APK resmi 1.0.7+, Android dengan rantai attestation Google yang valid, keystore TEE/StrongBox, dan verified boot berstatus terkunci/terverifikasi. Perangkat yang tidak memenuhi syarat ditolak untuk presensi GPS. Pengguna diarahkan menghubungi HR dan menggunakan proses koreksi presensi yang sudah tersedia. Penolakan tidak otomatis mencatat karyawan sebagai pelaku kecurangan.

Presensi mandiri dari desktop, APK lama, dan source iOS saat ini tidak membawa bukti Android yang diwajibkan. Halaman tetap menampilkan riwayat dan petunjuk menggunakan Android yang didukung. Login desktop dan layanan HR lainnya tetap tersedia. iOS memerlukan implementasi serta pengujian App Attest tersendiri sebelum dapat melakukan presensi GPS.

Alur pemeriksaan:

1. Server menerbitkan nonce 45 detik, terikat pada akun, sesi, ID tantangan presensi, dan jenis tindakan.
2. APK mengambil sampel baru melalui GPS Android. Lokasi simulasi (`isMock` pada Android 12+ atau `isFromMockProvider` pada versi sebelumnya), sampel lama, koordinat tidak akurat, dan izin yang ditolak tidak menghasilkan bukti yang dapat dikirim.
3. Perangkat menandatangani koordinat, akurasi, kecepatan, serta nonce menggunakan kunci EC P-256 di Android Keystore. Pendaftaran pertama pada sesi memakai attestation challenge dari server.
4. Server memeriksa rantai X.509, masa berlaku, root Google yang dibundel, status pencabutan, keamanan hardware, verified boot, package `com.vdni.vpeople`, kode versi minimal 7, serta SHA-256 sertifikat penandatangan APK.
5. Server memeriksa tanda tangan dan menandai nonce sekali pakai secara atomik. Koordinat yang ditandatangani menggantikan koordinat dari form sebelum validasi radius dan jejak lokasi.
6. Pemeriksaan diterapkan pada `POST /api/gps-log` dan seluruh jenis `POST /absen/{type}`. Mengganti user-agent atau mengirim `mocked=false` tidak memenuhi pemeriksaan.
7. UI berhenti mengulang pemeriksaan setelah kegagalan. Pengguna mendapat alasan dan tautan Ulangi pemeriksaan.

Kunci perangkat tersimpan pada Android Keystore. Server menyimpan kunci publik per sesi di cache hingga 24 jam, dibatasi masa berlaku sertifikat. Private key tidak dikirim ke server. Pembacaan lokasi dilakukan saat pemeriksaan presensi di foreground.

Server harus dapat mengakses HTTPS `https://android.googleapis.com/attestation/status` dengan validasi TLS aktif. Daftar pencabutan disimpan maksimum satu jam dengan memperhitungkan header CDN Age dan Cache-Control. Ketika daftar tidak tersedia dan cache tidak valid, pemeriksaan ditolak dengan pesan layanan belum tersedia. Server menunggu 15 detik sebelum mencoba layanan itu kembali untuk membatasi beban saat gangguan. Respons Google diperiksa dari lingkungan PHP lokal dan menghasilkan HTTP 200.

Root publik dibundel dalam `resources/security/android-attestation-roots.pem` dari [daftar resmi Google](https://android.googleapis.com/attestation/root). Pembaruan root harus mengikuti [panduan Android](https://developer.android.com/privacy-and-security/security-key-attestation). Jangan menggantinya dengan root yang dikirim oleh klien.

`MOBILE_APP_GPS_SIGNER_SHA256` harus sesuai sertifikat APK resmi, tanpa pemisah titik dua. Periksa dengan `apksigner verify --print-certs` saat mengganti signing key. Fingerprint ini merupakan informasi publik, bukan secret aplikasi.

Pengujian otomatis menggunakan sertifikat EC sintetis dengan CA yang dipercaya khusus di lingkungan test. Cakupannya termasuk tanda tangan asli, manipulasi koordinat, software key, boot tidak terkunci, signer APK salah, certificate chain tidak dipercaya, pencabutan, nonce kedaluwarsa/replay, perbedaan akun/sesi/jenis tindakan, koneksi gagal, dan respons native yang menolak Fake GPS. Unit test Android mencakup keputusan sampel simulasi, lama, dari masa depan, dan akurasi tidak valid.

Pengujian end-to-end pada HP Android fisik dengan hardware attestation dan aplikasi Fake GPS masih wajib dilakukan sebelum rollout ke seluruh karyawan. Verifikasi hardware memperkuat kepercayaan terhadap aplikasi/perangkat, sedangkan penanda mock berasal dari sistem Android. Perangkat yang dieksploitasi, kerentanan sistem, relay perangkat, dan pemalsuan sinyal radio GPS tetap menjadi batas perlindungan. Jangan mengklaim deteksi mutlak untuk semua bentuk pemalsuan lokasi.
