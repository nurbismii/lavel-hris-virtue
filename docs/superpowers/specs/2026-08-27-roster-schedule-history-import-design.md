# Desain Import Riwayat dan Kalkulasi Jadwal Roster

**Tanggal:** 27 Agustus 2026  
**Status:** Disetujui untuk dilanjutkan ke rencana implementasi  
**Project:** HRIS V-People

## 1. Tujuan

Menyediakan alur admin untuk mengunggah workbook riwayat roster, memvalidasi identitas karyawan melalui pasangan NIK dan nomor KTP, menampilkan preview sebelum penyimpanan, serta memblokir seluruh import apabila satu atau lebih baris gagal.

Setelah import dikonfirmasi, sistem harus:

- mencatat jadwal dan riwayat roster dari Excel;
- melanjutkan kalkulasi pola 70 hari kerja dan 14 hari off sampai akhir dua tahun ke depan;
- memperpanjang horizon jadwal setiap hari;
- mengirim reminder normal pada H-14;
- mengirim reminder langsung untuk jadwal mendatang yang sudah berada pada H-13 sampai H-0 saat import selesai;
- tidak mengirim reminder untuk jadwal yang sudah lewat atau sudah memiliki pengajuan terkait.

## 2. Konteks Workbook

Workbook referensi bernama `DATABASE ROSTER KIRIM BISMI MASUKAN HRIS (PRD 26 AGUSTUS 2026).xlsx` dengan karakteristik:

- satu sheet;
- 127 baris karyawan;
- NIK pada kolom B;
- nomor KTP pada kolom C;
- nama karyawan pada kolom D;
- blok tahun 2016 sampai 2028;
- setiap tahun mempunyai periode I sampai V dan kolom `REMARKS`;
- tanggal dapat berupa nilai Excel langsung atau hasil formula;
- `REMARKS` dapat mengandung cuti, insentif, bukan roster, penggabungan periode, dan kondisi yang memerlukan review manual.

NIK pada workbook referensi disimpan Excel sebagai angka. Importer wajib memperlakukannya sebagai identifier berbentuk string dan tidak boleh menebak atau mengoreksi NIK.

## 3. Pendekatan Arsitektur

Pendekatan yang dipilih adalah **preview sinkron dan konfirmasi/import melalui queue**.

Alasannya:

- admin memperoleh hasil validasi segera;
- penyimpanan ribuan jadwal tidak bergantung pada timeout request browser;
- double submit dapat dicegah;
- status proses dapat dipantau;
- project sudah memiliki `ImportHistory`, `ImportHistoryItem`, queue, audit trail, export Excel, dan pola polling;
- proses tetap sesuai untuk shared hosting selama scheduler dan queue worker tersedia.

Proses tidak membuat staging table roster baru. Infrastruktur `ImportHistory` digunakan kembali dan diperluas secara terarah.

## 4. Alur Admin

### 4.1 Upload dan validasi

1. Admin/HR membuka **Jadwal Roster → Import Riwayat**.
2. Admin mengunggah file `.xlsx`.
3. Form Request memvalidasi extension, MIME type, ukuran, dan keberadaan file.
4. File disimpan dengan nama UUID pada storage private.
5. Sistem mencatat checksum, pemilik, serta `expires_at` selama 12 jam.
6. Service membaca workbook dan menghasilkan preview tanpa mengubah jadwal roster.

### 4.2 Preview gagal

Jika terdapat minimal satu blocker:

- status menjadi `validation_failed`;
- tidak ada data roster yang disimpan;
- tombol konfirmasi dinonaktifkan;
- sistem membuat file Excel kegagalan pada storage private;
- admin dapat mengunduh file kegagalan selama belum kedaluwarsa;
- file kegagalan memuat nomor KTP lengkap sesuai keputusan bisnis.

### 4.3 Preview valid

Jika seluruh baris valid:

- status menjadi `awaiting_confirmation`;
- admin melihat ringkasan data baru, data yang akan diperbarui, data identik, warning nama berbeda, dan remarks yang perlu review;
- admin dapat menekan **Konfirmasi Import**.

### 4.4 Konfirmasi dan pemrosesan

1. Endpoint konfirmasi memvalidasi pemilik, authorization, status, masa berlaku, dan checksum.
2. Sistem mengubah status ke `queued` secara atomik dan dispatch job unik.
3. UI menampilkan `Menunggu antrean` lalu `Sedang diproses` dengan polling lima detik.
4. Job membaca dan memvalidasi ulang workbook.
5. Job menyimpan seluruh data dalam satu database transaction.
6. Status menjadi `completed` hanya setelah transaction berhasil di-commit.
7. Reminder dan notifikasi lain dijalankan setelah commit.

## 5. Model Data

### 5.1 `import_histories`

Tambahkan tipe import:

- `roster_schedule`

Tambahkan status:

- `awaiting_confirmation`
- `validation_failed`
- `expired`

Tambahkan kolom nullable:

- `file_checksum`
- `failure_file_path`
- `expires_at`
- `confirmed_at`
- `confirmed_by`

Kolom baru harus nullable agar migration aman untuk data lama. Index hanya ditambahkan pada kolom yang digunakan untuk lookup/status cleanup.

### 5.2 Data roster

- `roster_schedules` tetap menjadi sumber jadwal aktif dan mendatang.
- `roster_schedule_histories` tetap menyimpan riwayat import dan hasil klasifikasi.
- `cuti_roster` memperoleh `roster_schedule_id` nullable dan index untuk menghubungkan pengajuan dengan jadwal sumber.
- Foreign key database hanya digunakan apabila tipe kolom legacy kompatibel dan migration aman. Jika tidak, gunakan index dan validasi aplikasi tanpa memaksa perubahan tipe tabel legacy.

### 5.3 Retensi data sensitif

- File sumber dan file kegagalan disimpan di storage private maksimal 12 jam.
- Nomor KTP lengkap tidak disimpan pada `summary`, `failure_samples`, audit trail, atau log aplikasi.
- File kegagalan boleh memuat nomor KTP lengkap tetapi harus dihapus setelah kedaluwarsa.
- Metadata audit dan ringkasan angka tetap disimpan setelah file dihapus.

## 6. Aturan Validasi

### 6.1 Blocker

Seluruh import diblokir jika ditemukan salah satu kondisi berikut:

- file bukan `.xlsx`, MIME tidak sesuai, terlalu besar, atau tidak dapat dibaca;
- sheet/header tahun dan periode I–V tidak sesuai;
- NIK kosong, bukan digit, atau duplikat di dalam file;
- nomor KTP kosong, bukan 16 digit, telah rusak akibat pembulatan, atau scientific notation tidak dapat dipulihkan dengan pasti;
- NIK tidak ditemukan pada `employees.nik`;
- nomor KTP Excel tidak cocok dengan `employees.no_ktp` untuk NIK tersebut;
- tanggal tidak valid atau formula tanggal menghasilkan error;
- satu karyawan mempunyai tanggal periode yang duplikat atau urutan yang tidak logis;
- data bertabrakan dengan jadwal bersumber `manual` yang tidak boleh ditimpa.

### 6.2 Non-blocking

- Nama Excel berbeda dengan nama HRIS selama NIK dan nomor KTP cocok. Perbedaan nama ditampilkan sebagai warning.
- `REMARKS` tidak dapat diklasifikasikan otomatis. Riwayat disimpan sebagai `need_review` setelah import berhasil.
- Data identik sudah pernah diimpor. Data ditandai `unchanged` dan tidak ditulis ulang.

### 6.3 Karyawan tidak aktif

- Riwayat lama tetap dicatat.
- Jadwal hasil import ditandai nonaktif.
- Jadwal lanjutan tidak dibuat.
- Reminder tidak dikirim.

### 6.4 Konflik data existing

- Data existing identik: tidak diubah.
- Data existing bersumber `import` dan berubah: diperbarui dengan audit trail.
- Data existing bersumber `manual` pada NIK dan tanggal off yang sama: blocker.
- Hasil review manual yang sudah berstatus confirmed tidak boleh ditimpa oleh import ulang.

## 7. File Kegagalan

File kegagalan harus memuat:

- nomor urut;
- nomor baris Excel sumber;
- NIK;
- nomor KTP lengkap;
- nama karyawan Excel;
- nama karyawan HRIS jika ditemukan;
- tahun dan periode atau kolom sumber;
- nilai bermasalah;
- jenis kegagalan;
- alasan kegagalan;
- saran perbaikan.

File asli tidak dimodifikasi. Admin memperbaiki file sumber lalu mengunggah ulang.

Download dilakukan melalui controller dengan authorization; path storage tidak pernah diekspos. Aksi preview, konfirmasi, dan download dicatat pada audit trail.

## 8. Kalkulasi Jadwal

Untuk setiap tanggal periode Excel:

- tanggal periode dianggap sebagai `off_start`;
- `work_start = off_start - 70 hari`;
- `work_end = off_start - 1 hari`;
- `off_end = off_start + 13 hari`;
- periode berikutnya dimulai sehari setelah `off_end`;
- jarak antar `off_start` normal adalah 84 hari.

Jadwal lanjutan dimulai dari jadwal valid terakhir per karyawan dan dibuat sampai akhir dua tahun ke depan. Generator harian menjaga horizon tersebut terus tersedia.

Jika workbook sudah mempunyai jadwal lebih jauh daripada horizon, generator tidak membuat jadwal tambahan sampai horizon kembali perlu diperpanjang.

## 9. Transaction dan Idempotensi

Job `ProcessRosterScheduleImport` unik berdasarkan `import_id`.

Sebelum menulis data, job harus:

- mengunci `ImportHistory`;
- memastikan status valid untuk diproses;
- memeriksa masa berlaku dan checksum;
- membaca ulang file;
- mengunci karyawan terkait;
- mengulang validasi NIK dan nomor KTP.

Penulisan roster menggunakan batch insert/update di dalam satu transaction. Jika satu operasi gagal, seluruh perubahan job di-rollback.

Tidak boleh ada email, file remote, atau API eksternal di dalam transaction. Audit import dan dispatch reminder dilakukan setelah commit atau menggunakan mekanisme after-commit yang tersedia pada versi Laravel project.

Konfirmasi kedua pada import yang sama harus ditolak secara aman dan tidak boleh dispatch job kedua.

## 10. Reminder

Reminder hanya berlaku untuk jadwal yang:

- aktif;
- dimiliki karyawan aktif;
- belum pernah berhasil dikirimi reminder;
- belum memiliki pengajuan `cuti_roster` terkait;
- tanggal off-nya belum lewat.

Aturan pengiriman:

- H-14 diproses oleh scheduled command normal;
- H-13 sampai H-0 diproses segera setelah import berhasil;
- jadwal lampau tidak dikirimi;
- job reminder unik per `roster_schedule_id`;
- `reminder_sent_at` menjadi perlindungan idempotensi database;
- email tidak tersedia atau kegagalan pengiriman dicatat secara ringkas tanpa data sensitif.

Tautan reminder membuka formulir pengajuan roster dengan jadwal terkait. Backend wajib memastikan jadwal tersebut dimiliki karyawan login. Pengajuan berhasil menyimpan `roster_schedule_id` dan memperbarui realisasi jadwal sehingga reminder lanjutan tidak dikirim.

## 11. UI dan Feedback

### 11.1 Tahap upload

- area pilih/drag file;
- petunjuk format dan ukuran;
- tombol `Validasi & Preview`;
- loading state `Memvalidasi...`;
- pencegahan double submit frontend dan backend.

### 11.2 Tahap preview

Ringkasan menampilkan:

- nama file dan waktu kedaluwarsa;
- total karyawan;
- jumlah pasangan NIK dan KTP yang cocok;
- jumlah create, update, unchanged;
- jumlah warning nama;
- jumlah remarks `need_review`;
- jumlah blocker.

Tabel preview menampilkan baris Excel, NIK, nomor KTP lengkap, nama Excel, nama HRIS, status identitas, rentang periode, jumlah jadwal, status validasi, dan keterangan.

### 11.3 Tahap pemrosesan

- status badge queued/processing/completed/failed;
- polling setiap lima detik dan berhenti pada status terminal;
- total data serta jumlah create/update/unchanged;
- pesan error yang aman tanpa stack trace;
- tautan ke riwayat import;
- tombol konfirmasi tidak dapat digunakan ulang.

AJAX harus menangani 422, 401, 403, 419, 500, dan status jaringan 0. Feedback menggunakan SweetAlert/toast existing.

## 12. Authorization dan Keamanan

- Route import hanya untuk Super Admin dan HR sesuai pola role/menu project.
- Backend authorization wajib diterapkan pada upload, preview, konfirmasi, status, dan download.
- File disimpan pada disk private dengan nama UUID.
- Original filename hanya digunakan sebagai metadata yang telah disanitasi.
- Download memakai `Content-Disposition: attachment` dan `X-Content-Type-Options: nosniff`.
- Log tidak boleh memuat nomor KTP, isi workbook, atau path absolut server.
- Audit mencatat actor, import ID, nama file aman, checksum, hasil ringkas, konfirmasi, download, dan cleanup.

## 13. Cleanup

Scheduled command berjalan minimal setiap jam untuk:

- mencari import roster dengan `expires_at <= now()`;
- menghapus file sumber dan file kegagalan melalui storage service;
- mengosongkan path file sensitif;
- mengubah import yang masih menunggu konfirmasi menjadi `expired`;
- mempertahankan record audit dan ringkasan non-sensitif.

Command harus idempotent dan memakai `withoutOverlapping()`.

## 14. Penanganan Error

- Error validasi tampil per baris dan tersedia pada file kegagalan.
- Error sistem saat preview mengubah status menjadi failed tanpa menghasilkan data roster.
- Error job mengubah status menjadi failed setelah rollback.
- Stack trace hanya masuk log teknis dan tidak ditampilkan ke admin.
- Jika queue tidak berjalan, status queued tetap terlihat sehingga dapat dideteksi monitor.
- Retry job hanya aman sebelum transaction berhasil. Job yang mendapati status completed harus berhenti tanpa efek samping.

## 15. Testing

### 15.1 Unit test

- deteksi struktur header workbook;
- normalisasi NIK dan nomor KTP sebagai string;
- deteksi scientific notation/pembulatan nomor KTP;
- parsing tanggal langsung dan formula;
- parsing remarks;
- kalkulasi pola 70+14 hari;
- klasifikasi blocker, warning, create, update, dan unchanged.

### 15.2 Feature test

- authorization upload/preview/confirm/status/download;
- NIK dan KTP cocok dengan nama berbeda tetap valid;
- NIK tidak ditemukan atau KTP berbeda memblokir seluruh import;
- file kegagalan berisi informasi yang disyaratkan;
- checksum berubah atau import kedaluwarsa menolak konfirmasi;
- konfirmasi ganda tidak membuat duplikasi;
- konflik jadwal manual menjadi blocker;
- karyawan tidak aktif hanya mendapatkan histori nonaktif;
- rollback penuh ketika job gagal di tengah proses;
- file tidak dapat diunduh setelah 12 jam.

### 15.3 Reminder test

- reminder tepat H-14;
- reminder langsung H-13 sampai H-0;
- jadwal lampau dilewati;
- pengajuan existing dilewati;
- reminder tidak terkirim dua kali;
- karyawan tidak aktif atau email tidak tersedia ditangani aman.

### 15.4 Regression test

- command import existing tetap bekerja;
- CRUD jadwal manual tidak berubah;
- review riwayat existing tetap mempertahankan keputusan confirmed;
- pengajuan cuti/insentif roster lama tetap kompatibel tanpa `roster_schedule_id`.

## 16. Runtime dan Deployment

Project aktual menggunakan Laravel 13 dengan requirement `php: ^8.3`, sedangkan dependency hasil lock saat ini memerlukan PHP minimal 8.4.1. Pengujian lokal harus memakai `C:\xampp\php85\php.exe`; PHP XAMPP 7.4 tidak kompatibel.

Urutan rollout:

1. backup database;
2. deploy code;
3. jalankan migration non-destruktif;
4. pastikan queue worker dan scheduler aktif;
5. jalankan automated test;
6. jalankan dry-run workbook referensi;
7. uji satu upload melalui admin;
8. pantau import history, failed jobs, scheduler cleanup, dan pengiriman reminder;
9. gunakan workbook production setelah hasil verifikasi diterima.

Rollback aplikasi tidak menghapus jadwal yang telah berhasil diimpor. Migration hanya menambahkan kolom nullable dan index. Data hasil import dipertahankan agar rollback deployment tidak menjadi operasi destruktif.

## 17. Kriteria Penerimaan

Fitur diterima ketika:

- admin dapat mengunggah workbook referensi dan melihat preview;
- pasangan NIK dan nomor KTP menjadi validasi identitas utama;
- perbedaan nama hanya menjadi warning jika identitas cocok;
- satu blocker mencegah seluruh import;
- file kegagalan lengkap dapat diunduh selama 12 jam;
- konfirmasi menjalankan import atomic melalui queue;
- jadwal lanjutan tersedia sampai akhir dua tahun ke depan;
- reminder H-14 dan reminder terlambat bekerja tanpa duplikasi;
- pengajuan existing mencegah reminder yang tidak perlu;
- file sensitif terhapus otomatis setelah 12 jam;
- authorization, audit trail, test, dan feedback UI memenuhi standar project.
