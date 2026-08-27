<?php

namespace Tests\Feature;

use App\Http\Requests\Roster\UploadRosterScheduleImportRequest;
use App\Models\ImportHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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
        $template = file_get_contents(resource_path('views/admin/roster-schedules/import.blade.php'));

        $this->assertStringContainsString("{{ \$row['no_ktp'] }}", $template);
        $this->assertStringContainsString("{{ \$error['code'] }}", $template);
        $this->assertStringContainsString('Tidak ada baris roster untuk ditampilkan.', $template);
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
