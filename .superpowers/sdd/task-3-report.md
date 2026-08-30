# Task 3 Report — Reminder Ulang untuk Jadwal Overdue

## Status

Implementasi selesai pada worktree `codex-roster-priority-manual-submission` dengan alur TDD. Reminder terjadwal tetap memakai semantik H-N lama, tetapi sekarang hanya untuk jadwal dengan realisasi `pending`. Reminder overdue merupakan jalur manual terpisah yang tidak membuat approval.

## Perubahan

- Menambahkan konfigurasi `roster.overdue_reminder_cooldown_hours` dengan default 24 jam.
- Menambahkan `isOverdueEligible()` dan `dispatchOverdue()` pada eligibility service.
- Claim `reminder_queued_at` dilakukan melalui conditional database `UPDATE` yang memuat ulang seluruh syarat eligibility dan `whereNull('reminder_queued_at')`.
- Menambahkan mode `scheduled` dan `overdue` pada job dengan properti/constructor yang kompatibel PHP 7.4 dan default mode scheduled untuk caller lama.
- Job tetap unik per schedule tanpa memasukkan mode ke `uniqueId()`, sehingga scheduled dan overdue tidak bisa berjalan bersamaan.
- Job melakukan revalidasi backend sesuai mode dan melepas claim bila realisasi/status berubah.
- Kegagalan queue dispatch melepaskan claim milik percobaan tersebut saja sebelum best-effort release Laravel unique-job lock, melaporkan exception, lalu mengembalikan `false`.
- Final failure reminder ulang selalu melepaskan claim dan mencatat error generik tanpa menghapus `reminder_sent_at` dari pengiriman sebelumnya.
- Properti mode memiliki declared default `scheduled`, sehingga payload job lama yang belum menyimpan field mode tetap aman setelah deployment.
- Menambahkan copy email/database khusus overdue dengan subject `Tindak Lanjut Jadwal Roster Terlewat`, penjelasan bahwa periode telah dimulai, tanpa label H-0.
- Menambahkan route POST `roster-schedules.reminder.overdue` dengan middleware menu `roster_schedule` dan role `Super Admin,HR`.
- Menambahkan controller action yang mengantrekan job, mencatat event audit `roster_schedule.overdue_reminder_queued`, dan memakai pesan sukses yang jujur bahwa reminder telah `masuk antrean`.
- Menambahkan form POST/CSRF pada daftar admin dengan state eligible, queued, cooldown, waktu eligible berikutnya, serta duplicate-submit loading state `Memasukkan ke antrean...`.
- Memperbaiki assignment inline Blade yang sebelumnya menghasilkan compiled PHP tidak valid ketika blok aksi baru dirender, dengan mengubahnya menjadi blok `@php ... @endphp`.

## TDD dan Testing

### RED

Command:

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php
```

Hasil awal: 5 gagal, 13 lulus. Kegagalan tepat pada kontrak baru: realisasi non-pending masih eligible, method overdue belum ada, dan mode overdue belum ada.

Regression test tambahan untuk queue-dispatch failure awalnya gagal karena unique-job cache lock Laravel masih tertinggal walaupun database claim sudah dilepas. Implementasi kemudian diperbaiki agar kedua lock/claim dilepas dan retry benar-benar masuk queue.

### GREEN

Command:

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php tests/Feature/RosterSchedulePriorityTest.php tests/Feature/RosterScheduleApplicationLinkTest.php tests/Feature/RosterScheduleImportPreviewTest.php tests/Feature/RosterScheduleImportLifecycleTest.php tests/Feature/RosterScheduleImportJobTest.php tests/Feature/RosterScheduleImportControllerTest.php tests/Feature/CleanupExpiredRosterImportsTest.php
```

Hasil: **111 passed, 664 assertions**, exit code 0.

Validasi tambahan:

- `php -l` untuk service, job, notification, dan controller: tidak ada syntax error.
- `php artisan route:list --name=roster-schedules.reminder.overdue`: route POST terdaftar.
- `git diff --check`: tidak ada whitespace error.

## Self-review Risiko Utama

- **Concurrency:** conditional update dan `reminder_queued_at IS NULL` memastikan hanya satu request memenangkan claim; job unik tetap berbasis schedule, bukan mode.
- **Cooldown:** `reminder_sent_at <= now()->subHours(24)` eligible; 23 jam tidak eligible; boundary tepat 24 jam diuji.
- **Dispatch failure:** database claim dibersihkan hanya jika timestamp masih sama dengan claim percobaan ini; Laravel unique-job lock juga dilepas agar retry tidak menjadi false-positive.
- **Cache failure:** kegagalan saat release unique lock tidak dapat mencegah cleanup claim database dan tetap dilaporkan terpisah.
- **Final queue failure:** reminder overdue yang sudah pernah terkirim tetap melepas claim dan menyimpan failure generik ketika seluruh retry habis.
- **Deployment compatibility:** payload job lama tanpa field `mode` otomatis memakai mode `scheduled`.
- **Revalidation:** perubahan realisasi ke `cuti_roster` setelah queued menghentikan notifikasi dan membersihkan claim.
- **Copy:** subject overdue eksplisit, intro menyebut `telah dimulai`, payload database mengandung `:overdue:` dan tidak memuat `H-0`.
- **Authorization/audit:** hanya HR/Super Admin yang memiliki menu dapat memanggil endpoint; action queued dicatat pada audit trail; request yang tidak eligible tidak diaudit sebagai queued.
- **Production compatibility:** tidak ada package, migration, approval, promoted/readonly property, atau syntax baru yang ditambahkan ke file runtime Task 3.

## Catatan Production

- Bila nilai cooldown perlu diubah, set `ROSTER_OVERDUE_REMINDER_COOLDOWN_HOURS`; UI dan backend membaca config yang sama.
- Queue worker harus aktif agar status `Dalam antrean` dilanjutkan menjadi pengiriman.
- Tidak ada migration baru pada Task 3 karena kolom reminder sudah tersedia dari fondasi fitur sebelumnya.

## Review

Code review independen awal menemukan tiga isu penting: final failure reminder ulang dengan `reminder_sent_at` lama, payload job lama tanpa field `mode`, dan cleanup claim yang bergantung pada keberhasilan release cache lock. Ketiganya diperbaiki dengan regression test.

Re-review final tidak menemukan issue Critical, Important, maupun Minor dan memberi assessment **Ready to merge**. Reviewer juga menjalankan focused suite dan `git diff --check` dengan hasil bersih.

## Fix Review — Mandatory Findings

Patch mandatory-review ditemukan sebagai perubahan uncommitted dari turn fixer sebelumnya yang terhenti. Patch tersebut tidak langsung dianggap benar: seluruh diff diadopsi setelah audit manual terhadap `UniqueLock`, `PendingDispatch`, dan pelepasan lock oleh queue worker, lalu diverifikasi ulang dengan focused dan full roster regression suite.

### Perbaikan

- Dispatch overdue sekarang mengambil Laravel unique-job lock secara eksplisit sebelum memanggil bus dispatcher. Jika lock sudah dimiliki job/request lain, method mengembalikan `false`, tidak mendorong job baru, dan membersihkan hanya database claim dengan timestamp milik attempt ini. Lock milik pihak lain tidak dilepas.
- Jika enqueue melempar exception setelah lock berhasil dimiliki, database claim dibersihkan terlebih dahulu dan unique lock milik attempt ini dilepas secara best-effort. Kegagalan release lock dilaporkan tanpa menghalangi cleanup claim.
- Endpoint HTTP hanya mengaudit dan menampilkan sukses `masuk antrean` ketika dispatch benar-benar memperoleh unique lock dan diteruskan ke queue. Stale lock menghasilkan warning generik tanpa audit queued.
- Daftar admin memakai dua query terbatasi ID pada halaman aktif: query pertama memakai predicate backend penuh untuk eligibility, query kedua mengidentifikasi aplikasi digital aktif agar alasan disabled akurat. Jumlah query tetap konstan dan tidak bertambah per baris/N+1.
- Tombol untuk karyawan resign atau jadwal dengan aplikasi aktif dirender disabled dengan alasan `Karyawan tidak aktif` atau `Pengajuan digital aktif`, sehingga HR melihat penyebabnya tanpa query per baris.

### RED — mandatory review regressions

Command:

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php
```

Hasil sebelum fix: **3 failed, 27 passed (146 assertions)**, exit code 1.

- Service mengembalikan `true` saat unique lock sudah ditahan, bukan `false`.
- HTTP path tidak menampilkan warning karena stale lock masih dianggap sukses queued.
- Tombol karyawan resign dan jadwal dengan aplikasi aktif belum disabled.

### GREEN — mandatory review regressions

Focused command:

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php
```

Hasil final setelah follow-up review: **30 passed (166 assertions)**, exit code 0.

RED tambahan untuk alasan unavailable yang eksplisit:

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index_disables_overdue_reminder_for_resigned_employee_and_active_application
```

Hasil sebelum copy alasan diperbaiki: **1 failed (4 assertions)**, exit code 1. Row masih menampilkan label generik `Reminder tidak tersedia` dan tidak memuat `Karyawan tidak aktif`.

GREEN tambahan setelah alasan spesifik dirender:

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index_disables_overdue_reminder_for_resigned_employee_and_active_application
```

Hasil: **1 passed (9 assertions)**, exit code 0.

RED follow-up reviewer untuk blocker yang tumpang tindih:

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index_disables_overdue_reminder_for_resigned_employee_and_active_application
```

Hasil: **1 failed (4 assertions)**, exit code 1. Karyawan resign + cooldown masih hanya menampilkan `Dalam cooldown`; aplikasi aktif + queued masih berpotensi hanya menampilkan `Dalam antrean`.

GREEN final setelah business blocker diprioritaskan dan query count dibatasi:

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index_disables_overdue_reminder_for_resigned_employee_and_active_application
```

Hasil: **1 passed (16 assertions)**, exit code 0. Test memakai beberapa aplikasi aktif dan memastikan tepat dua query batch terkait `cuti_roster.roster_schedule_id`, bukan query per row.

Full roster regression command:

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php tests/Feature/RosterSchedulePriorityTest.php tests/Feature/RosterScheduleApplicationLinkTest.php tests/Feature/RosterScheduleImportPreviewTest.php tests/Feature/RosterScheduleImportLifecycleTest.php tests/Feature/RosterScheduleImportJobTest.php tests/Feature/RosterScheduleImportControllerTest.php tests/Feature/CleanupExpiredRosterImportsTest.php
```

Hasil final: **114 passed (692 assertions)**, exit code 0.

Validasi tambahan follow-up:

- `php -l app/Services/Roster/RosterScheduleReminderEligibilityService.php`: tidak ada syntax error.
- `php -l app/Http/Controllers/Admin/RosterScheduleController.php`: tidak ada syntax error.

### Self-review Follow-up

- **Stale unique lock:** gagal acquire tidak pernah melepaskan lock karena lock tersebut bukan milik attempt ini; claim database attempt dibersihkan dan response tetap gagal/warning.
- **Successful unique lock:** setelah queue menerima job, lock milik job tetap tertahan; regression membuktikan competing job tidak dapat mengambil lock sebelum worker melepaskannya.
- **Queue-dispatch exception:** sesudah lock diperoleh, exception dispatch membersihkan claim berdasarkan timestamp yang sama dan melepaskan lock sendiri agar retry berikutnya tidak terblokir.
- **Concurrency:** conditional database claim tetap menjadi pemenang tunggal sebelum unique lock; cleanup `where('reminder_queued_at', $claimedAt)` tidak dapat menghapus claim yang lebih baru.
- **Cooldown boundary:** query ID UI memakai `overdueEligibleQuery()` yang sama dengan backend, jadi boundary tepat 24 jam tetap konsisten.
- **UI/backend parity:** eligibility backend dan aplikasi aktif dievaluasi untuk seluruh ID halaman dalam dua query batch tetap. Alasan business blocker diprioritaskan atas cooldown/queued agar UI tidak menyiratkan job pasti terkirim ketika revalidation akan menghentikannya.
- **Audit/copy:** stale lock tidak membuat audit queued atau toast sukses; sukses yang sah tetap menyebut `masuk antrean`; copy overdue tetap tidak memakai H-0.

### Independent Re-review

Reviewer independen menemukan satu Important pada iterasi awal: alasan resign/aplikasi tertutup bila row sekaligus cooldown/queued. Regression overlap kemudian ditambahkan dan UI diperbaiki. Re-review final tidak menemukan issue Critical atau Important; catatan Minor tentang dokumentasi dan perlindungan N+1 diselesaikan dengan pembaruan report serta assertion tepat dua query batch.
