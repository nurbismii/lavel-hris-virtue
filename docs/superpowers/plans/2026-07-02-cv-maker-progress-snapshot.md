# CV Maker Progress Snapshot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build HRIS-side scheduled snapshots for CV Maker progress and show `Perlu Diingatkan` badges for incomplete draft CVs idle for more than 24 hours.

**Architecture:** Store latest progress per employee in HRIS and append compact history rows when progress/reminder state changes. Read CV Maker through the existing `cv_maker` connection in chunked batches, compute progress with a focused service, and keep UI rendering inside the existing compare module.

**Tech Stack:** Laravel 8, PHP 7.4, MySQL/MariaDB, Blade, Bootstrap 5, jQuery DataTables, PHPUnit.

## Global Constraints

- Do not modify CV Maker database schema or status values.
- Do not store document paths, file contents, full identity numbers, or sensitive CV field values in HRIS progress tables.
- Keep implementation compatible with Laravel 8 and PHP 7.4.
- Use chunked reads for CV Maker data and indexed HRIS snapshot tables.
- Preserve existing CV Maker Compare routes and role/menu authorization.

---

### Task 1: Progress Calculation Service

**Files:**
- Create: `app/Services/CvMaker/CvMakerProgressSnapshotService.php`
- Test: `tests/Unit/CvMakerProgressSnapshotServiceTest.php`

**Interfaces:**
- Produces: `evaluateProgress(array $profile, array $relatedRows, ?Carbon $now = null): array`
- Produces: progress payload keys `current_step`, `current_step_key`, `current_step_label`, `completed_step_count`, `total_step_count`, `is_complete`, `needs_reminder`, `last_activity_at`, `completed_steps`, `missing_steps`.

- [ ] Write failing tests for first incomplete step, complete step 8, and 24-hour reminder.
- [ ] Run `php artisan test --filter=CvMakerProgressSnapshotServiceTest` and confirm failures are due to missing service.
- [ ] Implement minimal progress calculation helpers.
- [ ] Re-run the unit test and confirm it passes.

### Task 2: Snapshot Storage

**Files:**
- Create: `database/migrations/2026_07_02_000001_create_cv_maker_progress_snapshot_tables.php`
- Create: `app/Models/CvMakerProgressStatus.php`
- Create: `app/Models/CvMakerProgressHistory.php`
- Test: `tests/Feature/CvMakerProgressSnapshotStorageTest.php`

**Interfaces:**
- Consumes: `evaluateProgress(...)`.
- Produces: `syncEmployeeProgress(string $nik, ?array $cvProfilePayload, bool $dryRun = false): array`.

- [ ] Write failing storage tests for first snapshot, progress change history, and reminder status change history.
- [ ] Run targeted storage test and confirm table/model failures.
- [ ] Add migration and models.
- [ ] Implement snapshot persistence with transaction and compact histories.
- [ ] Re-run targeted storage test.

### Task 3: CV Maker Batch Sync Command

**Files:**
- Create: `app/Console/Commands/SyncCvMakerProgressSnapshots.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/CvMakerProgressSyncCommandTest.php`

**Interfaces:**
- Produces command: `cv-maker:sync-progress {--limit=500} {--chunk=100} {--dry-run}`.

- [ ] Write failing command test with in-memory HRIS and cv_maker tables.
- [ ] Run targeted command test and confirm command is missing.
- [ ] Implement chunked employee reads and CV Maker batch lookups.
- [ ] Register hourly schedule with `withoutOverlapping()`.
- [ ] Re-run command test.

### Task 4: Compare UI Integration

**Files:**
- Modify: `app/Services/CvMaker/CvMakerCompareService.php`
- Modify: `resources/views/admin/cv-maker-compare/index.blade.php`
- Modify: `resources/views/admin/cv-maker-compare/show.blade.php`
- Modify: `public/assets/css/admin-cv-maker-compare.css`
- Test: extend `tests/Unit/CvMakerCompareServiceTest.php` or add feature test.

**Interfaces:**
- Consumes: `CvMakerProgressStatus` rows by employee NIK.
- Produces: DataTables row HTML with progress badge and reminder filter.

- [ ] Write failing test for rendering `Perlu Diingatkan` and current step in compare row.
- [ ] Add reminder filter to DataTables request and service query.
- [ ] Render progress summary in list and detail pages.
- [ ] Render latest progress histories in detail page.
- [ ] Re-run compare tests.

### Task 5: Verification

**Files:**
- No new files expected.

- [ ] Run `php artisan test --filter=CvMakerProgress`.
- [ ] Run `php artisan test --filter=CvMakerCompareServiceTest`.
- [ ] Run `php artisan route:list --name=cv-maker-compare`.
- [ ] Run `php artisan cv-maker:sync-progress --dry-run --limit=10`.
- [ ] Review `git diff` for sensitive data exposure and unrelated changes.
