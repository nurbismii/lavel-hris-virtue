# Login passkey

Implementasi ini memakai `lbuchs/webauthn` v2.2.0 pada Laravel 13 / PHP >=8.3 yang tercatat di Composer proyek. PHP harus menyediakan OpenSSL dan mbstring. Tidak membutuhkan Node build, layanan biometrik eksternal, queue, atau worker tambahan.

## Aktivasi

1. Deploy kode, `composer.json`, `composer.lock`, dan dependency:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

   Jika cPanel tidak menyediakan Composer, siapkan vendor sesuai lock file pada lingkungan build dengan PHP dan extension yang sama, lalu unggah hasilnya bersama kode.

2. Backup database dan jalankan migration khusus ini. Migration hanya menambahkan `user_passkeys` dan `passkey_challenges`; tidak mengubah data karyawan atau password lama.

   ```sh
   php artisan migrate --path=database/migrations/2026_09_13_000001_create_passkey_tables.php --force
   ```

   Foreign key mengikuti `users.id` string panjang 32 pada migration proyek. Periksa kesesuaiannya jika schema production pernah diubah manual.

3. Tentukan satu domain HTTPS permanen di `.env`. Contoh berikut harus diganti dengan domain aplikasi sebenarnya:

   ```dotenv
   PASSKEYS_ENABLED=true
   PASSKEYS_RP_NAME="V-People"
   PASSKEYS_RP_ID=hris.example.com
   PASSKEYS_ORIGIN=https://hris.example.com
   ```

   RP ID hanya hostname, tanpa scheme/path/port. Origin mencakup scheme, hostname, dan port nonstandar jika digunakan, tanpa path atau slash penutup. Untuk aplikasi di subfolder, path aplikasi tidak menjadi bagian origin. RP ID wajib sama persis dengan hostname origin; konfigurasi tidak menerima subdomain lain secara otomatis. Jangan mengganti domain setelah registrasi tanpa rencana pendaftaran ulang passkey.

   Pengujian lokal dapat memakai RP ID `localhost` dan origin seperti `http://localhost:8000`, dengan APP_ENV=local. HTTP pada domain lain tidak diterima. Gunakan HTTPS pada production. Pastikan session/cookie tersedia dan reverse proxy dikonfigurasi benar.

4. Perbarui cache setelah deploy:

   ```sh
   php artisan config:cache
   php artisan route:clear
   php artisan view:clear
   php artisan route:list --path=passkeys
   ```

   Bangun kembali route cache hanya jika deployment proyek memang menggunakannya. Pada pengembangan gunakan `php artisan config:clear` setelah mengubah `.env`.

## Alur pengguna

- Login memakai password, buka **Pengaturan akun → Masuk dengan passkey**.
- Isi nama passkey dan password saat ini, lalu konfirmasi pada perangkat.
- Setelah logout, pilih **Masuk dengan passkey**. Browser memilih akun dari passkey yang sudah terdaftar; email tidak perlu dimasukkan.
- Pengguna dapat melihat daftar, tanggal pembuatan, penggunaan terakhir, dan mencabut passkey miliknya dengan konfirmasi password.
- Maksimal 10 passkey per akun; batas dan timeout dapat diubah di `config/passkeys.php`.
- Pencabutan mencegah autentikasi berikutnya; tidak otomatis mengakhiri sesi yang sudah login. Passkey di pengelola sandi perangkat dihapus oleh pengguna secara terpisah.
- Password, forgot-password, verifikasi email, pembatasan menu, dan pilihan Ingat saya tetap menggunakan mekanisme proyek.
- Perubahan/reset password tidak otomatis mencabut passkey yang telah terdaftar. Jika akun/perangkat diduga diambil alih, cabut passkey yang tidak dikenal dan tangani sesi aktif melalui prosedur pemulihan akun yang berlaku. Registrasi yang masih berlangsung saat password berubah akan ditolak.

## Keamanan dan penyimpanan

- Tidak menyimpan sidik jari, foto wajah, private key, attestation certificate, atau payload mentah WebAuthn. Hanya ID kredensial, public key, counter, kemampuan backup, nama, dan timestamp yang disimpan.
- Challenge acak berlaku 120 detik, terikat sesi dan jenis operasi, dikonsumsi secara atomik sebelum verifikasi. Challenge gagal juga tidak dapat digunakan ulang. Cleanup terbatas menghapus maksimal 100 challenge kedaluwarsa saat challenge baru diterbitkan; tidak membutuhkan cron.
- Server memeriksa exact origin, RP ID hash, challenge, user presence, user verification, kepemilikan credential/userHandle, dan tanda tangan. Cross-origin iframe ditolak.
- Kredensial discoverable diwajibkan. Nama passkey hanya label pengguna, bukan bukti identitas perangkat.
- Counter diperiksa untuk kredensial yang tidak dapat disinkronkan; untuk passkey yang dapat disinkronkan, keamanan replay memakai challenge sekali pakai karena counter bisa berbeda antarperangkat. Perubahan flag backup-eligible ditolak.
- Endpoint dibatasi 20 request/menit/sesi (atau akun setelah login), dengan batas gabungan 600/menit/IP agar jaringan kantor bersama tetap dapat digunakan. Batas IP dapat diatur melalui `PASSKEYS_REQUESTS_PER_IP`. Konfirmasi password pendaftaran/pencabutan memiliki tambahan batas 6/menit/akun. Cache rate limiter harus persisten dan dibagi antar-instance jika aplikasi berjalan pada beberapa server.
- Penambahan dan pencabutan memakai `AuditTrailService` proyek, tanpa materi kredensial. Pencatatan audit mengikuti sifat best-effort service yang sudah ada.
- Pendaftaran/pengelolaan memerlukan akun login dengan email terverifikasi. Login mempertahankan pencatatan `terakhir_login`, session regeneration, dan pengalihan berdasarkan hak menu. Tidak menambahkan aturan status akun baru yang berbeda dari login password.
- Saat fitur nonaktif, konfigurasi tidak cocok, atau tabel belum ada, endpoint gagal tertutup. Login password tetap tersedia.

## Dukungan perangkat dan Android

Passkey memerlukan browser/platform WebAuthn. Verifikasi perangkat bisa berupa sidik jari, wajah, atau PIN; aplikasi tidak menjanjikan biometrik sebagai satu-satunya cara.

Kebijakan `RedirectAndroidToApp` proyek tetap dipertahankan: browser Android di production diarahkan ke aplikasi untuk area selain pengecualian yang sudah ada. Fitur ini tidak mengubah kebijakan tersebut. Android WebView V-PEOPLE belum dapat dianggap mendukung passkey tanpa pengujian aplikasi native dan integrasi Credential Manager/WebAuthn yang sesuai. Gunakan password sebagai fallback. Pengujian Windows Hello, iPhone/Safari, serta aplikasi Android perlu dilakukan pada perangkat sasaran.

## Validasi

```sh
php artisan test --compact --filter="PasskeyAuthenticationTest|EmailUrlTest|RouteControllerMethodTest"
node --check public/assets/js/passkeys.js
```

Test menggunakan SQLite in-memory dan kunci EC sementara, bukan database karyawan. Mencakup registrasi attestation dan login dengan tanda tangan nyata, replay, sesi/tujuan/expiry challenge, origin/RP salah, cross-origin, signature/handle salah, user verification, perubahan password, duplikasi, pencabutan milik orang lain, revocation, counter perangkat/sinkronisasi, rate limit, feature flag, dan sesi login HTTP.

Checklist perangkat asli setelah aktivasi:

1. Daftar passkey dari akun uji terverifikasi; pastikan muncul sekali pada daftar.
2. Logout lalu login dengan passkey. Cek halaman tujuan, Ingat saya, dan waktu login terakhir.
3. Batalkan dialog perangkat, ulangi klik, gunakan password salah, dan coba tanpa koneksi; loading harus pulih dengan pesan jelas.
4. Cabut passkey akun uji, logout, dan pastikan passkey tersebut tidak lagi bisa login.
5. Coba password/forgot-password ketika passkey tidak tersedia.
6. Cek desktop dan ponsel, perangkat tanpa biometrik, passkey tersinkronisasi, dan domain production yang benar.

## Menonaktifkan / rollback

Atur `PASSKEYS_ENABLED=false`, lalu `php artisan config:cache`. Ini menyembunyikan fitur dan memblokir endpoint tanpa menghapus kredensial. Pengguna tetap login dengan password. Pertahankan tabel agar passkey dapat diaktifkan lagi. Jangan menjalankan rollback migration di production hanya untuk mematikan fitur: `down()` menghapus semua pendaftaran passkey dan challenge.
