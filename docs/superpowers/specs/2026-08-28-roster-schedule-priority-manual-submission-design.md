# Desain Prioritas Jadwal Roster dan Pengajuan Manual

Tanggal: 2026-08-28
Status: Desain disetujui pengguna

## Latar Belakang

Halaman Jadwal Roster saat ini mengurutkan seluruh data berdasarkan `off_start` paling awal. Urutan tersebut tidak memperhitungkan tanggal hari ini sehingga jadwal lama dapat memenuhi halaman sebelum jadwal yang membutuhkan perhatian HR.

Pada masa transisi, sebagian karyawan juga masih menyerahkan pengajuan langsung ke ruangan HR. Sistem perlu mencatat kondisi tersebut tanpa membuat approval HOD atau HR yang tidak pernah dilakukan secara digital.

## Tujuan

1. Menempatkan jadwal terlambat yang belum direalisasikan sebagai prioritas tertinggi.
2. Menampilkan jadwal hari ini dan jadwal mendatang berdasarkan tanggal yang paling dekat.
3. Mempertahankan jadwal lampau yang sudah selesai sebagai riwayat di bagian bawah.
4. Memungkinkan HR mengirim reminder ulang secara aman dengan cooldown 24 jam.
5. Memungkinkan HR mencatat pengajuan offline sebagai Cuti Roster atau Insentif tanpa memalsukan status approval digital.

## Di Luar Ruang Lingkup

- Membuat approval HOD/HR otomatis dari pengajuan manual.
- Mendigitalkan seluruh isi formulir fisik ke tabel `cuti_roster`.
- Mengubah jadwal reminder otomatis H-14 yang sudah berjalan.
- Mengubah aturan bisnis periode kerja atau perhitungan hak OFF.

## Pengurutan Data

Pengurutan dilakukan pada query database sebelum pagination. Tanggal acuan menggunakan tanggal aplikasi pada zona waktu yang dikonfigurasi Laravel.

Urutan kelompok:

1. Jadwal dengan `off_start` sebelum hari ini dan `realization_type = pending`. Di dalam kelompok ini, tanggal yang paling baru terlewat ditampilkan terlebih dahulu.
2. Jadwal dengan `off_start` hari ini atau setelah hari ini. Di dalam kelompok ini, tanggal terdekat ditampilkan terlebih dahulu.
3. Jadwal lampau yang tidak lagi berstatus `pending`. Di dalam kelompok ini, tanggal terbaru ditampilkan terlebih dahulu.

`employee_nik` digunakan sebagai urutan tambahan agar hasil pagination stabil ketika beberapa jadwal memiliki tanggal yang sama.

Pengurutan menggunakan ekspresi `CASE` dengan parameter tanggal terikat agar kompatibel dengan MySQL/MariaDB dan tidak memasukkan nilai tanggal langsung ke SQL.

## Status Peringatan

Jadwal dikategorikan terlambat mengajukan apabila seluruh kondisi berikut terpenuhi:

- jadwal aktif;
- `off_start` sebelum hari ini;
- `realization_type` masih `pending`.

Baris menampilkan badge merah **Terlambat Mengajukan** dan jumlah hari keterlambatan. Kondisi harus dihitung kembali di backend ketika tindakan diproses; status visual bukan sumber otorisasi.

## Reminder Ulang

### Kelayakan

Tombol **Kirim Reminder Lagi** hanya tersedia ketika:

- jadwal aktif dan terlambat;
- realisasi masih `pending`;
- karyawan masih aktif;
- tidak ada reminder yang sedang berada dalam antrean;
- reminder berhasil terakhir sudah lewat minimal 24 jam.

Reminder yang gagal boleh dicoba kembali selama tidak sedang diantrekan. Backend memeriksa seluruh kondisi kembali untuk mencegah manipulasi request.

### Proses

1. HR mengirim POST dari tombol reminder.
2. Service melakukan atomic claim pada jadwal yang masih memenuhi syarat.
3. Job reminder khusus mode overdue dimasukkan ke queue.
4. Job memeriksa kelayakan kembali sebelum mengirim.
5. Notification menggunakan judul dan isi tindak lanjut keterlambatan, bukan label H-0.
6. Setelah berhasil, `reminder_sent_at` menjadi waktu pengiriman terakhir dan cooldown dimulai kembali.
7. Bila dispatch gagal, claim dilepas dan pengguna menerima pesan aman tanpa stack trace.

Mekanisme reminder otomatis H-14 tetap menggunakan aturan yang sekarang dan tidak diperluas ke jadwal lampau.

### Proteksi

- Route dilindungi middleware menu `roster_schedule` dan role HR/Super Admin seperti halaman induknya.
- Queue job tetap unik per jadwal untuk mencegah pengiriman bersamaan.
- Claim database mencegah klik ganda dan request paralel.
- Tindakan meminta reminder ulang dicatat ke audit trail dengan aktor dan ID jadwal.

## Pengajuan Manual

### Antarmuka

Tombol **Sudah Mengajukan Manual** tersedia untuk jadwal aktif yang masih `pending`, baik sebelum maupun setelah `off_start`. Tombol membuka modal dengan isian:

- jenis realisasi wajib: `Cuti Roster` atau `Insentif`;
- nomor surat opsional;
- catatan opsional.

Form memiliki loading state, tombol dinonaktifkan saat submit, dan feedback menggunakan komponen toast/SweetAlert yang sudah konsisten di project.

### Data

Tabel `roster_schedules` memperoleh kolom nullable berikut melalui migration yang aman:

- `manual_submitted_at`: waktu HR mencatat penerimaan berkas;
- `manual_submitted_by`: ID user HR/Super Admin yang mencatat;
- `manual_reference_number`: nomor surat opsional;
- `manual_submission_note`: catatan opsional.

Pilihan Cuti Roster atau Insentif disimpan pada `realization_type` yang sudah ada. Kolom `source` tidak digunakan karena kolom tersebut menunjukkan asal pembuatan jadwal (`generated`, `manual`, atau `import`), bukan asal realisasinya.

### Proses

1. Form Request memvalidasi role, jenis realisasi, panjang nomor surat, dan panjang catatan.
2. Service membuka transaction dan mengunci baris jadwal.
3. Service memastikan jadwal aktif dan masih `pending`.
4. Service menolak perubahan apabila pengajuan digital yang sah sudah terhubung atau status telah berubah.
5. Service menyimpan `realization_type` dan metadata pengajuan manual.
6. Claim reminder yang belum diproses dilepas. Job yang sudah terambil wajib memeriksa status ulang dan berhenti tanpa mengirim jika realisasi bukan lagi `pending`.
7. Audit trail mencatat nilai lama, nilai baru, aktor, dan sumber tindakan sebagai pengajuan manual.

Pencatatan ini hanya menyatakan bahwa HR menerima pilihan secara offline. Sistem tidak membuat record approval HOD/HR dan tidak menandainya disetujui.

## Komponen yang Terlibat

- Route admin untuk reminder ulang dan pencatatan pengajuan manual.
- Form Request khusus untuk validasi pengajuan manual.
- `RosterScheduleController` sebagai pengatur alur request dan response.
- Service roster untuk aturan transaksi pengajuan manual.
- `RosterScheduleReminderEligibilityService` untuk memisahkan kelayakan reminder otomatis dan reminder overdue manual.
- Job dan notification reminder roster untuk mendukung konteks overdue.
- Model `RosterSchedule` untuk cast/relasi/konstanta yang relevan.
- View `admin.roster-schedules.index` untuk badge, tombol, modal, dan loading state.
- Migration penambahan metadata pengajuan manual.
- Audit trail yang sudah ada untuk pencatatan tindakan penting.

## Respons dan Penanganan Error

- Berhasil reminder: informasikan bahwa reminder masuk antrean, bukan sudah terkirim.
- Cooldown aktif: tampilkan kapan reminder dapat dikirim kembali.
- Sedang antre: tampilkan bahwa reminder sebelumnya masih diproses.
- Status berubah: minta pengguna memuat ulang halaman.
- Akun/email tidak tersedia: job menyimpan pesan kegagalan yang aman dan halaman menampilkan status gagal.
- Pengajuan manual berhasil: tampilkan jenis realisasi yang dicatat dan refresh baris/halaman.
- Validasi gagal: tampilkan pesan pada isian terkait tanpa menghilangkan input modal.

## Pengujian

### Feature test pengurutan

- overdue pending berada di atas semua kelompok lain;
- overdue pending yang paling dekat dengan hari ini berada lebih dahulu;
- jadwal hari ini berada sebelum jadwal mendatang;
- jadwal mendatang diurutkan naik;
- jadwal lampau yang selesai berada paling bawah;
- pagination menghasilkan urutan stabil untuk tanggal yang sama.

### Feature test reminder ulang

- HR/Super Admin yang berwenang dapat mengantrekan reminder overdue;
- user tanpa role/menu ditolak;
- jadwal mendatang, nonaktif, atau sudah direalisasikan ditolak;
- reminder dalam antrean tidak dapat diduplikasi;
- reminder yang terkirim kurang dari 24 jam ditolak;
- reminder dapat dikirim setelah cooldown;
- job berhenti jika status berubah sebelum pengiriman;
- pesan overdue tidak menggunakan format H-0;
- dispatch dan kegagalan tidak mengekspos exception sensitif.

### Feature test pengajuan manual

- hanya jenis Cuti Roster atau Insentif yang diterima;
- nomor surat boleh kosong dan dibatasi panjangnya;
- metadata aktor dan waktu tersimpan;
- `realization_type` berubah sesuai pilihan;
- reminder berikutnya dihentikan;
- klik ganda atau request paralel tidak menghasilkan perubahan ganda;
- pengajuan digital atau jadwal yang sudah direalisasikan tidak dapat ditimpa;
- audit trail tersimpan;
- tidak ada record approval palsu pada `cuti_roster`.

## Deployment dan Rollback

Migration hanya menambah kolom nullable sehingga data lama tetap kompatibel. Deployment menjalankan migration normal; tidak diperlukan `migrate:fresh` atau perubahan data massal.

Rollback migration menghapus hanya kolom metadata pengajuan manual. Nilai `realization_type` yang telah dicatat selama fitur aktif tidak dikembalikan otomatis karena rollback tidak boleh menebak keputusan bisnis. Jika rollback aplikasi diperlukan setelah fitur dipakai, data tersebut harus dievaluasi lebih dahulu oleh HR.
