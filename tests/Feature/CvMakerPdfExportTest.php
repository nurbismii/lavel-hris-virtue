<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CvMakerPdfController;
use App\Http\Requests\CvMaker\StorePdfBatchRequest;
use App\Jobs\ProcessCvMakerPdfBatch;
use App\Models\CvMakerPdfBatch;
use App\Models\CvMakerProgressStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\CvMaker\CvMakerCompareService;
use App\Services\CvMaker\CvMakerPdfExportService;
use App\Services\CvMaker\CvMakerPdfRenderer;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CvMakerPdfExportTest extends TestCase
{
    private $actor;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'cv_pdf.connection' => 'database', 'cv_pdf.batch_limit' => 50]);
        DB::purge('sqlite');
        Cache::flush();
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        $storage = \Mockery::mock(SensitiveFileStorageService::class)->makePartial();
        $storage->shouldReceive('ensurePrivateDirectory')->andReturnUsing(function ($path) {
            Storage::disk('local')->makeDirectory('private/' . $path);
            return Storage::disk('local')->path('private/' . $path);
        });
        $this->app->instance(SensitiveFileStorageService::class, $storage);
        Schema::create('employees', function (Blueprint $t) {
            $t->string('nik')->primary(); $t->string('nama_karyawan'); $t->string('area_kerja');
            $t->string('status_resign'); $t->string('posisi')->nullable(); $t->string('jabatan')->nullable();
            $t->string('no_telp')->nullable(); $t->integer('departemen_id')->nullable(); $t->integer('divisi_id')->nullable();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->string('id', 36)->primary(); $t->integer('role_id'); $t->string('name');
            $t->string('nik_karyawan')->nullable(); $t->json('authorized_divisi_ids')->nullable();
            $t->json('authorized_departemen_ids')->nullable(); $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->increments('id'); $t->string('permission_role'); $t->json('menu_permissions')->nullable();
        });
        DB::table('roles')->insert([
            ['id' => 1, 'permission_role' => 'Super Admin', 'menu_permissions' => '["cv_maker_compare"]'],
            ['id' => 2, 'permission_role' => 'Audit CV', 'menu_permissions' => '["cv_maker_compare"]'],
            ['id' => 3, 'permission_role' => 'Admin Divisi', 'menu_permissions' => '["cv_maker_compare"]'],
        ]);
        Schema::create('cv_maker_progress_statuses', function (Blueprint $t) {
            $t->increments('id'); $t->string('employee_nik')->unique();
            $t->integer('cv_profile_id')->nullable(); $t->boolean('is_complete')->default(false); $t->timestamps();
        });
        (require base_path('database/migrations/2026_09_09_000001_create_cv_maker_pdf_export_tables.php'))->up();
        $this->actor = $this->actor();
        $this->service = app(CvMakerPdfExportService::class);
    }

    private function actor(int $role = 1): User
    {
        $id = (string) Str::uuid();
        DB::table('users')->insert(['id' => $id, 'role_id' => $role, 'name' => 'Test HR',
            'authorized_divisi_ids' => '[1]', 'authorized_departemen_ids' => '[]']);
        return User::findOrFail($id);
    }

    private function employee(string $nik, bool $complete = true, string $area = 'VDNI', int $division = 1): void
    {
        DB::table('employees')->insert(['nik' => $nik, 'nama_karyawan' => 'Karyawan ' . $nik,
            'area_kerja' => $area, 'status_resign' => 'AKTIF', 'departemen_id' => 1, 'divisi_id' => $division]);
        CvMakerProgressStatus::create(['employee_nik' => $nik, 'cv_profile_id' => 1, 'is_complete' => $complete]);
    }

    private function input(array $overrides = []): array
    {
        return array_merge(['idempotency_key' => (string) Str::uuid(), 'mode' => 'filtered',
            'status_resign' => 'AKTIF', 'allow_downloaded' => false], $overrides);
    }

    private function invalid(callable $call): void
    {
        try { $call(); $this->fail('Expected validation rejection.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    private function forbidden(callable $call, int $status = 403): void
    {
        try { $call(); $this->fail('Expected denied access.'); }
        catch (HttpException $e) { $this->assertSame($status, $e->getStatusCode()); }
    }

    private function fakeRenderer(?string $failureNik = null): void
    {
        $renderer = \Mockery::mock(CvMakerPdfRenderer::class);
        $renderer->shouldReceive('render')->andReturnUsing(function ($employee, $uuid, $id) use ($failureNik) {
            if ($employee->nik === $failureNik) throw new \RuntimeException('Test provider failure');
            $path = 'cv-pdf/' . $uuid . '/' . $id . '.pdf';
            Storage::disk('local')->put('private/' . $path, '%PDF-1.4 test fixture');
            return $path;
        });
        $this->app->instance(CvMakerPdfRenderer::class, $renderer);
    }

    private function finish(CvMakerPdfBatch $batch): void
    {
        for ($i = 0; $i <= $batch->total_count; $i++) $this->service->processNext($batch->id);
        $batch->refresh();
    }

    public function test_filtered_batch_preserves_filters_scope_and_uuid_idempotency(): void
    {
        $this->employee('0001'); $this->employee('0002', false);
        $this->employee('0003', true, 'VDNIP'); $this->employee('0004', true, 'VDNI', 2);
        $actor = $this->actor(3);
        $input = $this->input(['area' => ['VDNI'], 'search' => 'Karyawan']);
        $batch = $this->service->create($input, $actor);
        $again = $this->service->create($input, $actor);
        $this->assertSame(['0001'], $batch->items()->pluck('employee_nik')->all());
        $this->assertSame($batch->id, $again->id);
        $this->assertSame($actor->id, $batch->requested_by);
        Queue::assertPushed(ProcessCvMakerPdfBatch::class, 1);
        Queue::assertPushed(ProcessCvMakerPdfBatch::class, fn($job) => $job->connection === 'database' && $job->queue === 'cv-pdf');
    }

    public function test_claim_blocks_other_admin_even_after_pdf_is_ready(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(), $this->actor);
        $this->invalid(fn() => $this->service->create($this->input(['allow_downloaded' => true]), $this->actor()));
        $this->finish($batch);
        $this->assertSame('completed', $batch->status);
        $this->invalid(fn() => $this->service->create($this->input(), $this->actor()));
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('downloaded_at'));
    }

    public function test_single_pdf_requires_complete_scoped_employee_and_explicit_redownload(): void
    {
        $this->employee('0001', false); $this->employee('0002', true, 'VDNI', 2);
        $this->invalid(fn() => $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001']), $this->actor));
        $this->invalid(fn() => $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0002']), $this->actor(3)));
        CvMakerProgressStatus::where('employee_nik', '0001')->update(['is_complete' => true]);
        DB::table('cv_maker_pdf_downloads')->insert(['employee_nik' => '0001', 'downloaded_at' => now()]);
        $this->invalid(fn() => $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001']), $this->actor));
        $batch = $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001', 'allow_downloaded' => true]), $this->actor);
        $this->assertSame(1, $batch->total_count);
    }

    public function test_download_records_actor_only_when_served_and_can_reuse_same_file(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001']), $this->actor);
        $this->forbidden(fn() => $this->service->download($batch, $this->actor), 409);
        $this->finish($batch);
        $response = $this->service->download($batch, $this->actor);
        $this->assertStringEndsWith('.pdf', $response->getFile()->getPathname());
        $ledger = DB::table('cv_maker_pdf_downloads')->first();
        $this->assertSame($this->actor->id, $ledger->downloaded_by);
        $this->assertNotNull($ledger->downloaded_at); $this->assertNull($ledger->active_batch_id);
        $this->service->download($batch, $this->actor);
        $this->assertSame(1, CvMakerPdfBatch::count());
        $this->invalid(fn() => $this->service->create($this->input(), $this->actor));
    }

    public function test_partial_failure_archives_successes_only_and_releases_failed_employee(): void
    {
        $this->employee('0001'); $this->employee('0002'); $this->fakeRenderer('0002');
        $batch = $this->service->create($this->input(), $this->actor);
        $this->service->processNext($batch->id);
        $this->assertSame(1, $batch->items()->where('status', 'completed')->count());
        $this->assertSame(1, $batch->items()->where('status', 'pending')->count());
        $this->finish($batch);
        $this->assertSame('partial_failed', $batch->status);
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('local')->path('private/' . $batch->result_path));
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString('0001', $zip->getNameIndex(0));
        $zip->close();
        $this->service->download($batch, $this->actor);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->where('employee_nik', '0002')->value('downloaded_at'));
        $retry = $this->service->create($this->input(), $this->actor);
        $this->assertSame(['0002'], $retry->items()->pluck('employee_nik')->all());
    }

    public function test_pdf_status_filters_distinguish_untouched_reserved_and_downloaded(): void
    {
        foreach (['0001', '0002', '0003', '0004'] as $nik) $this->employee($nik);
        DB::table('cv_maker_pdf_downloads')->insert([
            ['employee_nik' => '0002', 'active_batch_id' => 1, 'downloaded_at' => null],
            ['employee_nik' => '0003', 'active_batch_id' => null, 'downloaded_at' => now()],
            ['employee_nik' => '0004', 'active_batch_id' => 2, 'downloaded_at' => now()],
        ]);
        foreach (['not_downloaded' => ['0001'], 'processing' => ['0002', '0004'], 'downloaded' => ['0003']] as $filter => $expected) {
            $actual = app(CvMakerCompareService::class)->filteredEmployeeQuery(new Request(['pdf_status' => $filter]), $this->actor)
                ->select('employees.nik')->orderBy('employees.nik')->toBase()->pluck('employees.nik')->all();
            $this->assertSame($expected, $actual);
        }
    }

    public function test_download_rechecks_owner_scope_completeness_expiry_and_file_existence(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(), $this->actor); $this->finish($batch);
        $this->forbidden(fn() => $this->service->download($batch, $this->actor()));
        CvMakerProgressStatus::query()->update(['is_complete' => false]);
        $this->forbidden(fn() => $this->service->download($batch, $this->actor));
        CvMakerProgressStatus::query()->update(['is_complete' => true]);
        $batch->update(['expires_at' => now()->subMinute()]);
        $this->forbidden(fn() => $this->service->download($batch, $this->actor), 410);
        $batch->update(['expires_at' => now()->addDay()]);
        Storage::disk('local')->delete('private/' . $batch->result_path);
        $this->forbidden(fn() => $this->service->download($batch, $this->actor), 410);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('downloaded_at'));
    }

    public function test_cancel_releases_reservations_and_maintenance_only_purges_own_files(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(), $this->actor); $this->finish($batch);
        $request = new Request(); $request->setUserResolver(fn() => $this->actor);
        (new CvMakerPdfController())->cancel($request, $batch, $this->service);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('active_batch_id'));
        Storage::disk('local')->put('private/unrelated/keep.txt', 'keep');
        $this->artisan('cv-maker:maintain-pdf-exports')->assertExitCode(0);
        Storage::disk('local')->assertMissing('private/' . $batch->result_path);
        Storage::disk('local')->assertExists('private/unrelated/keep.txt');
        $this->assertSame('expired', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->files_purged_at);
    }

    public function test_worker_rechecks_permission_and_recovers_interrupted_items_with_bounded_attempts(): void
    {
        $this->employee('0001');
        $batch = $this->service->create($this->input(), $this->actor);
        $batch->items()->update(['status' => 'processing', 'attempts' => 3]);
        $this->finish($batch);
        $this->assertSame('failed', $batch->status);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('active_batch_id'));
        $next = $this->service->create($this->input(), $this->actor);
        DB::table('roles')->where('id', 2)->update(['menu_permissions' => '[]']);
        DB::table('users')->where('id', $this->actor->id)->update(['role_id' => 2]);
        $this->service->processNext($next->id);
        $this->assertSame('failed', $next->fresh()->status);
    }

    public function test_large_filters_take_next_bounded_batch_and_sync_queue_is_rejected(): void
    {
        $this->employee('0001'); $this->employee('0002');
        config(['cv_pdf.batch_limit' => 1]);
        $first = $this->service->create($this->input(), $this->actor);
        $second = $this->service->create($this->input(), $this->actor);
        $this->assertSame(['0001'], $first->items()->pluck('employee_nik')->all());
        $this->assertSame(['0002'], $second->items()->pluck('employee_nik')->all());
        config(['cv_pdf.connection' => 'sync']);
        $this->invalid(fn() => $this->service->create($this->input(), $this->actor));
        $this->assertSame(2, CvMakerPdfBatch::count());
        $this->assertSame(2, DB::table('cv_maker_pdf_downloads')->count());
    }

    public function test_stale_dispatch_is_recovered_and_repeated_jobs_do_not_duplicate_items(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(), $this->actor);
        $batch->update(['updated_at' => now()->subMinutes(6)]);
        $this->artisan('cv-maker:maintain-pdf-exports')->assertExitCode(0);
        Queue::assertPushed(ProcessCvMakerPdfBatch::class, 2);
        $this->finish($batch);
        $this->service->processNext($batch->id);
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame(1, (int) $batch->items()->first()->attempts);
    }

    public function test_pdf_routes_and_request_protect_sensitive_exports(): void
    {
        foreach (['index', 'store', 'status', 'download', 'cancel'] as $suffix) {
            $route = app('router')->getRoutes()->getByName('cv-maker-compare.pdf.' . $suffix);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('menu:cv_maker_compare', $route->gatherMiddleware());
            $this->assertContains('role:Super Admin,HR,HOD,Manager,Supervisor,Admin Divisi,Audit CV', $route->gatherMiddleware());
        }
        $this->assertTrue(CvMakerPdfExportService::canAccess($this->actor(2)));
        $rules = (new StorePdfBatchRequest())->rules();
        $this->assertTrue(Validator::make($this->input(), $rules)->passes());
        $this->assertFalse(Validator::make($this->input(['mode' => 'single']), $rules)->passes());
        $this->assertFalse(Validator::make($this->input(['allow_downloaded' => 'yes']), $rules)->passes());
    }

    public function test_audit_cv_can_download_own_pdf_but_not_another_users_batch(): void
    {
        $audit = $this->actor(2);
        $this->employee('0001');
        $this->fakeRenderer();
        $batch = $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001']), $audit);
        $this->finish($batch);

        $this->forbidden(fn() => $this->service->download($batch, $this->actor(2)));
        $response = $this->service->download($batch, $audit);
        $this->assertStringEndsWith('.pdf', $response->getFile()->getPathname());
        $this->assertSame($audit->id, DB::table('cv_maker_pdf_downloads')->value('downloaded_by'));
    }

    public function test_audit_cv_without_menu_access_cannot_create_pdf_batch(): void
    {
        DB::table('roles')->where('id', 2)->update(['menu_permissions' => '[]']);
        $audit = $this->actor(2);
        $this->assertFalse(CvMakerPdfExportService::canAccess($audit));
        $this->forbidden(fn() => $this->service->create($this->input(), $audit));
    }

    public function test_repeated_packaging_timeouts_eventually_fail_and_release_claims(): void
    {
        $this->employee('0001'); $this->fakeRenderer();
        $batch = $this->service->create($this->input(), $this->actor);
        $this->service->processNext($batch->id);
        $batch->update(['packaging_attempts' => 3]);
        $this->service->processNext($batch->id);
        $this->assertSame('failed', $batch->fresh()->status);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('active_batch_id'));
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('downloaded_at'));
    }

    public function test_real_renderer_uses_fresh_cv_data_and_rejects_incomplete_or_unavailable_provider(): void
    {
        $this->employee('0001');
        config(['services.cv_maker.transport' => 'api', 'services.cv_maker.api_base_url' => 'https://cv.test',
            'services.cv_maker.api_token' => 'test-only', 'services.cv_maker.nik_hash_key' => 'test-only']);
        $profile = $this->profile();
        $upstreamStatus = 200;
        Http::fake(function () use (&$profile, &$upstreamStatus) {
            return Http::response(['success' => true, 'data' => ['profiles' => [$profile]]], $upstreamStatus);
        });
        $batch = $this->service->create($this->input(['mode' => 'single', 'employee_nik' => '0001']), $this->actor);
        $this->finish($batch);
        $this->assertSame('completed', $batch->status);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get('private/' . $batch->result_path));
        $this->service->close($batch, 'cancelled', 'Test re-export');
        $profile['related']['documents'] = [];
        $next = $this->service->create($this->input(), $this->actor); $this->finish($next);
        $this->assertSame('failed', $next->status);
        $upstreamStatus = 503;
        $last = $this->service->create($this->input(), $this->actor); $this->finish($last);
        $this->assertSame('failed', $last->status);
        $this->assertNull(DB::table('cv_maker_pdf_downloads')->value('downloaded_at'));
    }

    private function profile(): array
    {
        return [
            'vpeople_nik_hash' => app(CvMakerCompareService::class)->hashNik('0001'),
            'profile_id' => 1, 'full_name' => 'Karyawan Uji', 'birth_place' => 'Kendari', 'birth_date' => '1990-01-01',
            'gender' => 'L', 'marital_status' => 'Kawin', 'address' => 'Morosi', 'phone' => '0800000000',
            'email' => 'test@example.test', 'profile_summary' => 'Operator produksi berpengalaman.',
            'technical_skills' => ['Operasional'], 'position' => 'Operator',
            'related' => [
                'educations' => [['level' => 'SMA', 'institution' => 'Sekolah Uji', 'major' => 'IPA', 'graduation_year' => 2008]],
                'experiences' => [['position' => 'Operator', 'company' => 'Perusahaan Uji', 'department' => 'Produksi',
                    'division' => 'Operasional', 'start_month' => '2020-01-01', 'is_current' => true, 'responsibilities' => 'Mengoperasikan alat.']],
                'documents' => [['id' => 1, 'type' => 'ktp'], ['id' => 2, 'type' => 'family_card'], ['id' => 3, 'type' => 'diploma']],
                'certifications' => [], 'languages' => [], 'projects' => [], 'organizations' => [], 'emergency_contacts' => [], 'achievements' => [],
            ],
        ];
    }
}
