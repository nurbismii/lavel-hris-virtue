# Upgrade Laravel 8 ke Laravel 13

## Hasil akhir

Project telah dinaikkan berurutan melalui Laravel 9, 10, 11, 12, lalu 13. Struktur aplikasi Laravel lama tetap dipertahankan karena Laravel 11+ masih mendukung struktur tersebut; memindahkan seluruh bootstrap ke struktur skeleton baru tidak diperlukan dan akan memperbesar risiko regresi.

| Tahap | Framework yang berhasil di-resolve | Perubahan utama |
| --- | --- | --- |
| 8 ke 9 | 9.52.22 | Flysystem 3, Symfony Mailer, proxy/CORS bawaan framework, FakerPHP |
| 9 ke 10 | 10.50.3 | PHPUnit 10, Collision 7, Yajra DataTables 10 |
| 10 ke 11 | 11.56.0 | PHPUnit 11, Collision 8, Carbon 3 |
| 11 ke 12 | 12.67.0 | Laravel Excel 4, PhpSpreadsheet 5, DomPDF 3, Yajra 12 |
| 12 ke 13 | 13.26.1 | Tinker 3, PHPUnit 12, Yajra 13, middleware CSRF baru |

Versi final yang dikunci oleh `composer.lock`:

- PHP: `^8.3` (divalidasi memakai PHP 8.5.9)
- Laravel: 13.26.1
- Laravel Excel: 4.0.1
- PhpSpreadsheet: 5.9.0
- Yajra DataTables: 13.2.0
- DomPDF: 3.1.2
- PHPUnit: 12.5.33

## Perubahan kompatibilitas aplikasi

- `Fideloper\\Proxy` dan `Fruitcake\\Cors` diganti middleware resmi Laravel.
- Middleware CSRF aplikasi sekarang mewarisi `PreventRequestForgery` Laravel 13.
- `$dates` pada model `Employee` dipindah ke `$casts` bertipe `datetime`.
- Semua hitungan hari/menit/jam yang terdampak Carbon 3 dibuat eksplisit sebagai integer dan memakai mode absolute/signed yang sesuai perilaku sebelumnya.
- Nama environment filesystem baru didukung tanpa memutus `FILESYSTEM_DRIVER` lama.
- Deserialisasi object PHP dari cache dinonaktifkan sebagai hardening Laravel 13.
- Session tetap memakai serialisasi PHP agar deployment tidak langsung mengeluarkan seluruh pengguna. Migrasi ke JSON harus dijadwalkan sebagai deployment tersendiri karena akan menginvalidasi session aktif.
- Konstanta SSL PDO dibuat kompatibel dengan PHP 8.3–8.5.
- Konfigurasi PHPUnit diperbarui ke schema PHPUnit 12.

## Validasi yang sudah dijalankan

```text
Laravel 13.26.1 / PHP 8.5.9
Route registration: OK
PHP lint: 481 file, 0 error
PHPUnit: 172 test, 1303 assertion, OK
Composer manifest: valid
Composer platform requirements: OK
Security advisory saat composer update: tidak ditemukan
```

`php artisan migrate:status` belum dapat diverifikasi dari sandbox pengembangan karena koneksi MySQL remote diblokir. Tidak ada migration baru yang dibuat oleh upgrade ini.

## Deployment production

1. Buat backup database dan snapshot/release folder yang dapat di-rollback.
2. Pastikan web server, CLI, cron, dan queue worker memakai PHP 8.3 atau lebih baru. Untuk workstation ini gunakan `C:\\xampp\\php85\\php.exe`.
3. Gunakan Composer versi terbaru yang kompatibel dengan PHP 8.5. Composer 2.7.7 masih berhasil, tetapi menghasilkan deprecation notice pada PHP 8.5.
4. Deploy source dan `composer.lock`, lalu instal dependency dengan PHP yang sama dengan runtime web:

   ```powershell
   C:\xampp\php85\php.exe composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   ```

5. Pertahankan `SESSION_SERIALIZATION=php` pada deployment pertama.
6. Periksa migration lebih dahulu; jangan menjalankan migration yang belum direview:

   ```powershell
   C:\xampp\php85\php.exe artisan migrate:status
   ```

7. Bersihkan cache lama lalu bangun ulang cache production:

   ```powershell
   C:\xampp\php85\php.exe artisan optimize:clear
   C:\xampp\php85\php.exe artisan config:cache
   C:\xampp\php85\php.exe artisan route:cache
   C:\xampp\php85\php.exe artisan view:cache
   ```

8. Restart worker queue agar tidak ada proses lama yang masih memuat vendor Laravel 8.
9. Jalankan smoke test login, dashboard HR/HOD, presensi, cuti/izin, import/export Excel, DataTables, PDF, dan queue di staging sebelum mengalihkan traffic production.

## Rollback

Jika smoke test gagal, hentikan traffic/worker, kembalikan release source beserta `composer.lock` Laravel 8 dari snapshot yang sama, jalankan `composer install` menggunakan versi PHP yang kompatibel dengan release lama, pulihkan database hanya jika deployment menjalankan migration, lalu bersihkan cache aplikasi. Source, vendor, lock file, dan database harus di-rollback sebagai satu release konsisten.
