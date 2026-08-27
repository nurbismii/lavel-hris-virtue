<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Models\User;
use App\Services\ImportHistory\ImportHistoryService;
use App\Services\Roster\RosterScheduleImportPreviewService;
use App\Services\Roster\RosterScheduleImportValidationService;
use App\Services\Roster\RosterScheduleWorkbookReader;
use App\Support\Roster\RosterWorkbookData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleImportPreviewTest extends TestCase
{
    use CreatesRosterImportSchema;

    private const KTP = '7402243101930001';
    private const NIK = '016090940';

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

    public function test_empty_workbook_data_returns_empty_result_without_roster_schedule_query(): void
    {
        DB::enableQueryLog();
        $data = new RosterWorkbookData('Roster', [], collect([[
            'row_number' => 3,
            'nik' => self::NIK,
            'no_ktp' => self::KTP,
            'employee_name' => 'Nama Excel',
            'identity_error' => null,
            'periods' => [[
                'year' => 2026,
                'period_number' => 1,
                'source_column' => 'E',
                'remark_column' => 'F',
                'off_start' => null,
                'raw_remark' => null,
                'cell_error' => 'invalid_date',
            ]],
        ]]));
        $result = app(RosterScheduleImportValidationService::class)->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame(1, $result['summary']['total_rows']);
        $this->assertSame([], array_filter(DB::getQueryLog(), fn(array $query): bool => str_contains(strtolower($query['query']), 'roster_schedules')));
    }

    public function test_matching_identity_name_warning_and_blockers_are_classified(): void
    {
        $this->seedRosterEmployee(self::NIK, self::KTP, 'Nama HRIS');
        $result = $this->validate([['nik' => self::NIK, 'ktp' => self::KTP, 'name' => 'Nama Lain']]);

        $this->assertTrue($result['is_valid']);
        $this->assertCount(0, $result['errors']);
        $this->assertSame('name_mismatch', $result['warnings'][0]['code']);
    }

    public function test_numeric_unsafe_ktp_and_malformed_nik_are_blocked(): void
    {
        $this->seedRosterEmployee(self::NIK, self::KTP);
        $numeric = $this->validate([['nik' => self::NIK, 'ktp' => self::KTP, 'ktp_type' => DataType::TYPE_NUMERIC]]);
        $malformedNik = $this->validate([['nik' => 'NIK-INVALID', 'ktp' => self::KTP]]);

        $this->assertContains('invalid_ktp', array_column($numeric['errors'], 'code'));
        $this->assertContains('invalid_nik', array_column($malformedNik['errors'], 'code'));
    }

    public function test_malformed_stored_ktp_and_employee_not_found_are_blocked(): void
    {
        $this->seedRosterEmployee(self::NIK, 'invalid-ktp');
        $storedKtp = $this->validate([['nik' => self::NIK, 'ktp' => self::KTP]]);
        $missingEmployee = $this->validate([['nik' => '016090941', 'ktp' => '7402243101930002']]);

        $this->assertContains('invalid_ktp', array_column($storedKtp['errors'], 'code'));
        $this->assertContains('employee_not_found', array_column($missingEmployee['errors'], 'code'));
    }

    public function test_invalid_dates_and_duplicate_off_starts_are_blocked(): void
    {
        $this->seedRosterEmployee(self::NIK, self::KTP);
        $result = $this->validate([
            ['nik' => self::NIK, 'ktp' => self::KTP, 'off_start' => 'not a date'],
            ['nik' => self::NIK, 'ktp' => self::KTP],
            ['nik' => self::NIK, 'ktp' => self::KTP],
        ]);

        $codes = array_column($result['errors'], 'code');
        $this->assertContains('invalid_date', $codes);
        $this->assertContains('duplicate_off_start', $codes);
    }

    public function test_manual_conflict_unchanged_update_and_inactive_are_classified(): void
    {
        $this->seedRosterEmployee(self::NIK, self::KTP);
        $this->seedRosterEmployee('016090941', '7402243101930002', 'Nonaktif', 'RESIGN');
        DB::table('roster_schedules')->insert(['employee_nik' => self::NIK, 'period_year' => 2026, 'period_number' => 1, 'off_start' => '2026-09-10', 'source' => 'manual']);

        $this->assertContains('manual_conflict', array_column($this->validate([['nik' => self::NIK, 'ktp' => self::KTP]])['errors'], 'code'));
        DB::table('roster_schedules')->where('employee_nik', self::NIK)->update(['source' => 'import']);
        $this->assertSame('unchanged', $this->validate([['nik' => self::NIK, 'ktp' => self::KTP]])['rows'][0]['action']);
        DB::table('roster_schedules')->where('employee_nik', self::NIK)->update(['period_year' => 2025]);
        $this->assertSame('update', $this->validate([['nik' => self::NIK, 'ktp' => self::KTP]])['rows'][0]['action']);

        $inactive = $this->validate([['nik' => '016090941', 'ktp' => '7402243101930002']]);
        $this->assertTrue($inactive['is_valid']);
        $this->assertContains('inactive_employee', array_column($inactive['warnings'], 'code'));
    }

    public function test_invalid_preview_expires_after_twelve_hours_and_persists_no_ktp(): void
    {
        $actor = $this->actor('user-1');
        $history = $this->historyWithSource('import-1', $actor, [['nik' => self::NIK, 'ktp' => self::KTP, 'name' => '=formula']]);

        app(RosterScheduleImportPreviewService::class)->preview($history, $actor);
        $fresh = $history->fresh();

        $this->assertSame(ImportHistory::STATUS_VALIDATION_FAILED, $fresh->status);
        $this->assertNotNull($fresh->expires_at);
        $this->assertTrue($fresh->expires_at->between(now()->addHours(11), now()->addHours(12)->addMinute()));
        $this->assertStringNotContainsString(self::KTP, json_encode([$fresh->summary, $fresh->failure_samples]));
        $this->assertTrue(Storage::disk('local')->exists('private/' . $fresh->failure_file_path));
        $book = IOFactory::load(Storage::disk('local')->path('private/' . $fresh->failure_file_path));
        $this->assertSame(self::KTP, $book->getActiveSheet()->getCell('D2')->getValue());
        $this->assertSame("'=formula", $book->getActiveSheet()->getCell('E2')->getValue());
    }

    public function test_expired_confirmation_is_rejected(): void
    {
        $actor = $this->actor('user-2');
        $history = $this->history('expired-import', $actor, ['status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'expires_at' => now()->subMinute()]);

        $this->assertFalse(app(ImportHistoryService::class)->markConfirmed($history->id, $actor->id));
        $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->fresh()->status);
    }

    public function test_valid_preview_can_be_confirmed_only_once(): void
    {
        $this->seedRosterEmployee(self::NIK, self::KTP);
        $actor = $this->actor('confirm-user');
        $history = $this->historyWithSource('confirm-import', $actor, [['nik' => self::NIK, 'ktp' => self::KTP]]);

        app(RosterScheduleImportPreviewService::class)->preview($history, $actor);

        $service = app(ImportHistoryService::class);
        $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->fresh()->status);
        $this->assertTrue($service->markConfirmed($history->id, $actor->id));
        $this->assertFalse($service->markConfirmed($history->id, $actor->id));
    }

    public function test_preview_rejects_non_hr_no_menu_non_owner_wrong_type_and_status_before_file_read(): void
    {
        $owner = $this->actor('owner');
        $cases = [
            [$this->history('non-hr', $owner), $this->actor('non-hr', 'Employee')],
            [$this->history('no-menu', $owner), $this->actor('no-menu', 'HR', [])],
            [$this->history('other-owner', $owner), $this->actor('other-owner')],
            [$this->history('wrong-type', $owner, ['import_type' => ImportHistory::TYPE_EMPLOYEE]), $owner],
            [$this->history('wrong-status', $owner, ['status' => ImportHistory::STATUS_PROCESSING]), $owner],
        ];

        foreach ($cases as [$history, $actor]) {
            try {
                app(RosterScheduleImportPreviewService::class)->preview($history, $actor);
                $this->fail('Preview harus ditolak sebelum membaca file.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Anda tidak memiliki akses untuk memproses import ini.', $exception->getMessage());
            }
        }
    }

    public function test_failed_failure_export_does_not_persist_failure_path(): void
    {
        $actor = $this->actor('user-3');
        $history = $this->historyWithSource('store-false', $actor, [['nik' => self::NIK, 'ktp' => self::KTP]]);
        Excel::shouldReceive('store')->once()->andReturnFalse();

        try {
            app(RosterScheduleImportPreviewService::class)->preview($history, $actor);
            $this->fail('Failure export yang gagal harus membatalkan preview.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Workbook kegagalan tidak dapat disimpan.', $exception->getMessage());
        }

        $this->assertNull($history->fresh()->failure_file_path);
    }

    public function test_recursive_ktp_sanitization_removes_nested_and_embedded_values(): void
    {
        $actor = $this->actor('user-4');
        $history = $this->history('sanitize-import', $actor);
        app(ImportHistoryService::class)->markValidationFailed(
            $history->id,
            ['nested' => ['nomor_ktp' => self::KTP, 'message' => 'KTP ' . self::KTP]],
            [['payload' => ['ktp' => self::KTP, 'note' => 'embedded ' . self::KTP]]],
            'roster-imports/sanitize-import/failures.xlsx'
        );

        $persisted = json_encode([$history->fresh()->summary, $history->fresh()->failure_samples]);
        $this->assertStringNotContainsString(self::KTP, $persisted);
        $this->assertStringContainsString('[redacted]', $persisted);
    }

    private function validate(array $rows): array
    {
        return app(RosterScheduleImportValidationService::class)->validate(app(RosterScheduleWorkbookReader::class)->read($this->makeRosterWorkbook($rows)));
    }

    private function historyWithSource(string $importId, User $actor, array $rows): ImportHistory
    {
        $sourcePath = 'roster-imports/' . $importId . '/source.xlsx';
        Storage::disk('local')->put('private/' . $sourcePath, file_get_contents($this->makeRosterWorkbook($rows)));

        return $this->history($importId, $actor, ['file_path' => $sourcePath]);
    }

    private function history(string $importId, User $actor, array $overrides = []): ImportHistory
    {
        return ImportHistory::create(array_merge([
            'import_id' => $importId,
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_QUEUED,
            'created_by' => $actor->id,
            'file_path' => '../../not-readable.xlsx',
        ], $overrides));
    }

    private function actor(string $id, string $role = 'HR', array $menus = ['roster_schedule']): User
    {
        $roleId = DB::table('roles')->insertGetId(['permission_role' => $role, 'menu_permissions' => json_encode($menus)]);

        return User::create(['id' => $id, 'name' => $role, 'role_id' => $roleId]);
    }
}
