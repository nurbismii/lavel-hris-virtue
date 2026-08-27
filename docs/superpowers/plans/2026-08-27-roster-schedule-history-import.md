# Roster Schedule History Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin-only, preview-and-confirm Excel import that atomically records roster history, generates two years of future schedules, exports blocking row failures, and sends idempotent H-14 or late reminders.

**Architecture:** Reuse `ImportHistory` as the import lifecycle record, parse and validate the workbook synchronously, then confirm through a unique queued job. Keep workbook reading, validation, persistence, reminder eligibility, and private-file cleanup in separate services; database writes are atomic, while email and notification dispatch occur only after commit.

**Tech Stack:** Laravel 13, PHP 8.4.1+ (local CLI: `C:\xampp\php85\php.exe`), MySQL/MariaDB production, SQLite in-memory tests, Blade, Bootstrap 5, jQuery, SweetAlert2, PhpSpreadsheet/Maatwebsite Excel, Laravel Queue and Scheduler.

## Global Constraints

- Only users with `Super Admin` or `HR` role and `roster_schedule` menu access may upload, preview, confirm, poll, or download roster imports.
- The import is all-or-nothing: any blocker prevents confirmation; any persistence exception rolls back every roster write.
- Employee identity is valid when both Excel NIK and the full 16-digit KTP number match `employees.nik` and `employees.no_ktp`; a name mismatch is warning-only.
- Full KTP values may appear in the private preview/failure workbook but must not be stored in logs, audit metadata, `summary`, or `failure_samples`.
- Source and failure files expire after exactly 12 hours and remain under `storage/app/private/roster-imports/`.
- Roster dates use 70 work days followed by 14 off days; future schedules extend through the end of the second year ahead.
- Standard reminders run at H-14; schedules imported at H-13 through H-0 receive one immediate reminder; past schedules and schedules with an existing linked application are skipped.
- Preserve confirmed history review decisions and never overwrite a manual roster schedule during import.
- Do not add a new package; use the dependencies already present in `composer.json`.
- Keep existing CLI command signatures backward-compatible.

---

## File Structure

### New files

- `database/migrations/2026_08_27_000004_add_roster_import_lifecycle_columns.php` — nullable import lifecycle columns and nullable roster application link.
- `app/Support/Roster/RosterWorkbookData.php` — immutable normalized workbook payload.
- `app/Services/Roster/RosterScheduleWorkbookReader.php` — Excel/header/cell parsing only.
- `app/Services/Roster/RosterScheduleImportValidationService.php` — database-aware blocker/warning classification.
- `app/Services/Roster/RosterScheduleImportPreviewService.php` — preview lifecycle and private failure export orchestration.
- `app/Services/Roster/RosterScheduleImportCommitService.php` — revalidation and atomic persistence.
- `app/Services/Roster/RosterScheduleReminderEligibilityService.php` — one source of truth for reminder eligibility.
- `app/Exports/RosterScheduleImportFailuresExport.php` — failure workbook rows and headings.
- `app/Jobs/ProcessRosterScheduleImport.php` — unique confirmed import job.
- `app/Http/Controllers/Admin/RosterScheduleImportController.php` — admin import endpoints only.
- `app/Http/Requests/Roster/UploadRosterScheduleImportRequest.php` — upload authorization/validation.
- `app/Http/Requests/Roster/ConfirmRosterScheduleImportRequest.php` — confirmation authorization/validation.
- `app/Console/Commands/CleanupExpiredRosterImports.php` — idempotent 12-hour file cleanup.
- `resources/views/admin/roster-schedules/import.blade.php` — upload, preview, and processing UI.
- `public/assets/js/roster-schedule-import.js` — submit state, confirmation, and bounded polling.
- `tests/Support/CreatesRosterImportSchema.php` — reusable SQLite schema and fixture helpers.
- `tests/Unit/RosterScheduleWorkbookReaderTest.php` — workbook parsing tests.
- `tests/Feature/RosterScheduleImportPreviewTest.php` — identity and blocker tests.
- `tests/Feature/RosterScheduleImportJobTest.php` — atomic import/idempotency tests.
- `tests/Feature/RosterScheduleImportControllerTest.php` — authorization and lifecycle endpoint tests.
- `tests/Feature/RosterScheduleReminderEligibilityTest.php` — H-14/late/link suppression tests.
- `tests/Feature/CleanupExpiredRosterImportsTest.php` — private-file retention tests.

### Modified files

- `app/Models/ImportHistory.php` — roster type/status constants and lifecycle casts.
- `app/Models/Roster.php` — `schedule()` relation.
- `app/Models/RosterSchedule.php` — `applications()` relation.
- `app/Services/ImportHistory/ImportHistoryService.php` — explicit preview/confirm/job state transitions.
- `app/Services/Roster/RosterScheduleWorkbookImportService.php` — delegate parsing/validation/persistence while keeping CLI API.
- `app/Http/Controllers/User/RosterController.php` — secure schedule-prefill and linked submission.
- `app/Http/Requests/Roster/RosterRequest.php` — optional `roster_schedule_id` validation.
- `app/Console/Commands/SendRosterScheduleReminders.php` — shared eligibility query.
- `app/Jobs/SendRosterScheduleReminder.php` — recheck eligibility at execution time.
- `app/Notifications/RosterScheduleReminderNotification.php` — schedule-linked action URL.
- `app/Console/Kernel.php` — hourly cleanup schedule.
- `config/roster.php` — import retention, size, directory, and horizon.
- `routes/web.php` — admin import endpoints.
- `resources/views/admin/roster-schedules/index.blade.php` — import entry button.
- `resources/views/user/roster/create.blade.php` — hidden schedule link and prefilled context.
- `.env.example` — non-secret roster import configuration.

---

### Task 1: Add Import Lifecycle Schema and Model Contracts

**Files:**
- Create: `database/migrations/2026_08_27_000004_add_roster_import_lifecycle_columns.php`
- Modify: `app/Models/ImportHistory.php`
- Modify: `app/Models/Roster.php`
- Modify: `app/Models/RosterSchedule.php`
- Modify: `config/roster.php`
- Modify: `.env.example`
- Test: `tests/Feature/RosterScheduleImportLifecycleTest.php`

**Interfaces:**
- Produces: `ImportHistory::TYPE_ROSTER_SCHEDULE`, `STATUS_AWAITING_CONFIRMATION`, `STATUS_VALIDATION_FAILED`, `STATUS_EXPIRED`.
- Produces: `Roster::schedule(): BelongsTo` and `RosterSchedule::applications(): HasMany`.
- Produces config keys `roster.import.max_kb`, `retention_hours`, `directory`, and `generate_years_ahead`.

- [ ] **Step 1: Write the failing lifecycle model test**

```php
<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Models\Roster;
use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RosterScheduleImportLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('import_type');
            $table->string('status');
            $table->string('file_checksum')->nullable();
            $table->string('failure_file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->timestamps();
        });
        Schema::create('roster_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nik');
            $table->date('off_start');
            $table->timestamps();
        });
        Schema::create('cuti_roster', function (Blueprint $table) {
            $table->id();
            $table->string('nik_karyawan');
            $table->unsignedBigInteger('roster_schedule_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_roster_import_lifecycle_and_application_relation_are_cast_correctly(): void
    {
        $history = ImportHistory::create([
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION,
            'expires_at' => '2026-08-28 02:00:00',
        ]);
        $schedule = RosterSchedule::create(['employee_nik' => '16090940', 'off_start' => '2026-09-10']);
        Roster::create(['nik_karyawan' => '16090940', 'roster_schedule_id' => $schedule->id]);

        $this->assertInstanceOf(Carbon::class, $history->expires_at);
        $this->assertSame($schedule->id, $schedule->applications()->firstOrFail()->roster_schedule_id);
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run:

```powershell
& 'C:\xampp\php85\php.exe' artisan test tests/Feature/RosterScheduleImportLifecycleTest.php
```

Expected: FAIL because roster lifecycle constants/casts and relations do not exist.

- [ ] **Step 3: Add the non-destructive migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            $table->string('file_checksum', 64)->nullable()->after('file_size');
            $table->string('failure_file_path', 500)->nullable()->after('file_path');
            $table->timestamp('expires_at')->nullable()->after('finished_at')->index();
            $table->timestamp('confirmed_at')->nullable()->after('expires_at');
            $table->string('confirmed_by', 36)->nullable()->after('confirmed_at');
        });

        Schema::table('cuti_roster', function (Blueprint $table) {
            $table->unsignedBigInteger('roster_schedule_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('cuti_roster', function (Blueprint $table) {
            $table->dropIndex(['roster_schedule_id']);
            $table->dropColumn('roster_schedule_id');
        });
        Schema::table('import_histories', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['file_checksum', 'failure_file_path', 'expires_at', 'confirmed_at', 'confirmed_by']);
        });
    }
};
```

- [ ] **Step 4: Add constants, casts, relations, and configuration**

Add to `ImportHistory`:

```php
public const TYPE_ROSTER_SCHEDULE = 'roster_schedule';
public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
public const STATUS_VALIDATION_FAILED = 'validation_failed';
public const STATUS_EXPIRED = 'expired';

// Append to $casts:
'expires_at' => 'datetime',
'confirmed_at' => 'datetime',
```

Append the new statuses/type to `statusLabels()`, `statusBadgeClasses()`, and `typeLabels()`. Add relations:

```php
// App\Models\Roster
public function schedule()
{
    return $this->belongsTo(RosterSchedule::class, 'roster_schedule_id');
}

// App\Models\RosterSchedule
public function applications()
{
    return $this->hasMany(Roster::class, 'roster_schedule_id');
}
```

Append to `config/roster.php`:

```php
'import' => [
    'max_kb' => (int) env('ROSTER_IMPORT_MAX_KB', 10240),
    'retention_hours' => (int) env('ROSTER_IMPORT_RETENTION_HOURS', 12),
    'directory' => 'roster-imports',
],
```

Append to `.env.example`:

```dotenv
ROSTER_IMPORT_MAX_KB=10240
ROSTER_IMPORT_RETENTION_HOURS=12
ROSTER_GENERATE_YEARS_AHEAD=2
```

- [ ] **Step 5: Run the test and commit**

Run the Task 1 test. Expected: PASS. Then:

```powershell
git add database/migrations/2026_08_27_000004_add_roster_import_lifecycle_columns.php app/Models/ImportHistory.php app/Models/Roster.php app/Models/RosterSchedule.php config/roster.php .env.example tests/Feature/RosterScheduleImportLifecycleTest.php
git commit -m "feat: add roster import lifecycle schema"
```

---

### Task 2: Build a Pure Workbook Reader

**Files:**
- Create: `app/Support/Roster/RosterWorkbookData.php`
- Create: `app/Services/Roster/RosterScheduleWorkbookReader.php`
- Create: `tests/Unit/RosterScheduleWorkbookReaderTest.php`

**Interfaces:**
- Produces: `RosterScheduleWorkbookReader::read(string $absolutePath): RosterWorkbookData`.
- `RosterWorkbookData::$rows` contains `row_number`, `nik`, `no_ktp`, `employee_name`, and `periods`.
- Every period contains `year`, `period_number`, `source_column`, `remark_column`, `off_start`, `raw_remark`, and `cell_error`.

- [ ] **Step 1: Write failing tests using a generated workbook fixture**

Create tests that build a workbook with PhpSpreadsheet and assert:

```php
public function test_reader_normalizes_headers_identifiers_dates_and_remarks(): void
{
    $path = $this->workbookPath([
        ['nik' => '016090940', 'ktp' => '7402243101930001', 'name' => 'Nama Excel', 'off_start' => '2026-09-10', 'remark' => 'I. AMBIL CUTI'],
    ]);

    $data = app(RosterScheduleWorkbookReader::class)->read($path);
    $row = $data->rows->first();

    $this->assertSame('016090940', $row['nik']);
    $this->assertSame('7402243101930001', $row['no_ktp']);
    $this->assertSame('2026-09-10', $row['periods'][0]['off_start']);
    $this->assertSame('I. AMBIL CUTI', $row['periods'][0]['raw_remark']);
}

public function test_reader_marks_sixteen_digit_numeric_ktp_as_unsafe(): void
{
    $path = $this->numericKtpWorkbookPath(7402243101930001);
    $row = app(RosterScheduleWorkbookReader::class)->read($path)->rows->first();

    $this->assertSame('unsafe_numeric_identity', $row['identity_error']);
}
```

The fixture must set KTP text cells explicitly with `DataType::TYPE_STRING` and date cells as Excel dates or formulas. Delete fixture paths in `tearDown()`.

- [ ] **Step 2: Run tests and verify reader classes are missing**

Run:

```powershell
& 'C:\xampp\php85\php.exe' artisan test tests/Unit/RosterScheduleWorkbookReaderTest.php
```

Expected: FAIL because the reader/value object is absent.

- [ ] **Step 3: Implement the immutable workbook payload**

```php
<?php

namespace App\Support\Roster;

use Illuminate\Support\Collection;

final class RosterWorkbookData
{
    public function __construct(
        public readonly string $sheetName,
        public readonly array $columns,
        public readonly Collection $rows
    ) {
    }
}
```

- [ ] **Step 4: Implement reader responsibilities**

`RosterScheduleWorkbookReader` must:

```php
public function read(string $absolutePath): RosterWorkbookData;
private function scheduleColumns(Worksheet $sheet): array;
private function identifier(Cell $cell, bool $isKtp = false): array;
private function dateValue(Cell $cell): array;
private function remarksFor(Worksheet $sheet, array $column, int $row): ?string;
```

Key identity rule:

```php
if ($isKtp && $cell->getDataType() === DataType::TYPE_NUMERIC) {
    return ['value' => trim((string) $cell->getValue()), 'error' => 'unsafe_numeric_identity'];
}

$value = trim((string) $cell->getValue());
return ['value' => $value, 'error' => preg_match('/^\d+$/', $value) ? null : 'non_digit_identity'];
```

Use `getCalculatedValue()` for date formulas, catch formula errors, and return ISO `Y-m-d`. Only scan columns identified by row-1 year plus row-2 period I–V; ignore unrelated columns such as the external resign lookup.

- [ ] **Step 5: Run reader tests and commit**

Expected: all reader tests PASS.

```powershell
git add app/Support/Roster/RosterWorkbookData.php app/Services/Roster/RosterScheduleWorkbookReader.php tests/Unit/RosterScheduleWorkbookReaderTest.php
git commit -m "feat: parse roster history workbooks safely"
```

---

### Task 3: Validate Preview Rows and Produce Failure Workbooks

**Files:**
- Create: `app/Services/Roster/RosterScheduleImportValidationService.php`
- Create: `app/Services/Roster/RosterScheduleImportPreviewService.php`
- Create: `app/Exports/RosterScheduleImportFailuresExport.php`
- Modify: `app/Services/ImportHistory/ImportHistoryService.php`
- Create: `tests/Support/CreatesRosterImportSchema.php`
- Create: `tests/Feature/RosterScheduleImportPreviewTest.php`

**Interfaces:**
- Consumes: `RosterWorkbookData` from Task 2.
- Produces: `validate(RosterWorkbookData $data): array` with `is_valid`, `summary`, `rows`, `errors`, and `warnings`.
- Produces: `preview(ImportHistory $history, User $actor): array` and creates a private failure workbook only when blockers exist.

- [ ] **Step 1: Add reusable SQLite schema/fixture support**

The trait must configure SQLite `:memory:` and create at least `employees`, `users`, `import_histories`, `import_history_items`, `roster_schedules`, `roster_schedule_histories`, `cuti_roster`, and `periode_kerja_roster` with columns used by the new services. Provide:

```php
protected function createRosterImportSchema(): void;
protected function seedRosterEmployee(string $nik, string $ktp, string $name = 'Nama HRIS', string $status = 'AKTIF'): void;
protected function makeRosterWorkbook(array $rows): string;
```

- [ ] **Step 2: Write failing validation tests**

Cover these exact cases:

```php
public function test_matching_nik_and_ktp_with_different_name_is_warning_only(): void;
public function test_missing_nik_blocks_entire_import(): void;
public function test_ktp_mismatch_blocks_entire_import(): void;
public function test_duplicate_nik_in_workbook_blocks_entire_import(): void;
public function test_manual_schedule_conflict_blocks_entire_import(): void;
public function test_identical_import_schedule_is_unchanged(): void;
public function test_inactive_employee_is_valid_but_marked_nonactive(): void;
```

The first test must assert `is_valid === true`, zero blockers, and one `name_mismatch` warning. KTP values must never appear in persisted `summary` or `failure_samples`.

- [ ] **Step 3: Run tests and verify failure**

Run the preview test file. Expected: FAIL because validation/preview services do not exist.

- [ ] **Step 4: Implement bulk identity and schedule validation**

Fetch all employees once:

```php
$employees = Employee::query()
    ->whereIn('nik', $data->rows->pluck('nik')->filter()->unique())
    ->get(['nik', 'no_ktp', 'nama_karyawan', 'status_resign'])
    ->keyBy(fn(Employee $employee) => (string) $employee->nik);
```

Build blocker codes (`missing_nik`, `invalid_nik`, `invalid_ktp`, `employee_not_found`, `ktp_mismatch`, `duplicate_nik`, `invalid_date`, `duplicate_off_start`, `manual_conflict`) and warning codes (`name_mismatch`, `remark_need_review`, `inactive_employee`). Compare identity values as exact strings using `hash_equals()` only after both values pass digit/length validation.

Classify each schedule as `create`, `update`, `unchanged`, or `blocked`; never overwrite a `source=manual` schedule.

- [ ] **Step 5: Implement preview state transitions and failure export**

Add explicit methods to `ImportHistoryService`:

```php
public function markAwaitingConfirmation(int $id, array $summary): void;
public function markValidationFailed(int $id, array $summary, array $details, string $failurePath): void;
public function markConfirmed(int $id, string $actorId): bool;
public function markExpired(int $id): void;
```

`markConfirmed()` must use a conditional update from `awaiting_confirmation` to `queued` and return true only for the winning request.

`RosterScheduleImportFailuresExport` headings must be:

```php
[
    'No', 'Baris Excel', 'NIK', 'Nomor KTP', 'Nama Excel', 'Nama HRIS',
    'Tahun', 'Periode', 'Kolom', 'Nilai Bermasalah', 'Jenis Kegagalan',
    'Alasan', 'Saran Perbaikan',
]
```

Prefix any string beginning with `=`, `+`, `-`, or `@` with an apostrophe to prevent formula injection. Store through `Excel::store()` under `private/roster-imports/{import_id}/failures.xlsx` while persisting only the relative path `roster-imports/{import_id}/failures.xlsx`.

- [ ] **Step 6: Run tests, inspect the exported workbook, and commit**

Expected: preview tests PASS; assert the failure file exists on `Storage::fake('local')`, contains full KTP when loaded in the test, and DB JSON does not.

```powershell
git add app/Services/Roster/RosterScheduleImportValidationService.php app/Services/Roster/RosterScheduleImportPreviewService.php app/Exports/RosterScheduleImportFailuresExport.php app/Services/ImportHistory/ImportHistoryService.php tests/Support/CreatesRosterImportSchema.php tests/Feature/RosterScheduleImportPreviewTest.php
git commit -m "feat: validate roster imports with failure export"
```

---

### Task 4: Add Admin Upload, Preview, Status, and Download Endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/RosterScheduleImportController.php`
- Create: `app/Http/Requests/Roster/UploadRosterScheduleImportRequest.php`
- Modify: `routes/web.php`
- Create: `resources/views/admin/roster-schedules/import.blade.php`
- Create: `public/assets/js/roster-schedule-import.js`
- Modify: `resources/views/admin/roster-schedules/index.blade.php`
- Test: `tests/Feature/RosterScheduleImportControllerTest.php`

**Interfaces:**
- Produces named routes `roster-schedules.import.create`, `.store`, `.show`, `.status`, and `.failure`.
- Produces a complete read-only preview lifecycle; Task 5 adds the mutation/confirmation endpoint after the atomic job exists.

- [ ] **Step 1: Write failing controller feature tests**

Test:

```php
public function test_non_hr_user_cannot_open_or_submit_roster_import(): void;
public function test_hr_upload_stores_private_file_and_returns_preview(): void;
public function test_invalid_preview_has_no_confirmation_action_and_has_failure_download(): void;
public function test_unauthorized_user_cannot_poll_or_download_another_import(): void;
```

Use `Storage::fake('local')`, `Queue::fake()`, and a user with an in-memory `Role(['permission_role' => 'HR'])`.

- [ ] **Step 2: Run controller tests and verify missing routes**

Expected: FAIL with route/controller not found.

- [ ] **Step 3: Implement the upload Form Request**

Upload rules:

```php
public function authorize(): bool
{
    return $this->user()?->hasRole(['Super Admin', 'HR']) === true;
}

public function rules(): array
{
    return ['file' => ['required', 'file', 'mimes:xlsx', 'max:' . config('roster.import.max_kb', 10240)]];
}
```

- [ ] **Step 4: Implement controller routes and secure ownership lookup**

Use a private helper:

```php
private function ownedImport(Request $request, ImportHistory $history): ImportHistory
{
    abort_unless($history->import_type === ImportHistory::TYPE_ROSTER_SCHEDULE, 404);
    abort_unless(
        $request->user()->canAccessAllEmployees()
        && $request->user()->hasRole(['Super Admin', 'HR']),
        403
    );
    return $history;
}
```

Store uploads with `SensitiveFileStorageService::storeUploadedFileAs()` at `roster-imports/{uuid}/source.xlsx`, calculate SHA-256 from the resolved private file, and create `ImportHistory` with `expires_at = now()->addHours(12)`.

Return consistent JSON for status:

```php
return response()->json([
    'success' => true,
    'message' => $history->status_label,
    'data' => [
        'status' => $history->status,
        'summary' => $history->summary,
        'terminal' => in_array($history->status, [
            ImportHistory::STATUS_COMPLETED,
            ImportHistory::STATUS_FAILED,
            ImportHistory::STATUS_VALIDATION_FAILED,
            ImportHistory::STATUS_EXPIRED,
        ], true),
    ],
]);
```

Download via `response()->download()` from `SensitiveFileStorageService::resolvePath()`, with `nosniff`; reject expired imports.

Record `roster_schedule_import.uploaded`, `roster_schedule_import.previewed`, and `roster_schedule_import.failure_downloaded` through `AuditTrailService`. Audit payloads may contain actor, import ID, safe filename, checksum, status, and aggregate counts, but never full KTP values or row payloads.

- [ ] **Step 5: Implement the Blade and JavaScript state machine**

The Blade must contain:

- upload form with CSRF token;
- preview summary cards;
- row preview table;
- failure download button only for blockers;
- processing panel and status URL data attributes.

The JavaScript must disable buttons, preserve original labels, display SweetAlert success/error, handle 401/403/419/422/500/0, poll every 5 seconds, and stop after 12 minutes or a terminal status. Task 5 adds the confirmation action and duplicate-click guard.

- [ ] **Step 6: Add the index entry point and run tests**

Add an `Import Riwayat` button next to `Riwayat Excel` in `resources/views/admin/roster-schedules/index.blade.php`.

Expected: all controller tests PASS.

- [ ] **Step 7: Commit the admin workflow**

```powershell
git add app/Http/Controllers/Admin/RosterScheduleImportController.php app/Http/Requests/Roster/UploadRosterScheduleImportRequest.php routes/web.php resources/views/admin/roster-schedules/import.blade.php public/assets/js/roster-schedule-import.js resources/views/admin/roster-schedules/index.blade.php tests/Feature/RosterScheduleImportControllerTest.php
git commit -m "feat: add admin roster import preview"
```

---

### Task 5: Implement Confirmation and the Unique Atomic Import Job

**Files:**
- Create: `app/Services/Roster/RosterScheduleImportCommitService.php`
- Create: `app/Jobs/ProcessRosterScheduleImport.php`
- Create: `app/Http/Requests/Roster/ConfirmRosterScheduleImportRequest.php`
- Modify: `app/Services/Roster/RosterScheduleWorkbookImportService.php`
- Modify: `app/Http/Controllers/Admin/RosterScheduleImportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/roster-schedules/import.blade.php`
- Modify: `public/assets/js/roster-schedule-import.js`
- Test: `tests/Feature/RosterScheduleImportJobTest.php`
- Test: `tests/Feature/RosterScheduleImportControllerTest.php`

**Interfaces:**
- Consumes validated workbook data and `ImportHistory` from Tasks 1–4.
- Produces `RosterScheduleImportCommitService::commit(ImportHistory $history): array` with public count keys plus an internal `late_candidate_schedule_ids` list that is returned to the job but never persisted in import summary/audit JSON.
- Job implements `ShouldQueue`, `ShouldBeUnique`, and uses `WithoutOverlapping`.

- [ ] **Step 1: Write failing atomicity and idempotency tests**

Cover:

```php
public function test_job_revalidates_checksum_and_identity_before_writing(): void;
public function test_job_imports_history_and_generates_until_end_of_second_year(): void;
public function test_confirmed_manual_history_review_is_preserved(): void;
public function test_job_is_idempotent_when_run_twice(): void;
public function test_database_error_rolls_back_every_schedule_and_history(): void;
public function test_owner_can_confirm_valid_unexpired_import_once(): void;
public function test_expired_or_checksum_changed_import_cannot_be_confirmed(): void;
```

For rollback proof in SQLite, create a test trigger:

```sql
CREATE TRIGGER fail_second_roster
BEFORE INSERT ON roster_schedules
WHEN NEW.employee_nik = 'EMP002'
BEGIN
  SELECT RAISE(ABORT, 'forced roster failure');
END;
```

Run the commit service with EMP001 and EMP002 and assert both roster tables remain empty after the exception.

- [ ] **Step 2: Run tests and verify missing job/service**

Expected: FAIL.

- [ ] **Step 3: Implement commit service preconditions**

Before transaction:

```php
if ($history->status !== ImportHistory::STATUS_PROCESSING) {
    throw new LogicException('Import roster tidak berada pada status processing.');
}

$absolutePath = $storage->resolvePath($history->file_path, ['roster-imports/']);
if (!$absolutePath || !hash_equals((string) $history->file_checksum, hash_file('sha256', $absolutePath))) {
    throw new RuntimeException('File import berubah atau tidak tersedia.');
}
```

Read again and require a valid validation result. Never include KTP values in exception messages.

- [ ] **Step 4: Implement one-transaction persistence**

Inside `DB::transaction()`:

- lock the import row and all matching employee rows;
- re-check identity from the locked rows;
- reject any manual schedule conflict;
- upsert imported schedules by `employee_nik + off_start`;
- fetch their IDs and upsert history rows;
- skip classification/review field updates when the existing history is confirmed;
- call `RosterScheduleService::synchronizeSequence()` per imported NIK;
- call `generateUntil()` only for active employees through `now()->addYears(config('roster.generate_years_ahead', 2))->endOfYear()`;
- update counts and set import status completed inside the transaction.

Return non-sensitive counts plus schedule IDs used only in process memory:

```php
[
    'employees' => 127,
    'history_created' => 0,
    'history_updated' => 0,
    'unchanged' => 0,
    'future_generated' => 0,
    'need_review' => 0,
    'late_candidate_schedule_ids' => [101, 102],
]
```

Before saving `ImportHistory::summary` or writing audit metadata, remove `late_candidate_schedule_ids` with `Arr::except($result, ['late_candidate_schedule_ids'])`.

- [ ] **Step 5: Implement the job lifecycle**

```php
final class ProcessRosterScheduleImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $importHistoryId) {}
    public function uniqueId(): string { return 'roster-import-' . $this->importHistoryId; }
    public function middleware(): array { return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(960)]; }
}
```

`handle()` conditionally moves queued→processing and calls commit. Task 7 extends the job to dispatch late reminders after commit using the returned `late_candidate_schedule_ids`. `failed()` records a safe generic error and does not copy the exception SQL/message if it may contain identifiers.

- [ ] **Step 6: Add the confirmation endpoint after the job exists**

`ConfirmRosterScheduleImportRequest` authorizes only Super Admin/HR. The request accepts no file path, checksum, actor ID, or lifecycle status from the client.

Add route `roster-schedules.import.confirm`. The controller must call `ImportHistoryService::markConfirmed()` and dispatch `ProcessRosterScheduleImport` only when the conditional transition wins; otherwise return HTTP 409 with `Import sudah dikonfirmasi, kedaluwarsa, atau tidak valid.`

Record `roster_schedule_import.confirmed` in the controller and `roster_schedule_import.completed` or `.failed` in the job using aggregate counts only.

Add the confirmation button only for `awaiting_confirmation`. JavaScript must show a SweetAlert confirmation, disable the button before POST, and prevent a second click while the request or polling is active.

- [ ] **Step 7: Keep CLI import backward-compatible**

Retain:

```php
public function import(string $path, bool $dryRun = false, ?string $actorId = null): array
```

Delegate reader and validation to the new services. Dry-run returns counts only. Non-dry CLI import must use the same persistence rules but may create an internal `ImportHistory` record so audit behavior remains consistent.

- [ ] **Step 8: Run tests and commit**

Expected: job tests and existing roster unit tests PASS.

```powershell
git add app/Services/Roster/RosterScheduleImportCommitService.php app/Jobs/ProcessRosterScheduleImport.php app/Http/Requests/Roster/ConfirmRosterScheduleImportRequest.php app/Services/Roster/RosterScheduleWorkbookImportService.php app/Http/Controllers/Admin/RosterScheduleImportController.php routes/web.php resources/views/admin/roster-schedules/import.blade.php public/assets/js/roster-schedule-import.js tests/Feature/RosterScheduleImportJobTest.php tests/Feature/RosterScheduleImportControllerTest.php
git commit -m "feat: confirm and process roster imports atomically"
```

---

### Task 6: Link Employee Applications to Schedule Records

**Files:**
- Modify: `app/Http/Requests/Roster/RosterRequest.php`
- Modify: `app/Http/Controllers/User/RosterController.php`
- Modify: `resources/views/user/roster/create.blade.php`
- Test: `tests/Feature/RosterScheduleApplicationLinkTest.php`

**Interfaces:**
- Consumes `roster_schedule_id` from a server-authorized schedule link.
- Produces a linked `Roster` and updates `RosterSchedule::realization_type` to `cuti_roster` or `insentif`.

- [ ] **Step 1: Write failing linked-application tests**

Test that:

- employee can open only their own active schedule;
- another employee's schedule returns 404/403;
- submission stores `roster_schedule_id`;
- `tipe_rencana=1` sets `cuti_roster`, `2` sets `insentif`;
- a pending/approved linked application prevents a duplicate;
- legacy submission without a schedule ID still works.

- [ ] **Step 2: Run tests and verify failures**

Expected: FAIL because create/store ignore schedule IDs.

- [ ] **Step 3: Add optional request validation**

```php
'roster_schedule_id' => ['nullable', 'integer', 'exists:roster_schedules,id'],
```

This validation is not authorization; ownership is enforced in the controller under lock.

- [ ] **Step 4: Secure create prefill and store linkage**

In `create()`:

```php
$schedule = null;
if ($request->filled('roster_schedule')) {
    $schedule = RosterSchedule::query()
        ->active()
        ->where('employee_nik', $request->user()->nik_karyawan)
        ->findOrFail($request->integer('roster_schedule'));
}
return view('user.roster.create', compact('schedule'));
```

In `store()`, lock the schedule belonging to the authenticated employee, reject an existing linked application whose HOD/HR status is pending or approved, include `roster_schedule_id` in `Roster::create()`, and update realization within the same transaction:

```php
$schedule->update([
    'realization_type' => (string) $validated['tipe_rencana'] === '1'
        ? RosterSchedule::REALIZATION_CUTI
        : RosterSchedule::REALIZATION_INSENTIF,
    'updated_by' => (string) $request->user()->getAuthIdentifier(),
]);
```

The Blade shows the selected schedule period and adds a hidden field only when `$schedule` exists.

- [ ] **Step 5: Run tests and commit**

Expected: PASS, including legacy behavior.

```powershell
git add app/Http/Requests/Roster/RosterRequest.php app/Http/Controllers/User/RosterController.php resources/views/user/roster/create.blade.php tests/Feature/RosterScheduleApplicationLinkTest.php
git commit -m "feat: link roster applications to schedules"
```

---

### Task 7: Centralize Reminder Eligibility and Dispatch Late Reminders

**Files:**
- Create: `app/Services/Roster/RosterScheduleReminderEligibilityService.php`
- Modify: `app/Console/Commands/SendRosterScheduleReminders.php`
- Modify: `app/Jobs/SendRosterScheduleReminder.php`
- Modify: `app/Notifications/RosterScheduleReminderNotification.php`
- Modify: `app/Jobs/ProcessRosterScheduleImport.php`
- Test: `tests/Feature/RosterScheduleReminderEligibilityTest.php`
- Test: `tests/Unit/RosterScheduleReminderNotificationTest.php`

**Interfaces:**
- Produces `eligibleQuery(Carbon $from, Carbon $to): Builder`.
- Produces `isEligible(RosterSchedule $schedule, ?Carbon $today = null): bool`.
- Produces `dispatchLate(array $scheduleIds, Carbon $from, Carbon $to): int` covering today through today+13 days.

- [ ] **Step 1: Write failing reminder tests**

Cover active/inactive employee, already sent, linked application, past date, H-14, H-13, H-0, and duplicate job claim. Assert reminder action URL contains `roster_schedule={id}`.

- [ ] **Step 2: Run tests and verify current command over-selects schedules**

Expected: FAIL for linked application suppression and late range.

- [ ] **Step 3: Implement eligibility service**

The query must require:

```php
->active()
->whereNull('reminder_sent_at')
->whereBetween('off_start', [$from->toDateString(), $to->toDateString()])
->whereHas('employee', fn($query) => $query->where('status_resign', 'AKTIF'))
->whereDoesntHave('applications', function ($query) {
    $query->whereIn('status_pengajuan', [0, 1])
        ->whereIn('status_pengajuan_hrd', [0, 1]);
});
```

Do not filter only by `realization_type`; the linked application is the authoritative suppression signal, while imported historical classifications may use realization values.

- [ ] **Step 4: Reuse eligibility in command and job**

The scheduled command requests exactly `today + reminder_days`. The job re-fetches the schedule and calls `isEligible()` immediately before notification so an application submitted after queueing suppresses the email.

For a skipped job, clear `reminder_queued_at`; do not mark it as delivery failure when the reason is an existing application.

- [ ] **Step 5: Dispatch late reminders only after import commit**

Call:

```php
$eligibility->dispatchLate(
    $result['late_candidate_schedule_ids'] ?? [],
    Carbon::today(),
    Carbon::today()->addDays(13)
);
```

Restrict the query with `whereKey($scheduleIds)`, apply the eligibility range, claim each schedule with a conditional update, and dispatch `SendRosterScheduleReminder` with configured stagger delay. Do not persist the ID list in `ImportHistory::summary`, `failure_samples`, or audit metadata.

- [ ] **Step 6: Update notification URL and tests**

```php
->action('Buka Pengajuan Roster', route('roster.create', ['roster_schedule' => $this->schedule->id]))
```

Update `toArray()['url']` identically.

- [ ] **Step 7: Run tests and commit**

```powershell
git add app/Services/Roster/RosterScheduleReminderEligibilityService.php app/Console/Commands/SendRosterScheduleReminders.php app/Jobs/SendRosterScheduleReminder.php app/Notifications/RosterScheduleReminderNotification.php app/Jobs/ProcessRosterScheduleImport.php tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Unit/RosterScheduleReminderNotificationTest.php
git commit -m "feat: send idempotent roster schedule reminders"
```

---

### Task 8: Enforce 12-Hour Private File Cleanup

**Files:**
- Create: `app/Console/Commands/CleanupExpiredRosterImports.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/CleanupExpiredRosterImportsTest.php`

**Interfaces:**
- Produces Artisan command `roster:cleanup-expired-imports --limit=500`.
- Completed/failed records retain status; awaiting-confirmation and validation-failed records become expired after cleanup.

- [ ] **Step 1: Write failing cleanup tests**

Use `Storage::fake('local')` and assert:

- expired source/failure files are deleted;
- paths are nulled;
- pending preview becomes expired;
- completed history remains completed;
- unexpired files remain;
- repeated command execution is harmless.

- [ ] **Step 2: Run test and verify missing command**

Expected: FAIL.

- [ ] **Step 3: Implement bounded idempotent cleanup**

Query roster imports with `expires_at <= now()` and a non-null source/failure path, ordered by ID and limited. For each record, call `SensitiveFileStorageService::delete($path, ['roster-imports/'])`; then conditionally clear paths. Change status to expired only when current status is `awaiting_confirmation` or `validation_failed`.

Record `roster_schedule_import.cleaned` with import ID, actor `system`, prior status, and deleted-file count; do not include filenames containing user input or identity values.

Never recursively delete directories and never construct filesystem paths from user input.

- [ ] **Step 4: Schedule hourly without overlap**

Add:

```php
$schedule->command('roster:cleanup-expired-imports --limit=500')
    ->hourly()
    ->withoutOverlapping();
```

- [ ] **Step 5: Run tests and commit**

```powershell
git add app/Console/Commands/CleanupExpiredRosterImports.php app/Console/Kernel.php tests/Feature/CleanupExpiredRosterImportsTest.php
git commit -m "feat: expire private roster import files"
```

---

### Task 9: End-to-End Regression, Workbook Dry-Run, and Production Checklist

**Files:**
- Modify only files required by failures found in this task.
- Verify: `docs/superpowers/specs/2026-08-27-roster-schedule-history-import-design.md`

**Interfaces:**
- Consumes all prior task deliverables.
- Produces a verified feature with no known spec gaps.

- [ ] **Step 1: Run focused test suite**

```powershell
& 'C:\xampp\php85\php.exe' artisan test tests/Unit/RosterScheduleWorkbookReaderTest.php tests/Unit/RosterHistoryRemarkParserTest.php tests/Unit/RosterScheduleServiceTest.php tests/Unit/RosterScheduleReminderNotificationTest.php tests/Feature/RosterScheduleImportLifecycleTest.php tests/Feature/RosterScheduleImportPreviewTest.php tests/Feature/RosterScheduleImportControllerTest.php tests/Feature/RosterScheduleImportJobTest.php tests/Feature/RosterScheduleApplicationLinkTest.php tests/Feature/RosterScheduleReminderEligibilityTest.php tests/Feature/CleanupExpiredRosterImportsTest.php
```

Expected: all listed tests PASS with zero failures and zero errors.

- [ ] **Step 2: Run broader roster regression tests**

```powershell
& 'C:\xampp\php85\php.exe' artisan test --filter=Roster
```

Expected: zero failures. Investigate every regression; do not weaken existing assertions.

- [ ] **Step 3: Run route and migration safety checks**

```powershell
& 'C:\xampp\php85\php.exe' artisan route:list --name=roster-schedules.import
& 'C:\xampp\php85\php.exe' artisan migrate:status
```

Expected: all six import routes are listed; the new migration appears pending locally before manual migration. Never run `migrate:fresh` or `db:wipe`.

- [ ] **Step 4: Run a read-only dry-run against the reference workbook**

```powershell
& 'C:\xampp\php85\php.exe' artisan roster:import-schedules "C:\Users\New Owner\Downloads\DATABASE ROSTER KIRIM BISMI MASUKAN HRIS (PRD 26 AGUSTUS 2026).xlsx" --dry-run
```

Expected: summary reports 127 employee rows, no database writes, and explicit blocker/warning counts. Run this only against an authorized environment; do not copy NIK/KTP values into terminal logs or the final report.

- [ ] **Step 5: Verify UI manually in a non-production environment**

Check desktop and mobile widths:

1. upload valid workbook;
2. observe disabled/loading state;
3. verify full KTP is visible only to authorized HR/Super Admin;
4. confirm name mismatch is warning-only;
5. upload a KTP-mismatch fixture and verify confirmation is absent;
6. download and inspect failure workbook;
7. confirm a valid import and observe queued→processing→completed polling;
8. verify refresh cannot dispatch a second job;
9. verify failure download returns 404/410 after expiry.

- [ ] **Step 6: Review privacy and audit evidence**

Search logs and persisted JSON for a known test KTP:

```powershell
rg -n "7402243101930001" storage/logs tests/.output 2>$null
```

Expected: no match outside intentionally generated private test failure files. Verify audit rows contain actor/import ID/counts but no KTP.

- [ ] **Step 7: Run final Git diff review**

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: no whitespace errors; only planned files plus pre-existing user changes are present. Do not stage unrelated worktree changes.

- [ ] **Step 8: Commit any verification-only fixes**

If verification required code changes, stage only those exact files and commit:

```powershell
git commit -m "fix: harden roster import verification"
```

If no changes were required, do not create an empty commit.

---

## Production Deployment Checklist

- Use a PHP runtime satisfying the installed lock file (currently PHP 8.4.1+; local verified CLI is PHP 8.5).
- Back up the database before migration.
- Run `php artisan migrate --force`; do not use destructive migration commands.
- Ensure a queue worker is running with a timeout greater than 900 seconds for the import queue.
- Ensure `php artisan schedule:run` executes every minute through cron.
- Ensure `storage/app/private/roster-imports` is not web-accessible.
- Run the workbook dry-run before the first confirmation.
- Confirm cleanup, reminder, failed-job, and import-history monitoring after deployment.
- Roll back application code without deleting successfully imported roster data; nullable columns may remain until a controlled rollback window.
