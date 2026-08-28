<?php

namespace Tests\Feature;

use App\Http\Requests\Roster\UploadRosterScheduleImportRequest;
use App\Models\ImportHistory;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use App\Jobs\ProcessRosterScheduleImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleImportControllerTest extends TestCase
{
    use CreatesRosterImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        $this->createRosterImportSchema();
    }

    protected function tearDown(): void
    {
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_upload_request_requires_hr_menu_access_and_xlsx_limit(): void
    {
        $employee = $this->user('employee', 'Employee', ['roster_schedule']);
        $hrWithoutMenu = $this->user('hr-no-menu', 'HR', []);
        $hr = $this->user('hr', 'HR', ['roster_schedule']);

        $this->assertFalse($this->requestFor($employee)->authorize());
        $this->assertFalse($this->requestFor($hrWithoutMenu)->authorize());
        $this->assertTrue($this->requestFor($hr)->authorize());
        $this->assertContains('mimes:xlsx', $this->requestFor($hr)->rules()['file']);
        $this->assertContains('mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $this->requestFor($hr)->rules()['file']);
        $this->assertContains('max:10240', $this->requestFor($hr)->rules()['file']);
    }

    public function test_all_preview_routes_are_named_with_confirmation_route(): void
    {
        $this->assertTrue(route('roster-schedules.import.create') !== '');
        $this->assertTrue(route('roster-schedules.import.store') !== '');
        $this->assertTrue(route('roster-schedules.import.show', 1) !== '');
        $this->assertTrue(route('roster-schedules.import.status', 1) !== '');
        $this->assertTrue(route('roster-schedules.import.failure', 1) !== '');
        $this->assertTrue(app('router')->getRoutes()->hasNamedRoute('roster-schedules.import.confirm'));
    }

    public function test_confirmation_javascript_has_confirmation_duplicate_guard_and_bounded_polling(): void
    {
        $script = file_get_contents(public_path('assets/js/roster-schedule-import.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("button.prop('disabled')", $script);
        $this->assertStringContainsString("button.data('label', original).prop('disabled', true)", $script);
        $this->assertStringContainsString("title: 'Konfirmasi import?'", $script);
        $this->assertStringContainsString('window.setTimeout(poll, 0)', $script);
        $this->assertStringContainsString('Date.now() - started > 720000', $script);
        $this->assertStringContainsString('xhr.status === 419', $script);
    }

    public function test_non_hr_and_hr_without_menu_are_forbidden_by_real_routes(): void
    {
        foreach ([$this->user('employee-http', 'Employee', ['roster_schedule']), $this->user('no-menu-http', 'HR', [])] as $user) {
            $this->actingAs($user)->get(route('roster-schedules.import.create'))->assertForbidden();
            $this->actingAs($user)->post(route('roster-schedules.import.store'), [
                'file' => UploadedFile::fake()->create('roster.xlsx', 10),
            ])->assertForbidden();
        }
    }

    public function test_invalid_upload_validation_uses_real_http_response(): void
    {
        $hr = $this->user('hr-validation', 'HR', ['roster_schedule']);

        $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), [
            'file' => UploadedFile::fake()->create('roster.txt', 10, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $disguised = UploadedFile::fake()->createWithContent('roster.xlsx', 'not an xlsx workbook');
        $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $disguised])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), [
            'file' => UploadedFile::fake()->create('roster.xlsx', 10241, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_hr_upload_uses_private_storage_and_returns_preview_lifecycle(): void
    {
        $nik = '016090940';
        $ktp = '7402243101930001';
        $this->seedRosterEmployee($nik, $ktp);
        $hr = $this->user('hr-upload', 'HR', ['roster_schedule']);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        $file = new UploadedFile($path, 'roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $file]);
        $response->assertOk()->assertJsonPath('success', true);
        $history = ImportHistory::firstOrFail();

        $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->status);
        $this->assertMatchesRegularExpression('#^roster-imports/[0-9a-f-]+/source\.xlsx$#', $history->file_path);
        $this->assertTrue(Storage::disk('local')->exists('private/' . $history->file_path));
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path('private/' . $history->file_path)), $history->file_checksum);
        $this->assertTrue($history->expires_at->between(now()->addHours(11), now()->addHours(12)->addMinute()));
    }

    public function test_cross_user_status_show_and_failure_are_forbidden_before_file_access(): void
    {
        $owner = $this->user('owner-http', 'HR', ['roster_schedule']);
        $other = $this->user('other-http', 'Employee', []);
        $history = ImportHistory::create([
            'import_id' => 'foreign-import',
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_VALIDATION_FAILED,
            'created_by' => $owner->id,
            'file_path' => 'roster-imports/foreign-import/source.xlsx',
            'failure_file_path' => 'roster-imports/foreign-import/failures.xlsx',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($other)->get(route('roster-schedules.import.show', $history))->assertForbidden();
        $this->actingAs($other)->getJson(route('roster-schedules.import.status', $history))->assertForbidden();
        $this->actingAs($other)->get(route('roster-schedules.import.failure', $history))->assertForbidden();
    }

    public function test_authorized_valid_show_renders_full_identity_and_confirmation_action(): void
    {
        $nik = '016090940';
        $ktp = '7402243101930001';
        $this->seedRosterEmployee($nik, $ktp, 'Nama HRIS');
        $hr = $this->user('hr-show', 'HR', ['roster_schedule']);
        $hr->update(['nik_karyawan' => $nik]);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp, 'name' => '<b>Nama Excel</b>']]);
        Storage::disk('local')->put('private/roster-imports/show-import/source.xlsx', file_get_contents($path));
        $history = ImportHistory::create([
            'import_id' => 'show-import', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/show-import/source.xlsx',
            'summary' => ['total_rows' => 1, 'blocker_count' => 0, 'warning_count' => 0],
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($hr)->get(route('roster-schedules.import.show', $history))
            ->assertOk()->assertSee($nik)->assertSee($ktp)->assertSee('&lt;b&gt;Nama Excel&lt;/b&gt;', false)
            ->assertSee('id="roster-import-confirm-form"', false)
            ->assertSee('Konfirmasi dan Proses');
    }

    public function test_invalid_preview_show_renders_blocker_and_failure_download_without_confirmation(): void
    {
        $nik = '016090942';
        $ktp = '7402243101930003';
        $actorNik = '016090999';
        $this->seedRosterEmployee($actorNik, '7402243101930099', 'HR Preview');
        $hr = $this->user('hr-invalid-show', 'HR', ['roster_schedule']);
        $hr->update(['nik_karyawan' => $actorNik]);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp, 'name' => 'Tidak Ditemukan']]);
        Storage::disk('local')->put('private/roster-imports/invalid-show/source.xlsx', file_get_contents($path));
        Storage::disk('local')->put('private/roster-imports/invalid-show/failures.xlsx', 'failure content');
        $history = ImportHistory::create([
            'import_id' => 'invalid-show', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_VALIDATION_FAILED, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/invalid-show/source.xlsx',
            'failure_file_path' => 'roster-imports/invalid-show/failures.xlsx',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($hr)->get(route('roster-schedules.import.show', $history))
            ->assertOk()->assertSee('employee_not_found')->assertSee('Unduh File Kegagalan')
            ->assertSee(route('roster-schedules.import.failure', $history), false)
            ->assertDontSee('Konfirmasi dan Proses');
    }

    public function test_status_and_failure_endpoints_keep_sensitive_values_private_and_download_safe_file(): void
    {
        $nik = '016090941';
        $ktp = '7402243101930002';
        $this->seedRosterEmployee($nik, $ktp);
        $hr = $this->user('hr-status', 'HR', ['roster_schedule']);
        $history = ImportHistory::create([
            'import_id' => 'failure-import', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_VALIDATION_FAILED, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/failure-import/source.xlsx',
            'failure_file_path' => 'roster-imports/failure-import/failures.xlsx',
            'file_checksum' => 'secret-checksum', 'summary' => ['total_rows' => 1], 'expires_at' => now()->addHour(),
        ]);
        Storage::disk('local')->put('private/' . $history->failure_file_path, 'failure content');

        $json = $this->actingAs($hr)->getJson(route('roster-schedules.import.status', $history))
            ->assertOk()->assertJsonPath('data.terminal', true)->getContent();
        $this->assertStringNotContainsString($ktp, $json);
        $this->assertStringNotContainsString($history->file_path, $json);
        $this->assertStringNotContainsString($history->failure_file_path, $json);
        $this->assertStringNotContainsString('secret-checksum', $json);

        $download = $this->actingAs($hr)->get(route('roster-schedules.import.failure', $history))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename=roster-import-failures.xlsx')
            ->assertStreamedContent('failure content');
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
    }

    public function test_status_json_marks_awaiting_and_expired_records_and_redacts_untrusted_summary(): void
    {
        $nik = '016090943';
        $ktp = '7402243101930004';
        $hr = $this->user('hr-status-lifecycle', 'HR', ['roster_schedule']);
        $awaiting = ImportHistory::create([
            'import_id' => 'awaiting-status', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/awaiting-status/source.xlsx',
            'failure_file_path' => 'roster-imports/awaiting-status/failures.xlsx',
            'file_checksum' => 'checksum-must-not-leak',
            'summary' => [
                'total_rows' => '7',
                'blocker_count' => true,
                'warning_count' => 2.9,
                'no_ktp' => $ktp,
                'relative_path' => 'roster-imports/' . $nik . '/source.xlsx',
                'absolute_path' => 'C:/private/' . $nik,
                'raw_rows' => [['no_ktp' => $ktp]],
                'unexpected_message' => 'KTP ' . $ktp,
            ],
            'expires_at' => now()->addHour(),
        ]);
        $expired = ImportHistory::create([
            'import_id' => 'expired-status', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id, 'expires_at' => now()->subMinute(),
        ]);

        $awaitingJson = $this->actingAs($hr)->getJson(route('roster-schedules.import.status', $awaiting))
            ->assertOk()->assertJsonPath('data.terminal', false)
            ->assertJsonPath('data.summary.total_rows', 7)
            ->assertJsonPath('data.summary.blocker_count', 1)
            ->assertJsonPath('data.summary.warning_count', 2)
            ->getContent();
        $this->assertStringNotContainsString($ktp, $awaitingJson);
        $this->assertStringNotContainsString('relative_path', $awaitingJson);
        $this->assertStringNotContainsString('absolute_path', $awaitingJson);
        $this->assertStringNotContainsString('raw_rows', $awaitingJson);
        $this->assertStringNotContainsString('checksum-must-not-leak', $awaitingJson);
        $this->actingAs($hr)->getJson(route('roster-schedules.import.status', $expired))
            ->assertOk()->assertJsonPath('data.status', ImportHistory::STATUS_EXPIRED)->assertJsonPath('data.terminal', true);
    }

    public function test_wrong_type_expired_and_missing_failure_file_return_safe_http_errors(): void
    {
        $hr = $this->user('hr-errors', 'HR', ['roster_schedule']);
        $wrongType = ImportHistory::create(['import_id' => 'wrong-type', 'import_type' => ImportHistory::TYPE_EMPLOYEE, 'status' => 'queued', 'created_by' => $hr->id]);
        $expired = ImportHistory::create(['import_id' => 'expired', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE, 'status' => 'expired', 'created_by' => $hr->id, 'expires_at' => now()->subMinute()]);
        $missing = ImportHistory::create(['import_id' => 'missing', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE, 'status' => ImportHistory::STATUS_VALIDATION_FAILED, 'created_by' => $hr->id, 'failure_file_path' => 'roster-imports/missing/failures.xlsx', 'expires_at' => now()->addHour()]);

        $this->actingAs($hr)->get(route('roster-schedules.import.show', $wrongType))->assertNotFound();
        $this->actingAs($hr)->get(route('roster-schedules.import.failure', $expired))->assertGone();
        $this->actingAs($hr)->get(route('roster-schedules.import.failure', $missing))->assertNotFound();
    }

    public function test_authorized_confirmation_dispatches_one_job_and_rejects_duplicate_or_invalid_state(): void
    {
        Queue::fake();
        $audit = new class extends AuditTrailService {
            public array $records = [];

            public function record(array $data): ?\App\Models\AuditTrail
            {
                $this->records[] = $data;

                return null;
            }
        };
        $this->app->instance(AuditTrailService::class, $audit);
        $hr = $this->user('hr-confirm', 'HR', ['roster_schedule']);
        $nik = '016090948';
        $ktp = '7402243101930009';
        $this->seedRosterEmployee($nik, $ktp);
        $workbook = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        Storage::disk('local')->put('private/roster-imports/confirm-import/source.xlsx', file_get_contents($workbook));
        $history = ImportHistory::create([
            'import_id' => 'confirm-import', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/confirm-import/source.xlsx',
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/roster-imports/confirm-import/source.xlsx')),
            'summary' => ['total_rows' => 1, 'blocker_count' => 0, 'warning_count' => 0],
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $history), [
            'file_path' => 'attacker-path', 'file_checksum' => 'attacker-checksum', 'status' => 'completed',
        ])->assertUnprocessable();
        Queue::assertNothingPushed();

        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $history))
            ->assertOk()->assertJsonPath('data.status', ImportHistory::STATUS_QUEUED);
        Queue::assertPushed(ProcessRosterScheduleImport::class, fn (ProcessRosterScheduleImport $job) => $job->historyId === $history->id);
        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $history))->assertStatus(409);
        Queue::assertPushed(ProcessRosterScheduleImport::class, 1);
        $this->assertSame(ImportHistory::STATUS_QUEUED, $history->fresh()->status);
        $this->assertSame('roster-imports/confirm-import/source.xlsx', $history->fresh()->file_path);
        $this->assertCount(1, $audit->records);
        $this->assertSame('roster_schedule_import.confirmed', $audit->records[0]['event']);
        $this->assertSame([
            'import_id' => 'confirm-import',
            'status' => ImportHistory::STATUS_QUEUED,
            'summary' => ['total_rows' => 1, 'blocker_count' => 0, 'warning_count' => 0],
        ], $audit->records[0]['metadata']);
    }

    public function test_confirmation_preflight_rejects_missing_or_changed_source_without_queueing(): void
    {
        Queue::fake();
        $hr = $this->user('hr-confirm-preflight', 'HR', ['roster_schedule']);
        $history = ImportHistory::create([
            'import_id' => 'missing-confirm-source', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/missing-confirm-source/source.xlsx', 'file_checksum' => 'changed',
            'summary' => ['total_rows' => 1, 'blocker_count' => 0], 'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $history));
        $response->assertStatus(409)->assertJsonPath('message', 'Import sudah dikonfirmasi, kedaluwarsa, atau tidak valid.');
        Queue::assertNothingPushed();
        $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->fresh()->status);
        $this->assertStringNotContainsString('roster-imports/missing-confirm-source', $response->getContent());
    }

    public function test_expired_changed_blocked_wrong_type_and_unauthorized_confirmations_never_queue(): void
    {
        Queue::fake();
        $hr = $this->user('hr-confirm-rejected', 'HR', ['roster_schedule']);
        $ordinary = $this->user('ordinary-confirm-rejected', 'Employee', []);
        $nik = '016090949';
        $ktp = '7402243101930019';
        $this->seedRosterEmployee($nik, $ktp);

        $validWorkbook = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        Storage::disk('local')->put('private/roster-imports/expired-confirm/source.xlsx', file_get_contents($validWorkbook));
        Storage::disk('local')->put('private/roster-imports/changed-confirm/source.xlsx', file_get_contents($validWorkbook));
        Storage::disk('local')->put('private/roster-imports/unauthorized-confirm/source.xlsx', file_get_contents($validWorkbook));

        $expired = ImportHistory::create([
            'import_id' => 'expired-confirm', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/expired-confirm/source.xlsx',
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/roster-imports/expired-confirm/source.xlsx')),
            'expires_at' => now()->subMinute(),
        ]);
        $changed = ImportHistory::create([
            'import_id' => 'changed-confirm', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/changed-confirm/source.xlsx', 'file_checksum' => str_repeat('0', 64),
            'expires_at' => now()->addHour(),
        ]);
        $blockedWorkbook = $this->makeRosterWorkbook([['nik' => '', 'ktp' => $ktp]]);
        Storage::disk('local')->put('private/roster-imports/blocked-confirm/source.xlsx', file_get_contents($blockedWorkbook));
        $blocked = ImportHistory::create([
            'import_id' => 'blocked-confirm', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/blocked-confirm/source.xlsx',
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/roster-imports/blocked-confirm/source.xlsx')),
            'expires_at' => now()->addHour(),
        ]);
        $wrongType = ImportHistory::create([
            'import_id' => 'wrong-confirm-type', 'import_type' => ImportHistory::TYPE_EMPLOYEE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'expires_at' => now()->addHour(),
        ]);
        $unauthorized = ImportHistory::create([
            'import_id' => 'unauthorized-confirm', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $hr->id,
            'file_path' => 'roster-imports/unauthorized-confirm/source.xlsx',
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/roster-imports/unauthorized-confirm/source.xlsx')),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $expired))->assertStatus(409);
        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $changed))->assertStatus(409);
        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $blocked))->assertStatus(409);
        $this->actingAs($hr)->postJson(route('roster-schedules.import.confirm', $wrongType))->assertNotFound();
        $this->actingAs($ordinary)->postJson(route('roster-schedules.import.confirm', $unauthorized))->assertForbidden();

        Queue::assertNothingPushed();
        foreach ([$expired, $changed, $blocked, $unauthorized] as $history) {
            $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->fresh()->status);
        }
    }

    public function test_hr_can_access_another_hr_import_while_an_ordinary_menu_holder_is_forbidden(): void
    {
        $owner = $this->user('owner-all-access', 'HR', ['roster_schedule']);
        $otherHr = $this->user('other-all-access', 'HR', ['roster_schedule']);
        $menuHolder = $this->user('employee-menu-holder', 'Employee', ['roster_schedule']);
        $history = ImportHistory::create([
            'import_id' => 'cross-access', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'created_by' => $owner->id,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($otherHr)->getJson(route('roster-schedules.import.status', $history))->assertOk();
        $this->actingAs($menuHolder)->getJson(route('roster-schedules.import.status', $history))->assertForbidden();
    }

    public function test_audit_metadata_for_upload_preview_and_failure_download_excludes_identity_and_paths(): void
    {
        $nik = '016090944';
        $ktp = '7402243101930005';
        $this->seedRosterEmployee($nik, $ktp);
        $hr = $this->user('hr-audit', 'HR', ['roster_schedule']);
        $audit = new class extends AuditTrailService {
            public array $records = [];

            public function record(array $data): ?\App\Models\AuditTrail
            {
                $this->records[] = $data;

                return null;
            }
        };
        $this->app->instance(AuditTrailService::class, $audit);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        $file = new UploadedFile($path, 'employee-private-name.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $file])->assertOk();
        $history = ImportHistory::firstOrFail();
        $history->update([
            'status' => ImportHistory::STATUS_VALIDATION_FAILED,
            'failure_file_path' => 'roster-imports/' . $history->import_id . '/failures.xlsx',
            'summary' => [
                'total_rows' => '4',
                'blocker_count' => true,
                'warning_count' => 2.9,
                'unexpected' => ['no_ktp' => $ktp, 'path' => 'C:/private/source.xlsx'],
            ],
        ]);
        Storage::disk('local')->put('private/' . $history->failure_file_path, 'failure content');
        $this->actingAs($hr)->get(route('roster-schedules.import.failure', $history))->assertOk();

        $this->assertSame([
            'roster_schedule_import.uploaded',
            'roster_schedule_import.previewed',
            'roster_schedule_import.failure_downloaded',
        ], array_column($audit->records, 'event'));
        foreach ($audit->records as $record) {
            $this->assertAuditDataIsSafe($record['metadata'], [$ktp, $history->file_path, 'employee-private-name.xlsx']);
        }
        $this->assertSame([
            'total_rows' => 4,
            'blocker_count' => 1,
            'warning_count' => 2,
        ], $audit->records[2]['metadata']['summary']);
    }

    public function test_source_storage_failure_returns_generic_response_without_history_or_private_files(): void
    {
        $nik = '016090946';
        $ktp = '7402243101930007';
        $hr = $this->user('hr-storage-failure', 'HR', ['roster_schedule']);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        $file = new UploadedFile($path, 'roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->app->instance(SensitiveFileStorageService::class, new class extends SensitiveFileStorageService {
            public function storeUploadedFileAs(UploadedFile $file, string $relativeDirectory, string $filename): string
            {
                throw new RuntimeException('C:/private/roster-imports/source.xlsx KTP 7402243101930007');
            }
        });
        $this->bindNoopAudit();
        Log::spy();

        $response = $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $file]);

        $response->assertStatus(500)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'File gagal diproses. Silakan periksa format workbook dan coba lagi.');
        $this->assertSame(0, ImportHistory::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('private/roster-imports'));
        $this->assertSafePreviewFailureLog($ktp, 'C:/private');
    }

    public function test_history_creation_failure_cleans_source_file_without_history_or_sensitive_log_details(): void
    {
        $nik = '016090947';
        $ktp = '7402243101930008';
        $hr = $this->user('hr-history-failure', 'HR', ['roster_schedule']);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        $file = new UploadedFile($path, 'roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        DB::unprepared("CREATE TRIGGER fail_roster_import_history BEFORE INSERT ON import_histories BEGIN SELECT RAISE(ABORT, 'C:/private/roster-imports KTP 7402243101930008'); END;");
        Log::spy();

        $response = $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $file]);

        $response->assertStatus(500)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'File gagal diproses. Silakan periksa format workbook dan coba lagi.');
        $this->assertSame(0, ImportHistory::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('private/roster-imports'));
        $this->assertSafePreviewFailureLog($ktp, 'C:/private');
    }

    public function test_preview_exception_after_failure_file_creation_removes_private_files_history_and_sensitive_log_details(): void
    {
        $nik = '016090945';
        $ktp = '7402243101930006';
        $hr = $this->user('hr-preview-exception', 'HR', ['roster_schedule']);
        $path = $this->makeRosterWorkbook([['nik' => '', 'ktp' => $ktp]]);
        $file = new UploadedFile($path, 'roster.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $failurePath = null;
        $audit = new class extends AuditTrailService {
            public function record(array $data): ?\App\Models\AuditTrail
            {
                return null;
            }
        };
        $this->app->instance(AuditTrailService::class, $audit);
        Log::spy();
        Excel::shouldReceive('store')->once()->andReturnUsing(function ($export, string $path, string $disk) use (&$failurePath, $ktp): bool {
            $failurePath = $path;
            Storage::disk($disk)->put($path, 'deterministic failure workbook');

            throw new RuntimeException('exception path C:/private/roster-imports/secret.xlsx KTP ' . $ktp);
        });

        $response = $this->actingAs($hr)->postJson(route('roster-schedules.import.store'), ['file' => $file]);

        $response->assertStatus(500)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'File gagal diproses. Silakan periksa format workbook dan coba lagi.');
        $this->assertNotNull($failurePath);
        $relativeFailurePath = substr($failurePath, strlen('private/'));
        $this->assertFalse(Storage::disk('local')->exists($failurePath));
        $this->assertFalse(Storage::disk('local')->exists(dirname($failurePath) . '/source.xlsx'));
        $this->assertSame(0, ImportHistory::query()->count());
        $this->assertStringNotContainsString($ktp, $response->getContent());
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($ktp, $relativeFailurePath): bool {
            return $message === 'Roster import preview failed.'
                && ($context['code'] ?? null) === 'roster_import_preview_failed'
                && ($context['exception_class'] ?? null) === RuntimeException::class
                && !str_contains(json_encode($context), $ktp)
                && !str_contains(json_encode($context), $relativeFailurePath)
                && !str_contains(json_encode($context), 'C:/private');
        });
    }

    private function requestFor(User $user): UploadRosterScheduleImportRequest
    {
        $request = UploadRosterScheduleImportRequest::create('/admin/roster-schedules/import', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function user(string $id, string $role, array $menus): User
    {
        $roleId = DB::table('roles')->insertGetId(['permission_role' => $role, 'menu_permissions' => json_encode($menus)]);

        return User::create(['id' => $id, 'name' => $role, 'role_id' => $roleId]);
    }

    private function assertAuditDataIsSafe(array $data, array $forbiddenValues): void
    {
        foreach ($data as $key => $value) {
            $this->assertNotContains(strtolower((string) $key), [
                'ktp',
                'no_ktp',
                'row',
                'rows',
                'source_path',
                'failure_file_path',
                'original_filename',
            ]);
            if (is_array($value)) {
                $this->assertAuditDataIsSafe($value, $forbiddenValues);
                continue;
            }

            foreach ($forbiddenValues as $forbiddenValue) {
                $this->assertStringNotContainsString($forbiddenValue, (string) $value);
            }
        }
    }

    private function bindNoopAudit(): void
    {
        $this->app->instance(AuditTrailService::class, new class extends AuditTrailService {
            public function record(array $data): ?\App\Models\AuditTrail
            {
                return null;
            }
        });
    }

    private function assertSafePreviewFailureLog(string $ktp, string $path): void
    {
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($ktp, $path): bool {
            return $message === 'Roster import preview failed.'
                && ($context['code'] ?? null) === 'roster_import_preview_failed'
                && !str_contains(json_encode($context), $ktp)
                && !str_contains(json_encode($context), $path);
        });
    }
}
