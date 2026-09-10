# Download PDF CV Maker

Fitur berada pada **Compare CV Maker**, dengan download single pada daftar/detail serta batch berdasarkan filter yang sedang aktif.

## Alur pengguna

1. Pilih filter organisasi/progres/pencarian. Hanya snapshot `is_complete = true` dan profil CV yang tersedia yang boleh masuk antrean.
2. Klik **Buat Batch PDF Hasil Filter**. Setiap klik mengambil maksimal 50 CV berikutnya, urut NIK. Jika hasil filter ribuan, lanjutkan membuat batch berikutnya; CV yang sudah ditahan dalam batch tidak terpilih lagi.
3. Tunggu riwayat berubah dari menunggu menjadi siap. Satu job memproses satu CV; job berikutnya mengemas PDF dalam ZIP tanpa kompresi berat. Single menghasilkan PDF langsung.
4. Unduh hasil pada riwayat. File sudah dibuat sebelumnya, sehingga request download hanya mengirim file privat.
5. Untuk kegagalan sebagian, unduh PDF yang berhasil lalu ajukan batch dengan filter **Belum Diunduh**. Detail gagal menunjukkan NIK yang masih berada dalam hak akses pengguna.
6. Untuk jaringan terputus, gunakan **Unduh ulang hasil** pada riwayat yang sama. Tidak perlu membuat PDF baru.

Filter status PDF:

- **Belum Diunduh**: belum pernah dilayani unduhannya dan tidak sedang ditahan oleh batch.
- **Dalam Batch / Siap Diunduh**: ditahan sejak diterima sampai diunduh, gagal, dibatalkan, atau kedaluwarsa. Berlaku lintas admin.
- **Sudah Diunduh**: server sudah melayani permintaan unduhan dan tidak ada batch aktif.

Status unduhan berlaku global per NIK, bukan per admin atau per versi CV. Perubahan data CV tidak otomatis menghapus riwayat unduhan. Centang **Sertakan yang sudah diunduh** dan konfirmasi untuk membuat versi baru. Browser dapat gagal menyimpan file walaupun server telah melayani download; status tidak mengklaim bahwa file sudah tersimpan di perangkat.

Pencatatan dimulai dari fitur ini. Unduhan lama atau unduhan langsung di aplikasi CV Maker tidak diimpor otomatis.

## Isi dan hak akses

PDF menggunakan data CV Maker yang dibaca kembali saat job berjalan, lalu dicetak dengan template HRIS khusus CV. Ini bukan endpoint PDF dari aplikasi CV Maker dan bukan laporan perbandingan. Template mencakup profil, informasi pribadi, pengalaman, pendidikan, keahlian, sertifikasi, organisasi, bahasa, proyek, prestasi, serta minat/bakat. Foto dan lampiran KTP/KK/ijazah tidak dibundel.

Pembacaan PDF memakai mode strict: kegagalan API/database tidak menjadi CV kosong yang terlihat berhasil. Kelengkapan delapan tahap diperiksa ulang menggunakan evaluator progres yang sudah ada. Endpoint API CV Maker saat ini membatasi masing-masing relasi pada 50 baris per profil; ekspor menolak batas 50 agar tidak diam-diam mencetak data yang mungkin terpotong. Database transport memiliki batas 500 baris per bagian; kelebihan ditolak.

Role mengikuti pengelola Compare CV Maker: Super Admin, HR, HOD, Manager, Supervisor, Admin Divisi dengan akses menu tersebut. Role Audit CV, termasuk gabungan Audit CV + HR, tidak dapat mengunduh file pribadi. Scope organisasi diperiksa pada pengajuan, pengerjaan job, dan unduhan. Riwayat batch hanya dapat diakses pemohonnya. Download juga memeriksa kelengkapan snapshot saat ini. Bila scope/progres berubah setelah file siap, batalkan batch dan ajukan ulang.

## Instalasi

Tidak ada paket Composer baru. Gunakan PHP dan Laravel sesuai `composer.json` proyek (saat implementasi: PHP 8.3+, Laravel 13). Ekstensi ZIP diperlukan untuk batch, Dompdf sudah tersedia.

Jalankan migration baru saja bila deployment perlu dipisahkan dari migration lain:

```bash
php artisan migrate --path=database/migrations/2026_09_09_000001_create_cv_maker_pdf_export_tables.php --force
php artisan config:clear
```

Migration menambahkan tiga tabel baru: batch, item, dan ledger unduhan/reservasi. Tidak mengubah data karyawan. Kolom pemohon/pengunduh memakai string UUID sesuai tabel users.

Konfigurasi opsional `.env`:

```dotenv
CV_PDF_QUEUE_CONNECTION=database
CV_PDF_QUEUE=cv-pdf
CV_PDF_BATCH_LIMIT=50
CV_PDF_RETENTION_DAYS=7
CV_PDF_MAX_FILE_BYTES=10485760
CV_PDF_MAX_ARCHIVE_BYTES=104857600
```

Batas batch dipagari 1–100. Arsip maksimal 100 MiB secara default. Gunakan queue database/Redis atau backend asynchronous yang didukung; `sync`, `null`, `background`, dan `deferred` ditolak untuk fitur ini. Queue default aplikasi tidak perlu diubah. Pastikan tabel jobs sudah tersedia untuk koneksi database.

Worker khusus:

```bash
php artisan queue:work database --queue=cv-pdf --sleep=3 --tries=2 --timeout=120
```

`retry_after` koneksi queue harus lebih besar dari TTL lock 240 detik (misalnya 300; konfigurasi database proyek saat ini default 1800). Worker menggunakan cache lock bersama. Pada beberapa server, cache dan disk privat harus dapat diakses bersama. Batas waktu keras worker membutuhkan dukungan runtime/process manager yang sesuai; jangan mengandalkan timeout browser untuk menghentikan job.

Untuk cPanel tanpa worker menetap, cron satu menit dapat menjalankan worker dengan `--stop-when-empty --max-time=50 --timeout=120`. Cegah cron worker bertumpuk, misalnya melalui `flock` bila tersedia. `--max-time` diperiksa di antara job, bukan untuk memotong render yang sedang berjalan. Batas proses hosting tetap harus cukup untuk satu PDF. Gunakan path PHP CLI yang sama versinya dengan aplikasi.

Aktifkan scheduler aplikasi yang sudah ada:

```bash
php artisan schedule:run
```

Scheduler menjalankan `cv-maker:maintain-pdf-exports` setiap menit untuk mengirim ulang batch yang tidak bergerak lebih dari 5 menit dan membersihkan file kedaluwarsa/dibatalkan. Tidak menjalankan pembuatan PDF dalam request web. Worker yang mati meninggalkan item `processing`; setelah lock habis job berikutnya mencoba lagi, maksimal tiga percobaan per item. Kegagalan pengemasan batch dapat diulang oleh queue, kemudian ditandai gagal. Batch tidak menggantung tanpa jalan pemulihan: pengguna juga bisa membatalkan untuk melepas reservasi.

File berada di `storage/app/private/cv-pdf/{uuid}/`. Download menggunakan endpoint POST ber-CSRF, pemeriksaan pemilik/scope, dan respons file privat. Retensi menghapus hanya direktori batch dengan UUID dari server; ledger unduhan tetap tersimpan. Folder harus writable oleh web dan worker. Jangan membuat symlink folder privat ke public.

Pengemasan juga dibatasi tiga percobaan yang dicatat di database agar worker yang berulang kali terhenti tidak membuat batch berjalan tanpa batas.

## Validasi

### Font Mandarin

PDF CV mendukung karakter Mandarin melalui font lokal `storage/fonts/NotoSansSC-Regular.ttf` yang sudah tersedia dalam proyek. Pastikan file ini ikut diunggah ke production dan direktori cache font Dompdf (`storage/fonts` secara default) writable oleh worker. Tidak perlu memasang font pada komputer pengguna atau mengaktifkan akses font lewat internet.

Template menggunakan DejaVu Sans untuk Latin dan Noto Sans SC sebagai fallback Mandarin. Karena font CJK yang tersedia hanya regular, karakter Mandarin pada judul menggunakan bentuk regular yang sama; teks Latin tetap bold. Font subsetting diaktifkan khusus untuk PDF CV agar hanya glyph yang dipakai disematkan. PDF yang sudah dibuat sebelumnya tidak berubah: buat PDF baru setelah deployment, bukan mengunduh ulang berkas lama dari riwayat.

```bash
php artisan test --filter=CvMaker
php artisan route:list --name=cv-maker-compare.pdf
node --check public/assets/js/admin-cv-maker-pdf.js
```

Pengujian otomatis memakai SQLite in-memory, penyimpanan palsu, antrean palsu, dan API palsu. Mencakup filter/scope, kelengkapan, UUID idempotensi, reservasi lintas admin, PDF nyata dengan Dompdf, ZIP, kegagalan sebagian, perubahan permission, retry worker, retensi, dan pencatatan download. Kontensi transaksi MySQL perlu diverifikasi di staging dengan dua admin mengajukan filter yang sama secara bersamaan; unique key dan lock pada baris progres/ledger menjadi proteksi server.

Uji manual staging: single lengkap/tidak lengkap, batch dengan filter lebih dari 50 CV, dua admin bersamaan, koneksi CV Maker mati, worker mati/dihidupkan kembali, login habis, download terputus dan diulang, pembatalan, retensi, serta tampilan mobile. Riwayat memantau setiap 5 detik, berhenti saat selesai/gagal atau setelah 5 menit, dan dapat dilanjutkan lewat Refresh.

Untuk rollback, hentikan worker `cv-pdf` dan nonaktifkan aksi UI dahulu. Kembalikan patch fitur. Sebaiknya pertahankan tabel ledger untuk audit; migration `down()` menghapus ketiga tabel sehingga akan kehilangan riwayat dan proteksi duplikasi jika dipasang ulang. Tidak perlu menghapus atau mengubah data karyawan.
