# Perbaikan UI V-People — 27 Oktober 2026

Perbaikan ini menindaklanjuti `ui-audit-2026-10-27.md`. Fondasi Blade, Bootstrap, Kaiadmin, jQuery, dialog, dan permission yang sudah ada tetap digunakan.

## Yang diterapkan

| Temuan audit | Perubahan |
| --- | --- |
| Tabel approval mobile terpotong | Delapan daftar cuti/izin/roster/OFF HOD dan HR memiliki area scroll berlabel. Empty state berada di luar tabel agar pesannya terbaca penuh pada HP. |
| Navigasi HR tidak tersedia di mobile | Sidebar yang sama kini dapat dibuka sebagai drawer melalui tombol 44 px. Menu mengikuti permission existing; tersedia backdrop, Escape, pengelolaan fokus, dan background inert saat menu terbuka. |
| Form izin kehilangan input/error | Create/edit memakai field bersama, `old()`, error summary dan error per field, label terhubung, field wajib, serta petunjuk JPG/PNG maksimal 2 MB. Kategori PAID hanya diwajibkan dan dikirim ketika PAID dipilih. |
| Dialog import roster tidak konsisten | Script memakai `AppDialog`, tidak melewati konfirmasi, membatasi klik ganda, memulihkan tombol saat gagal, dan menampilkan error sesi/jaringan. Polling memakai label/badge dari model serta menyediakan tindakan refresh ketika berhenti. |
| Monitor timeout | Pemeriksaan kolom SLA digunakan ulang per instance, monitor hanya menghitung tipe anomali yang dibutuhkan, dan ringkasan approval/closing disimpan 60 detik. Kegagalan ringkasan ditampilkan sebagai tidak tersedia, bukan indikator normal bernilai nol. |
| Dua pagination pada approval | DataTables client-side pada delapan daftar tersebut diganti satu pagination Laravel dengan pencarian nama/NIK di server. Filter tetap berada di dalam scope akses existing. |
| Daftar role memuat semua user | Pagination 25 akun dengan pencarian server untuk nama/NIK/email/peran. Nomor baris mengikuti halaman. |
| Aksesibilitas/kontrol kecil | Label form izin terhubung; label checkbox hari off dan select shift menyebut karyawan/tanggal. Target sentuh diperbesar. Pada HP, nama tetap sticky sementara pola kerja/tanggal dapat digeser. |
| Gaya tidak konsisten | Styling bersama untuk card, header, tombol, input, tabel, kontrol DataTables, fokus keyboard, dan reduced motion diterapkan pada layout utama. Dashboard HR lebih ringkas dan menempatkan angka utama sebelum monitor upload. |
| Teks/konteks keliru | Judul edit SP diperbaiki, jumlah baris riwayat import mengikuti paginator, judul tab mengikuti halaman, dan email reset memakai lebar fleksibel. Ditambahkan 27 terjemahan Mandarin untuk label baru. |

Style khusus modul tetap dipertahankan. Ini memperbaiki fondasi bersama dan area audit yang bermasalah; bukan penulisan ulang setiap template.

## Hasil verifikasi

- **65 tes Laravel lulus**, 3.694 assertion, pada kelompok tes yang terkait.
- **3 tes JavaScript lulus**: batal konfirmasi tidak mengirim request, import yang dikonfirmasi hanya dikirim sekali, dan error sesi terlihat tanpa SweetAlert2 serta memulihkan tombol.
- Seluruh Blade berhasil dikompilasi melalui `php artisan view:cache`.
- PHP/JavaScript yang berubah diperiksa sintaksnya; diff diperiksa untuk whitespace error.
- Tes database memakai SQLite in-memory. Tidak menjalankan migrasi atau mengubah data bisnis lokal/produksi.

Tes baru mencakup pencarian pengajuan di luar 100 data pertama, pelestarian scope query, pagination role, pemulihan input dan escaping form, label input, empty state, pemakaian ulang ringkasan, serta kegagalan ringkasan yang terlihat.

## Pengukuran Monitor Terpusat

Pengukuran lokal terhadap service, pada database dan koneksi yang sama:

| Kondisi | Query | Waktu |
| --- | ---: | ---: |
| Sebelum | 247 | 28,43 detik |
| Sesudah, pembukaan pertama | 115 | 11,25 detik |
| Sesudah, ringkasan dipakai ulang | 43 | 4,31 detik |

Angka tersebut merupakan waktu service, bukan jaminan waktu total halaman atau hasil uji beban. Halaman `/admin/monitor-terpusat` juga berhasil dibuka di browser tanpa timeout. Ringkasan approval dan closing dapat tertinggal sampai 60 detik; keterangan ini ditampilkan di UI.

## Verifikasi browser

- Desktop 1366 × 900: dashboard HR, monitor, dan daftar role.
- Mobile 390 × 844: tabel approval, pencarian/empty state, drawer menu, Escape/Tab, dan form izin.
- Form izin: setiap input memiliki label; pemilihan PAID mengaktifkan kewajiban kategori, UNPAID menonaktifkannya.
- Daftar role: 25 baris dan satu pagination server; tidak ada wrapper DataTables client-side.
- Dashboard: kartu ringkasan tampil sebelum upload monitor.
- Tidak melakukan approval/reject, import, submit pengajuan, perubahan role, atau penghapusan pada data aplikasi melalui browser.

## Penerapan

Tidak ada paket, migrasi, worker baru, atau npm build yang diperlukan. Sertakan semua perubahan PHP, Blade, CSS, JS, dan katalog terjemahan.

Saat menerapkan ke hosting:

1. Naikkan `ASSET_VERSION` sesuai versi rilis agar browser mengambil CSS/JS yang baru.
2. Jika konfigurasi dicache, perbarui cache konfigurasi mengikuti prosedur deployment project.
3. Bersihkan cache view atau bangun ulang dengan `php artisan view:cache`.
4. Pastikan cache Laravel dapat ditulis; ringkasan monitor menggunakan cache existing dengan TTL 60 detik.
5. Periksa menu pada akun HR, HOD, admin divisi, dan karyawan, termasuk tombol keputusan pada pengajuan yang memang boleh diproses.

Tidak melakukan deployment atau commit otomatis.

## Batas verifikasi

Belum dilakukan uji seluruh kombinasi permission khusus, beban 5.000 pengguna, kamera/liveness/GPS pada perangkat nyata, pengiriman email, render PDF/email di seluruh client, atau transaksi nyata. Pengujian browser memakai sesi Super Admin; akses dan scope HR, HOD, Admin Divisi, serta Staff diperiksa melalui tes otomatis dengan data uji.

## Perbaikan lanjutan

- Form departemen dan divisi memakai Form Request untuk validasi server, membatasi panjang sesuai kolom database (telepon 16 karakter), dan memeriksa keberadaan perusahaan/departemen. Controller hanya menyimpan data tervalidasi. Pemeriksaan menu mengikuti izin yang sudah digunakan route.
- Form departemen, divisi, dan edit akun memiliki label, nilai lama, ringkasan/penanda kesalahan, status menyimpan, serta pencegahan klik ganda. Tombol minimal 44 px dan status tombol dipulihkan saat kembali melalui browser. Form akun menampilkan status `tidak aktif` dengan benar; NIK/nama ditandai hanya baca.
- Pemeriksaan struktur tabel/kolom monitor dan ringkasan periode dibatasi satu kali per tabel selama satu pemanggilan. Data kepegawaian dan keputusan closing tetap dibaca dengan aturan/scope yang sama; tidak ada cache tambahan untuk keputusan closing.
- Error GPS menghapus status siap dan bukti sebelumnya, mereset pengumpulan lokasi stabil, dan mengunci tombol presensi. Pesan membedakan izin ditolak, lokasi tidak tersedia, dan timeout. Stream kamera dilepas jika pembukaan kamera gagal.
- Respons provider wajah HTTP 401/403/429/5xx dianggap gangguan provider dan mengikuti konfigurasi fail-open/fail-closed. Hasil penolakan biometrik pada respons normal tetap mengikuti keputusan yang ada. Tes menggunakan payload simulasi tanpa memanggil provider eksternal.
- URL stylesheet PDF slip gaji diperbaiki agar terbaca di Windows/Linux. Slip dan kontrak menggunakan font Mandarin yang sudah ada di proyek melalui partial yang juga dipakai CV. Font disubset saat export agar berkas tetap kecil. Kolom informasi membungkus nama panjang; judul pasal kontrak diusahakan tetap bersama paragraf berikutnya.
- Email verifikasi/reset memakai viewport mobile dan pembungkusan nama panjang. Contoh HTML diperiksa pada lebar 390 px tanpa horizontal overflow. Tidak mengirim email sebenarnya.

### Hasil pemeriksaan lanjutan

- Monitor, pemanggilan service lokal dengan cache array terisolasi: tanpa cache **82 query / 8,29 detik**, pengulangan **25 query / 2,56 detik**. Pada tahap sebelumnya: 115 query / 11,25 detik dan 43 query / 4,31 detik. Ini pengukuran service di lingkungan lokal, bukan uji beban atau waktu seluruh halaman; waktu dapat berubah mengikuti database dan cache.
- **87 tes Laravel / 3.898 assertion** dan **5 tes JavaScript** lulus. Cakupan termasuk validasi/isian lama, akses monitor walau menu dipaksakan, penolakan akses form organisasi, scope presensi HOD/Admin Divisi, akses slip gaji, fallback provider, dan pembatalan status siap GPS setelah error.
- PDF contoh memakai data fiktif: slip pengguna/admin satu halaman dengan nama panjang dan Mandarin, kontrak lima halaman dengan penanda akhir terverifikasi. Font normal/bold dan pemuatan stylesheet PDF diperiksa oleh tes render. Pemeriksaan visual dilakukan dengan hasil render Poppler.
- Seluruh Blade berhasil dikompilasi; syntax JS, katalog JSON terjemahan, dan pemeriksaan whitespace diff lolos.
- Browser mobile 390 × 844: form departemen/divisi tidak overflow, seluruh input memiliki label, tombol 44 px. Tidak mengirim form atau mengubah akun/data bisnis pada database aplikasi.

Penerapan lanjutan tetap tanpa migrasi atau paket baru. Pastikan `storage/fonts/NotoSansSC-Regular.ttf` yang sudah ada ikut tersedia di hosting, direktori font/cache dapat ditulis, serta `ASSET_VERSION` dan cache view diperbarui saat deployment.
