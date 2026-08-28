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
