# Pemulihan data dan master komponen gaji

## Pemakaian

- **Pengaturan → Tempat Sampah** menampilkan karyawan, perusahaan, departemen, divisi, dan komponen gaji terhapus. HR/Super Admin tetap harus memiliki izin menu modul asal. Pencarian menggunakan ID/NIK persis atau awalan nama, 40 baris per halaman.
- Penghapusan pada modul tersebut memakai soft delete: data, NIK, dokumen, dan riwayat tetap disimpan. Pemulihan mengaktifkan baris yang sama. Permintaan berulang aman; setiap perubahan dicatat melalui Audit Trail yang sudah tersedia. Tidak ada hapus permanen pada fitur ini.
- Master organisasi yang masih dirujuk karyawan, struktur jabatan, atau master turunannya tidak boleh dihapus, termasuk referensi di Tempat Sampah. Impor karyawan menolak NIK terhapus; pembentukan NIK baru tetap memperhitungkan NIK terhapus.
- Data yang sudah dihapus permanen sebelum pembaruan ini tidak dapat dipulihkan tanpa backup. Transaksi cuti/izin/approval dan master jabatan/level belum dimasukkan ke Tempat Sampah ini.

## Komponen gaji dinamis

1. Buka **Kontrak & Gaji → Riwayat Upah** untuk menetapkan gaji pokok dan total tunjangan menurut tanggal berlaku. Buka **Master Komponen Gaji** untuk membuat kode, nama, jenis, dan sifat rincian/pemasukan/potongan. Kode, jenis, dan sifat dikunci setelah pembuatan; nama bisa diperbarui. Gunakan kode baru untuk ketentuan berbeda.
2. Buka **Proses Payroll → Siapkan data karyawan**. Isi nominal komponen tambahan pada isian karyawan. Komponen tetap dapat diberi nilai berulang mulai periode terpilih sampai diperbarui, dalam tahun payroll yang sama. Nilai periode berjalan menggantikan nilai berulang, termasuk 0. Untuk menghentikan nilai berulang, centang ubah pengaturan dan kosongkan/isi 0.
3. **Pemasukan tetap hanya merupakan rincian tunjangan tetap dari Riwayat Upah, sehingga tidak ditambahkan lagi ke penghasilan kas, PPh 21, atau beban gaji.** Jumlah rincian tetap efektif (setelah penggantian bulanan atas nilai berulang) tidak boleh melebihi tunjangan tetap lain sesudah tunjangan masa kerja otomatis. Batas diperiksa saat penyimpanan dan perhitungan, termasuk isian lama. Sisa tunjangan tetap tetap dibayar dari Riwayat Upah.
4. Pemasukan **tidak tetap** merupakan tambahan kas kena pajak di luar upah; potongan mengurangi kas setelah pajak. Bagian tambahan bawaan juga khusus nominal di luar upah. Fitur tidak menebak kesamaan komponen dari nama/nominal; penetapan sifat oleh HR harus sesuai sumber upah. Dasar BPJS tetap ditinjau HR. Contoh: gaji pokok Rp5 juta + tunjangan tetap Rp1 juta menghasilkan upah Rp6 juta. Rincian tetap Rp250 ribu pada master tidak menambah upah itu; insentif tidak tetap Rp100 ribu menambah kas menjadi Rp6,1 juta sebelum komponen lain/potongan.
5. Rincian kode/nama/jenis/nominal beserta penanda sudah termasuk upah disalin ke snapshot payroll. Ekspor langsung dari HRIS mempertahankan A–AM; AN hanya pemasukan tambahan tidak tetap, AO potongan tambahan. Sheet **RINCIAN KOMPONEN** membedakan rincian upah dan tambahan kas. Ekspor sumber Excel lama tetap 39 kolom.
6. Perubahan master setelah antrean/pratinjau, serta revisi Riwayat Upah setelah pratinjau, menahan finalisasi sampai dihitung ulang. Pratinjau lama yang masih menambahkan pemasukan tetap juga ditahan melalui versi aturan komponen. Arsip final dan file yang sudah disahkan tetap tersimpan dengan hasil semula; perbaikan ini tidak mengoreksi pembayaran periode lama secara otomatis.
7. Menghapus komponen menghentikan penggunaannya pada perhitungan baru. Memulihkannya dapat mengaktifkan kembali pengaturan berulang lama; periksa isian karyawan sebelum menghitung ulang. Menghapus rincian tetap tidak mengubah total tunjangan tetap dalam Riwayat Upah.

## Penerapan

Runtime mengikuti proyek saat ini: Laravel 13 dan PHP >= 8.3. Tidak ada dependency baru.

Perbaikan tumpang tindih tidak memerlukan migration tambahan. Deploy kode dan restart worker; hitung ulang antrean/pratinjau payroll lama sebelum finalisasi. Jika fitur awal belum diterapkan, kedua migration di bawah tetap diperlukan.

Database workspace dapat menunjuk server eksternal. Seluruh pengujian fitur menggunakan SQLite dan data sintetis; migration belum dijalankan pada database server.

1. Backup database dan aktifkan maintenance sesuai prosedur deployment. Penambahan kolom pada tabel pegawai/master dapat mengunci tabel; lakukan pada waktu sepi.
2. Terapkan migration berikut sebelum membuka aplikasi dengan model baru:

   ```text
   php artisan migrate --path=database/migrations/2026_11_11_000001_add_recoverable_deletions.php --force
   php artisan migrate --path=database/migrations/2026_11_11_000002_create_salary_components_table.php --force
   ```

3. Deploy kode dan aset, bersihkan cache view/config sesuai prosedur proyek, lalu restart worker payroll. Buka kembali aplikasi setelah kedua migration selesai. Migrasi payroll sebelumnya tetap diperlukan.
4. Uji hapus/pulihkan data sintetis dan satu payroll contoh sebelum penggunaan operasional. Tidak ada migrasi data gaji lama ke komponen tambahan secara otomatis.

Rollback migration ditahan jika masih ada data terhapus atau master komponen berisi data, agar rollback tidak membuka kembali baris terhapus atau membuang master. Untuk rollback kode, pertahankan tabel/kolom tambahan dan gunakan prosedur pemulihan database yang ditinjau operator; jangan membuang data produksi.

## Pemeriksaan

```text
php -d memory_limit=768M vendor/bin/phpunit --do-not-cache-result tests/Feature/DeletedDataWorkflowTest.php tests/Feature/PayrollWorkflowTest.php tests/Feature/EmployeeSalaryWorkflowTest.php tests/Feature/OrganizationStructureServiceTest.php
node --test tests/js/payroll.test.js
```

Pemeriksaan browser memakai HTML yang dirender dari fixture sintetis pada layar kecil dan desktop. Alur gagal menyimpan mempertahankan isian dan memulihkan tombol; konfirmasi pemulihan dapat dibatalkan. Mutasi sebenarnya, perhitungan, dan ekspor diverifikasi oleh feature test terisolasi.

Hasil pemeriksaan 11 November 2026:

- Perbaikan tumpang tindih: 65 tes payroll, Riwayat Upah, pajak/BPJS, dan lembur lulus (586 assertion). Mencakup rincian tetap yang tidak menambah kas, pemasukan tidak tetap yang tetap dibayar, batas total rincian termasuk masa kerja/nilai berulang, penahanan isian lama yang berlebih, pratinjau aturan lama, dan revisi upah setelah pratinjau. Tampilan sumber upah/batas rincian diperiksa pada desktop dan lebar 390 px dengan data sintetis.

- 58 feature test terkait pemulihan, payroll, riwayat gaji, dan organisasi lulus (531 assertion).
- 8 tes JavaScript lulus; pemeriksaan sintaks 95 file PHP yang berubah/baru dan `git diff --check` lulus.
- Regresi menyeluruh terakhir: 579 tes, 11 gagal dan tanpa error. Enam kegagalan berasal dari assertion SQL yang mengharuskan dialek MySQL ketika suite diarahkan ke SQLite; seluruh 33 tes `CvMakerCompareServiceTest` kemudian lulus dengan konfigurasi MySQL dummy (117 assertion).
- Lima kegagalan lain berada pada ekspektasi judul SLA, teks presensi iOS, validasi login passkey tanpa tabel pendukung, tautan email reminder roster, dan kandidat reminder import roster yang bergantung tanggal. Tidak ada perubahan untuk menyesuaikan perilaku modul tersebut dalam pekerjaan ini. Suite menyeluruh belum seluruhnya lulus.
