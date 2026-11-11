<?php

namespace Tests\Feature;

use App\Jobs\ProcessSalaryImport;
use App\Models\EmployeeSalaryHistory;
use App\Models\ImportHistory;
use App\Models\OvertimeOrder;
use App\Models\OvertimePayRule;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Overtime\OvertimePayCalculatorService;
use App\Services\Salary\EmployeeSalaryService;
use App\Services\Salary\SalaryImportService;
use App\Services\Salary\SalaryWorkbookReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeSalaryWorkflowTest extends TestCase
{
    private $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'salary.import.connection' => 'database']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        Queue::fake();
        $this->mock(AuditTrailService::class)->shouldReceive('record')->andReturnNull();
        Schema::create('employees', function (Blueprint $table) { $table->softDeletes(); $table->string('deleted_by', 36)->nullable();
            $table->string('nik')->primary(); $table->string('nama_karyawan');
            $table->unsignedBigInteger('work_pattern_id')->nullable();
            $table->unsignedBigInteger('departemen_id')->nullable(); $table->unsignedBigInteger('divisi_id')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id'); $table->string('permission_role'); $table->text('menu_permissions')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('name'); $table->unsignedInteger('role_id');
            $table->string('email')->nullable(); $table->string('nik_karyawan')->nullable(); $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('notifiable_type'); $table->string('notifiable_id');
            $table->string('type'); $table->text('data'); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_05_09_000001_create_import_histories_table.php'))->up();
        Schema::table('import_histories', function (Blueprint $table) {
            $table->string('file_checksum')->nullable(); $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable(); $table->string('confirmed_by')->nullable();
        });
        (require database_path('migrations/2026_08_25_000001_create_import_history_items_table.php'))->up();
        (require database_path('migrations/2026_08_25_000002_add_employee_name_to_import_history_items_table.php'))->up();
        (require database_path('migrations/2026_10_31_000001_create_employee_salary_histories_table.php'))->up();
        (require database_path('migrations/2026_04_30_000001_create_overtime_pay_rules_table.php'))->up();
        DB::table('employees')->insert(['nik' => '00123', 'nama_karyawan' => 'Karyawan Uji']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) { unlink($path); } }
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_upload_is_private_queued_and_identical_upload_is_not_dispatched_twice(): void
    {
        $hr = $this->user();
        $file = $this->workbook([$this->row()]);
        $this->actingAs($hr)->postJson(route('employee-salaries.store'), ['file' => $file])->assertStatus(202)->assertJsonPath('success', true);
        $history = ImportHistory::firstOrFail();
        $this->assertSame('queued', $history->status);
        $this->assertTrue(Storage::disk('local')->exists('private/' . $history->file_path));
        $this->assertStringNotContainsString('secret-salary', $history->file_name);
        $this->actingAs($hr)->postJson(route('employee-salaries.store'), ['file' => $file])->assertStatus(202);
        $this->assertSame(1, ImportHistory::count());
        Queue::assertPushed(ProcessSalaryImport::class, 1);
        Queue::assertPushed(ProcessSalaryImport::class, function ($job) { return $job->connection === 'database' && $job->phase === 'preview'; });
    }

    public function test_preview_confirmation_and_effective_date_selection_preserve_old_versions(): void
    {
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, [$this->row(['gaji_pokok' => 1730000, 'berlaku_mulai' => '2026-01-01']),
            $this->row(['gaji_pokok' => 3460000, 'berlaku_mulai' => '2027-01-01'])]);
        $this->assertSame('awaiting_confirmation', $history->status);
        $this->assertSame(0, EmployeeSalaryHistory::count());
        $this->assertFalse(Storage::disk('local')->exists('private/' . $history->file_path));
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertOk();
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertStatus(409);
        $this->apply($history);
        $salaryService = app(EmployeeSalaryService::class);
        $this->assertSame(1730000.0, (float) $salaryService->requireForDate('00123', '2026-12-31')->basic_salary);
        $this->assertSame(3460000.0, (float) $salaryService->requireForDate('00123', '2027-01-01')->basic_salary);
        $renewal = new \App\Models\EmployeeContractRenewal(['employee_nik' => '00123', 'current_contract_end_date' => '2026-12-31']);
        $this->assertSame(3460000.0, app(\App\Services\ContractRenewals\ContractRenewalSalaryService::class)->resolveSalary($renewal));
        $this->assertNull($salaryService->forDate('00123', '2025-12-31'));
        $this->apply($history);
        $this->assertSame(2, EmployeeSalaryHistory::count());
        $this->assertSame('completed', $history->fresh()->status);
        $this->assertSame('Diterapkan; versi upah tersimpan.', $history->items()->first()->message);
        $revision = $this->uploadAndPreview($hr, [$this->row(['gaji_pokok' => 4000000, 'berlaku_mulai' => '2027-01-01'])]);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $revision), ['reviewed' => 1])->assertOk();
        $this->apply($revision);
        $this->assertSame(3, EmployeeSalaryHistory::count());
        $this->assertSame(4000000.0, (float) $salaryService->requireForDate('00123', '2027-01-01')->basic_salary);
        $this->assertSame(1730000.0, (float) $salaryService->requireForDate('00123', '2026-12-31')->basic_salary);
    }

    public function test_invalid_rows_block_the_entire_preview_without_changing_salary(): void
    {
        $history = $this->uploadAndPreview($this->user(), [
            $this->row(), $this->row(),
            $this->row(['nik' => 'unknown', 'gaji_pokok' => -100, 'berlaku_mulai' => '2027-02-30']),
            $this->row(['gaji_pokok' => '=1000+2000', 'berlaku_mulai' => '2027-03-01']),
        ]);
        $this->assertSame('validation_failed', $history->status);
        $this->assertSame(3, $history->failed_count);
        $this->assertSame(0, EmployeeSalaryHistory::count());
        $this->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertStatus(409);
    }

    public function test_concurrent_salary_revision_after_preview_is_not_silently_overwritten(): void
    {
        $hr = $this->user();
        $first = $this->uploadAndPreview($hr, [$this->row()]);
        $second = $this->uploadAndPreview($hr, [$this->row(['gaji_pokok' => 6000000])]);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $second), ['reviewed' => 1])->assertOk();
        $this->apply($second);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $first), ['reviewed' => 1])->assertOk();
        $this->apply($first);
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertSame(1, $first->fresh()->failed_count);
        $this->assertSame(1, EmployeeSalaryHistory::count());
        $this->assertSame(6000000.0, (float) EmployeeSalaryHistory::first()->basic_salary);
    }

    public function test_non_hr_and_hr_without_payroll_menu_cannot_access_salary_routes(): void
    {
        $history = $this->uploadAndPreview($this->user(), [$this->row()]);
        foreach ([$this->user('Staff', ['slip_gaji_admin']), $this->user('HR', [])] as $user) {
            $this->actingAs($user)->getJson(route('employee-salaries.index'))->assertForbidden();
            $this->getJson(route('employee-salaries.show', $history))->assertForbidden();
            $this->getJson(route('employee-salaries.status', $history))->assertForbidden();
            $this->getJson(route('employee-salaries.template'))->assertForbidden();
            $this->getJson(route('employee-salaries.failures', $history))->assertForbidden();
            $this->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertForbidden();
            $this->postJson(route('employee-salaries.store'), ['file' => $this->workbook([$this->row()])])->assertForbidden();
        }
    }

    public function test_invalid_upload_and_unreviewed_or_expired_confirmation_are_rejected(): void
    {
        $hr = $this->user();
        $this->actingAs($hr)->postJson(route('employee-salaries.store'), ['file' => UploadedFile::fake()->createWithContent('salary.xlsx', 'not a workbook')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('employee-salaries.store'), ['file' => UploadedFile::fake()->create('salary.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $history = $this->uploadAndPreview($hr, [$this->row()]);
        $this->postJson(route('employee-salaries.confirm', $history))->assertUnprocessable();
        $history->update(['expires_at' => now()->subMinute()]);
        $this->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertStatus(409);
        $json = $this->getJson(route('employee-salaries.status', $history))->assertOk()->assertJsonPath('data.status', 'expired')->getContent();
        $this->assertStringNotContainsString('file_path', $json);
        $this->assertStringNotContainsString('5000000', $json);
    }

    public function test_sync_queue_is_rejected_before_creating_an_import(): void
    {
        config(['salary.import.connection' => 'sync']);
        $this->actingAs($this->user())->postJson(route('employee-salaries.store'), ['file' => $this->workbook([$this->row()])])
            ->assertUnprocessable()->assertJsonValidationErrors('queue');
        $this->assertSame(0, ImportHistory::count());
        Queue::assertNothingPushed();
    }

    public function test_wage_basis_obeys_fixed_component_and_seventy_five_percent_floor(): void
    {
        $service = app(EmployeeSalaryService::class);
        $this->assertSame(6000000.0, $service->basis(5000000, 1000000, 0));
        $this->assertSame(6000000.0, $service->basis(5000000, 1000000, 1000000));
        $this->assertSame(7500000.0, $service->basis(5000000, 1000000, 4000000));
        $this->expectException(\InvalidArgumentException::class);
        $service->basis(5000000, -1, 0);
    }

    public function test_order_overtime_uses_salary_on_order_date_instead_of_latest_salary(): void
    {
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, [$this->row(['gaji_pokok' => 1730000, 'berlaku_mulai' => '2026-01-01']),
            $this->row(['gaji_pokok' => 3460000, 'berlaku_mulai' => '2027-01-01'])]);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1]);
        $this->apply($history);
        $order = new OvertimeOrder(['nik_karyawan' => '00123', 'overtime_date' => '2026-12-31',
            'overtime_type' => OvertimeOrder::TYPE_EXTRA_HOURS, 'required_minutes' => 180]);
        $order->setRelation('employee', null);
        $calculator = app(OvertimePayCalculatorService::class);
        $this->assertSame(55000.0, (float) $calculator->calculateForOrder($order)['amount']);
        $order->overtime_date = '2027-01-01';
        $this->assertSame(110000.0, (float) $calculator->calculateForOrder($order)['amount']);
        $this->assertSame(OvertimePayRule::DAY_OFF_OR_HOLIDAY,
            $calculator->resolveDayTypeFromOvertimeType(OvertimeOrder::TYPE_OFF, OvertimePayRule::SCHEDULE_SIX_ONE, true));
    }

    public function test_missing_salary_requires_review_instead_of_zero_or_future_salary(): void
    {
        $this->expectException(ValidationException::class);
        app(EmployeeSalaryService::class)->requireForDate('00123', '2027-01-01');
    }

    public function test_preview_rechecks_period_locks_before_applying_changes(): void
    {
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, [$this->row()]);
        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id(); $table->string('status'); $table->date('start_date'); $table->date('end_date');
        });
        DB::table('attendance_period_locks')->insert(['status' => 'locked', 'start_date' => '2027-01-16', 'end_date' => '2027-02-15']);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertOk();
        $this->apply($history);
        $this->assertSame(0, EmployeeSalaryHistory::count());
        $this->assertSame('failed', $history->fresh()->status);
    }

    public function test_import_crosses_chunk_boundary_without_losing_zeroes_or_repeating_rows(): void
    {
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            $rows[] = $this->row(['berlaku_mulai' => \Carbon\Carbon::parse('2027-01-01')->addDays($i)->toDateString()]);
        }
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, $rows);
        $this->assertSame(201, $history->total_rows);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertOk();
        $this->apply($history);
        $this->assertSame(201, EmployeeSalaryHistory::where('employee_nik', '00123')->count());
        $this->assertSame(201, $history->fresh()->success_count);
    }

    public function test_hr_can_open_the_real_salary_pages_and_download_the_template(): void
    {
        $hr = $this->user();
        $this->actingAs($hr)->get(route('employee-salaries.index'))->assertOk()->assertSee('Upload perubahan upah');
        $history = $this->uploadAndPreview($hr, [$this->row()]);
        $this->get(route('employee-salaries.show', $history))->assertOk()->assertSee('Tinjau Riwayat Upah')->assertSee('00123');
        $response = $this->get(route('employee-salaries.template'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'salary-template-');
        $this->paths[] = $path;
        file_put_contents($path, $response->streamedContent());
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $info = $reader->listWorksheetInfo($path);
        $this->assertSame(1, $info[0]['totalRows']);
        $chunks = iterator_to_array((new SalaryWorkbookReader())->chunks($path));
        $this->assertSame([[]], $chunks);
    }

    public function test_calculator_http_request_reads_history_and_manual_simulation_applies_wage_floor(): void
    {
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, [$this->row(['gaji_pokok' => 1730000])]);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1])->assertOk();
        $this->apply($history);
        $this->post(route('overtime-masters.calculate'), ['wage_source' => 'history', 'nik_karyawan' => '00123',
            'overtime_date' => '2027-01-01', 'schedule_type' => 'five_two', 'day_type' => 'workday', 'overtime_hours' => 3,
            'monthly_wage' => 999999999])
            ->assertOk()->assertViewHas('calculationResult', function ($result) {
                return (float) $result['amount'] === 55000.0 && (float) $result['monthly_wage'] === 1730000.0
                    && $result['salary_history']->employee_nik === '00123';
            });
        $this->post(route('overtime-masters.calculate'), ['wage_source' => 'manual', 'basic_salary' => 1000000,
            'fixed_allowance' => 0, 'non_fixed_allowance' => 2000000,
            'schedule_type' => 'five_two', 'day_type' => 'workday', 'overtime_hours' => 3])
            ->assertOk()->assertViewHas('calculationResult', function ($result) { return $result['monthly_wage'] === 2250000.0; });
        $this->postJson(route('overtime-masters.calculate'), ['wage_source' => 'history', 'nik_karyawan' => '00123',
            'overtime_date' => '2026-12-31', 'schedule_type' => 'five_two', 'day_type' => 'workday', 'overtime_hours' => 3])
            ->assertUnprocessable()->assertJsonValidationErrors('overtime_date');
    }

    public function test_revoked_approver_cannot_apply_queued_changes(): void
    {
        $hr = $this->user();
        $history = $this->uploadAndPreview($hr, [$this->row()]);
        $this->actingAs($hr)->postJson(route('employee-salaries.confirm', $history), ['reviewed' => 1]);
        DB::table('roles')->where('id', $hr->role_id)->update(['menu_permissions' => '[]']);
        try {
            $this->apply($history);
            $this->fail('Revoked approver was accepted.');
        } catch (\RuntimeException $exception) {
            (new ProcessSalaryImport($history->id, 'commit'))->failed($exception);
        }
        $this->assertSame(0, EmployeeSalaryHistory::count());
        $this->assertSame('failed', $history->fresh()->status);
    }

    private function row(array $changes = []): array
    {
        return array_merge(['nik' => '00123', 'gaji_pokok' => 5000000, 'tunjangan_tetap' => 0,
            'tunjangan_tidak_tetap' => 0, 'berlaku_mulai' => '2027-01-01', 'alasan' => 'Promosi', 'referensi' => 'SK-001'], $changes);
    }

    private function user(string $role = 'HR', array $menus = ['slip_gaji_admin', 'lembur']): User
    {
        $roleId = DB::table('roles')->insertGetId(['permission_role' => $role, 'menu_permissions' => json_encode($menus)]);
        $user = User::create(['id' => 'salary-user-' . $roleId, 'name' => $role, 'role_id' => $roleId]);
        $user->setRelation('employee', \App\Models\Employee::first());
        return $user;
    }

    private function workbook(array $rows): UploadedFile
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(SalaryWorkbookReader::HEADINGS, null, 'A1');
        foreach ($rows as $i => $row) {
            foreach (array_values($row) as $j => $value) {
                $sheet->setCellValueExplicit(chr(65 + $j) . ($i + 2), (string) $value, DataType::TYPE_STRING);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'salary-test-');
        $this->paths[] = $path;
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return new UploadedFile($path, 'secret-salary.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function uploadAndPreview(User $hr, array $rows): ImportHistory
    {
        $this->actingAs($hr)->postJson(route('employee-salaries.store'), ['file' => $this->workbook($rows)])->assertStatus(202);
        $history = ImportHistory::latest('id')->firstOrFail();
        (new ProcessSalaryImport($history->id))->handle(app(SalaryImportService::class));
        return $history->fresh();
    }

    private function apply(ImportHistory $history): void
    {
        (new ProcessSalaryImport($history->id, 'commit'))->handle(app(SalaryImportService::class));
    }
}
