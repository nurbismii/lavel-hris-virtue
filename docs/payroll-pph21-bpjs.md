# Payroll gross-up, BPJS, dan arsip final

Master komponen tambahan dan pemulihan data dijelaskan di [Pemulihan dan komponen gaji](pemulihan-dan-komponen-gaji.md). Ekspor payroll langsung dari HRIS kini menambahkan AN/AO serta sheet rincian komponen tambahan; format impor/ekspor Excel lama tetap kompatibel.

## Proses periode baru langsung di HRIS

1. Pilih perusahaan dan periode pada **Proses Payroll**. Sistem mengambil daftar karyawan perusahaan pada bulan payroll dan menetapkan cut-off tanggal 16 bulan lalu sampai 15 bulan payroll. Karyawan yang sudah final untuk periode itu dikeluarkan dari perhitungan baru.
2. Buka **Siapkan data karyawan** bila ada data yang belum siap. Konfirmasi profil pajak dan BPJS saat pertama kali menyiapkan karyawan atau setelah data berubah. Profil bisa dipakai pada bulan berikutnya dalam tahun yang sama. Jika riwayat upah berganti, HR harus memeriksa dasar BPJS dan mengonfirmasi kembali. Masukkan bonus, potongan, dan menit lembur yang disahkan untuk periode itu saja. Saldo pajak masa terakhir diambil dari arsip bila semua bulan sebelumnya tersedia. Jika belum lengkap, isi saldo aktual di isian karyawan.
3. Isi tanggal gajian dan masa pajak terutang. Tekan **Hitung payroll**. Bila ada baris gagal, buka tautan isian pada baris tersebut, perbaiki, lalu tekan **Hitung ulang**. Perubahan isian HR setelah pratinjau menahan finalisasi sampai dihitung ulang. Perubahan sumber lain seperti presensi perlu diikuti perhitungan ulang sebelum hasil disetujui.
4. Tinjau cakupan karyawan dan nominal hasil. Centang pemeriksaan, tekan **Finalisasi**, lalu unduh Excel dari arsip final. Tidak ada unggahan Excel pada alur ini.

Komponen **Tunjangan dan potongan berulang** berlaku dari periode yang dipilih sampai pengaturan berikutnya pada tahun payroll yang sama. Isian bulanan menggantikan nominal berulang untuk komponen itu, termasuk saat diisi 0. Saat memperbarui pengaturan, kosongkan komponen yang ingin dihentikan.

**Laporan beban gaji** pada menu Proses Payroll menampilkan total dan rincian dari arsip final, dengan filter periode, perusahaan, dan NIK. Beban gaji memakai penghasilan kas setelah potongan unpaid leave dan alpa, tunjangan pajak, serta BPJS yang ditanggung perusahaan.

- Gaji pokok dan tunjangan tetap diambil dari riwayat upah yang berlaku selama bulan kalender payroll. Masa kerja dipisahkan ke kolom N tanpa menambah total tunjangan tetap. Jika nominal tunjangan tetap lebih kecil dari masa kerja yang dihitung, periksa Riwayat Gaji. Prorata dan kasus di luar cakupan dapat ditandai dengan alasan pemrosesan terpisah. Hasil menampilkan jumlah karyawan yang dipisahkan.
- Uang makan Rp17.500 per tanggal hadir dalam cut-off dengan jam masuk dan pulang yang lengkap. Presensi yang ditolak, berstatus absen/cuti, atau tanpa jam lengkap tidak menambah uang makan. Presensi lama tanpa status verifikasi dengan dua jam yang valid tetap dapat dihitung. Review yang belum selesai menahan perhitungan.
- Tunjangan masa kerja Rp50.000 per tahun penuh dihitung sampai akhir bulan payroll berdasarkan tanggal masuk.
- Insentif roster = gaji pokok / 30 × jumlah tanggal insentif yang unik dalam cut-off. Sumbernya rentang insentif dan tanggal mingguan berstatus BEKERJA pada pengajuan insentif yang sudah disetujui HOD dan HR. Jadwal impor berstatus insentif tanpa pengajuan yang menetapkan hari memerlukan verifikasi manual.
- HR memasukkan menit yang disahkan pada perintah lembur yang diterima, termasuk 0 jika nihil. Sistem memeriksa rentang perintah dan presensi lalu memakai upah efektif pada tanggal lembur. Untuk ketentuan khusus seperti minimum libur Smelter 5:2, gunakan total lembur yang sudah diverifikasi beserta alasannya pada isian karyawan.

Menu **Kontrak & Gaji → Proses Payroll** tersedia untuk HR/Super Admin dengan izin Slip Gaji Admin. Implementasi menggunakan runtime proyek yang sudah ada (Laravel 13, PHP >=8.3); tidak menambah dependency atau mengubah versi runtime.

## Impor Excel untuk data lama atau kasus khusus

1. Buka bagian **Impor Excel untuk data lama atau kasus khusus** dan unduh template. Salin komponen ke `FORMAT BULK`, mengikuti 38 kolom contoh `KOMPONEN GAJI (01-2026).xlsx`. Isi satu baris `PARAMETER` untuk setiap NIK. Jika memilih penghasilan dari HRIS, gunakan juga `LEMBUR` sesuai `PANDUAN`.
2. Pilih pemberi kerja, periode payroll, **masa pajak terutang**, cut-off, dan tanggal gajian. Masa pajak ditentukan HR berdasarkan saat penghasilan dibayar/terutang; jangan menyamakan otomatis dengan cut-off presensi. Kolom AH/AJ/AK/AL dapat kosong atau harus cocok dengan form. NIK/KTP/rekening harus teks; tanggal ISO atau sel tanggal Excel tanpa jam. Nominal rupiah bulat, tanpa pemisah ribuan.
3. Upload XLSX maksimal 5 MB / 10.000 karyawan. Antrean memvalidasi identitas/perusahaan, parameter, duplikasi, dan komponen, lalu menghitung pajak/BPJS dan membuat ekspor privat. Status, jumlah dibaca/valid/gagal, dan alasan kegagalan tampil di halaman tinjauan. Semua baris harus valid; bila ada kesalahan, perbaiki dan upload ulang **file lengkap**.
4. Tinjau seluruh halaman dan rincian. Centang pernyataan pemeriksaan dan finalisasi dalam 24 jam. Hasil disimpan atomik sebagai snapshot di `payroll_entries`, dengan penyetuju, waktu, versi aturan, dan hash. Finalisasi ulang aman; karyawan tidak dapat difinalisasi dua kali untuk periode payroll atau masa pajak yang sama pada pemberi kerja yang sama.
5. Unduh Excel final. Ekspor mempertahankan 38 kolom contoh dan menambahkan **AM: TUNJANGAN PAJAK**, beserta RINGKASAN, RINCIAN HITUNG, dan PARAMETER. Nominal tersimpan sebagai nilai, tanpa formula/makro/tautan eksternal. NIK/KTP/rekening tetap teks. Arsip tidak ditulis ke database V-Payslip dan tidak mengirim pembayaran.

Perubahan arsip pemberi kerja setelah pratinjau membuat finalisasi ditahan dan meminta perhitungan ulang. Finalisasi masa pajak harus berurutan. Rehire setelah rekonsiliasi masa terakhir dan koreksi arsip final memerlukan workflow tersendiri; hasil final tidak ditimpa. Semua endpoint dan jalur ekspor riwayat import umum menegakkan izin payroll. File sumber dihapus setelah pratinjau selesai/gagal; hasil Excel dan snapshot final tetap privat untuk arsip.

## Arti komponen dan parameter

- **J** adalah gaji sebelum potongan AA/AB. Gaji/tunjangan/lembur berasal dari komponen yang telah diperiksa HR. Modul ini tidak menyimpulkan realisasi lembur atau prorata kehadiran dari presensi.
- Penghasilan kas = J + K + L + M + N + O + P + Q + U + V + W + X + Y + Z + AI − AA − AB. AA/AB merupakan upah yang tidak diperoleh, sehingga mengurangi bruto pajak. AC adalah potongan kas setelah penghasilan diperoleh (misalnya utang); tidak mengurangi bruto pajak.
- `iuran_pensiun_lain` dan `zakat` adalah bagian **yang sudah termasuk AC**, bukan tambahan potongan kas. Keduanya menjadi pengurang pajak tahunan bila memenuhi syarat PMK 168 dan dibayar melalui pemberi kerja. Isi 0 jika nihil. JHT/JP pegawai dihitung sendiri dan tidak dimasukkan lagi ke parameter tersebut.
- `upah_bpjs_tk` dan `upah_bpjs_kes` adalah upah bulanan yang dilaporkan, berdasarkan gaji pokok dan tunjangan tetap. Jangan memakai total pendapatan yang termasuk bonus/lembur atau upah setelah prorata tanpa dasar yang tepat. `upah_minimum` adalah UMK/UMP untuk lokasi kerja yang berlaku, bukan nilai perkiraan.
- `jkk_persen` adalah tingkat risiko **terdaftar**: 0.24, 0.54, 0.89, 1.27, atau 1.74. Flag aktif harus sesuai kepesertaan dan kewajiban masing-masing program; tidak otomatis diasumsikan dari ada/tidaknya nomor BPJS. Keluarga tambahan dihitung 1% per orang di luar keluarga inti yang menjadi tanggungan standar.
- `ptkp` harus ditentukan HR berdasarkan status pajak awal tahun, termasuk aturan karyawati. Sistem tidak menebak dari status perkawinan. NIK KTP valid harus disediakan. `nik_npwp_valid=1` wajib setelah HR memverifikasi identitas pajak terintegrasi DJP; engine tidak menganggap NIK 16 digit otomatis telah valid. Identitas yang belum memenuhi tarif normal ditahan. Lihat [penjelasan resmi DJP tahun 2026 mengenai NIK terintegrasi](https://www.pajak.go.id/index.php/en/node/120452).
- `masa_terakhir=1` untuk bulan berhenti; Desember otomatis masa terakhir. Isi `bulan_mulai_pajak`, `ytd_bulan`, `ytd_bruto`, `ytd_iuran`, `ytd_zakat`, dan `ytd_pph21`. Saldo adalah aktual sebelum masa ini **pada pemberi kerja yang sama**. Bruto YTD sudah termasuk tunjangan pajak dan premi kena pajak. Jumlah bulan harus lengkap sejak mulai bekerja pada tahun tersebut. Jika arsip HRIS lengkap, saldo harus sama persis; jika baru sebagian, saldo tidak boleh lebih rendah dari yang sudah tercatat. Jangan menggabungkan penghasilan pemberi kerja lain atau memasukkan masa berjalan lagi.
- `natura` hanya manfaat nonkas kena pajak yang belum tercakup pada komponen lain. Nilainya menambah bruto pajak, tidak menambah kas diterima. Ringkasan `employer_cost` berarti kas payroll + BPJS perusahaan, belum termasuk biaya natura.

## Aturan yang diimplementasikan

Diverifikasi 6 November 2026; engine membatasi masa pajak **Januari–Desember 2026**, agar batas BPJS tahun berikutnya tidak diterapkan dengan angka lama. Tarif dan sumber tersimpan di `config/payroll.php`; setiap perubahan aturan harus memperbarui `rules_version`, pengujian, dan periode validitas.

- **TER A/B/C** untuk pegawai tetap selain masa terakhir: seluruh bruto kena pajak, termasuk THR, bonus, rapel upah yang dipajaki pada masa ini, natura kena pajak, serta premi Kes/JKK/JKM pemberi kerja. JHT/JP perusahaan tidak ditambahkan sebagai bruto pajak. Batas atas tiap lapisan bersifat inklusif. Sumber: [PP 58/2023, Lampiran A–C](https://jdih.kemenkeu.go.id/api/download/e47c3fc4-a912-4bf1-bcad-335fee3f71f8/2023pp058.pdf) dan [PMK 168/2023, Pasal 5 dan 15](https://jdih.kemenkeu.go.id/api/download/e60a82e0-b218-40f5-9d18-b924aa1e11ce/2023pmkeuangan168.pdf).
- **Gross-up**: mencari tunjangan pajak rupiah bulat yang sama dengan PPh berjalan setelah tunjangan tersebut masuk bruto. Iterasi dimulai dari nol sehingga melewati perubahan lapisan TER secara benar; kegagalan konvergensi tidak menghasilkan nilai diam-diam.
- **Masa terakhir**: penghasilan aktual setahun/bagian tahun, biaya jabatan 5% maksimal Rp500.000 per bulan/Rp6 juta setahun, iuran pensiun/JHT/JP pegawai dan zakat yang memenuhi syarat, PTKP, pembulatan PKP turun ke ribuan, tarif Pasal 17 (5/15/25/30/35%), lalu dikurangi PPh yang sudah dipotong. PPh negatif tetap dicatat sebagai pengembalian kepada karyawan; tunjangan pajak tidak dibuat negatif. Sumber: [PMK 168/2023, Pasal 8–10, 15–16 dan lampiran](https://jdih.kemenkeu.go.id/api/download/e60a82e0-b218-40f5-9d18-b924aa1e11ce/2023pmkeuangan168.pdf).
- **BPJS TK**: JHT pegawai 2% / perusahaan 3,7%; JP 1% / 2%; JKK sesuai risiko dan JKM 0,3% dibayar perusahaan. Batas upah JP Januari–Februari 2026 Rp10.547.400; mulai Maret 2026 Rp11.086.300. Tidak menambahkan potongan JKP baru ke karyawan/perusahaan karena pembiayaannya melalui pemerintah/rekomposisi. Sumber: [BPJS Ketenagakerjaan](https://www.bpjsketenagakerjaan.go.id/artikel/18913/artikel-berapa-besaran-iuran-jht,-jkk,-jkm,-jp-dan-jkp), [surat BPJSTK B/1226/022026, salinan surat bertanda tangan elektronik](https://494075.fs1.hubspotusercontent-na1.net/hubfs/494075/compliance-portal/notification-number-b1226022026.pdf).
- **BPJS Kes PPU swasta**: pegawai 1% / perusahaan 4%, dasar minimal UMK/UMP dan maksimal Rp12 juta; keluarga tambahan dibebankan ke pegawai. Sumber: [informasi resmi BPJS Kesehatan](https://web.bpjs-kesehatan.go.id/uploads/information/4bd28c6ea8f022040f6eb93cfcd6e723.pdf), Perpres 82/2018 beserta perubahannya.
- PPh ke bawah ke rupiah penuh; iuran per komponen ke rupiah terdekat. Tidak membulatkan potongan tiap karyawan ke ratusan hanya untuk menyamakan pembulatan pembayaran agregat BPJS. Rekonsiliasi dengan tagihan BPJS/Coretax tetap menjadi pemeriksaan HR sebelum pembayaran/pelaporan.

**Cakupan**: pegawai tetap domestik dengan kewajiban subjektif sepanjang tahun (`DN_SETAHUN`), fasilitas normal (`NORMAL`). Pegawai yang mulai bekerja di tengah tahun tetapi sudah menjadi subjek pajak domestik sepanjang tahun menggunakan penghasilan aktual pada pemberi kerja tersebut. Kedatangan/keberangkatan yang mengubah kewajiban subjektif, WPLN/PPh 26, pegawai tidak tetap, pajak pesangon/kompensasi/uang pisah, rehire, serta fasilitas DTP/relaksasi belum diotomatisasi dan harus dipisahkan; kolom Z bukan otomatis penghasilan biasa. Untuk 2026 terdapat [PMK 105/2025 tentang DTP sektor tertentu](https://www.jdih.kemenkeu.go.id/dok/pmk-105-tahun-2025/summary); jangan memilih NORMAL untuk pegawai/pemberi kerja yang menggunakan fasilitas tersebut.

## Deployment

Konfigurasi database workspace saat pengerjaan menunjuk server di luar komputer ini. Pengujian menggunakan SQLite terisolasi dan data sintetis; migration belum diterapkan ke database server.

1. Backup database dan deploy file perubahan menurut prosedur perusahaan. Migration menambah `payroll_entries`, `payroll_inputs`, dan kolom kunci deduplikasi nullable di `import_histories`. Data upah dan arsip V-Payslip tetap berada pada sumbernya.
2. Jalankan migration baru secara terarah setelah disetujui operator:

   ```text
   php artisan migrate --path=database/migrations/2026_11_06_000001_create_payroll_entries_table.php --force
   php artisan migrate --path=database/migrations/2026_11_09_000001_create_payroll_inputs_table.php --force
   ```

3. Gunakan disk `local` privat; `storage/app/private` tidak boleh disajikan publik. Pastikan migration riwayat import/kolom konfirmasi yang sudah digunakan Pembaruan Gaji telah terpasang.
4. Payroll memakai koneksi `database` dan mengikuti nama antrean koneksi itu (`QUEUE_NAME`). Jika menetapkan `PAYROLL_QUEUE_CONNECTION` atau `PAYROLL_QUEUE`, jalankan worker pada koneksi dan antrean yang sama. Job timeout 600 detik, 3 percobaan, backoff 30 detik, dan lock overlap 660 detik. `retry_after` harus lebih panjang dari timeout dan lock (default proyek 1.800 detik). Contoh worker yang mengikuti nama antrean koneksi `database` adalah `queue:work database --stop-when-empty --tries=3 --timeout=600 --max-time=900`.
5. Jalankan `php artisan config:clear` dan `php artisan view:clear`, restart worker agar memakai kode/aturan baru; naikkan versi aset jika deployment menggunakan `ASSET_VERSION`.
6. Rekonsiliasi beberapa contoh representatif dengan HR/pajak perusahaan (TER, bonus/THR, gross-up, berhenti, akhir tahun, dan tagihan BPJS) sebelum memakai hasil sebagai nominal pembayaran.

Import membaca per 200 baris dengan lookup karyawan/arsip secara batch. Ekspor menggunakan streaming XML/XLSX agar tidak menyimpan ratusan ribu objek sel di memori. Finalisasi menggunakan transaksi dan constraint unik database. File yang korup atau berubah ditahan dengan pemeriksaan checksum. Polling 5 detik berhenti pada status akhir atau setelah 10 menit; tombol Refresh dapat melanjutkan.

## Verifikasi

```text
php vendor/bin/phpunit --do-not-cache-result tests/Unit/PayrollCalculatorTest.php tests/Feature/PayrollWorkflowTest.php tests/Unit/OvertimePayCalculatorServiceTest.php tests/Feature/EmployeeSalaryWorkflowTest.php tests/Feature/UserSlipGajiSecurityTest.php
node --test tests/js/payroll.test.js
```

Semua pengujian memakai database terisolasi. Cakupan: batas TER A/B/C dan lapisan tertinggi; gross-up yang menyeberangi lapisan; premi kena pajak; batas/floor BPJS; rekonsiliasi contoh resmi PP 58; pengembalian pajak; data hilang/fasilitas tak didukung; NIK nol di depan; duplikasi upload/NIK/finalisasi; data berubah/kedaluwarsa; izin HR dan jalur riwayat import; ekspor sebelum final; file arsip berubah; impor 201 karyawan; ekspor 5.001 baris dan teks menyerupai formula. UI diverifikasi pada desktop dan viewport 390 px memakai data sintetis, termasuk dialog konfirmasi/batal dan respons validasi gagal tanpa penyimpanan.
