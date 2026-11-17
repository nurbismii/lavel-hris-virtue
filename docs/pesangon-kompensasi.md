# Pesangon dan kompensasi

Menu **Kontrak & Gaji → Pesangon & Kompensasi** (`/admin/pesangon`). Tidak mengubah status kerja, payroll reguler, maupun saldo cuti HRIS.

## Akses dan alur

Buat/pilih peran melalui **Peran dan Akses** dan berikan menu berikut kepada tiga orang berbeda. Preset `Staff HI`, `Supervisor Payroll`, dan `Supervisor HI` tersedia; modul tidak memberikan akses baru kepada akun HR yang sudah ada.

| Pelaku             | Menu akses          | Tugas                                                                                                       |
| ------------------ | ------------------- | ----------------------------------------------------------------------------------------------------------- |
| Staf HI            | `severance_staff`   | Unggah, lengkapi draf miliknya, tentukan pasal/tanggal/ongkos, kirim ke Payroll; cetak/export setelah final |
| Supervisor Payroll | `severance_payroll` | Periksa lalu setujui tiga komponen upah dari V Payslip                                                      |
| Supervisor HI      | `severance_hi`      | Isi sisa cuti secara eksplisit, periksa pratinjau, lalu setujui final                                       |

Super Admin dapat mengelola seluruh batch, memperbaiki/mengirim draf, menjalankan kedua tahap persetujuan termasuk untuk pengajuan sendiri, serta mencetak/mengexport hasil final. Akun dan waktu pada setiap tindakan tetap direkam. Sisa cuti dan pemeriksaan pratinjau tetap wajib; tahap Payroll harus selesai sebelum tahap HI.

Untuk akun selain Super Admin, pembuat, penyetuju Payroll, dan penyetuju HI wajib berbeda. Akses menu tidak otomatis memberikan akses slip gaji lain. Staf hanya dapat membaca/mengexport batch miliknya. Supervisor melihat pengajuan yang sudah dikirim. Pengembalian wajib memuat alasan dan menghapus persetujuan upah sehingga kedua tahap harus diulang. Hasil final terkunci; perubahan sumber sesudah persetujuan tidak mengubah arsip. Halaman detail menampilkan langkah berikutnya dan alasan jika suatu akun tidak dapat memberikan persetujuan.

## Sumber dan aturan

-   Kartu verifikasi upah menampilkan NIK, nama, perusahaan/area kerja, departemen, divisi, posisi, jabatan, status karyawan/kerja, tanggal masuk/akhir kerja, dan masa kerja. Identitas/tanggal mengikuti pengajuan; unit, posisi, jabatan, dan status dibaca dari HRIS saat halaman dibuka. Data yang belum tersedia ditandai `—`. Gaji pokok, Tunjangan Pengawas, tunjangan masa kerja, dan total upah dasar tetap memakai sumber FULL atau snapshot upah yang telah disetujui.
-   Referensi format: `MASTER PERHITUNGAN PESANGON 2025.xlsx` dan file pengajuan yang diberikan pengguna. Kedua file asli tidak diubah/disalin ke repositori.
-   Header pengajuan di baris 7: NIK B, alasan L, ongkos pulang M (kolom lama belum berjudul), pasal R. Maksimal 5 MB, satu sheet, 10.000 baris. Unduh template dari menu. NIK harus disimpan sebagai teks untuk mempertahankan nol awal.
-   Nama dan tanggal masuk memakai HRIS; biodata pelengkap file dipertahankan sebagai nilai/cache, tanpa menjalankan formula/link eksternal. Formula NIK/alasan/ongkos/pasal ditolak. Kolom gaji/cuti unggahan diabaikan. Format kosong sampai baris Excel paling bawah tidak dihitung sebagai pegawai.
-   V Payslip dibaca melalui koneksi `epayslip`; identitas NIK dan KTP harus cocok tepat satu akun. Pilih slip berstatus `FULL` terakhir dengan `akhir_periode <= tanggal akhir kerja`, lalu urut ID terbaru. `PRORATE`, slip masa depan, dan nilai take-home tidak digunakan; tidak ada perhitungan balik prorata.
-   `gaji_pokok` → gaji pokok; `tunj_pengawas` → tunjangan jabatan; `tunj_mk` → tunjangan masa kerja. Koefisien jabatan tidak dimasukkan. Persetujuan menyimpan nominal, ID/periode sumber dan hash. Jika sumber berubah sejak tampil di layar, Payroll harus memeriksa ulang.
-   Upah dasar pesangon/kompensasi = ketiga komponen di atas. Penggantian cuti = **gaji pokok / 25 × sisa cuti**. Sisa cuti wajib diketik Supervisor HI, termasuk angka 0; tidak membaca/menulis saldo cuti HRIS.
-   Masa kerja berdasarkan tanggal, termasuk hari terakhir. Pesangon dasar maksimum 9 bulan upah. Penghargaan masa kerja memakai tabel lengkap Pasal 40(3), termasuk 12–<15 tahun = 5 bulan dan >=24 tahun = 10 bulan.
-   Pengali master dipertahankan: 40 = 1; 52(1) = 0,5; 55 = 2; 56 = 1,75. Uang pisah 50 menggunakan 0/1,5/2,5 kali gaji pokok pada `tunj_mk / 50.000` di bawah 3 / mulai 3 / mulai 6; 51 menggunakan 0/0,25 pada batas 3; 52(2) = 0,25. Besaran uang pisah adalah kebijakan master perusahaan, bukan besaran yang ditetapkan PP.
-   Pasal 16: bulan penuh / 12 × total upah, tanpa memotong angka bulan menjadi dua karakter. HI wajib meninjau sisa hari serta periode kontrak yang belum pernah dibayar. Cuti dan ongkos tidak ditambahkan pada hasil pasal 16, sesuai master.
-   Perhitungan menggunakan sen dan pembulatan tiap komponen sampai dua desimal. Hasil **bruto sebelum pajak**; pemotongan pajak pesangon/kompensasi, manfaat pensiun, hak tambahan dan penetapan hak secara hukum tidak diotomatisasi.

Rujukan yang diperiksa: [PP 35/2021, Pasal 16, 40, 50–52, 55–56](https://peraturan.bpk.go.id/Download/154582/PP%20Nomor%2035%20Tahun%202021.pdf) dan [Putusan MK 168/PUU-XXI/2023](https://www.mkri.id/perkara/persidangan/putusan?jenis=PUU&page=1&search=168%2FPUU-XXI%2F2023). Supervisor HI memeriksa penerapan pasal/kebijakan pada kasus sebenarnya, termasuk perubahan hukum dan hak yang lebih menguntungkan; modul merekam hasil pemeriksaannya.

## Pemasangan

Tidak ada paket baru. Gunakan runtime dan dependensi proyek saat ini (checkout ini menggunakan Laravel 13/PHP >=8.3).

```sh
php artisan migrate --path=database/migrations/2026_11_15_000001_create_severance_requests_table.php
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Impor dan export menggunakan `import_histories`/`import_history_items` yang sudah ada. Satu tabel baru menyimpan pengajuan dan riwayat persetujuannya; kolom unique di history mencegah dispatch berulang. Constraint NIK + tanggal akhir kerja mencegah pengajuan ganda lintas batch. Migrasi hanya menambah struktur. Jangan rollback setelah digunakan sebelum membackup arsip dan memastikan data baru boleh dihapus.

Antrean default `database`, queue mengikuti default koneksi. Opsional: `SEVERANCE_QUEUE_CONNECTION`, `SEVERANCE_QUEUE`. Worker/cron yang sudah memproses queue tersebut dapat digunakan. Timeout job 300 detik; `retry_after` harus lebih besar dari 360 detik. Contoh cPanel (sesuaikan path dan nama queue jika dikustomisasi):

```sh
/usr/local/bin/php /home/USERNAME/project/artisan queue:work database --stop-when-empty --max-time=600 --timeout=300 --tries=3
```

Gunakan satu proses cron aktif untuk queue ini. Pastikan koneksi V Payslip dapat dibaca dan direktori storage privat dapat ditulis. Jangan menaruh arsip/file sumber pada folder publik.

## Kegagalan dan keluaran

Status antrean, jumlah dibaca/berhasil/gagal, dan alasan gagal muncul di halaman batch/Riwayat Export. Polling 5 detik berhenti setelah selesai/gagal atau 10 menit; Refresh status tersedia. Baris gagal tidak diajukan. Unggah ulang hanya baris yang gagal setelah memperbaiki data. Untuk kegagalan proses sementara, gunakan **Coba lagi** pada batch yang sama agar baris yang sudah tersimpan tidak digandakan.

File sumber disimpan privat dan dihapus setelah impor selesai. Sumber proses yang gagal dipertahankan untuk retry. File export disimpan privat dan diunduh melalui endpoint dengan pemeriksaan pemilik. Retensi arsip/file gagal mengikuti kebijakan penyimpanan organisasi; modul tidak menghapus arsip final otomatis.

Staf dapat memilih satu/beberapa baris final pada halaman aktif, atau **Export semua final**. Maksimal 1.000 pengajuan per export Excel; hasil berisi `PENGAJUAN` dan `RINCIAN HITUNG` dengan nilai tetap yang sudah disetujui. Cetak pilihan maksimal 50 pengajuan. Cetak batch menggunakan A4 portrait dengan empat pengajuan per lembar (2 × 2); sisa pengajuan lanjut ke lembar berikutnya. Isi yang panjang diperkecil agar tetap utuh dalam kotaknya. Cetak satu pengajuan tetap satu lembar A4 landscape. Melalui dialog cetak browser (termasuk Simpan PDF), pilih **1 halaman per lembar**, karena susunan 2 × 2 sudah disiapkan aplikasi. Tidak menjalankan pembayaran atau mengirim pesan/email.

Pratinjau, hasil final, cetak, dan rincian Excel menggunakan poin master: A. Pesangon/Uang Pisah, B. Penghargaan Masa Kerja (bila berlaku), C. Penggantian Hak Cuti, D. Ongkos Pulang, E. Total. Pasal 16 menggunakan A. Kompensasi dan B. Total sesuai master. Label tampilan diterapkan juga pada arsip lama tanpa menghitung ulang atau mengubah snapshot persetujuannya. Jalankan export kembali untuk menghasilkan file berformat baru; file export lama tetap tersimpan sebagai riwayat.

## Verifikasi

```sh
php vendor/bin/phpunit tests/Unit/SeveranceCalculatorTest.php tests/Feature/SeveranceWorkflowTest.php --do-not-cache-result
node --check public/assets/js/severance.js
```

Tes menggunakan identitas, upah dan database SQLite sementara. Cakupan: batas masa kerja/pengali, FULL versus PRORATE/slip masa depan, input cuti kosong/nol, pemisahan tiga pelaku, sumber/pratinjau yang berubah, pengiriman ulang, pengembalian untuk revisi, data final terkunci, akses pemilik, serta cetak/export. Tampilan diuji pada desktop dan lebar ponsel 390 piksel dengan data sintetis.
