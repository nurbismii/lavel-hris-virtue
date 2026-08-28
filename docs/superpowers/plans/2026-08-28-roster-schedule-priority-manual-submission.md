# Roster Schedule Priority and Manual Submission Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengurutkan Jadwal Roster berdasarkan urgensi terhadap hari ini, menyediakan reminder ulang overdue dengan cooldown 24 jam, dan mencatat pengajuan offline tanpa membuat approval digital palsu.

**Architecture:** Aturan urutan dan status dipusatkan pada model/query scope, sedangkan mutasi reminder dan pengajuan manual dipusatkan pada service dengan validasi ulang, atomic claim, transaction, dan audit trail. Controller tetap tipis; pengiriman email tetap melalui queue dan notification yang sudah ada, dengan mode overdue terpisah agar reminder otomatis H-14 tetap kompatibel.

**Tech Stack:** Laravel 8.x, PHP 7.4, Eloquent, Blade, Bootstrap 5, SweetAlert2/toast existing, Laravel Queue/Notification, PHPUnit, MySQL/MariaDB production, SQLite in-memory tests.

## Global Constraints

- Jangan menambah package atau memakai fitur PHP di atas PHP 7.4.
- Jangan membuat record `cuti_roster` atau status approval HOD/HR dari pengajuan manual.
- Reminder otomatis H-14 tetap memakai jadwal, command, dan konfigurasi yang sekarang.
- Semua query daftar harus diurutkan di database sebelum pagination.
- Route mutasi hanya dapat diakses role HR/Super Admin dengan menu `roster_schedule`.
- Pesan sukses reminder harus menyatakan “masuk antrean”, bukan “sudah terkirim”.
- Nomor surat dan catatan pengajuan manual bersifat opsional; jenis realisasi wajib Cuti Roster atau Insentif.
- Semua mutasi penting harus aman terhadap klik ganda, dicatat pada audit trail, dan tidak mengekspos stack trace.
- Migration production hanya menambah kolom nullable dan index; jalankan pada jam aktivitas rendah karena pembuatan index dapat mengunci tabel pada sebagian versi MySQL/MariaDB.

---

## File Structure

- Create `database/migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php`: metadata pengajuan manual dan index prioritas.
- Modify `app/Models/RosterSchedule.php`: casts, relasi pencatat manual, helper overdue, dan scope urutan prioritas.
- Modify `tests/Support/CreatesRosterImportSchema.php`: schema fixture yang mencerminkan kolom production baru.
- Create `tests/Feature/RosterSchedulePriorityTest.php`: kontrak urutan lintas kelompok dan helper overdue.
- Modify `app/Http/Controllers/Admin/RosterScheduleController.php`: memakai scope prioritas serta endpoint reminder/manual yang tipis.
- Modify `app/Services/Roster/RosterScheduleReminderEligibilityService.php`: kelayakan dan atomic claim reminder overdue.
- Modify `app/Jobs/SendRosterScheduleReminder.php`: mode reminder scheduled/overdue dan revalidasi sebelum kirim.
- Modify `app/Notifications/RosterScheduleReminderNotification.php`: copy H-N untuk scheduled dan copy keterlambatan untuk overdue.
- Modify `config/roster.php`: cooldown reminder overdue 24 jam.
- Create `app/Http/Requests/Roster/StoreManualRosterSubmissionRequest.php`: authorization dan validasi form manual.
- Create `app/Services/Roster/RosterScheduleManualSubmissionService.php`: transaction, row lock, perubahan realisasi, dan audit.
- Modify `routes/web.php`: dua POST route admin sebelum resource route.
- Modify `resources/views/admin/roster-schedules/index.blade.php`: warning, status manual, tombol, modal, dan loading state.
- Modify `tests/Feature/RosterScheduleReminderEligibilityTest.php`: kontrak mode overdue, cooldown, revalidasi, dan copy notification.
- Create `tests/Feature/RosterScheduleAdminWorkflowTest.php`: authorization, HTTP response, audit, manual submission, dan rendering UI.

---

### Task 1: Fondasi Data dan Model Jadwal

**Files:**
- Create: `database/migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php`
- Modify: `app/Models/RosterSchedule.php`
- Modify: `tests/Support/CreatesRosterImportSchema.php`
- Test: `tests/Feature/RosterSchedulePriorityTest.php`

**Interfaces:**
- Produces: `RosterSchedule::scopePriorityForToday(Builder $query, Carbon $today): Builder`
- Produces: `RosterSchedule::isOverduePending(?Carbon $today = null): bool`
- Produces: `RosterSchedule::manualSubmitter()` relationship.
- Produces nullable attributes: `manual_submitted_at`, `manual_submitted_by`, `manual_reference_number`, `manual_submission_note`.

- [ ] **Step 1: Write the failing model and migration tests**

Create `tests/Feature/RosterSchedulePriorityTest.php` with SQLite setup using `CreatesRosterImportSchema`. Freeze time at `2026-09-01 08:00:00`, then assert the exact order below:

```php
public function test_priority_scope_orders_overdue_upcoming_and_completed_stably(): void
{
    $completedPast = $this->schedule('001', '2026-08-28', RosterSchedule::REALIZATION_CUTI);
    $futureFar = $this->schedule('002', '2026-09-10');
    $overdueOld = $this->schedule('003', '2026-08-20');
    $today = $this->schedule('004', '2026-09-01');
    $overdueNearB = $this->schedule('006', '2026-08-31');
    $futureNear = $this->schedule('007', '2026-09-02');
    $overdueNearA = $this->schedule('005', '2026-08-31');

    $ordered = RosterSchedule::query()
        ->priorityForToday(Carbon::today())
        ->pluck('employee_nik')
        ->all();

    $this->assertSame([
        $overdueNearA->employee_nik,
        $overdueNearB->employee_nik,
        $overdueOld->employee_nik,
        $today->employee_nik,
        $futureNear->employee_nik,
        $futureFar->employee_nik,
        $completedPast->employee_nik,
    ], $ordered);
}

public function test_overdue_helper_requires_active_pending_schedule_before_today(): void
{
    $this->assertTrue($this->schedule('010', '2026-08-31')->isOverduePending());
    $this->assertFalse($this->schedule('011', '2026-09-01')->isOverduePending());
    $this->assertFalse($this->schedule('012', '2026-08-31', RosterSchedule::REALIZATION_CUTI)->isOverduePending());
    $this->assertFalse($this->schedule('013', '2026-08-31', RosterSchedule::REALIZATION_PENDING, false)->isOverduePending());
}
```

Add a migration lifecycle assertion that first drops and recreates a minimal pre-feature `roster_schedules` table without the four manual columns, runs the migration `up()`, checks all four manual columns plus index `roster_schedules_priority_index`, then runs `down()` and confirms only those additions are removed while `roster_schedules` remains. Recreate the fixture table after this test only if another assertion in the same method needs it.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
php artisan test tests/Feature/RosterSchedulePriorityTest.php
```

Expected: FAIL because `priorityForToday()`, `isOverduePending()`, and the manual columns do not exist.

- [ ] **Step 3: Add the production-safe migration**

Create the migration with this `up()` structure:

```php
Schema::table('roster_schedules', function (Blueprint $table): void {
    $table->timestamp('manual_submitted_at')->nullable()->after('realization_type');
    $table->string('manual_submitted_by', 36)->nullable()->after('manual_submitted_at');
    $table->string('manual_reference_number', 100)->nullable()->after('manual_submitted_by');
    $table->string('manual_submission_note', 500)->nullable()->after('manual_reference_number');
    $table->index('manual_submitted_by', 'roster_schedules_manual_submitter_index');
    $table->index(
        ['is_active', 'realization_type', 'off_start'],
        'roster_schedules_priority_index'
    );
});
```

The `down()` method must drop both named indexes first, then drop exactly the four new columns. Do not drop or recreate the table.

- [ ] **Step 4: Add model casts, relationship, helper, and stable priority scope**

Add `manual_submitted_at => datetime` to `$casts`, then add:

```php
public function manualSubmitter()
{
    return $this->belongsTo(User::class, 'manual_submitted_by', 'id');
}

public function isOverduePending(?Carbon $today = null): bool
{
    $today = ($today ?: Carbon::today())->copy()->startOfDay();

    return $this->is_active
        && $this->realization_type === self::REALIZATION_PENDING
        && $this->off_start
        && $this->off_start->copy()->startOfDay()->lt($today);
}

public function scopePriorityForToday(Builder $query, Carbon $today): Builder
{
    $date = $today->copy()->startOfDay()->toDateString();

    return $query
        ->orderByRaw(
            'CASE WHEN off_start < ? AND realization_type = ? THEN 0 '
            . 'WHEN off_start >= ? THEN 1 ELSE 2 END',
            [$date, self::REALIZATION_PENDING, $date]
        )
        ->orderByRaw(
            'CASE WHEN off_start < ? AND realization_type = ? THEN off_start END DESC',
            [$date, self::REALIZATION_PENDING]
        )
        ->orderByRaw('CASE WHEN off_start >= ? THEN off_start END ASC', [$date])
        ->orderByRaw('CASE WHEN off_start < ? AND realization_type <> ? THEN off_start END DESC', [
            $date,
            self::REALIZATION_PENDING,
        ])
        ->orderBy('employee_nik')
        ->orderBy('id');
}
```

Import `Carbon\Carbon` and keep the existing `Builder` import.

- [ ] **Step 5: Update the shared test schema and make the tests GREEN**

Add the four nullable manual fields to `CreatesRosterImportSchema::createRosterImportSchema()` immediately after `realization_type`. Run:

```bash
php artisan test tests/Feature/RosterSchedulePriorityTest.php
php artisan test tests/Feature/RosterScheduleImportLifecycleTest.php tests/Feature/RosterScheduleReminderEligibilityTest.php
```

Expected: all focused tests PASS.

- [ ] **Step 6: Commit the data/model foundation**

```bash
git add database/migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php app/Models/RosterSchedule.php tests/Support/CreatesRosterImportSchema.php tests/Feature/RosterSchedulePriorityTest.php
git commit -m "feat: add roster schedule priority metadata"
```

---

### Task 2: Terapkan Urutan Prioritas dan Peringatan pada Daftar

**Files:**
- Modify: `app/Http/Controllers/Admin/RosterScheduleController.php`
- Modify: `resources/views/admin/roster-schedules/index.blade.php`
- Test: `tests/Feature/RosterScheduleAdminWorkflowTest.php`

**Interfaces:**
- Consumes: `RosterSchedule::priorityForToday(Carbon $today)` and `RosterSchedule::isOverduePending()` from Task 1.
- Produces: index response whose paginator is already in business-priority order.

- [ ] **Step 1: Write failing index ordering and warning rendering tests**

Create `tests/Feature/RosterScheduleAdminWorkflowTest.php` with the same SQLite/time setup, create role HR with `menu_permissions => ['roster_schedule']`, authenticate the HR user, and assert:

```php
$response = $this->actingAs($hr)->get(route('roster-schedules.index'));

$response->assertOk();
$response->assertSeeInOrder([
    'NIK-OVERDUE-NEAR',
    'NIK-OVERDUE-OLD',
    'NIK-TODAY',
    'NIK-FUTURE',
    'NIK-COMPLETED',
]);
$response->assertSee('Terlambat Mengajukan');
$response->assertSee('Terlambat 1 hari');
```

Also assert an inactive pending past schedule does not render the overdue badge beside its NIK.

- [ ] **Step 2: Run the test and verify RED**

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index
```

Expected: FAIL because the controller still orders by absolute `off_start` and the warning copy is absent.

- [ ] **Step 3: Replace the controller order with the model scope**

In `RosterScheduleController::index()`, replace both initial `orderBy()` calls with:

```php
$today = Carbon::today();
$query = RosterSchedule::query()
    ->with(['employee:nik,nama_karyawan,departemen_id,divisi_id,status_resign'])
    ->priorityForToday($today);
```

Pass `'today' => $today` to the view. Do not sort the paginator collection after retrieval.

- [ ] **Step 4: Render the overdue badge and lateness text**

Inside the `Jadwal Off` cell, add:

```blade
@if($schedule->isOverduePending($today))
    <div class="mt-1">
        <span class="badge bg-danger">Terlambat Mengajukan</span>
        <small class="d-block text-danger mt-1">
            Terlambat {{ $schedule->off_start->diffInDays($today) }} hari
        </small>
    </div>
@endif
```

Keep output escaped and do not add browser-native alerts.

- [ ] **Step 5: Run tests and verify GREEN**

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=index
php artisan test tests/Feature/RosterSchedulePriorityTest.php
```

Expected: PASS with overdue rows first and deterministic order.

- [ ] **Step 6: Commit the priority list UI**

```bash
git add app/Http/Controllers/Admin/RosterScheduleController.php resources/views/admin/roster-schedules/index.blade.php tests/Feature/RosterScheduleAdminWorkflowTest.php
git commit -m "feat: prioritize urgent roster schedules"
```

---

### Task 3: Reminder Ulang untuk Jadwal Overdue

**Files:**
- Modify: `config/roster.php`
- Modify: `app/Services/Roster/RosterScheduleReminderEligibilityService.php`
- Modify: `app/Jobs/SendRosterScheduleReminder.php`
- Modify: `app/Notifications/RosterScheduleReminderNotification.php`
- Modify: `app/Http/Controllers/Admin/RosterScheduleController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/roster-schedules/index.blade.php`
- Modify: `tests/Feature/RosterScheduleReminderEligibilityTest.php`
- Modify: `tests/Feature/RosterScheduleAdminWorkflowTest.php`

**Interfaces:**
- Produces: `RosterScheduleReminderEligibilityService::dispatchOverdue(RosterSchedule $schedule): bool`.
- Produces: `RosterScheduleReminderEligibilityService::isOverdueEligible(RosterSchedule $schedule, ?Carbon $today = null): bool`.
- Produces: `SendRosterScheduleReminder::MODE_SCHEDULED` and `MODE_OVERDUE`; constructor remains backward-compatible with scheduled mode default.
- Produces route name: `roster-schedules.reminder.overdue`.

- [ ] **Step 1: Write failing service/job tests for overdue boundaries**

Extend `RosterScheduleReminderEligibilityTest` with cases covering past pending schedule, future schedule, completed realization, inactive employee, queued claim, and cooldown. The core assertions must be:

```php
config()->set('roster.overdue_reminder_cooldown_hours', 24);

$this->assertTrue($service->isOverdueEligible($neverSent));
$this->assertTrue($service->isOverdueEligible($sent25HoursAgo));
$this->assertFalse($service->isOverdueEligible($sent23HoursAgo));
$this->assertFalse($service->isOverdueEligible($future));
$this->assertFalse($service->isOverdueEligible($completed));
$this->assertFalse($service->isOverdueEligible($inactiveEmployee));
```

Use `Queue::fake()` to assert one `SendRosterScheduleReminder` is pushed with `mode === MODE_OVERDUE`, and a second dispatch for the same schedule returns false while `reminder_queued_at` remains claimed.

Replace the old `test_realization_without_application_does_not_suppress_reminder` expectation with false: a manually realized schedule without a `cuti_roster` row must no longer receive scheduled reminders.

- [ ] **Step 2: Write failing job and notification copy tests**

Queue an overdue job, then change `realization_type` to `cuti_roster` before calling `handle()`. Assert no notification is sent and `reminder_queued_at` is cleared. For a valid overdue schedule, assert notification mail/database data contains:

```php
$this->assertSame('Tindak Lanjut Jadwal Roster Terlewat', $mail->subject);
$this->assertStringContainsString('telah dimulai', implode(' ', $mail->introLines));
$this->assertStringNotContainsString('H-0', json_encode($notification->toArray($user)));
```

- [ ] **Step 3: Run reminder tests and verify RED**

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php
```

Expected: FAIL because overdue eligibility, mode, and copy do not exist.

- [ ] **Step 4: Add configuration and separate overdue eligibility**

Add to `config/roster.php`:

```php
'overdue_reminder_cooldown_hours' => (int) env('ROSTER_OVERDUE_REMINDER_COOLDOWN_HOURS', 24),
```

In the eligibility service, keep `eligibleQuery()` for scheduled/future reminders, but add `realization_type = pending` to the shared base eligibility. Implement overdue eligibility using:

```php
private function overdueEligibleQuery(Carbon $today): Builder
{
    $cooldownHours = max(1, (int) config('roster.overdue_reminder_cooldown_hours', 24));
    $cooldownEndsBefore = now()->subHours($cooldownHours);

    return RosterSchedule::query()
        ->active()
        ->where('realization_type', RosterSchedule::REALIZATION_PENDING)
        ->whereDate('off_start', '<', $today->toDateString())
        ->whereHas('employee', fn (Builder $query) => $query->where('status_resign', 'AKTIF'))
        ->whereDoesntHave('applications', fn (Builder $query) => $this->applyActiveApplicationFilter($query))
        ->where(function (Builder $query) use ($cooldownEndsBefore): void {
            $query->whereNull('reminder_sent_at')
                ->orWhere('reminder_sent_at', '<=', $cooldownEndsBefore);
        });
}
```

Extract the existing active-application predicate into `applyActiveApplicationFilter(Builder $query): void` and reuse it in scheduled and overdue queries.

`dispatchOverdue()` must atomically update `reminder_queued_at` only where it is null and all eligibility conditions still hold. Dispatch `new SendRosterScheduleReminder($id, MODE_OVERDUE)`. If queue dispatch throws, clear only this claim, report the exception, and return false.

- [ ] **Step 5: Add mode-aware job revalidation and notification**

Change the job constructor compatibly:

```php
public const MODE_SCHEDULED = 'scheduled';
public const MODE_OVERDUE = 'overdue';

public function __construct(
    public readonly int $scheduleId,
    public readonly string $mode = self::MODE_SCHEDULED
) {
}
```

In `handle()`, use `isOverdueEligible()` for overdue mode and existing `isEligible()` otherwise. Both paths must clear the claim and return when status changed. Pass `$mode` to the notification. Preserve `uniqueId()` per schedule so scheduled and overdue jobs cannot send concurrently.

Update notification constructor with a default scheduled mode. For overdue mode, return subject `Tindak Lanjut Jadwal Roster Terlewat`, explain that the OFF period has started, and retain the action URL to the online roster form. Use a database key containing `:overdue:` plus the dispatch timestamp/date so separate reminders remain auditable; scheduled keys retain the existing H-N shape.

- [ ] **Step 6: Add HTTP route/controller action and audit**

Before `Route::resource('/roster-schedules', ...)`, add:

```php
Route::post('/roster-schedules/{rosterSchedule}/reminder-overdue', [RosterScheduleController::class, 'sendOverdueReminder'])
    ->middleware(['menu:roster_schedule', 'role:Super Admin,HR'])
    ->name('roster-schedules.reminder.overdue');
```

Controller action:

```php
public function sendOverdueReminder(
    Request $request,
    RosterSchedule $rosterSchedule,
    RosterScheduleReminderEligibilityService $service
) {
    if (!$service->dispatchOverdue($rosterSchedule)) {
        toast()->warning('Belum Diproses', 'Reminder belum dapat dikirim. Periksa status antrean, realisasi, dan cooldown 24 jam.');
        return back();
    }

    app(AuditTrailService::class)->record([
        'event' => 'roster_schedule.overdue_reminder_queued',
        'module' => 'roster_schedule',
        'auditable_type' => RosterSchedule::class,
        'auditable_id' => (string) $rosterSchedule->id,
        'reference_table' => 'roster_schedules',
        'reference_id' => (string) $rosterSchedule->id,
        'employee_nik' => $rosterSchedule->employee_nik,
        'actor' => $request->user(),
    ]);

    toast()->success('Masuk Antrean', 'Reminder ulang telah masuk antrean pengiriman.');
    return back();
}
```

- [ ] **Step 7: Render reminder action with truthful disabled states**

For overdue pending rows, render a POST form to `roster-schedules.reminder.overdue`. Disable the button and show `Dalam antrean` when claimed. When `reminder_sent_at` is newer than `now()->subHours(config('roster.overdue_reminder_cooldown_hours'))`, disable it and show the next eligible time. Otherwise label it `Kirim Reminder Lagi`.

Add a `.js-roster-action-form` submit handler that stores the original HTML, disables the submit button, and replaces its content with spinner + `Memasukkan ke antrean...`. It must use normal POST/CSRF and must not claim the email has already been sent.

- [ ] **Step 8: Add HTTP authorization, audit, and duplicate tests**

In `RosterScheduleAdminWorkflowTest`, assert:

```php
$this->actingAs($hr)
    ->post(route('roster-schedules.reminder.overdue', $schedule))
    ->assertRedirect();

Queue::assertPushed(SendRosterScheduleReminder::class, 1);
$this->assertNotNull($schedule->fresh()->reminder_queued_at);
$this->assertSame('roster_schedule.overdue_reminder_queued', $audit->records[0]['event']);
```

Also test employee/no-menu user receives 403, future/completed/cooldown requests do not queue, and two sequential requests queue only once.

- [ ] **Step 9: Run reminder and workflow tests**

```bash
php artisan test tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php
```

Expected: PASS; queue assertions show no duplicate jobs and copy contains no H-0.

- [ ] **Step 10: Commit overdue reminder**

```bash
git add config/roster.php app/Services/Roster/RosterScheduleReminderEligibilityService.php app/Jobs/SendRosterScheduleReminder.php app/Notifications/RosterScheduleReminderNotification.php app/Http/Controllers/Admin/RosterScheduleController.php routes/web.php resources/views/admin/roster-schedules/index.blade.php tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php
git commit -m "feat: add overdue roster reminders"
```

---

### Task 4: Pencatatan Pengajuan Manual oleh HR

**Files:**
- Create: `app/Http/Requests/Roster/StoreManualRosterSubmissionRequest.php`
- Create: `app/Services/Roster/RosterScheduleManualSubmissionService.php`
- Modify: `app/Http/Controllers/Admin/RosterScheduleController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/roster-schedules/index.blade.php`
- Modify: `tests/Feature/RosterScheduleAdminWorkflowTest.php`
- Modify: `tests/Feature/RosterScheduleReminderEligibilityTest.php`

**Interfaces:**
- Produces: `RosterScheduleManualSubmissionService::record(RosterSchedule $schedule, array $data, User $actor): RosterSchedule`.
- Produces route name: `roster-schedules.manual-submission.store`.
- Consumes: `realization_type` constants and manual metadata from Task 1.

- [ ] **Step 1: Write failing validation, persistence, concurrency, and audit tests**

Add tests that POST both valid plan types and assert no `cuti_roster` row is created:

```php
$response = $this->actingAs($hr)->post(
    route('roster-schedules.manual-submission.store', $schedule),
    [
        'realization_type' => RosterSchedule::REALIZATION_CUTI,
        'manual_reference_number' => 'RST/HR/IX/2026',
        'manual_submission_note' => 'Berkas fisik diterima HR.',
    ]
);

$response->assertRedirect();
$fresh = $schedule->fresh();
$this->assertSame(RosterSchedule::REALIZATION_CUTI, $fresh->realization_type);
$this->assertSame($hr->id, $fresh->manual_submitted_by);
$this->assertNotNull($fresh->manual_submitted_at);
$this->assertSame('RST/HR/IX/2026', $fresh->manual_reference_number);
$this->assertSame(0, Roster::where('roster_schedule_id', $schedule->id)->count());
$this->assertSame('roster_schedule.manual_submission_recorded', $audit->records[0]['event']);
```

Add 422/session validation assertions for `pending`, unknown type, reference over 100 characters, and note over 500 characters. Add 403 cases for unauthorized roles. Add conflict cases for inactive, already realized, and a linked pending/approved digital application. Submit twice and assert the second request does not overwrite actor, timestamp, or original values.

- [ ] **Step 2: Run the workflow test and verify RED**

```bash
php artisan test tests/Feature/RosterScheduleAdminWorkflowTest.php --filter=manual
```

Expected: FAIL because request, service, route, and controller action do not exist.

- [ ] **Step 3: Create the Form Request**

Implement:

```php
public function authorize(): bool
{
    return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
}

public function rules(): array
{
    return [
        'realization_type' => [
            'required',
            Rule::in([
                RosterSchedule::REALIZATION_CUTI,
                RosterSchedule::REALIZATION_INSENTIF,
            ]),
        ],
        'manual_reference_number' => ['nullable', 'string', 'max:100'],
        'manual_submission_note' => ['nullable', 'string', 'max:500'],
    ];
}
```

Provide Indonesian messages for required type, invalid type, and both max lengths.

- [ ] **Step 4: Implement the transaction-safe manual submission service**

`record()` must re-fetch and lock the row inside `DB::transaction()`:

```php
$locked = RosterSchedule::query()
    ->whereKey($schedule->getKey())
    ->lockForUpdate()
    ->firstOrFail();

if (!$locked->is_active || $locked->realization_type !== RosterSchedule::REALIZATION_PENDING) {
    throw ValidationException::withMessages([
        'realization_type' => 'Jadwal sudah diproses atau tidak lagi aktif.',
    ]);
}

$hasActiveDigitalApplication = $locked->applications()
    ->where(function (Builder $query): void {
        $query->where(function (Builder $hod): void {
            $hod->whereNull('status_pengajuan')->orWhere('status_pengajuan', '!=', 2);
        })->where(function (Builder $hrd): void {
            $hrd->whereNull('status_pengajuan_hrd')->orWhere('status_pengajuan_hrd', '!=', 2);
        });
    })
    ->exists();

if ($hasActiveDigitalApplication) {
    throw ValidationException::withMessages([
        'realization_type' => 'Pengajuan digital sudah tersedia dan tidak boleh ditimpa.',
    ]);
}
```

Then update only:

```php
$locked->forceFill([
    'realization_type' => $data['realization_type'],
    'manual_submitted_at' => now(),
    'manual_submitted_by' => $actor->getAuthIdentifier(),
    'manual_reference_number' => $data['manual_reference_number'] ?? null,
    'manual_submission_note' => $data['manual_submission_note'] ?? null,
    'reminder_queued_at' => null,
    'updated_by' => (string) $actor->getAuthIdentifier(),
])->save();
```

Record audit event `roster_schedule.manual_submission_recorded` with old/new values, actor, employee NIK, schedule ID, and metadata `submission_channel => offline`. Return the fresh schedule after the transaction.

- [ ] **Step 5: Add route and thin controller action**

Before the resource route, add:

```php
Route::post('/roster-schedules/{rosterSchedule}/manual-submission', [RosterScheduleController::class, 'storeManualSubmission'])
    ->middleware(['menu:roster_schedule', 'role:Super Admin,HR'])
    ->name('roster-schedules.manual-submission.store');
```

Controller action:

```php
public function storeManualSubmission(
    StoreManualRosterSubmissionRequest $request,
    RosterSchedule $rosterSchedule,
    RosterScheduleManualSubmissionService $service
) {
    $updated = $service->record(
        $rosterSchedule,
        $request->validated(),
        $request->user()
    );

    toast()->success(
        'Berhasil',
        'Pengajuan manual dicatat sebagai ' . $updated->realization_label . '.'
    );

    return back();
}
```

Allow `ValidationException` to flow through Laravel so field errors and old input survive redirect. Catch only unexpected `Throwable`, call `report()`, show a generic toast, and return back with input.

- [ ] **Step 6: Add modal and manual-status UI**

For active pending rows, render a button with data attributes for schedule ID, employee display, and POST URL. Create one Bootstrap modal containing:

```blade
<select name="realization_type" class="form-select" required>
    <option value="">Pilih realisasi</option>
    <option value="{{ \App\Models\RosterSchedule::REALIZATION_CUTI }}">Cuti Roster</option>
    <option value="{{ \App\Models\RosterSchedule::REALIZATION_INSENTIF }}">Insentif</option>
</select>
<input type="text" name="manual_reference_number" maxlength="100" class="form-control">
<textarea name="manual_submission_note" maxlength="500" class="form-control" rows="3"></textarea>
```

The modal copy must say this records receipt of an offline submission and does not create digital approval. JavaScript sets form `action` from the clicked button, disables submit on send, and changes the label to `Menyimpan...` with a spinner. If validation redirects back with `old('manual_schedule_id')`, reopen the modal and restore values; include hidden `manual_schedule_id` solely for UI restoration, not authorization.

For manually realized rows, show badge `Pengajuan Manual`, optional escaped reference number, timestamp, and manual submitter name/ID. Do not render raw HTML from notes.

- [ ] **Step 7: Verify a queued reminder stops after manual recording**

In `RosterScheduleReminderEligibilityTest`, claim an overdue reminder, record manual submission, then execute the already-created job. Assert no notification is sent, the claim is cleared, and the manual realization remains unchanged.

- [ ] **Step 8: Run all feature-focused tests**

```bash
php artisan test tests/Feature/RosterSchedulePriorityTest.php tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php tests/Feature/RosterScheduleApplicationLinkTest.php
```

Expected: all tests PASS and no `cuti_roster` approval row is created by the manual endpoint.

- [ ] **Step 9: Commit manual submission workflow**

```bash
git add app/Http/Requests/Roster/StoreManualRosterSubmissionRequest.php app/Services/Roster/RosterScheduleManualSubmissionService.php app/Http/Controllers/Admin/RosterScheduleController.php routes/web.php resources/views/admin/roster-schedules/index.blade.php tests/Feature/RosterScheduleAdminWorkflowTest.php tests/Feature/RosterScheduleReminderEligibilityTest.php
git commit -m "feat: record manual roster submissions"
```

---

### Task 5: Regression, Static Checks, and Production Handoff

**Files:**
- Verify: all files changed in Tasks 1–4
- Reference: `docs/superpowers/specs/2026-08-28-roster-schedule-priority-manual-submission-design.md`

**Interfaces:**
- Consumes all earlier task outputs.
- Produces a verified, deployment-ready change set with no uncommitted implementation files.

- [ ] **Step 1: Run PHP syntax checks on every changed PHP file**

```bash
php -l app/Models/RosterSchedule.php
php -l app/Http/Controllers/Admin/RosterScheduleController.php
php -l app/Http/Requests/Roster/StoreManualRosterSubmissionRequest.php
php -l app/Services/Roster/RosterScheduleManualSubmissionService.php
php -l app/Services/Roster/RosterScheduleReminderEligibilityService.php
php -l app/Jobs/SendRosterScheduleReminder.php
php -l app/Notifications/RosterScheduleReminderNotification.php
php -l database/migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php
```

Expected: each command prints `No syntax errors detected`.

- [ ] **Step 2: Run the complete roster regression set**

```bash
php artisan test --filter=Roster
```

Expected: all roster-related tests PASS with zero failures/errors.

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test
```

Expected: PASS. If unrelated pre-existing failures occur, capture exact test names and prove all focused roster tests still pass; do not silently claim the suite is green.

- [ ] **Step 4: Verify routes and migration status without mutation**

```bash
php artisan route:list --name=roster-schedules
php artisan migrate:status
git diff --check
git status --short
```

Expected: both POST routes appear with HR/Super Admin and menu middleware; migration is pending locally until explicitly run; `git diff --check` is clean; only intentional files are present.

- [ ] **Step 5: Perform manual browser acceptance checks**

Log in as HR and verify:

1. overdue pending rows appear first with red warning and correct late-day count;
2. upcoming rows follow in ascending date order;
3. completed past rows are at the bottom;
4. reminder button shows queue loading state and success copy says `masuk antrean`;
5. cooldown disables repeat send and exposes the next allowed time;
6. manual modal requires a plan type but allows blank reference/note;
7. manual save shows its badge and removes reminder action;
8. employee/no-menu accounts receive 403 if they call either POST route directly;
9. mobile table remains horizontally usable and modal fits the viewport.

- [ ] **Step 6: Document deployment and rollback commands in the handoff**

Deployment:

```bash
php artisan migrate --force
php artisan config:cache
php artisan queue:restart
```

Run migration during low traffic because the composite index may lock `roster_schedules`. Confirm a queue worker/cron is active; otherwise the UI truthfully remains `Dalam antrean` and email will not be sent.

Rollback must use the specific migration batch only after reviewing manual records. Do not use `migrate:rollback` blindly on a mixed batch. If application rollback happens after manual records exist, export `manual_*` values first because rolling back the columns loses that metadata and does not restore `realization_type`.

- [ ] **Step 7: Commit any verification-only corrections**

If verification required code corrections, stage only the corrected implementation files from this explicit feature set, then commit them:

```bash
git add app/Models/RosterSchedule.php app/Http/Controllers/Admin/RosterScheduleController.php app/Http/Requests/Roster/StoreManualRosterSubmissionRequest.php app/Services/Roster/RosterScheduleManualSubmissionService.php app/Services/Roster/RosterScheduleReminderEligibilityService.php app/Jobs/SendRosterScheduleReminder.php app/Notifications/RosterScheduleReminderNotification.php config/roster.php routes/web.php resources/views/admin/roster-schedules/index.blade.php database/migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php tests/Support/CreatesRosterImportSchema.php tests/Feature/RosterSchedulePriorityTest.php tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/RosterScheduleAdminWorkflowTest.php
git commit -m "fix: harden roster schedule workflows"
```

If no correction was required, do not create an empty commit.
