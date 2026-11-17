<?php

namespace Tests\Feature;

use App\Jobs\ProcessSeveranceFile;
use App\Models\ImportHistory;
use App\Models\SeveranceRequest;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Severance\SeveranceExport;
use App\Services\Severance\SeveranceSalarySource;
use App\Services\Severance\SeveranceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SeveranceWorkflowTest extends TestCase
{
    private $paths = [];
    private $staff;
    private $payroll;
    private $hi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.connections.epayslip' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'severance.connection' => 'database']);
        foreach (['sqlite', 'epayslip'] as $connection) { DB::purge($connection); DB::reconnect($connection); }
        Storage::fake('local'); Queue::fake();
        $this->mock(AuditTrailService::class)->shouldReceive('record')->andReturnNull();
        Schema::create('roles', function (Blueprint $table) { $table->increments('id'); $table->string('permission_role'); $table->text('menu_permissions')->nullable(); $table->timestamps(); });
        Schema::create('users', function (Blueprint $table) { $table->string('id')->primary(); $table->string('name'); $table->string('email')->nullable(); $table->unsignedInteger('role_id'); $table->string('nik_karyawan')->nullable(); $table->timestamps(); });
        Schema::create('notifications', function (Blueprint $table) { $table->string('id')->primary(); $table->string('type'); $table->string('notifiable_type'); $table->string('notifiable_id'); $table->text('data'); $table->timestamp('read_at')->nullable(); $table->timestamps(); });
        Schema::create('employees', function (Blueprint $table) { $table->string('nik')->primary(); $table->string('nama_karyawan'); $table->string('no_ktp'); $table->date('entry_date'); $table->softDeletes();
            $table->string('area_kerja')->nullable(); $table->unsignedBigInteger('departemen_id')->nullable(); $table->unsignedBigInteger('divisi_id')->nullable();
            $table->string('posisi')->nullable(); $table->string('jabatan')->nullable(); $table->string('status_karyawan')->nullable(); $table->string('status_resign')->nullable();
        });
        Schema::create('departemens', function (Blueprint $table) { $table->increments('id'); $table->string('departemen'); $table->softDeletes(); });
        Schema::create('divisis', function (Blueprint $table) { $table->increments('id'); $table->string('nama_divisi'); $table->softDeletes(); });
        (require database_path('migrations/2026_05_09_000001_create_import_histories_table.php'))->up();
        (require database_path('migrations/2026_08_25_000001_create_import_history_items_table.php'))->up();
        (require database_path('migrations/2026_11_15_000001_create_severance_requests_table.php'))->up();
        Schema::connection('epayslip')->create('data_karyawans', function (Blueprint $table) { $table->increments('id'); $table->string('nik'); $table->string('no_ktp'); });
        Schema::connection('epayslip')->create('komponen_gajis', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('data_karyawan_id'); $table->string('periode'); $table->date('akhir_periode');
            $table->string('status_gaji'); $table->string('gaji_pokok'); $table->string('tunj_pengawas')->nullable(); $table->string('tunj_mk')->nullable(); $table->string('tunj_koefisien')->nullable();
        });
        DB::table('departemens')->insert(['id' => 1, 'departemen' => 'Departemen Sintetis']);
        DB::table('divisis')->insert(['id' => 1, 'nama_divisi' => 'Divisi Sintetis']);
        DB::table('employees')->insert(['nik' => '00123', 'nama_karyawan' => 'Karyawan Sintetis', 'no_ktp' => '0000000000000001', 'entry_date' => '2016-11-16',
            'area_kerja' => 'VDNI', 'departemen_id' => 1, 'divisi_id' => 1, 'posisi' => 'Operator Sintetis', 'jabatan' => 'Staff', 'status_karyawan' => 'PKWTT', 'status_resign' => 'AKTIF']);
        DB::connection('epayslip')->table('data_karyawans')->insert(['id' => 1, 'nik' => '00123', 'no_ktp' => '0000000000000001']);
        $this->slip('FULL', '2026-09-15', 4000000);
        $this->slip('PRORATE', '2026-10-15', 1500000);
        $this->slip('FULL', '2026-12-15', 9000000);
        $this->staff = $this->actor('staff', ['severance_staff']);
        $this->payroll = $this->actor('payroll', ['severance_payroll']);
        $this->hi = $this->actor('hi', ['severance_hi']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) { unlink($path); } }
        DB::disconnect('sqlite'); DB::disconnect('epayslip');
        parent::tearDown();
    }

    public function test_full_workflow_locks_salary_requires_leave_and_exports_single_and_batch_only_after_final_approval(): void
    {
        $batch = $this->upload();
        $this->runFile($batch);
        $entry = SeveranceRequest::firstOrFail();
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('00123', $entry->employee_nik);
        $this->assertNull($entry->salary_snapshot);
        $this->actingAs($this->staff)->postJson(route('severance.export', $batch), ['ids' => [$entry->id]])->assertStatus(409);
        $this->postJson(route('severance.submit', $batch))->assertOk();
        $this->postJson(route('severance.submit', $batch))->assertOk();
        $entry->refresh(); $source = app(SeveranceSalarySource::class)->resolve($entry);
        $this->assertEquals(4000000, $source['basic_salary']);
        $this->assertEquals(500000, $source['position_allowance']);
        $this->assertSame('2026-09', $source['period']);
        $this->actingAs($this->payroll)->postJson(route('severance.payroll', $entry), [
            'revision' => $entry->revision, 'source_hash' => $source['hash'], 'reviewed' => 1,
            'salary_snapshot' => ['basic_salary' => 1], 'calculation' => ['total' => 1],
        ])->assertOk();
        $entry->refresh();
        $this->assertEquals(4000000, $entry->salary_snapshot['basic_salary']);
        $this->assertNull($entry->calculation);
        DB::connection('epayslip')->table('komponen_gajis')->where('id', 1)->update(['gaji_pokok' => 7000000]);
        $this->actingAs($this->hi)->postJson(route('severance.hi', $entry), ['revision' => $entry->revision, 'intent' => 'preview'])->assertUnprocessable()->assertJsonValidationErrors('leave_days');
        $preview = $this->postJson(route('severance.hi', $entry), ['revision' => $entry->revision, 'intent' => 'preview', 'leave_days' => 12])
            ->assertOk()->json('data');
        $this->assertEquals(42750000, $preview['calculation']['total']);
        $labels = ['A. PESANGON', 'B. PENGHARGAAN MASA KERJA', 'C. PENGGANTIAN HAK CUTI', 'D. ONGKOS PULANG'];
        $this->assertSame($labels, array_column($preview['calculation']['lines'], 'label'));
        $this->assertSame('E. TOTAL', $preview['calculation']['total_label']);
        $this->assertNull($entry->fresh()->calculation);
        $this->postJson(route('severance.hi', $entry), ['revision' => $entry->revision, 'intent' => 'approve', 'leave_days' => 0,
            'reviewed' => 1, 'preview_hash' => $preview['preview_hash']])->assertStatus(409);
        $this->postJson(route('severance.hi', $entry), ['revision' => $entry->revision, 'intent' => 'approve', 'leave_days' => 12,
            'reviewed' => 1, 'preview_hash' => $preview['preview_hash']])->assertOk();
        $this->assertSame('approved', $entry->fresh()->status);
        $snapshot = $entry->fresh()->calculation;
        $this->assertSame('Pesangon', $snapshot['lines'][0]['label']);
        $this->assertArrayNotHasKey('total_label', $snapshot);
        $this->assertSame(hash('sha256', json_encode($snapshot)), $entry->fresh()->calculation_hash);
        $legacyExport = ImportHistory::create(['import_id' => 'legacy-severance-export', 'module' => 'severance_export',
            'import_type' => ImportHistory::TYPE_SEVERANCE_EXPORT, 'source' => 'hris', 'status' => 'completed',
            'created_by' => $this->staff->id,
            'severance_process_key' => hash('sha256', 'export|' . $this->staff->id . '|' . $entry->id)]);
        $this->actingAs($this->staff)->postJson(route('severance.export', $batch), ['ids' => [$entry->id]])->assertAccepted();
        $this->postJson(route('severance.export', $batch), ['all' => 1])->assertAccepted();
        $export = ImportHistory::where('module', 'severance_export')->latest('id')->firstOrFail();
        $this->assertSame(2, ImportHistory::where('module', 'severance_export')->count());
        $this->assertNotSame($legacyExport->id, $export->id);
        $this->assertSame('completed', $legacyExport->fresh()->status);
        $this->get(route('severance.download', $export))->assertStatus(409);
        $this->runFile($export); $this->runFile($export);
        $response = $this->get(route('severance.download', $export))->assertOk();
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $book->getSheetByName('PENGAJUAN');
        $this->assertSame('00123', $sheet->getCell('B8')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B8')->getDataType());
        $this->assertEquals(4000000, $sheet->getCell('N8')->getValue());
        $this->assertEquals(42750000, $sheet->getCell('Z8')->getValue());
        $this->assertSame('Aktor Sintetis payroll', $sheet->getCell('AB8')->getValue());
        $this->assertSame(8, $sheet->getHighestDataRow());
        $this->assertSame('52(1)', $sheet->getCell('R8')->getValue());
        $details = $book->getSheetByName('RINCIAN HITUNG');
        $this->assertNotNull($details);
        $this->assertSame(array_merge($labels, ['E. TOTAL']), array_column($details->rangeToArray('D2:D6'), 0));
        $this->assertEquals(42750000, $details->getCell('F6')->getValue());
        $book->disconnectWorksheets();
        $this->get(route('severance.print', ['history' => $batch, 'ids' => [$entry->id]]))->assertOk()
            ->assertSeeInOrder(array_merge($labels, ['E. TOTAL']))->assertSee('42.750.000,00');
        $this->get(route('severance.show', $entry))->assertOk()->assertSeeInOrder(array_merge($labels, ['E. TOTAL']));
        $this->assertSame($snapshot, $entry->fresh()->calculation);
    }

    public function test_batch_print_places_four_final_results_on_each_a4_sheet_with_a_partial_last_sheet(): void
    {
        [$batch, $entry] = $this->payrollApproved();
        $data = ['revision' => $entry->revision, 'intent' => 'preview', 'leave_days' => 12];
        $this->actingAs($this->hi);
        $preview = $this->postJson(route('severance.hi', $entry), $data)->assertOk()->json('data');
        $data['intent'] = 'approve'; $data['preview_hash'] = $preview['preview_hash']; $data['reviewed'] = 1;
        $this->postJson(route('severance.hi', $entry), $data)->assertOk();
        $entry->refresh(); $snapshot = $entry->calculation;
        $ids = [$entry->id];
        for ($i = 1; $i < 5; $i++) {
            $copy = $entry->replicate();
            $copy->fill(['row_number' => 8 + $i, 'employee_nik' => '0012' . (3 + $i),
                'employee_name' => $i === 3 ? substr(str_repeat('Nama Karyawan Sintetis Panjang ', 7), 0, 200) : 'Karyawan Sintetis ' . ($i + 1),
                'reason' => $i === 3 ? substr(str_repeat('Alasan sintetis panjang untuk pemeriksaan hasil cetak. ', 20), 0, 1000) : 'Alasan sintetis']);
            $copy->save(); $ids[] = $copy->id;
        }
        $this->actingAs($this->staff);
        foreach ([1 => [1], 2 => [2], 4 => [4], 5 => [4, 1]] as $count => $expected) {
            $response = $this->get(route('severance.print', ['history' => $batch, 'ids' => array_slice($ids, 0, $count)]))->assertOk();
            $dom = new \DOMDocument();
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $pages = $xpath->query('//section[@class="print-page"]');
            $this->assertCount(count($expected), $pages);
            foreach ($pages as $index => $page) {
                $this->assertCount($expected[$index], $xpath->query('./article', $page));
            }
            $response->assertSee($count > 1 ? 'class="batch-print"' : 'class="single-print"', false);
            $this->assertCount($count, $xpath->query('//tfoot//th[contains(., "E. TOTAL")]'));
        }
        $this->assertSame($snapshot, $entry->fresh()->calculation);
    }

    public function test_duplicate_upload_wrong_stage_self_approval_and_stale_salary_are_rejected(): void
    {
        $file = $this->workbook();
        $batch = $this->upload($file); $this->upload($file);
        $this->assertSame(1, ImportHistory::count()); Queue::assertPushed(ProcessSeveranceFile::class, 1);
        $this->runFile($batch); $entry = SeveranceRequest::firstOrFail();
        $this->actingAs($this->hi)->postJson(route('severance.hi', $entry), ['revision' => 1, 'intent' => 'preview', 'leave_days' => 0])->assertForbidden();
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $entry->refresh(); $source = app(SeveranceSalarySource::class)->resolve($entry);
        $data = ['revision' => $entry->revision, 'source_hash' => $source['hash'], 'reviewed' => 1];
        $this->postJson(route('severance.payroll', $entry), $data)->assertForbidden();
        DB::connection('epayslip')->table('komponen_gajis')->where('id', 1)->update(['gaji_pokok' => 4100000]);
        $this->actingAs($this->payroll)->postJson(route('severance.payroll', $entry), $data)->assertStatus(409);
        $data['source_hash'] = app(SeveranceSalarySource::class)->resolve($entry)['hash'];
        $this->postJson(route('severance.payroll', $entry), $data)->assertOk();
        $this->postJson(route('severance.payroll', $entry), $data)->assertStatus(409);
        $this->assertCount(1, $entry->fresh()->decisions);
        $role = $this->payroll->role; $role->menu_permissions = ['severance_payroll', 'severance_hi']; $role->save();
        $this->payroll->unsetRelation('role');
        $this->postJson(route('severance.hi', $entry), ['revision' => $entry->fresh()->revision, 'intent' => 'preview', 'leave_days' => 0])->assertForbidden();
    }

    public function test_return_for_revision_clears_approval_and_repeat_old_requests_cannot_approve_new_version(): void
    {
        [$batch, $entry] = $this->payrollApproved();
        $oldRevision = $entry->revision;
        $this->actingAs($this->hi)->postJson(route('severance.reject', $entry), ['revision' => $oldRevision, 'note' => 'Periksa kembali awal kontrak.'])->assertOk();
        $entry->refresh(); $this->assertSame('draft', $entry->status); $this->assertNull($entry->salary_snapshot);
        $this->assertNull($entry->payroll_approved_by);
        $this->actingAs($this->staff)->postJson(route('severance.update', $entry), ['revision' => $entry->revision,
            'article' => '56', 'reason' => 'Pensiun sintetis', 'calculation_start' => '2016-11-16', 'termination_date' => '2026-11-15', 'return_fare' => 0])->assertOk();
        $this->postJson(route('severance.submit', $batch))->assertOk();
        $this->actingAs($this->hi)->postJson(route('severance.hi', $entry), ['revision' => $oldRevision, 'intent' => 'preview', 'leave_days' => 0])->assertStatus(409);
        $this->assertSame('payroll_review', $entry->fresh()->status);
    }

    public function test_only_owner_can_read_export_print_and_bypass_through_generic_history_is_forbidden(): void
    {
        [$batch, $entry] = $this->payrollApproved();
        $other = $this->actor('other-staff', ['severance_staff']);
        $this->actingAs($other)->get(route('severance.batch', $batch))->assertForbidden();
        $this->get(route('severance.show', $entry))->assertForbidden();
        $this->get(route('severance.status', $batch))->assertForbidden();
        $this->postJson(route('severance.export', $batch), ['all' => 1])->assertForbidden();
        $this->actingAs($this->staff)->get(route('severance.print', ['history' => $batch, 'ids' => [$entry->id]]))->assertStatus(409);
        $admin = $this->actor('admin', ['import_history'], 'Super Admin');
        $this->actingAs($admin)->get(route('import-histories.export', [$batch, 'failed']))->assertForbidden();
    }

    public function test_original_template_with_blank_formatted_far_rows_is_read_without_trusting_uploaded_salary_or_leave(): void
    {
        $file = $this->workbook(true);
        $batch = $this->upload($file); $this->runFile($batch);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(1, $batch->fresh()->total_rows);
        $entry = SeveranceRequest::firstOrFail();
        $this->assertNull($entry->salary_snapshot); $this->assertNull($entry->leave_days);
        $this->assertSame('Karyawan Sintetis', $entry->employee_name);
        $this->assertSame('52(1)', $entry->article);
    }

    public function test_prorate_only_and_unavailable_payslip_leave_the_application_unchanged(): void
    {
        $batch = $this->upload(); $this->runFile($batch);
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $entry = SeveranceRequest::firstOrFail();
        DB::connection('epayslip')->table('komponen_gajis')->where('status_gaji', 'FULL')->delete();
        $this->actingAs($this->payroll)->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => str_repeat('0', 64), 'reviewed' => 1])->assertUnprocessable();
        $this->assertNull($entry->fresh()->salary_snapshot);
        $this->assertSame('payroll_review', $entry->fresh()->status);
    }

    public function test_pages_render_for_staff_payroll_and_hi_without_leaking_the_unapproved_result(): void
    {
        $batch = $this->upload(); $this->runFile($batch);
        $entry = SeveranceRequest::firstOrFail();
        $this->get(route('severance.index'))->assertOk()->assertSee('Pesangon &amp; Kompensasi', false);
        $this->get(route('severance.batch', $batch))->assertOk()->assertSee('Karyawan Sintetis');
        $this->get(route('severance.show', $entry))->assertOk()->assertDontSee('4.000.000,00');
        $this->postJson(route('severance.submit', $batch))->assertOk();
        $this->actingAs($this->payroll)->get(route('severance.show', $entry))->assertOk()->assertSee('4.000.000,00')->assertDontSee('42.750.000,00');
        $source = app(SeveranceSalarySource::class)->resolve($entry->fresh());
        $this->postJson(route('severance.payroll', $entry), ['revision' => $entry->fresh()->revision, 'source_hash' => $source['hash'], 'reviewed' => 1])->assertOk();
        $this->actingAs($this->hi)->get(route('severance.show', $entry))->assertOk()->assertSee('Tampilkan pratinjau')->assertDontSee('42.750.000,00');
        $this->actingAs($this->staff)->get(route('severance.show', $entry))->assertOk()->assertDontSee('data-hi-form', false)->assertDontSee('42.750.000,00');
    }

    public function test_payroll_verification_displays_hris_employee_details_and_full_wage_total_without_private_identity_fields(): void
    {
        $batch = $this->upload(); $this->runFile($batch);
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $entry = SeveranceRequest::firstOrFail();
        $entry->update(['source_metadata' => ['F' => 'Status dari unggahan', 'G' => 'Posisi dari unggahan']]);
        $this->actingAs($this->payroll)->get(route('severance.show', $entry))->assertOk()
            ->assertSeeInOrder(['Detail karyawan', '00123', 'Karyawan Sintetis', 'VDNI', 'Departemen Sintetis', 'Divisi Sintetis', 'Operator Sintetis'])
            ->assertSee('PKWTT')->assertSee('AKTIF')->assertSee('10 tahun 0 bulan 0 hari')
            ->assertSee('Tunjangan jabatan')->assertSee('500.000,00')->assertSee('Tunjangan masa kerja')->assertSee('300.000,00')
            ->assertSee('Total upah dasar')->assertSee('4.800.000,00')
            ->assertDontSee('Posisi dari unggahan')->assertDontSee('0000000000000001')
            ->assertViewHas('employee', function ($employee) { return !array_key_exists('no_ktp', $employee->getAttributes()); });
        DB::table('employees')->where('nik', '00123')->update(['posisi' => '<script>alert("employee")</script>']);
        $this->get(route('severance.show', $entry))->assertOk()->assertSee('<script>alert("employee")</script>')
            ->assertDontSee('<script>alert("employee")</script>', false);
        $this->assertNull($entry->fresh()->salary_snapshot);
        $this->assertNull($entry->fresh()->calculation);
    }

    public function test_employee_details_remain_readable_for_archived_or_missing_employee_records(): void
    {
        $batch = $this->upload(); $this->runFile($batch);
        $entry = SeveranceRequest::firstOrFail();
        DB::table('employees')->where('nik', '00123')->update(['deleted_at' => now()]);
        $this->actingAs($this->staff)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Departemen Sintetis')->assertSee('Operator Sintetis')->assertDontSee('4.800.000,00');
        DB::table('employees')->where('nik', '00123')->delete();
        $this->get(route('severance.show', $entry))->assertOk()->assertSee('Karyawan Sintetis')
            ->assertSee('Data organisasi karyawan belum tersedia di HRIS.')->assertDontSee('Operator Sintetis');
    }

    public function test_zero_leave_can_be_approved_but_final_data_cannot_be_changed_or_returned(): void
    {
        [$batch, $entry] = $this->payrollApproved();
        $this->actingAs($this->hi);
        $data = ['revision' => $entry->revision, 'intent' => 'preview', 'leave_days' => 0];
        $preview = $this->postJson(route('severance.hi', $entry), $data)->assertOk()->json('data');
        $data['intent'] = 'approve'; $data['preview_hash'] = $preview['preview_hash']; $data['reviewed'] = 1;
        $this->postJson(route('severance.hi', $entry), $data)->assertOk();
        $this->postJson(route('severance.hi', $entry), $data)->assertStatus(409);
        $entry->refresh(); $this->assertEquals(0, $entry->leave_days);
        $this->postJson(route('severance.reject', $entry), ['revision' => $entry->revision, 'note' => 'Ubah final'])->assertForbidden();
        $this->actingAs($this->staff)->postJson(route('severance.update', $entry), ['revision' => $entry->revision,
            'article' => '50', 'reason' => 'Perubahan setelah final', 'calculation_start' => '2016-11-16',
            'termination_date' => '2026-11-15', 'return_fare' => 0])->assertStatus(409);
        $this->assertSame('approved', $entry->fresh()->status);
    }

    public function test_formula_input_and_duplicate_employee_are_reported_without_submitting_failed_rows(): void
    {
        $file = $this->workbook(); $book = IOFactory::load($file->getRealPath()); $sheet = $book->getActiveSheet();
        $sheet->setCellValueExplicit('B9', '00123', DataType::TYPE_STRING);
        $sheet->setCellValue('R9', '50'); $sheet->setCellValue('L9', 'Duplikat');
        $sheet->setCellValue('B10', '=1+1'); $sheet->setCellValue('R10', '50'); $sheet->setCellValue('L10', 'Formula');
        (new Xlsx($book))->save($file->getRealPath()); $book->disconnectWorksheets();
        $batch = $this->upload($file); $this->runFile($batch);
        $this->assertSame('completed_with_errors', $batch->fresh()->status);
        $this->assertSame(2, $batch->fresh()->failed_count); $this->assertSame(1, SeveranceRequest::count());
        $this->assertSame(2, $batch->items()->count());
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $this->assertSame('payroll_review', SeveranceRequest::first()->status);
    }

    public function test_missing_article_blocks_submit_and_failed_import_can_be_retried_on_the_same_batch(): void
    {
        $file = $this->workbook(); $book = IOFactory::load($file->getRealPath());
        $book->getActiveSheet()->setCellValue('R8', '');
        (new Xlsx($book))->save($file->getRealPath()); $book->disconnectWorksheets();
        $batch = $this->upload($file); $this->runFile($batch);
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertUnprocessable();
        $this->assertSame('draft', SeveranceRequest::first()->status);
        $batch->update(['status' => 'failed', 'error_message' => 'Percobaan uji']);
        $this->postJson(route('severance.retry', $batch))->assertOk();
        $this->postJson(route('severance.retry', $batch))->assertStatus(409);
        Queue::assertPushed(ProcessSeveranceFile::class, 2);
    }

    public function test_super_admin_can_complete_own_application_with_both_approvals_and_mandatory_leave(): void
    {
        $admin = $this->actor('owner-admin', [], 'Super Admin');
        $this->actingAs($admin)->postJson(route('severance.store'), ['file' => $this->workbook(),
            'title' => 'Batch Super Admin', 'termination_date' => '2026-11-15'])->assertAccepted();
        $batch = ImportHistory::where('module', 'severance')->firstOrFail(); $this->runFile($batch);
        $entry = SeveranceRequest::firstOrFail();
        $this->actingAs($admin)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Langkah berikutnya')->assertSee('Buka batch dan kirim draf');
        $this->postJson(route('severance.submit', $batch))->assertOk();
        $this->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Periksa dan setujui upah')->assertSee('name="source_hash"', false);
        $entry->refresh();
        $source = app(SeveranceSalarySource::class)->resolve($entry);
        $this->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => $source['hash'], 'reviewed' => 1])->assertOk();
        $this->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Isi cuti dan periksa hasil')->assertSee('data-hi-form', false);
        $entry->refresh();
        $data = ['revision' => $entry->revision, 'intent' => 'preview'];
        $this->postJson(route('severance.hi', $entry), $data)->assertUnprocessable()->assertJsonValidationErrors('leave_days');
        $data['leave_days'] = 12;
        $preview = $this->postJson(route('severance.hi', $entry), $data)->assertOk()->json('data');
        $data['intent'] = 'approve'; $data['preview_hash'] = $preview['preview_hash'];
        $this->postJson(route('severance.hi', $entry), $data)->assertUnprocessable()->assertJsonValidationErrors('reviewed');
        $data['reviewed'] = 1;
        $this->postJson(route('severance.hi', $entry), $data)->assertOk();
        $this->postJson(route('severance.hi', $entry), $data)->assertStatus(409);
        $entry->refresh();
        $this->assertSame('approved', $entry->status);
        $this->assertSame($admin->id, $entry->created_by);
        $this->assertSame($admin->id, $entry->payroll_approved_by);
        $this->assertSame($admin->id, $entry->hi_approved_by);
        $this->assertCount(2, $entry->decisions);
        $this->assertEquals(42750000, $entry->calculation['total']);
        $this->get(route('severance.show', $entry))->assertOk()->assertSee('Cetak / simpan PDF')->assertSee('Export Excel');
        $this->get(route('severance.print', ['history' => $batch, 'ids' => [$entry->id]]))->assertOk();
        $this->postJson(route('severance.export', $batch), ['ids' => [$entry->id]])->assertAccepted();
        $this->postJson(route('severance.update', $entry), ['revision' => $entry->revision,
            'article' => '50', 'reason' => 'Ubah setelah final', 'calculation_start' => '2016-11-16',
            'termination_date' => '2026-11-15', 'return_fare' => 0])->assertStatus(409);
        $this->postJson(route('severance.reject', $entry), ['revision' => $entry->revision, 'note' => 'Ubah final'])->assertForbidden();
    }

    public function test_super_admin_can_manage_staff_drafts_and_download_staff_exports(): void
    {
        $batch = $this->upload();
        $admin = $this->actor('reviewer-admin', [], 'Super Admin');
        $this->actingAs($admin)->get(route('severance.index'))->assertOk()->assertSee('Batch sintetis');
        $this->get(route('severance.status', $batch))->assertOk()->assertJsonPath('data.status', 'queued');
        $this->get(route('severance.batch', $batch))->assertOk();
        $this->runFile($batch); $entry = SeveranceRequest::firstOrFail();
        $this->get(route('severance.batch', $batch))->assertOk()->assertSee('Kirim 1 draf');
        $this->get(route('severance.show', $entry))->assertOk()->assertSee('Simpan draf');
        $this->postJson(route('severance.update', $entry), ['revision' => $entry->revision,
            'article' => '52(1)', 'reason' => 'Draf diperiksa Super Admin', 'calculation_start' => '2016-11-16',
            'termination_date' => '2026-11-15', 'return_fare' => 30000])->assertOk();
        $this->postJson(route('severance.submit', $batch))->assertOk(); $entry->refresh();
        $this->actingAs($admin)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Periksa dan setujui upah')->assertSee('id="payroll-review"', false);
        $source = app(SeveranceSalarySource::class)->resolve($entry);
        $this->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => $source['hash'], 'reviewed' => 1])->assertOk();
        $this->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Isi cuti dan periksa hasil')->assertSee('data-hi-form', false);
        $entry->refresh();
        $data = ['revision' => $entry->revision, 'intent' => 'preview', 'leave_days' => 0];
        $preview = $this->postJson(route('severance.hi', $entry), $data)->assertOk()->json('data');
        $data['intent'] = 'approve'; $data['preview_hash'] = $preview['preview_hash']; $data['reviewed'] = 1;
        $this->postJson(route('severance.hi', $entry), $data)->assertOk();
        $this->assertSame($this->staff->id, $entry->fresh()->created_by);
        $this->get(route('severance.show', $entry))->assertOk()->assertSee('Export Excel');
        $this->get(route('severance.print', ['history' => $batch, 'ids' => [$entry->id]]))->assertOk();
        $this->actingAs($this->staff)->postJson(route('severance.export', $batch), ['ids' => [$entry->id]])->assertAccepted();
        $export = ImportHistory::where('module', 'severance_export')->firstOrFail(); $this->runFile($export);
        $this->actingAs($admin)->get(route('severance.download', $export))->assertOk();
        $this->get(route('severance.status', $export))->assertOk();
        $this->get(route('severance.index'))->assertOk()->assertSee('Export #' . $export->id);
    }

    public function test_normal_users_with_multiple_permissions_keep_separation_and_clear_guidance(): void
    {
        $batch = $this->upload(); $this->runFile($batch);
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $entry = SeveranceRequest::firstOrFail();
        DB::table('roles')->where('id', $this->staff->role_id)->update(['menu_permissions' => json_encode(['severance_staff', 'severance_payroll', 'severance_hi'])]);
        $this->staff->unsetRelation('role');
        $this->actingAs($this->staff)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Anda adalah pembuat pengajuan ini.')->assertDontSee('name="source_hash"', false);
        $source = app(SeveranceSalarySource::class)->resolve($entry);
        $this->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => $source['hash'], 'reviewed' => 1])->assertForbidden();
        DB::table('roles')->where('id', $this->payroll->role_id)->update(['menu_permissions' => json_encode(['severance_payroll', 'severance_hi'])]);
        $this->payroll->unsetRelation('role');
        $this->actingAs($this->payroll)->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => $source['hash'], 'reviewed' => 1])->assertOk();
        $this->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Anda sudah menyetujui tahap Payroll.')->assertDontSee('data-hi-form', false);
        $entry->refresh();
        $this->postJson(route('severance.hi', $entry), ['revision' => $entry->revision,
            'intent' => 'preview', 'leave_days' => 0])->assertForbidden();
        $this->actingAs($this->staff)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Anda adalah pembuat pengajuan ini.')->assertDontSee('data-hi-form', false);
        $this->postJson(route('severance.hi', $entry), ['revision' => $entry->revision,
            'intent' => 'preview', 'leave_days' => 0])->assertForbidden();
        $this->actingAs($this->hi)->get(route('severance.show', $entry))->assertOk()
            ->assertSee('Isi cuti dan periksa hasil')->assertSee('data-hi-form', false);
    }

    private function actor(string $id, array $menus, ?string $roleName = null): User
    {
        $roleId = DB::table('roles')->insertGetId(['permission_role' => $roleName ?: 'Uji ' . $id, 'menu_permissions' => json_encode($menus)]);
        return User::create(['id' => $id, 'name' => 'Aktor Sintetis ' . $id, 'email' => $id . '@example.test', 'role_id' => $roleId]);
    }

    private function slip(string $status, string $end, int $basic): void
    {
        DB::connection('epayslip')->table('komponen_gajis')->insert(['data_karyawan_id' => 1, 'status_gaji' => $status,
            'akhir_periode' => $end, 'periode' => substr($end, 0, 7), 'gaji_pokok' => $basic,
            'tunj_pengawas' => 500000, 'tunj_mk' => 300000, 'tunj_koefisien' => 999999]);
    }

    private function workbook(bool $farStyle = false): UploadedFile
    {
        $book = app(SeveranceExport::class)->template(); $sheet = $book->getActiveSheet();
        $sheet->setCellValueExplicit('B8', '00123', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('L8', 'Alasan sintetis', DataType::TYPE_STRING);
        $sheet->setCellValue('M8', 30000); $sheet->setCellValue('R8', '52(1)');
        $sheet->setCellValue('N8', 1); $sheet->setCellValue('O8', 999999); $sheet->setCellValue('Q8', 366);
        if ($farStyle) {
            $sheet->setCellValue('R8', '52 Ayat (1)');
            $sheet->getStyle('T1047553')->getFill()->setFillType('solid')->getStartColor()->setRGB('EEEEEE');
        }
        $path = tempnam(sys_get_temp_dir(), 'severance-'); $this->paths[] = $path;
        (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        return new UploadedFile($path, 'pengajuan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function upload(?UploadedFile $file = null): ImportHistory
    {
        $this->actingAs($this->staff)->postJson(route('severance.store'), ['file' => $file ?: $this->workbook(),
            'title' => 'Batch sintetis', 'termination_date' => '2026-11-15'])->assertAccepted();
        return ImportHistory::where('module', 'severance')->firstOrFail();
    }

    private function runFile(ImportHistory $history): void
    {
        (new ProcessSeveranceFile($history->id))->handle(app(SeveranceService::class), app(SeveranceExport::class));
    }

    private function payrollApproved(): array
    {
        $batch = $this->upload(); $this->runFile($batch);
        $this->actingAs($this->staff)->postJson(route('severance.submit', $batch))->assertOk();
        $entry = SeveranceRequest::firstOrFail(); $source = app(SeveranceSalarySource::class)->resolve($entry);
        $this->actingAs($this->payroll)->postJson(route('severance.payroll', $entry), ['revision' => $entry->revision,
            'source_hash' => $source['hash'], 'reviewed' => 1])->assertOk();
        return [$batch, $entry->fresh()];
    }
}
