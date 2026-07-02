# CV Maker Progress Snapshot Design

## Tujuan

HRIS menampilkan status progres pengisian CV Maker per karyawan dan memberi badge `Perlu Diingatkan` ketika user belum melanjutkan pengisian lebih dari 24 jam. Fitur ini hanya berjalan di HRIS dan tidak mengubah status atau struktur database aplikasi CV Maker.

## Sumber Data

HRIS membaca database CV Maker melalui koneksi `cv_maker` yang sudah ada. Relasi karyawan memakai hash NIK yang sama dengan modul `CvMakerCompareService`.

Tabel CV Maker yang dibaca:

- `users`
- `cv_profiles`
- `cv_emergency_contacts`
- `cv_educations`
- `cv_experiences`
- `cv_certifications`
- `cv_languages`
- `cv_projects`
- `cv_organizations`
- `cv_documents`

Data sensitif seperti isi file dokumen, path storage, dan nomor identitas lengkap tidak disalin ke tabel progress HRIS.

## Definisi Tahap

Tahap mengikuti wizard resmi CV Maker:

1. `personal` - Data Pribadi
2. `summary` - Ringkasan Profil
3. `education` - Pendidikan
4. `experience` - Pengalaman
5. `skills` - Keahlian
6. `certifications` - Sertifikasi
7. `extras` - Tambahan
8. `documents` - Dokumen

Tahap dianggap selesai dengan aturan berikut:

- Tahap 1 selesai jika `full_name`, `birth_date`, `birth_place`, `gender`, `marital_status`, `address`, `phone`, dan `email` terisi.
- Tahap 2 selesai jika `profile_summary` terisi.
- Tahap 3 selesai jika ada minimal satu pendidikan lengkap. `level`, `institution`, dan `graduation_year` wajib; `major` wajib kecuali level SD atau SMP.
- Tahap 4 selesai jika ada minimal satu pengalaman lengkap. `position`, `company`, `department`, `division`, `start_month`, dan `responsibilities` wajib; `end_month` wajib jika bukan pekerjaan saat ini.
- Tahap 5 selesai jika `technical_skills` berisi minimal satu item.
- Tahap 6 selesai jika tidak ada sertifikasi/pelatihan, atau semua baris yang ada memiliki `name`, `issuer`, dan `year`.
- Tahap 7 selesai jika tidak ada bahasa, proyek, atau organisasi, atau semua baris yang ada lengkap sesuai validasi wizard CV Maker.
- Tahap 8 selesai jika dokumen wajib `ktp`, `family_card`, dan `diploma` tersedia.

`current_step` adalah tahap pertama yang belum selesai. Jika semua tahap selesai, `is_complete = true` dan `current_step = 8`.

## Definisi Aktivitas Terakhir

`last_activity_at` adalah waktu terbaru dari:

- `cv_profiles.updated_at`
- `cv_emergency_contacts.updated_at`
- `cv_educations.updated_at`
- `cv_experiences.updated_at`
- `cv_certifications.updated_at`
- `cv_languages.updated_at`
- `cv_projects.updated_at`
- `cv_organizations.updated_at`
- `cv_documents.uploaded_at`
- `cv_documents.updated_at`

Jika tidak ada profil CV Maker, `last_activity_at` bernilai `null`.

## Status Reminder

Badge `Perlu Diingatkan` aktif jika semua kondisi berikut terpenuhi:

- Profil CV Maker ditemukan.
- `cv_profiles.status = draft`.
- `is_complete = false`.
- `last_activity_at <= now() - 24 jam`.

Jika salah satu kondisi tidak terpenuhi, badge reminder tidak aktif.

## Penyimpanan HRIS

HRIS menambahkan dua tabel:

- `cv_maker_progress_statuses`: snapshot terbaru per NIK karyawan.
- `cv_maker_progress_histories`: riwayat perubahan progress/reminder sejak fitur ini aktif.

Snapshot menyimpan NIK, ID user/profile CV Maker, status CV, tahap saat ini, jumlah tahap selesai, total tahap, status selesai, status reminder, aktivitas terakhir, waktu sync terakhir, dan metadata ringkas seperti tahap selesai/belum selesai. Tabel histori menyimpan event perubahan tahap dan perubahan status reminder tanpa menyimpan data pribadi detail.

## Proses Terjadwal

Artisan command `cv-maker:sync-progress` membaca karyawan HRIS aktif secara chunk, mengambil data CV Maker dalam batch, menghitung progress, lalu melakukan upsert snapshot. Command mendukung:

- `--limit=500` untuk batas jumlah karyawan per run.
- `--chunk=100` untuk ukuran batch query.
- `--dry-run` untuk pengecekan tanpa menulis database.

Scheduler menjalankan command setiap jam dengan `withoutOverlapping()`.

## Tampilan HRIS

Halaman Compare CV Maker menampilkan:

- Badge `Perlu Diingatkan` pada kolom CV Maker jika kondisi reminder aktif.
- Tahap saat ini, contoh `Tahap 4/8 - Pengalaman`.
- Aktivitas terakhir.
- Filter status reminder: semua, perlu diingatkan, tidak perlu.

Halaman detail menampilkan ringkasan progress dan daftar riwayat progress terbaru.

## Testing

Test wajib mencakup:

- Kalkulasi tahap lengkap dan tahap pertama yang belum selesai.
- Reminder aktif untuk draft tidak lengkap yang idle lebih dari 24 jam.
- Reminder tidak aktif jika sudah lengkap, status bukan draft, atau aktivitas kurang dari 24 jam.
- Sync snapshot membuat histori saat tahap berubah atau reminder berubah.
- DataTables tetap mengembalikan badge progress tanpa membuka akses data sensitif.

## Risiko Production

- Riwayat progress sebelum fitur ini aktif tidak tersedia. Snapshot pertama hanya menjadi baseline awal.
- Jika schema CV Maker berubah, service harus tetap gagal aman dan mencatat warning tanpa membuat halaman HRIS error.
- Scheduler di shared hosting bergantung pada cron `php artisan schedule:run`.
