<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayrollImport;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\PayrollEntry;
use App\Models\PayrollInput;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\PayrollWorkbook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    private $paths=[];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],
            'cache.default'=>'array','payroll.connection'=>'database']);
        DB::purge('sqlite'); DB::reconnect('sqlite'); Storage::fake('local'); Queue::fake();
        $this->mock(AuditTrailService::class)->shouldReceive('record')->andReturnNull();
        Schema::create('perusahaan',function (Blueprint $t) { $t->softDeletes(); $t->string('deleted_by', 36)->nullable(); $t->increments('id'); $t->string('nama_perusahaan'); $t->string('kode_perusahaan'); });
        Schema::create('departemens',function (Blueprint $t) { $t->softDeletes(); $t->string('deleted_by', 36)->nullable(); $t->increments('id'); $t->unsignedInteger('perusahaan_id'); $t->string('departemen'); });
        Schema::create('employees',function (Blueprint $t) { $t->softDeletes(); $t->string('deleted_by', 36)->nullable();
            $t->string('nik')->primary(); $t->string('nama_karyawan'); $t->unsignedInteger('departemen_id');
            $t->unsignedInteger('divisi_id')->nullable(); $t->unsignedInteger('work_pattern_id')->nullable();
            $t->string('no_ktp'); $t->string('no_rekening')->nullable(); $t->string('nama_bank')->nullable(); $t->date('tgl_resign')->nullable();
            $t->date('entry_date')->nullable();
        });
        Schema::create('roles',function (Blueprint $t) { $t->increments('id'); $t->string('permission_role'); $t->text('menu_permissions')->nullable(); });
        Schema::create('users',function (Blueprint $t) { $t->string('id')->primary(); $t->string('name'); $t->unsignedInteger('role_id'); $t->string('email')->nullable(); $t->string('nik_karyawan')->nullable(); $t->timestamps(); });
        Schema::create('notifications',function (Blueprint $t) { $t->string('id')->primary(); $t->string('notifiable_type'); $t->string('notifiable_id'); $t->string('type'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        (require database_path('migrations/2026_05_09_000001_create_import_histories_table.php'))->up();
        Schema::table('import_histories',function (Blueprint $t) { $t->timestamp('expires_at')->nullable(); $t->timestamp('confirmed_at')->nullable(); $t->string('confirmed_by')->nullable(); });
        (require database_path('migrations/2026_08_25_000001_create_import_history_items_table.php'))->up();
        (require database_path('migrations/2026_08_25_000002_add_employee_name_to_import_history_items_table.php'))->up();
        (require database_path('migrations/2026_11_06_000001_create_payroll_entries_table.php'))->up();
        (require database_path('migrations/2026_11_09_000001_create_payroll_inputs_table.php'))->up();
        (require database_path('migrations/2026_11_11_000002_create_salary_components_table.php'))->up();
        DB::table('perusahaan')->insert([['id'=>1,'nama_perusahaan'=>'Perusahaan Uji','kode_perusahaan'=>'VDNI'],['id'=>2,'nama_perusahaan'=>'Perusahaan Lain','kode_perusahaan'=>'VDNIP']]);
        DB::table('departemens')->insert(['id'=>1,'perusahaan_id'=>1,'departemen'=>'HR Uji']);
        DB::table('employees')->insert(['nik'=>'00123','nama_karyawan'=>'Karyawan Sintetis','departemen_id'=>1,'no_ktp'=>'0000000000000001','no_rekening'=>'00123456','nama_bank'=>'Bank Uji']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) { unlink($path); } }
        DB::disconnect('sqlite'); parent::tearDown();
    }

    public function test_payroll_job_uses_the_connection_queue_unless_an_override_is_configured(): void
    {
        config(['payroll.queue'=>null, 'queue.connections.database.queue'=>'local']);
        $queue = new \Illuminate\Queue\DatabaseQueue(DB::connection(), 'jobs', config('queue.connections.database.queue'));
        $job = new ProcessPayrollImport(1);
        $this->assertSame('database', $job->connection);
        $this->assertSame('local', $queue->getQueue($job->queue));

        config(['payroll.queue'=>'payroll']);
        $this->assertSame('payroll', $queue->getQueue((new ProcessPayrollImport(1))->queue));
    }

    public function test_upload_preview_finalization_and_39_column_export_preserve_identifiers(): void
    {
        $hr=$this->actor();
        $h=$this->upload($hr); $this->assertSame('queued',$h->status);
        Queue::assertPushed(ProcessPayrollImport::class,1);
        $this->assertTrue(Storage::disk('local')->exists('private/'.$h->file_path));
        $this->actingAs($hr)->get(route('payroll.download',$h))->assertStatus(409);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $h->refresh(); $this->assertSame('awaiting_confirmation',$h->status);
        $this->assertFalse(Storage::disk('local')->exists('private/'.$h->file_path));
        $this->assertSame(9600000,$h->items()->first()->payload['result']['take_home']);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertOk();
        $this->assertSame('finalized',$h->fresh()->status); $this->assertSame(1,PayrollEntry::count());
        $entry=PayrollEntry::first();
        $this->assertSame(hash('sha256',json_encode($entry->snapshot,JSON_UNESCAPED_UNICODE)),$entry->snapshot_hash);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertOk();
        $this->assertSame(1,PayrollEntry::count());
        $response=$this->get(route('payroll.download',$h))->assertOk();
        $book=IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet=$book->getSheetByName('FORMAT BULK');
        $this->assertSame('AM',$sheet->getHighestColumn());
        $this->assertSame('00123',(string) $sheet->getCell('A2')->getValue());
        $this->assertSame('0000000000000001',(string) $sheet->getCell('B2')->getValue());
        $this->assertSame('00123456',(string) $sheet->getCell('AF2')->getValue());
        $this->assertSame('@',$sheet->getStyle('AF2')->getNumberFormat()->getFormatCode());
        $this->assertSame('00123456',$sheet->getCell('AF2')->getFormattedValue());
        $this->assertSame('9,600,000',$sheet->getCell('AE2')->getFormattedValue());
        $this->assertSame(323319,$sheet->getCell('AM2')->getValue());
        $this->assertSame(323319,$sheet->getCell('AD2')->getValue());
        $this->assertSame(9600000,$sheet->getCell('AE2')->getValue());
        $this->assertSame('2026-01',(string) $sheet->getCell('AH2')->getValue());
        $this->assertSame(4,$book->getSheetCount());
        $book->disconnectWorksheets();
    }

    public function test_identical_upload_is_not_dispatched_twice(): void
    {
        $hr=$this->actor(); $file=$this->workbook();
        $data=$this->settings()+['file'=>$file];
        $this->actingAs($hr)->postJson(route('payroll.store'),$data)->assertStatus(202);
        $this->actingAs($hr)->postJson(route('payroll.store'),$data)->assertStatus(202);
        $this->assertSame(1,ImportHistory::count()); Queue::assertPushed(ProcessPayrollImport::class,1);
    }

    public function test_missing_parameters_invalid_ptkp_wrong_employer_and_severance_hold_finalization(): void
    {
        $hr=$this->actor();
        foreach ([[['ptkp'=>''],[]], [[],['Z'=>1000000]], [[],['A'=>'other']], [[],['B'=>'0000000000000002']], [[],['J'=>-1]], [[],['AL'=>'2026-02-25']]] as [$params,$columns]) {
            $h=$this->upload($hr,$params,$columns);
            (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
            $h->refresh(); $this->assertSame('validation_failed',$h->status);
            $this->assertSame(1,$h->failed_count);
            $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertUnprocessable();
        }
        $this->assertSame(0,PayrollEntry::count());
    }

    public function test_duplicate_employee_and_formula_cells_are_rejected(): void
    {
        $hr=$this->actor();
        foreach ([['duplicate'=>true],['formula'=>true]] as $options) {
            $h=$this->upload($hr,[],[],$options);
            (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
            $this->assertSame('validation_failed',$h->fresh()->status);
        }
        $this->assertSame(0,PayrollEntry::count());
    }

    public function test_expired_preview_and_tampered_export_cannot_be_finalized(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr);
        $h->update(['expires_at'=>now()->subMinute()]);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertUnprocessable();
        $h->update(['expires_at'=>now()->addHour()]);
        Storage::disk('local')->put('private/'.$h->summary['export_path'],'changed');
        $this->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertUnprocessable();
        $this->assertSame(0,PayrollEntry::count());
    }

    public function test_finalized_period_and_later_tax_period_cannot_be_overwritten(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertOk();
        $second=$this->upload($hr,[],['J'=>11000000]);
        (new ProcessPayrollImport($second->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed',$second->fresh()->status);
        $this->assertSame(1,PayrollEntry::count());
    }

    public function test_second_valid_preview_becomes_stale_after_another_finalization(): void
    {
        $hr=$this->actor(); $first=$this->preview($hr); $second=$this->preview($hr,[],['J'=>11000000]);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$first),['reviewed'=>1])->assertOk();
        $this->postJson(route('payroll.finalize',$second),['reviewed'=>1])->assertUnprocessable();
        $this->assertSame(1,PayrollEntry::count());
    }

    public function test_non_hr_and_hr_without_menu_cannot_read_or_finalize_payroll(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr);
        foreach ([$this->actor('Staff'),$this->actor('HR',[])] as $user) {
            $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
            $this->get(route('payroll.show',$h))->assertForbidden();
            $this->getJson(route('payroll.status',$h))->assertForbidden();
            $this->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertForbidden();
            $this->get(route('payroll.download',$h))->assertForbidden();
            $this->postJson(route('payroll.store'),$this->settings()+['file'=>$this->workbook()])->assertForbidden();
        }
        $this->assertSame(0,PayrollEntry::count());
    }

    public function test_generic_import_export_does_not_bypass_payroll_permissions(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr);
        $user=$this->actor('HR',['import_history']);
        $this->actingAs($user)->get(route('import-histories.export',[$h,'updated']))->assertForbidden();
    }

    public function test_status_does_not_expose_money_or_storage_paths_and_revoked_actor_stops_job(): void
    {
        $hr=$this->actor(); $h=$this->upload($hr);
        $response=$this->actingAs($hr)->getJson(route('payroll.status',$h))->assertOk();
        $this->assertStringNotContainsString('export_path',$response->getContent());
        $this->assertStringNotContainsString('10000000',$response->getContent());
        DB::table('roles')->where('id',$hr->role_id)->update(['menu_permissions'=>'[]']);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed',$h->fresh()->status);
    }

    public function test_missing_migration_stops_upload_with_clear_feedback(): void
    {
        Schema::drop('payroll_entries');
        $this->actingAs($this->actor())->postJson(route('payroll.store'),$this->settings()+['file'=>$this->workbook()])
            ->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertSame(0,ImportHistory::count()); Queue::assertNothingPushed();
    }

    public function test_real_pages_and_template_are_available_to_hr(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr);
        $this->actingAs($hr)->get(route('payroll.index'))->assertOk()->assertSee('Proses Payroll')->assertDontSee('name="tenure_in_fixed"', false);
        $this->get(route('payroll.show',$h))->assertOk()->assertSee('Finalisasi 1 karyawan');
        $response=$this->get(route('payroll.template'))->assertOk();
        $path=tempnam(sys_get_temp_dir(),'payroll-template-'); $this->paths[]=$path;
        file_put_contents($path,$response->streamedContent());
        $reader=IOFactory::createReader('Xlsx');
        $this->assertSame(['FORMAT BULK', 'PARAMETER', 'LEMBUR', 'PANDUAN'], $reader->listWorksheetNames($path));
    }

    public function test_cost_report_totals_final_batches_and_filters_archived_employees(): void
    {
        $hr = $this->actor();
        $filters = ['period'=>'2026-01', 'employer_id'=>1];
        $first = $this->preview($hr);
        $this->get(route('payroll.costs', $filters))->assertOk()->assertSee('Belum ada payroll final');
        $this->postJson(route('payroll.finalize', $first), ['reviewed'=>1])->assertOk();

        DB::table('employees')->insert(['nik'=>'00234','nama_karyawan'=>'Karyawan Arsip Kedua','departemen_id'=>1,'no_ktp'=>'0000000000000002']);
        $second = $this->preview($hr, ['nik'=>'00234'], ['A'=>'00234']);
        $this->postJson(route('payroll.finalize', $second), ['reviewed'=>1])->assertOk();

        $this->get(route('payroll.costs', $filters))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('2 baris payroll')->assertSee('Rp 22.694.638')->assertSee('Rp 2.048.000')
            ->assertSee(route('payroll.show', $second))->assertDontSee('0000000000000002');
        $this->get(route('payroll.costs', $filters + ['nik'=>'00123']))->assertOk()
            ->assertSee('Rp 11.347.319')->assertDontSee('Karyawan Arsip Kedua');
        $this->get(route('payroll.costs', ['period'=>'2026-02','employer_id'=>1]))->assertOk()->assertSee('Belum ada payroll final');
        $this->get(route('payroll.costs', ['period'=>'2026-01','employer_id'=>2]))->assertOk()->assertSee('Belum ada payroll final');
        $this->actingAs($this->actor('HOD'))->get(route('payroll.costs', $filters))->assertForbidden();
    }

    public function test_hris_source_calculates_approved_components_and_preserves_the_bulk_format(): void
    {
        $this->hrisFixtures();
        $hr = $this->actor();
        $h = $this->upload($hr, [], ['J'=>'', 'W'=>125000], ['hris'=>true, 'overtime'=>[[1, 45]]]);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $h->refresh();
        $this->assertSame('awaiting_confirmation', $h->status, $h->items()->first()->message);
        $p = $h->items()->first()->payload;
        $overtime = (int) round(10000000 / 173 * 0.75 * 1.5);
        $this->assertSame([2, 9850000, 35000, 150000, $overtime, 125000, 1200000], array_values(array_intersect_key($p['columns'], array_flip(['H','J','K','N','P','W','X']))));
        $this->assertSame(10000000 + 35000 + $overtime + 125000 + 1200000, $p['input']['cash_income']);
        $this->assertSame(45, $p['hris']['overtime'][0]['minutes']);
        $this->assertSame(['2025-12-17', '2026-01-09', '2026-01-14', '2026-01-15'], array_keys($p['hris']['roster_incentive']));
        $this->assertSame($p['result']['pph21'], $p['result']['tax_allowance']);
        $this->actingAs($hr)->get(route('payroll.show', $h))->assertOk()->assertSee('Komponen dari HRIS')->assertSee('4 hari');
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertOk();
        $this->assertSame($p['hris'], PayrollEntry::first()->snapshot['hris']);
        $download = $this->get(route('payroll.download', $h))->assertOk();
        $book = IOFactory::load($download->baseResponse->getFile()->getPathname());
        $bulk = $book->getSheetByName('FORMAT BULK');
        $this->assertSame('AM', $bulk->getHighestColumn());
        $this->assertSame(9850000, $bulk->getCell('J2')->getValue());
        $this->assertSame(150000, $bulk->getCell('N2')->getValue());
        $this->assertSame(1200000, $bulk->getCell('X2')->getValue());
        $this->assertSame(4, $book->getSheetByName('RINCIAN HITUNG')->getCell('AO2')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_native_payroll_prepares_calculates_and_finalizes_without_an_upload(): void
    {
        $this->hrisFixtures();
        $this->actingAs($this->actor());
        $scope = ['employer_id'=>1,'period'=>'2026-01'];
        $start = $scope + ['tax_period'=>'2026-01','pay_date'=>'2026-01-25'];
        $edit = ['nik'=>'00123'] + $scope;
        $this->get(route('payroll.index'))->assertOk()->assertSee('Hitung payroll dari HRIS')->assertSee('Siapkan data karyawan');
        $this->get(route('payroll.inputs', $scope))->assertOk()->assertSee('Perlu konfirmasi');
        $this->get(route('payroll.inputs.edit', $edit))->assertOk()->assertSee('Bonus dan potongan periode ini')->assertSee('Perintah 180 menit');
        $this->get(route('payroll.inputs.edit', ['nik'=>'00123','employer_id'=>2,'period'=>'2026-01']))->assertNotFound();

        $this->postJson(route('payroll.calculate'), $start)->assertStatus(202);
        $this->postJson(route('payroll.calculate'), $start)->assertStatus(202);
        Queue::assertPushed(ProcessPayrollImport::class, 1);
        $h = ImportHistory::latest('id')->firstOrFail();
        $this->assertSame(ImportHistory::SOURCE_HRIS, $h->source);
        $this->assertNull($h->file_path);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed', $h->fresh()->status);
        $this->get(route('payroll.show', $h))->assertOk()->assertSee('Profil pajak dan BPJS belum dikonfirmasi')->assertSee('Buka isian');

        $fields = $scope + ['revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(), 'components'=>['W'=>125000], 'minutes'=>[1=>45]];
        $save = route('payroll.inputs.save', ['nik'=>'00123']);
        $this->postJson($save, array_replace($fields, ['minutes'=>[999=>0]]))->assertUnprocessable();
        $this->postJson($save, $fields)->assertOk();
        $this->get(route('payroll.inputs', $scope))->assertOk()->assertSee('Profil tersimpan');
        $this->postJson($save, $fields)->assertUnprocessable();
        $this->postJson(route('payroll.calculate'), $start)->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $h->refresh();
        $this->assertSame('awaiting_confirmation', $h->status, $h->items()->first()?->message ?? $h->error_message ?? 'Preview failed');
        $p = $h->items()->first()->payload;
        $this->assertSame(125000, $p['columns']['W']);
        $this->assertSame(45, $p['hris']['overtime'][0]['minutes']);
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertOk();
        $this->get(route('payroll.download', $h))->assertOk();
        $this->postJson($save, $fields)->assertNotFound();

        $next = ['employer_id'=>1,'period'=>'2026-02'];
        $this->postJson($save, $next + ['revision'=>0,'tax'=>['masa_terakhir'=>1]])->assertOk();
        $this->postJson(route('payroll.calculate'), $next + ['tax_period'=>'2026-02','pay_date'=>'2026-02-25'])->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('awaiting_confirmation', $h->fresh()->status, $h->items()->first()?->message ?? $h->error_message ?? 'Preview failed');
        $nextPayload = $h->items()->first()->payload;
        $this->assertSame(0, $nextPayload['columns']['W']);
        $this->assertSame('TK/0', $nextPayload['input']['ptkp']);
        $this->assertSame($p['result']['tax_gross'], $nextPayload['input']['ytd_gross']);
    }

    public function test_native_payroll_holds_a_preview_when_period_inputs_change(): void
    {
        $this->hrisFixtures();
        $this->actingAs($this->actor());
        $scope = ['employer_id'=>1,'period'=>'2026-01'];
        $save = route('payroll.inputs.save', ['nik'=>'00123']);
        $this->postJson($save, $scope + ['revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(), 'minutes'=>[1=>45]])->assertOk();
        $this->postJson(route('payroll.calculate'), $scope + ['tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        $this->postJson($save, $scope + ['revision'=>PayrollInput::max('id'), 'minutes'=>[1=>45], 'components'=>['W'=>250000]])->assertOk();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame(0, $h->items()->first()->payload['columns']['W']);
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_native_payroll_reuses_recurring_components_and_honors_monthly_zero_and_stop(): void
    {
        $this->hrisFixtures();
        $this->actingAs($this->actor());
        $save = route('payroll.inputs.save', '00123');
        $january = ['employer_id'=>1, 'period'=>'2026-01', 'revision'=>0, 'verify_profile'=>1,
            'profile'=>$this->nativeProfile(), 'minutes'=>[1=>0], 'save_recurring'=>1];
        $this->postJson($save, $january + ['recurring'=>['J'=>250000]])->assertUnprocessable()->assertJsonValidationErrors('recurring');
        $this->postJson($save, $january + ['recurring'=>['M'=>250000]])->assertOk();
        $this->postJson($save, ['employer_id'=>1, 'period'=>'2026-02', 'revision'=>0, 'components'=>['M'=>0]])->assertOk();
        $this->postJson($save, ['employer_id'=>1, 'period'=>'2026-04', 'revision'=>0, 'save_recurring'=>1, 'recurring'=>['M'=>null]])->assertOk();

        foreach (['2026-01'=>250000, '2026-02'=>0, '2026-03'=>250000, '2026-05'=>0] as $period=>$expected) {
            $this->postJson(route('payroll.calculate'), ['employer_id'=>1, 'period'=>$period, 'tax_period'=>$period, 'pay_date'=>$period.'-25'])->assertStatus(202);
            $history = ImportHistory::latest('id')->firstOrFail();
            (new ProcessPayrollImport($history->id))->handle(app(PayrollService::class));
            $this->assertSame('awaiting_confirmation', $history->fresh()->status, $history->items()->first()?->message ?? 'Preview failed');
            $payload = $history->items()->first()->payload;
            $this->assertSame($expected, $payload['columns']['M']);
            $this->assertSame($expected, $payload['hris']['recurring_components']['M'] ?? 0);
        }
    }

    public function test_native_payroll_shows_latest_closed_period_and_waits_through_cutoff_day(): void
    {
        $this->actingAs($this->actor());
        $settings = ['employer_id'=>1, 'period'=>'2026-11', 'tax_period'=>'2026-11', 'pay_date'=>'2026-11-25'];
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 23:59:59'));
        $this->get(route('payroll.index'))->assertOk()->assertSee('Cut-off sudah selesai hingga Oktober 2026');
        $this->postJson(route('payroll.calculate'), $settings)->assertUnprocessable()->assertJsonPath('errors.payroll.0',
            'Cut-off periode 2026-11 berakhir 15/11/2026. Payroll bisa dihitung mulai 16/11/2026.');
        Queue::assertNothingPushed();

        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 00:00:00'));
        $this->get(route('payroll.index'))->assertOk()->assertSee('Cut-off sudah selesai hingga November 2026');
        $this->postJson(route('payroll.calculate'), $settings)->assertStatus(202);
        Queue::assertPushed(ProcessPayrollImport::class, 1);
        $this->travelBack();
    }

    public function test_native_payroll_reports_separated_employees_and_records_manual_overtime(): void
    {
        $this->hrisFixtures();
        DB::table('employees')->insert(['nik'=>'00234','nama_karyawan'=>'Karyawan Terpisah','departemen_id'=>1,'entry_date'=>'2026-01-20','no_ktp'=>'0000000000000002']);
        $this->actingAs($this->actor());
        $scope = ['employer_id'=>1,'period'=>'2026-01'];
        $this->postJson(route('payroll.inputs.save', '00234'), $scope + ['revision'=>0, 'separate_reason'=>'Prorata ditangani terpisah'])->assertOk();
        $save = route('payroll.inputs.save', '00123');
        $fields = $scope + ['revision'=>0, 'verify_profile'=>1, 'profile'=>$this->nativeProfile(), 'manual_overtime'=>300000];
        $this->postJson($save, $fields)->assertUnprocessable()->assertJsonValidationErrors('overtime_note');
        $this->postJson($save, $fields + ['overtime_note'=>'Nominal terverifikasi HR'])->assertOk();
        $this->postJson(route('payroll.calculate'), $scope + ['tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('awaiting_confirmation', $h->fresh()->status);
        $this->assertSame(1, $h->fresh()->summary['separate_count']);
        $this->assertSame(300000, $h->items()->first()->payload['columns']['P']);
        $this->get(route('payroll.show', $h))->assertOk()->assertSee('1 karyawan ditandai')->assertSee('Nominal terverifikasi HR');
    }

    private function nativeProfile(): array
    {
        return ['ptkp'=>'TK/0','upah_bpjs_tk'=>10000000,'upah_bpjs_kes'=>10000000,'upah_minimum'=>4000000,'jkk_persen'=>0.24,
            'jht_aktif'=>1,'jp_aktif'=>1,'kes_aktif'=>1,'jkk_jkm_aktif'=>1,'keluarga_tambahan'=>0,'bulan_mulai_pajak'=>1,'nik_npwp_valid'=>1];
    }

    public function test_hris_source_requires_verified_minutes_and_can_calculate_again_after_data_changes(): void
    {
        $this->hrisFixtures();
        $hr = $this->actor();
        foreach ([
            [[], 'Isi menit yang disahkan'],
            [[[1, 61]], 'melebihi rentang'],
            [[[1, 45], [999, 0]], 'tidak diterima'],
        ] as [$minutes, $expected]) {
            $h = $this->upload($hr, [], ['J'=>''], ['hris'=>true, 'overtime'=>$minutes]);
            (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
            $h->refresh();
            $this->assertSame('validation_failed', $h->status);
            $this->assertStringContainsString($expected, $h->error_message.' '.$h->items()->first()?->message);
        }

        $data = $this->settings() + ['income_source'=>'hris', 'tenure_in_fixed'=>0, 'file'=>$this->workbook([], ['J'=>''], ['hris'=>true, 'overtime'=>[[1, 0]]])];
        $this->postJson(route('payroll.store'), $data)->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $p = $h->items()->first()->payload;
        $this->assertSame(9850000, $p['columns']['J']);
        $this->assertSame(150000, $p['columns']['N']);
        $this->assertSame(0, $p['columns']['P']);
        DB::table('cuti_roster')->where('id', 1)->update(['status_pengajuan_hrd'=>0]);
        $this->postJson(route('payroll.store'), $data)->assertStatus(202);
        $new = ImportHistory::latest('id')->firstOrFail();
        $this->assertNotSame($h->id, $new->id);
        (new ProcessPayrollImport($new->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed', $new->fresh()->status);
        $this->assertStringContainsString('belum disetujui HOD dan HR', $new->items()->first()->message);
    }

    public function test_hris_source_rejects_inconsistent_fixed_allowance_and_old_additive_preview(): void
    {
        $this->hrisFixtures();
        $hr = $this->actor();
        $options = ['hris'=>true, 'overtime'=>[[1, 0]]];
        $h = $this->upload($hr, [], ['J'=>''], $options);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $summary = $h->fresh()->summary;
        $summary['settings']['tenure_in_fixed'] = 0;
        $h->update(['summary'=>$summary]);
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertSame(0, PayrollEntry::count());

        DB::table('employee_salary_histories')->update(['fixed_allowance'=>100000]);
        $h = $this->upload($hr, [], ['J'=>''], $options);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed', $h->fresh()->status);
        $this->assertStringContainsString('Tunjangan tetap dalam riwayat lebih kecil', $h->items()->first()->message);
    }

    public function test_hris_source_holds_smelter_holiday_pay_for_manual_verification(): void
    {
        $this->hrisFixtures();
        Schema::create('national_holidays', function (Blueprint $t) { $t->id(); $t->date('holiday_date'); });
        DB::table('national_holidays')->insert(['holiday_date'=>'2026-01-01']);
        DB::table('departemens')->where('id', 1)->update(['departemen'=>'Smelter Uji']);
        $h = $this->upload($this->actor(), [], ['J'=>''], ['hris'=>true, 'overtime'=>[[1, 45]]]);
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed', $h->fresh()->status);
        $this->assertStringContainsString('Lembur libur Smelter 5:2', $h->items()->first()->message);
    }

    private function hrisFixtures(): void
    {
        (require database_path('migrations/2026_10_31_000001_create_employee_salary_histories_table.php'))->up();
        (require database_path('migrations/2026_04_23_000003_create_overtime_orders_table.php'))->up();
        Schema::create('work_patterns', function (Blueprint $t) { $t->id(); $t->string('pattern_basis'); $t->text('weekly_work_days'); });
        Schema::create('absensis', function (Blueprint $t) {
            $t->id(); $t->string('nik_karyawan'); $t->date('tanggal'); $t->string('status_presensi')->nullable(); $t->string('status_absen')->nullable();
            $t->dateTime('jam_masuk')->nullable(); $t->dateTime('jam_pulang')->nullable();
        });
        Schema::create('cuti_roster', function (Blueprint $t) {
            $t->id(); $t->string('nik_karyawan'); $t->unsignedBigInteger('roster_schedule_id')->nullable();
            $t->date('tgl_awal_kerja'); $t->date('tgl_akhir_kerja'); $t->integer('status_pengajuan'); $t->integer('status_pengajuan_hrd');
        });
        Schema::create('periode_kerja_roster', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('cuti_roster_id'); $t->integer('tipe_rencana');
            foreach (['satu','dua','tiga','empat','lima'] as $day) { $t->string($day)->nullable(); $t->date('tanggal_'.$day)->nullable(); }
        });
        Schema::create('roster_schedules', function (Blueprint $t) {
            $t->id(); $t->string('employee_nik'); $t->string('source'); $t->string('realization_type'); $t->boolean('is_active'); $t->date('off_start'); $t->date('off_end');
        });
        DB::table('employees')->update(['entry_date'=>'2022-12-20', 'work_pattern_id'=>1]);
        DB::table('work_patterns')->insert(['id'=>1, 'pattern_basis'=>'weekly', 'weekly_work_days'=>'["mon","tue","wed","thu","fri"]']);
        DB::table('employee_salary_histories')->insert(['employee_nik'=>'00123', 'effective_from'=>'2022-12-20', 'basic_salary'=>9000000,
            'fixed_allowance'=>1000000, 'reason'=>'Upah uji', 'reference'=>'UJI', 'import_history_id'=>1, 'row_number'=>2, 'approved_by'=>'test', 'approved_at'=>now()]);
        DB::table('overtime_orders')->insert(['id'=>1, 'nik_karyawan'=>'00123', 'requested_by_user_id'=>1, 'overtime_type'=>'EXTRA_HOURS', 'overtime_date'=>'2026-01-08',
            'start_time'=>'17:00:00', 'end_time'=>'20:00:00', 'required_minutes'=>180, 'reason'=>'Uji', 'employee_response_status'=>'ACCEPTED']);
        foreach ([['2026-01-08','verified',null,true], ['2026-01-09','verified',null,true], ['2026-01-09','verified',null,true],
            ['2026-01-10','rejected',null,true], ['2026-01-11','verified','Cuti',true], ['2026-01-12','verified',null,false], ['2026-01-16','verified',null,true]] as [$date,$status,$absence,$complete]) {
            DB::table('absensis')->insert(['nik_karyawan'=>'00123', 'tanggal'=>$date, 'status_absen'=>$status, 'status_presensi'=>$absence,
                'jam_masuk'=>$date.' 08:00:00', 'jam_pulang'=>$complete ? $date.' 18:00:00' : null]);
        }
        DB::table('cuti_roster')->insert(['id'=>1, 'nik_karyawan'=>'00123', 'tgl_awal_kerja'=>'2026-01-14', 'tgl_akhir_kerja'=>'2026-01-17', 'status_pengajuan'=>1, 'status_pengajuan_hrd'=>1]);
        DB::table('periode_kerja_roster')->insert(['cuti_roster_id'=>1, 'tipe_rencana'=>2, 'satu'=>'BEKERJA', 'tanggal_satu'=>'2025-12-17',
            'dua'=>'BEKERJA', 'tanggal_dua'=>'2026-01-09', 'tiga'=>'OFF', 'tanggal_tiga'=>'2026-01-13']);
    }

    public function test_import_crosses_chunk_boundary_and_finalizes_201_distinct_employees(): void
    {
        $new=[];
        for ($i=1;$i<201;$i++) { $new[]=['nik'=>str_pad((string) (123+$i),5,'0',STR_PAD_LEFT),'nama_karyawan'=>'Sintetis '.$i,'departemen_id'=>1,'no_ktp'=>'0000000000000001']; }
        DB::table('employees')->insert($new);
        $hr=$this->actor(); $h=$this->upload($hr,[],[],['count'=>201]);
        DB::enableQueryLog();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        $employeeReads=array_filter($queries,function ($q) { return str_contains($q['query'],'select') && str_contains($q['query'],'"employees"'); });
        $this->assertLessThan(10,count($employeeReads),'Employee lookups must be batched, not one query per row.');
        $this->assertSame('awaiting_confirmation',$h->fresh()->status);
        $this->assertSame(201,$h->fresh()->total_rows);
        DB::table('departemens')->insert(['id'=>2,'perusahaan_id'=>2,'departemen'=>'Lain']);
        DB::table('employees')->where('nik','00323')->update(['departemen_id'=>2]);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertUnprocessable();
        $this->assertSame(0,PayrollEntry::count(),'A failure in the second chunk must roll back every earlier insert.');
        DB::table('employees')->where('nik','00323')->update(['departemen_id'=>1]);
        $this->actingAs($hr)->postJson(route('payroll.finalize',$h),['reviewed'=>1])->assertOk();
        $this->assertSame(201,PayrollEntry::count());
    }

    public function test_streaming_export_keeps_all_5001_rows_and_does_not_turn_text_into_formulas(): void
    {
        $hr=$this->actor(); $h=$this->preview($hr); $item=$h->items()->first();
        for ($start=3;$start<=5002;$start+=200) {
            $rows=[];
            for ($number=$start;$number<min($start+200,5003);$number++) {
                $payload=$item->payload;
                $payload['columns']['A']=str_pad((string) $number,8,'0',STR_PAD_LEFT);
                $payload['columns']['AG']='=2+2';
                $rows[]=['import_history_id'=>$h->id,'category'=>'updated','row_number'=>$number,'nik'=>$payload['columns']['A'],
                    'payload'=>json_encode($payload),'created_at'=>now(),'updated_at'=>now()];
            }
            DB::table('import_history_items')->insert($rows);
        }
        $path=tempnam(sys_get_temp_dir(),'payroll-scale-'); $this->paths[]=$path;
        app(\App\Services\Payroll\PayrollExportService::class)->write($h,$h->summary,$path);
        $reader=IOFactory::createReader('Xlsx'); $reader->setLoadSheetsOnly('FORMAT BULK');
        $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell(string $columnAddress,int $row,string $worksheetName=''): bool { return $row===5002; }
        });
        $book=$reader->load($path);
        $this->assertSame('00005002',(string) $book->getSheet(0)->getCell('A5002')->getValue());
        $this->assertSame(9600000,$book->getSheet(0)->getCell('AE5002')->getValue());
        $this->assertSame(323319,$book->getSheet(0)->getCell('AM5002')->getValue());
        $this->assertSame('=2+2',(string) $book->getSheet(0)->getCell('AG5002')->getValue());
        $this->assertNotSame(DataType::TYPE_FORMULA,$book->getSheet(0)->getCell('AG5002')->getDataType());
        $book->disconnectWorksheets();
    }

    public function test_excel_serial_dates_are_normalized_and_fractional_dates_are_rejected(): void
    {
        $hr=$this->actor();
        $serial=\PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel(new \DateTime('2026-01-25'));
        $h=$this->preview($hr,[],['AL'=>$serial,'G'=>$serial]);
        $this->assertSame('2026-01-25',$h->items()->first()->payload['columns']['AL']);
        $this->assertSame('2026-01-25',$h->items()->first()->payload['columns']['G']);
        $bad=$this->upload($hr,[],['AL'=>$serial+0.5]);
        (new ProcessPayrollImport($bad->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed',$bad->fresh()->status);
    }

    public function test_dynamic_components_flow_through_payroll_recurring_overrides_and_export(): void
    {
        $this->hrisFixtures();
        $this->actingAs($this->actor());
        $this->postJson(route('salary-components.store'), ['code'=>'SHIFT_EXTRA','name'=>'Tunjangan shift','type'=>'income','fixed'=>1])->assertOk();
        $income = \App\Models\SalaryComponent::firstOrFail();
        $this->postJson(route('salary-components.store'), ['code'=>'CICILAN','name'=>'Cicilan','type'=>'deduction','fixed'=>0])->assertOk();
        $deduction = \App\Models\SalaryComponent::latest('id')->first();
        $this->postJson(route('salary-components.store'), ['code'=>'INSENTIF_TUGAS','name'=>'Insentif tugas','type'=>'income','fixed'=>0])->assertOk();
        $variable = \App\Models\SalaryComponent::latest('id')->first();
        $this->postJson(route('salary-components.store'), ['code'=>'SHIFT_EXTRA','name'=>'Duplikat','type'=>'income','fixed'=>1])->assertUnprocessable();
        $save = route('payroll.inputs.save', '00123');
        $scope = ['employer_id'=>1, 'period'=>'2026-01'];
        $base = $scope + ['revision'=>0, 'verify_profile'=>1, 'profile'=>$this->nativeProfile(), 'minutes'=>[1=>0]];
        $this->postJson($save, $base + ['custom'=>[99999=>100]])->assertUnprocessable()->assertJsonValidationErrors('custom');
        $this->postJson($save, $base + ['save_custom_recurring'=>1, 'custom_recurring'=>[$deduction->id=>100]])->assertUnprocessable();
        $this->postJson($save, $base + ['save_custom_recurring'=>1, 'custom_recurring'=>[$income->id=>250000], 'custom'=>[$deduction->id=>25000, $variable->id=>125000]])->assertOk();
        $this->postJson(route('payroll.calculate'), $scope + ['tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $history = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($history->id))->handle(app(PayrollService::class));
        $this->assertSame('awaiting_confirmation', $history->fresh()->status, $history->items()->first()?->message ?? '');
        $p = $history->items()->first()->payload;
        $this->assertCount(3, $p['custom_components']);
        $this->assertSame(0, $p['columns']['Y']);
        $this->assertSame(0, $p['columns']['AC']);
        $cash = array_sum(array_intersect_key($p['columns'], array_flip(PayrollWorkbook::INCOME_COLUMNS))) - $p['columns']['AA'] - $p['columns']['AB'];
        $this->assertSame($cash + 125000, $p['input']['cash_income']);
        $this->assertTrue(collect($p['custom_components'])->firstWhere('id', $income->id)['included_in_wage']);
        $this->assertFalse(collect($p['custom_components'])->firstWhere('id', $variable->id)['included_in_wage']);
        $this->assertSame(25000, $p['input']['cash_deductions']);
        $this->postJson(route('payroll.finalize', $history), ['reviewed'=>1])->assertOk();
        $this->putJson(route('salary-components.update', $income), ['revision'=>1, 'name'=>'Nama baru', 'type'=>'deduction'])->assertOk();
        $this->assertSame('income', $income->fresh()->type);
        $this->putJson(route('salary-components.update', $income), ['revision'=>1, 'name'=>'Konflik'])->assertUnprocessable();
        $this->assertSame('Tunjangan shift', collect(PayrollEntry::first()->snapshot['custom_components'])->firstWhere('id', $income->id)['name']);
        $response = $this->get(route('payroll.download', $history))->assertOk();
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame('AO', $book->getSheetByName('FORMAT BULK')->getHighestColumn());
        $this->assertSame(125000, $book->getSheetByName('FORMAT BULK')->getCell('AN2')->getValue());
        $this->assertSame(25000, $book->getSheetByName('FORMAT BULK')->getCell('AO2')->getValue());
        $this->assertSame('CICILAN', (string) $book->getSheetByName('RINCIAN KOMPONEN')->getCell('C2')->getValue());
        $this->assertStringContainsString('sudah dibayar melalui Riwayat Upah', (string) $book->getSheetByName('RINCIAN KOMPONEN')->getCell('H4')->getValue());
        $book->disconnectWorksheets();
        $this->postJson($save, ['employer_id'=>1,'period'=>'2026-02','revision'=>0,'custom'=>[$income->id=>0]])->assertOk();
        $this->postJson($save, ['employer_id'=>1,'period'=>'2026-04','revision'=>0,'save_custom_recurring'=>1,'custom_recurring'=>[$income->id=>null]])->assertOk();
        foreach (['2026-02'=>0, '2026-03'=>250000, '2026-05'=>0] as $period=>$amount) {
            $this->postJson(route('payroll.calculate'), ['employer_id'=>1,'period'=>$period,'tax_period'=>$period,'pay_date'=>$period.'-25'])->assertStatus(202);
            $next = ImportHistory::latest('id')->firstOrFail();
            (new ProcessPayrollImport($next->id))->handle(app(PayrollService::class));
            $this->assertSame('awaiting_confirmation', $next->fresh()->status, $next->items()->first()?->message ?? '');
            $this->assertSame($amount, collect($next->items()->first()->payload['custom_components'] ?? [])->where('type','income')->sum('amount'));
        }
    }

    public function test_archiving_component_blocks_stale_finalization_and_can_be_restored(): void
    {
        $this->hrisFixtures(); $this->actingAs($this->actor());
        $this->postJson(route('salary-components.store'), ['code'=>'BONUS_X','name'=>'Bonus X','type'=>'income','fixed'=>0])->assertOk();
        $component = \App\Models\SalaryComponent::first();
        $scope = ['employer_id'=>1,'period'=>'2026-01'];
        $this->postJson(route('payroll.inputs.save','00123'), $scope + ['revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(),'minutes'=>[1=>0], 'custom'=>[$component->id=>10000]])->assertOk();
        $this->postJson(route('payroll.calculate'), $scope + ['tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $h = ImportHistory::latest('id')->firstOrFail();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $this->deleteJson(route('salary-components.destroy', $component))->assertOk();
        $this->assertTrue($component->fresh()->trashed());
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertUnprocessable();
        $this->postJson(route('deleted-data.restore', ['type'=>'komponen-gaji','id'=>$component->id]))->assertOk();
        $this->postJson(route('deleted-data.restore', ['type'=>'komponen-gaji','id'=>$component->id]))->assertOk();
        $this->assertSame(1, \App\Models\SalaryComponent::count());
        $this->get(route('salary-components.index'))->assertOk()->assertSee('BONUS_X');
        $this->actingAs($this->actor('HOD', ['slip_gaji_admin']));
        $this->get(route('salary-components.index'))->assertForbidden();
        $this->postJson(route('deleted-data.restore', ['type'=>'komponen-gaji','id'=>$component->id]))->assertForbidden();
        $this->actingAs($this->actor('HR', ['data_karyawan']));
        $this->get(route('salary-components.index'))->assertForbidden();
    }

    public function test_fixed_component_breakdown_cannot_exceed_wage_history_after_tenure(): void
    {
        $this->hrisFixtures(); $this->actingAs($this->actor());
        $service = app(\App\Services\Payroll\SalaryComponentService::class);
        foreach (['JABATAN','TRANSPORT'] as $code) {
            $this->postJson(route('salary-components.store'), ['code'=>$code,'name'=>$code,'type'=>'income','fixed'=>1])->assertOk();
        }
        [$a, $b] = \App\Models\SalaryComponent::orderBy('id')->pluck('id')->all();
        $base = ['employer_id'=>1,'period'=>'2026-01','revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(),'minutes'=>[1=>0]];
        $save = route('payroll.inputs.save', '00123');
        $this->postJson($save, $base + ['custom'=>[$a=>600000,$b=>300000]])->assertUnprocessable()->assertJsonValidationErrors('custom');
        $this->assertSame(0, PayrollInput::count());
        $this->postJson($save, $base + ['save_custom_recurring'=>1,'custom_recurring'=>[$a=>600000], 'custom'=>[$b=>250000]])->assertOk();
        $this->postJson($save, ['employer_id'=>1,'period'=>'2026-02','revision'=>0,'custom'=>[$b=>300000]])->assertUnprocessable();
        $this->postJson(route('payroll.calculate'), ['employer_id'=>1,'period'=>'2026-01','tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $history = ImportHistory::latest('id')->first();
        (new ProcessPayrollImport($history->id))->handle(app(PayrollService::class));
        $this->assertSame('awaiting_confirmation', $history->fresh()->status, $history->items()->first()?->message ?? '');
        $p = $history->items()->first()->payload;
        $this->assertSame(850000, $service->fixedIncomeTotal($p['custom_components']));
        $this->assertSame(1000000, $p['hris']['other_fixed_allowance'] + $p['columns']['N']);
        $this->assertSame($p['input']['cash_income'] - $p['input']['cash_deductions'] - $p['result']['employee_bpjs'], $p['result']['take_home']);
    }

    public function test_existing_recurring_fixed_breakdown_is_revalidated_when_calculated(): void
    {
        $this->hrisFixtures(); $this->actingAs($this->actor());
        $this->postJson(route('salary-components.store'), ['code'=>'LAMA','name'=>'Tunjangan lama','type'=>'income','fixed'=>1])->assertOk();
        $component = \App\Models\SalaryComponent::first();
        $this->postJson(route('payroll.inputs.save','00123'), ['employer_id'=>1,'period'=>'2026-01','revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(),'minutes'=>[1=>0]])->assertOk();
        $old = PayrollInput::first();
        $data = $old->data;
        $data['custom_recurring'] = app(\App\Services\Payroll\SalaryComponentService::class)->snapshots([$component->id=>900000], true);
        PayrollInput::create(['employer_id'=>1,'period'=>'2026-01','employee_nik'=>'00123','created_by'=>$old->created_by,'data'=>$data]);
        $this->postJson(route('payroll.calculate'), ['employer_id'=>1,'period'=>'2026-01','tax_period'=>'2026-01','pay_date'=>'2026-01-25'])->assertStatus(202);
        $history = ImportHistory::latest('id')->first();
        (new ProcessPayrollImport($history->id))->handle(app(PayrollService::class));
        $this->assertSame('validation_failed', $history->fresh()->status);
        $this->assertStringContainsString('rincian tunjangan tetap melebihi', $history->items()->first()->message);
        $this->postJson(route('payroll.finalize', $history), ['reviewed'=>1])->assertUnprocessable();
        $this->assertSame(0, PayrollEntry::count());
    }

    public function test_old_additive_component_preview_and_changed_wage_history_cannot_be_finalized(): void
    {
        $this->hrisFixtures(); $this->actingAs($this->actor());
        $this->postJson(route('payroll.inputs.save','00123'), ['employer_id'=>1,'period'=>'2026-01','revision'=>0,'verify_profile'=>1,'profile'=>$this->nativeProfile(),'minutes'=>[1=>0]])->assertOk();
        $settings = ['employer_id'=>1,'period'=>'2026-01','tax_period'=>'2026-01','pay_date'=>'2026-01-25'];
        $this->postJson(route('payroll.calculate'), $settings)->assertStatus(202);
        $h = ImportHistory::latest('id')->first();
        (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $summary = $h->fresh()->summary;
        $legacy = $summary;
        $legacy['settings']['component_version'] = hash('sha256', \App\Models\SalaryComponent::withTrashed()->orderBy('id')->get(['id','revision','deleted_at'])->toJson());
        $h->update(['summary'=>$legacy]);
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertUnprocessable()->assertJsonPath('errors.payroll.0', 'Master atau aturan sumber komponen gaji berubah. Hitung ulang payroll sebelum finalisasi.');
        $h->update(['summary'=>$summary]);
        $revision = \App\Models\EmployeeSalaryHistory::first()->replicate();
        $revision->fixed_allowance = 200000; $revision->row_number = 3; $revision->save();
        $this->postJson(route('payroll.finalize', $h), ['reviewed'=>1])->assertUnprocessable()->assertJsonPath('errors.payroll.0', 'Riwayat Upah berubah setelah pratinjau. Hitung ulang agar tunjangan tetap tidak tercatat ganda.');
        $this->assertSame(0, PayrollEntry::count());
    }

    private function actor(string $role='HR', array $menus=['slip_gaji_admin']): User
    {
        $id=DB::table('roles')->insertGetId(['permission_role'=>$role,'menu_permissions'=>json_encode($menus)]);
        $user=User::create(['id'=>'payroll-user-'.$id,'name'=>$role,'role_id'=>$id]);
        $user->setRelation('employee',Employee::first()); return $user;
    }

    private function settings(): array { return ['employer_id'=>1,'period'=>'2026-01','tax_period'=>'2026-01','start_date'=>'2025-12-16','end_date'=>'2026-01-15','pay_date'=>'2026-01-25']; }

    private function upload(User $hr, array $params=[], array $columns=[], array $options=[]): ImportHistory
    {
        $settings = $this->settings();
        if ($options['hris'] ?? false) { $settings += ['income_source'=>'hris']; }
        $this->actingAs($hr)->postJson(route('payroll.store'),$settings+['file'=>$this->workbook($params,$columns,$options)])->assertStatus(202);
        return ImportHistory::latest('id')->firstOrFail();
    }

    private function preview(User $hr, array $params=[], array $columns=[]): ImportHistory
    {
        $h=$this->upload($hr,$params,$columns); (new ProcessPayrollImport($h->id))->handle(app(PayrollService::class));
        $h->refresh(); $this->assertSame('awaiting_confirmation',$h->status,$h->error_message ?: 'Preview failed.'); return $h;
    }

    private function workbook(array $params=[], array $columns=[], array $options=[]): UploadedFile
    {
        $book=new Spreadsheet(); $sheet=$book->getActiveSheet()->setTitle('FORMAT BULK');
        $sheet->fromArray(array_slice(PayrollWorkbook::HEADINGS,0,38),null,'A1');
        $data=array_merge(['A'=>'00123','J'=>10000000],$columns);
        foreach ($data as $col=>$value) { $sheet->setCellValueExplicit($col.'2',$value,is_string($value)?DataType::TYPE_STRING:DataType::TYPE_NUMERIC); }
        for ($i=1;$i<($options['count'] ?? 1);$i++) {
            foreach ($data as $col=>$value) { $sheet->setCellValueExplicit($col.($i+2),$col==='A'?str_pad((string) (123+$i),5,'0',STR_PAD_LEFT):$value,is_string($value)?DataType::TYPE_STRING:DataType::TYPE_NUMERIC); }
        }
        if ($options['duplicate'] ?? false) { foreach ($data as $col=>$value) { $sheet->setCellValueExplicit($col.'3',$value,is_string($value)?DataType::TYPE_STRING:DataType::TYPE_NUMERIC); } }
        if ($options['formula'] ?? false) { $sheet->setCellValue('J2','=1+1'); }
        $p=array_merge(['nik'=>'00123','ptkp'=>'TK/0','upah_bpjs_tk'=>10000000,'upah_bpjs_kes'=>10000000,'upah_minimum'=>4000000,'jkk_persen'=>0.24,
            'jht_aktif'=>1,'jp_aktif'=>1,'kes_aktif'=>1,'jkk_jkm_aktif'=>1,'keluarga_tambahan'=>0,'masa_terakhir'=>0,'natura'=>0,'iuran_pensiun_lain'=>0,'zakat'=>0,'subjek_pajak'=>'DN_SETAHUN','fasilitas'=>'NORMAL','nik_npwp_valid'=>1],$params);
        $tab=$book->createSheet()->setTitle('PARAMETER'); $tab->fromArray(PayrollWorkbook::PARAMETERS,null,'A1');
        foreach (PayrollWorkbook::PARAMETERS as $i=>$key) {
            if (isset($p[$key])) { $value=$p[$key]; $tab->setCellValueExplicit(Coordinate::stringFromColumnIndex($i+1).'2',$value,is_string($value)?DataType::TYPE_STRING:DataType::TYPE_NUMERIC); }
        }
        for ($r=1;$r<($options['count'] ?? 1);$r++) {
            foreach (PayrollWorkbook::PARAMETERS as $i=>$key) {
                if (isset($p[$key])) { $value=$p[$key]; $tab->setCellValueExplicit(Coordinate::stringFromColumnIndex($i+1).($r+2),$key==='nik'?str_pad((string) (123+$r),5,'0',STR_PAD_LEFT):$value,is_string($value)?DataType::TYPE_STRING:DataType::TYPE_NUMERIC); }
            }
        }
        if ($options['hris'] ?? false) {
            $book->createSheet()->setTitle('LEMBUR')->fromArray(array_merge([['ID_PERINTAH_LEMBUR', 'MENIT_DISAHKAN']], $options['overtime'] ?? []), null, 'A1', true);
        }
        $path=tempnam(sys_get_temp_dir(),'payroll-test-'); $this->paths[]=$path;
        (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        return new UploadedFile($path,'payroll.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true);
    }
}
