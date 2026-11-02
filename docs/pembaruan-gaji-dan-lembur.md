# Pembaruan gaji bertanggal efektif dan lembur

Fitur tersedia bagi **HR / Super Admin** dengan akses menu **Slip Gaji Admin**. Buka **Pembaruan gaji** pada sidebar atau tombol di halaman Slip Gaji / Master Lembur.

## Alur HR

1. Unduh template XLSX. Gunakan satu sheet, maksimal 10.000 baris / 5 MB.
2. Isi `nik` sebagai teks (pertahankan nol di depan), `gaji_pokok`, `tunjangan_tetap`, `tunjangan_tidak_tetap`, `berlaku_mulai`, `alasan`, dan `referensi` SK/keputusan.
3. Nominal merupakan komponen **upah bulanan**, tanpa pemisah ribuan; isi `0` bila tunjangan tidak ada. Tanggal berupa `YYYY-MM-DD` atau sel tanggal Excel. Nominal adalah nilai absolut yang baru, bukan persentase/selisih kenaikan.
   Tunjangan tidak tetap harus mencerminkan dasar upah periode terkait; upload versi baru bila nilainya berubah. Sistem tidak menyimpulkan klasifikasi tunjangan dari nama kolom e-payslip.
4. Upload. Validasi berjalan di antrean; status dan jumlah baris tampil di halaman tinjauan. Bila ada baris salah, seluruh file ditahan; unduh detail, perbaiki, dan upload kembali.
5. Tinjau semua halaman, termasuk gaji sebelumnya, komponen upah baru, tanggal efektif, dan referensi keputusan. Centang pernyataan review dan konfirmasi. Hak akses penyetuju diperiksa kembali saat job berjalan.
6. Setelah penerapan, periksa jumlah berhasil/gagal. Jika data berubah setelah pratinjau, baris terkait ditolak; upload ulang **hanya baris yang gagal**. Sebagian berhasil ditandai jelas.

Setiap persetujuan menambahkan versi baru; versi sebelumnya tidak ditimpa. Revisi dengan tanggal efektif sama memilih persetujuan terbaru. Tidak tersedia penghapusan riwayat melalui UI. Indeks `(employee_nik, effective_from, id)` mendukung pencarian upah untuk tanggal tertentu; pasangan import/baris unik menjaga retry agar tidak mengulang perubahan.

File identik dideteksi dengan checksum dan kunci unik database. File disimpan di disk `local`, folder privat `salary-imports`, dan dihapus setelah pratinjau selesai atau job dinyatakan gagal. Rincian normalisasi tersimpan di tabel riwayat import yang sudah ada. Status publik tidak memuat nominal, checksum, atau path file; seluruh endpoint tetap dibatasi HR dan menu payroll.

## Penggunaan upah

- Pilih **Riwayat upah yang disetujui HR** di Master Lembur, tentukan karyawan dan tanggal pelaksanaan. Sistem mengambil versi dengan tanggal efektif terakhir yang tidak melewati tanggal lembur.
- Jika belum ada versi yang berlaku, sistem meminta review/upload; tidak mengambil upah masa depan atau menganggap gaji pokok e-payslip sebagai seluruh dasar upah lembur.
- Data awal dari e-payslip dapat dimasukkan melalui template setelah HR mengklasifikasikan tunjangan tetap/tidak tetap. Sinkronisasi slip tidak menimpa riwayat persetujuan lokal.
- Detail perintah lembur menampilkan estimasi berdasarkan riwayat upah hanya kepada HR/Super Admin yang memiliki menu payroll. Estimasi memakai durasi perintah; bukan bukti pembayaran/realisasi. Jam istirahat, aktual kehadiran, dan persetujuan pembayaran perlu direview HR.
- Pembuatan adendum perpanjangan kontrak menggunakan gaji pokok yang berlaku pada hari setelah akhir kontrak saat ini bila tersedia. Kontrak yang sudah dibuat tidak diperbarui otomatis. Fallback kontrak/e-payslip yang sudah ada dipertahankan ketika riwayat lokal belum tersedia.
- Pembaruan yang berpotensi memengaruhi periode presensi terkunci ditolak saat validasi dan diperiksa ulang saat penerapan. Gunakan alur buka ulang periode yang tercatat. **Fitur ini tidak mengubah slip terbit, mengirim pembayaran, atau membuat/membayar rapel otomatis.** Pembayaran dan rekonsiliasi tetap melalui proses payroll/e-payslip yang berwenang.

## Dasar perhitungan

Diperiksa **31 Oktober 2026**: [JDIH Kemnaker menyatakan PP 35/2021 berlaku](https://jdih.kemnaker.go.id/peraturan/detail/1723/peraturan-pemerintah-nomor-35-tahun-2021). Naskah resmi: [PP 35/2021](https://jdih.kemnaker.go.id/asset/data_puu/PP352021.pdf), Pasal 26 dan 31–34.

- Upah sejam = dasar upah bulanan / 173.
- Dasar upah bulanan = gaji pokok + tunjangan tetap. Bila jumlah ini kurang dari 75% total gaji pokok + tunjangan tetap + tunjangan tidak tetap, gunakan 75% total tersebut (Pasal 32).
- Hari kerja: jam pertama 1,5×; berikutnya 2×.
- Off/libur pola 5 hari kerja: jam 1–8 sebesar 2×; jam 9 sebesar 3×; jam 10–12 sebesar 4×.
- Off/libur pola 6 hari kerja: jam 1–7 sebesar 2×; jam 8 sebesar 3×; jam 9–11 sebesar 4×.
- Libur resmi pada hari kerja terpendek pola 6 hari: jam 1–5 sebesar 2×; jam 6 sebesar 3×; jam 7–9 sebesar 4×. Perintah lembur/rekap membaca jadwal hari keenam bila durasinya lebih pendek. Bila jadwal belum lengkap, HR harus menentukan kategorinya di kalkulator.
- Menit parsial dihitung proporsional; nominal akhir dibulatkan ke rupiah. Lembur hari kerja lebih dari 4 jam mendapat peringatan (ketentuan juga membatasi 18 jam per minggu). Kalkulator satu tanggal belum mengesahkan kepatuhan akumulasi mingguan atau memberikan izin lembur.
- Rentang libur di luar tabel peraturan ditandai sebagai durasi yang belum dihitung otomatis. HR harus mereview; nominal parsial bukan nilai pembayaran final.
- Master perusahaan tidak dapat menurunkan tarif minimum. Bila master tumpang tindih, setiap jam dibayar sekali dengan pengali tertinggi; ketentuan perusahaan yang lebih baik dipertahankan (Pasal 34).
- Rekap HR mempertahankan manfaat minimum Smelter 16 unit yang sebelumnya ada, dan menambahkan selisih ketika jam aktual menghasilkan unit yang lebih tinggi. Rekap masih memakai durasi bruto presensi sesuai alur yang ada; pemeriksaan waktu istirahat/realisasi tetap diperlukan sebelum pembayaran.

Template ini untuk pekerja bergaji **bulanan** pada pola standar 5/6 hari dan 40 jam seminggu. Konversi upah harian (21/25), satuan hasil/rata-rata 12 bulan beserta upah minimum (Pasal 33), pengecualian sektor/jabatan, dan pola roster khusus memerlukan dasar upah/kebijakan yang diverifikasi HR; jangan menganggapnya sudah dihitung otomatis oleh template ini.

## Deployment dan antrean

Tidak ada dependency baru. Proyek saat ini memakai Laravel 13/PHP >=8.3 menurut `composer.json`; perubahan tidak melakukan upgrade runtime.

1. Backup database sesuai prosedur deployment, lalu jalankan `php artisan migrate --force`. Migration baru hanya menambah tabel riwayat upah dan kunci deduplikasi nullable pada `import_histories`; tidak mengubah data e-payslip.
2. Naikkan `ASSET_VERSION` bila environment menggunakannya, lalu jalankan `php artisan config:clear` dan `php artisan view:clear` setelah deploy agar CSS/JavaScript baru terbaca. Pastikan tabel `jobs`/`failed_jobs` dan disk `local` tersedia serta tidak disajikan sebagai direktori publik.
3. Import memakai koneksi `database` secara default agar parsing tidak berjalan di request web walaupun `QUEUE_CONNECTION=sync`. Sesuaikan `SALARY_IMPORT_QUEUE_CONNECTION` dan `SALARY_IMPORT_QUEUE` jika sudah memakai worker lain. Gunakan driver antrean yang persisten, bukan `sync`.
4. Job timeout 600 detik, maksimal 3 percobaan, backoff 30 detik, transaksi per 200 baris. Pastikan `retry_after` koneksi lebih besar dari 660 detik (default proyek 1.800 detik). Worker harus memiliki waktu eksekusi/memori yang memadai.
5. Untuk cPanel, gunakan cron dengan executable PHP dan path proyek yang sebenarnya, misalnya `php /path/project/artisan queue:work database --queue=default --stop-when-empty --tries=3 --timeout=600 --max-time=900`. Hindari worker cron yang bertumpuk. Jangan menjalankan contoh path sebelum disesuaikan.
6. Halaman memeriksa status setiap 5 detik dan berhenti setelah 10 menit atau status akhir. Tombol Refresh status dapat melanjutkan pemeriksaan. Konfirmasi pratinjau kedaluwarsa setelah 24 jam.

## Verifikasi

Jalankan pengujian pada database SQLite terisolasi, tidak pada database produksi:

```text
php vendor/bin/phpunit tests/Feature/EmployeeSalaryWorkflowTest.php tests/Unit/OvertimePayCalculatorServiceTest.php tests/Feature/UserSlipGajiSecurityTest.php tests/Feature/AdminPresensiScopeTest.php
node --test tests/js/employee-salaries.test.js
```

Checklist manual: upload valid; nol di depan NIK; promosi/kenaikan tahunan dengan tanggal berbeda; revisi tanggal sama; baris duplikat/negatif/NIK tidak dikenal; double click; tanggal sebelum baseline; hak akses Staff/HOD; antrean berhenti/gagal; konflik sesudah pratinjau; periode terkunci; hasil kategori 5:2/6:1/hari terpendek; tampilan HP; download detail gagal. Uji MySQL/cPanel dan rekonsiliasi dengan contoh payroll HR sebelum pemakaian produksi.
