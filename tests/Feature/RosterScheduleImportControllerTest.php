<?php

namespace Tests\Feature;

use App\Http\Requests\Roster\UploadRosterScheduleImportRequest;
use App\Models\ImportHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $this->createRosterImportSchema();
    }

    protected function tearDown(): void
    {
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
        $this->assertSame(['required', 'file', 'mimes:xlsx', 'max:10240'], $this->requestFor($hr)->rules()['file']);
    }

    public function test_all_preview_routes_are_named_without_confirmation_route(): void
    {
        $this->assertTrue(route('roster-schedules.import.create') !== '');
        $this->assertTrue(route('roster-schedules.import.store') !== '');
        $this->assertTrue(route('roster-schedules.import.show', 1) !== '');
        $this->assertTrue(route('roster-schedules.import.status', 1) !== '');
        $this->assertTrue(route('roster-schedules.import.failure', 1) !== '');
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('roster-schedules.import.confirm'));
    }

    public function test_authorized_preview_view_renders_full_identity_rows_without_persisting_them_in_status_data(): void
    {
        $history = new ImportHistory([
            'id' => 1,
            'import_id' => 'preview-test',
            'status' => ImportHistory::STATUS_VALIDATION_FAILED,
            'summary' => ['total_rows' => 1, 'blocker_count' => 1, 'warning_count' => 0],
            'expires_at' => now()->addHour(),
        ]);
        $html = view('admin.roster-schedules.import', [
            'history' => $history,
            'rows' => [[
                'row_number' => 3, 'nik' => '016090940', 'no_ktp' => '7402243101930001',
                'employee_name' => 'Nama Excel', 'hris_name' => 'Nama HRIS', 'year' => 2026,
                'period_number' => 1, 'off_start' => '2026-09-10', 'action' => 'blocked',
                'errors' => [['code' => 'ktp_mismatch', 'reason' => 'Nomor KTP tidak sesuai']], 'warnings' => [],
            ]],
        ])->render();

        $this->assertStringContainsString('7402243101930001', $html);
        $this->assertStringContainsString('ktp_mismatch', $html);
        $this->assertStringContainsString('Tidak ada baris roster untuk ditampilkan.', view('admin.roster-schedules.import', ['history' => $history, 'rows' => []])->render());
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
}
