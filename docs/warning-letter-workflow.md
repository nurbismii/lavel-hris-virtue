# Pengajuan dan penerbitan surat peringatan

Karyawan yang dilaporkan cukup terdaftar di master `employees`, tanpa harus memiliki akun `users`. Akun login hanya diperlukan untuk pengaju dan pemeriksa. Pemilihan NIK tetap mengikuti cakupan akses admin; pengajuan maupun approval tidak membuat akun karyawan secara otomatis.

Menu **Data Pelanggaran → Buat Pelanggaran** menerima pengajuan admin sesuai cakupan akses karyawan. NIK wajib cocok dengan master, dan identitas karyawan diambil kembali oleh backend. Masukkan HOD, level SP, tanggal mulai berlaku, uraian kejadian, dan pelapor. Tanggal akhir otomatis enam bulan setelah tanggal mulai dan dihitung ulang oleh backend. Pengajuan masuk status menunggu; belum menambahkan data ke `sp_report`.

HR/Super Admin dengan akses menu pelanggaran membuka **Pengajuan & Approval**, memeriksa rincian, lalu menyetujui atau menolak. Penolakan wajib beralasan. Persetujuan menyimpan identitas pemeriksa, waktu, nomor surat, dan salinan data serta tanda tangan; sekaligus memasukkan satu record ke `sp_report`. Surat tersedia sebagai PDF pada detail pengajuan dan tautan dari riwayat karyawan. HOD menandatangani kolom kosong secara manual.

## Persiapan deployment

- Deploy perubahan dalam satu rilis. Gunakan maintenance window singkat jika aplikasi sedang aktif karena pembacaan riwayat SP sekarang memakai tabel pengajuan.
- Jalankan hanya migration fitur ini: `php artisan migrate --path=database/migrations/2026_09_11_120000_create_warning_letter_requests.php --force`.
- Muat ulang config/route/view cache sesuai prosedur deployment. File konfigurasi baru: `config/warning_letters.php`. Override opsional: `WARNING_LETTER_NUMBER_CODE`, `WARNING_LETTER_PLACE`, `WARNING_LETTER_COMPANY`.
- Restart worker queue yang berjalan setelah mengganti kode import. Import dan approval memakai lock penomoran yang sama.
- Lengkapi nama, jabatan, dan gambar PNG/JPEG pihak pertama pada master tanda tangan kontrak elektronik. Approval ditolak jika data tanda tangan belum tersedia.
- Pastikan PHP GD tersedia, font `storage/fonts/NotoSansSC-Regular.ttf` ikut deployment, serta `storage/app/private/warning-letters` dan `storage/framework/cache` dapat ditulis oleh PHP.

Migration hanya menambah `warning_letter_requests` dan `warning_letter_sequences`; tidak mengubah data atau struktur `sp_report`. Angka SP berikutnya menggunakan nilai terbesar antara counter dan nomor pada data lama, ditambah satu. Tidak reset tiap tahun. Bulan Romawi dan tahun mengikuti waktu approval pada timezone aplikasi. Masa berlaku pengajuan baru adalah enam bulan kalender setelah tanggal mulai yang dipilih. Jika tanggal yang sama tidak tersedia pada bulan tujuan, gunakan hari terakhir bulan tersebut (contoh 31 Agustus 2026 → 28 Februari 2027). Surat dan pengajuan lama tidak diubah otomatis.

Nomor SP terbit tidak dapat ditimpa melalui import, edit, atau hapus pada modul lama. Import data historis hanya tersedia bagi HR/Super Admin. Data historis tetap mengikuti perilaku lama dan tidak otomatis mendapatkan surat elektronik.

## Pengujian

`php artisan test --compact --filter=WarningLetterWorkflowTest`

Test menggunakan SQLite in-memory dan storage fake, bukan database karyawan. Cakupan: NIK dalam/luar scope, token submit ganda, otorisasi approval, urutan nomor, salinan identitas/tanda tangan, approval ganda, penolakan, kegagalan penyimpanan, PDF sebelum approval, validasi tanggal/alasan, proteksi endpoint lama dan import, serta PDF dengan teks Mandarin dan uraian panjang. Lock transaksi MySQL perlu diuji pada staging dengan dua sesi approval bersamaan sebelum rollout berskala besar.

Uji manual dengan akun admin dan HR terpisah: ajukan satu data uji pada staging, pastikan belum ada di riwayat SP, setujui lewat HR, unduh PDF, lalu pastikan riwayat bertambah satu. Ulangi untuk penolakan dan percobaan mengakses karyawan di luar scope. Jangan menerbitkan SP uji untuk karyawan nyata di production.

## Rollback

Jika perlu rollback aplikasi, pulihkan versi kode sebelumnya dan pertahankan kedua tabel serta file tanda tangan. Jangan menjalankan rollback migration setelah ada pengajuan/approval karena akan menghapus jejak proses; lakukan backup dan rekonsiliasi terlebih dahulu. PDF dibentuk dari snapshot tersimpan dan diunduh melalui endpoint terotorisasi, bukan URL storage publik.
