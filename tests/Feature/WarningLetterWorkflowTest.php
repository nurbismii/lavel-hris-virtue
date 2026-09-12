<?php

namespace Tests\Feature;

use App\Models\ElectronicContractFirstPartySignature;
use App\Models\Employee;
use App\Models\SuratPeringatan;
use App\Models\User;
use App\Models\WarningLetterRequest;
use App\Services\SuratPeringatan\WarningLetterWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class WarningLetterWorkflowTest extends TestCase
{
    private WarningLetterWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        config()->set('filesystems.default', 'local');
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 9, 11)->setTime(10, 0));
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary(); $table->string('nama_karyawan');
            $table->integer('departemen_id'); $table->integer('divisi_id'); $table->string('posisi');
            $table->timestamps();
        });
        Schema::create('departemens', function (Blueprint $table) { $table->id(); $table->string('departemen'); });
        Schema::create('divisis', function (Blueprint $table) { $table->id(); $table->string('nama_divisi'); $table->integer('departemen_id'); });
        Schema::create('sp_report', function (Blueprint $table) {
            $table->increments('id'); $table->string('nik_karyawan', 32); $table->string('no_sp', 8);
            $table->string('level_sp', 4); $table->date('tgl_mulai'); $table->date('tgl_berakhir');
            $table->text('keterangan'); $table->string('pelapor', 128)->nullable(); $table->timestamps();
        });
        Schema::create('electronic_contract_first_party_signatures', function (Blueprint $table) {
            $table->id(); $table->string('signer_key'); $table->string('signer_name');
            $table->string('signer_position'); $table->string('signature_path'); $table->timestamps();
        });
        (require database_path('migrations/2026_09_11_120000_create_warning_letter_requests.php'))->up();
        DB::table('departemens')->insert([
            ['id' => 1, 'departemen' => 'TRANSPORTASI 储运部'],
            ['id' => 2, 'departemen' => 'PRODUKSI'],
        ]);
        DB::table('divisis')->insert([
            ['id' => 1, 'nama_divisi' => 'ALAT BERAT 车队', 'departemen_id' => 1],
            ['id' => 2, 'nama_divisi' => 'OPERASIONAL', 'departemen_id' => 2],
            ['id' => 3, 'nama_divisi' => 'LOGISTIK', 'departemen_id' => 1],
        ]);
        Employee::create(['nik' => '001234567', 'nama_karyawan' => 'KARYAWAN UJI', 'departemen_id' => 1, 'divisi_id' => 1, 'posisi' => 'DRIVER 自卸车驾驶员']);
        Employee::create(['nik' => '001234568', 'nama_karyawan' => 'KARYAWAN SATU DIVISI', 'departemen_id' => 1, 'divisi_id' => 1, 'posisi' => 'HELPER']);
        Employee::create(['nik' => '001234569', 'nama_karyawan' => 'KARYAWAN SATU DEPARTEMEN', 'departemen_id' => 1, 'divisi_id' => 3, 'posisi' => 'ADMIN LOGISTIK']);
        Employee::create(['nik' => '009999999', 'nama_karyawan' => 'DI LUAR CAKUPAN', 'departemen_id' => 2, 'divisi_id' => 2, 'posisi' => 'OPERATOR']);
        $image = imagecreatetruecolor(220, 55);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 4, 10, 20, 'TEST SIGNATURE', imagecolorallocate($image, 0, 0, 0));
        ob_start(); imagepng($image); $signature = ob_get_clean();
        Storage::put('master-signature.png', $signature);
        ElectronicContractFirstPartySignature::create(['signer_key' => 'first_party', 'signer_name' => 'HR PENGUJIAN', 'signer_position' => 'HR SITE MANAGER', 'signature_path' => 'master-signature.png']);
        $this->service = app(WarningLetterWorkflowService::class);
        $this->withoutMiddleware([
            \App\Http\Middleware\RedirectIfNotAuthorized::class,
            \App\Http\Middleware\CheckMenuAccess::class,
            \App\Http\Middleware\CheckRole::class,
        ]);
    }

    private function actor(bool $hr = false, bool $menu = true, ?string $roleName = null): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => $hr ? 'hr-test' : 'admin-test', 'name' => $hr ? 'HR Uji' : 'Admin Uji', 'authorized_divisi_ids' => json_encode([1])]);
        $role = $roleName ?? ($hr ? 'HR' : 'Admin Divisi');
        $user->shouldReceive('hasRole')->andReturnUsing(static function ($roles) use ($role) { return in_array($role, (array) $roles, true); });
        $user->shouldReceive('hasMenuAccess')->andReturn($menu);
        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['submission_token' => (string) Str::uuid(), 'nik' => '001234567', 'hod_name' => 'HOD UJI 张伟',
            'level_sp' => 'SP2', 'keterangan' => 'Keterangan pengujian. Solar habis pada saat bertugas. 工作记录。',
            'pelapor' => 'PELAPOR UJI', 'tgl_mulai' => '2026-09-11', 'tgl_berakhir' => '2027-03-11'], $overrides);
    }

    public function test_submit_is_pending_and_duplicate_token_does_not_create_two_requests(): void
    {
        $payload = $this->payload(); $actor = $this->actor();
        $first = $this->service->submit($payload, $actor);
        $second = $this->service->submit($payload, $actor);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(WarningLetterRequest::PENDING, $first->status);
        $this->assertNull($first->letter_number);
        $this->assertSame(0, SuratPeringatan::count());
        $this->assertSame('001234567', $first->employee_snapshot['nik']);
    }

    public function test_employee_outside_admin_scope_is_rejected(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service->submit($this->payload(['nik' => '009999999']), $this->actor());
    }

    public function test_admin_cannot_approve_even_if_they_can_view(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        $this->expectException(AuthorizationException::class);
        $this->service->review($letter, ['decision' => 'approve'], $this->actor());
    }

    public function test_approval_uses_highest_legacy_number_and_immutable_snapshots(): void
    {
        foreach (['8400', '9603', '100'] as $number) {
            SuratPeringatan::create(['nik_karyawan' => '001234567', 'no_sp' => $number, 'level_sp' => 'SP1', 'tgl_mulai' => '2025-01-01', 'tgl_berakhir' => '2025-06-01', 'keterangan' => 'Legacy']);
        }
        $letter = $this->service->submit($this->payload(), $this->actor());
        $approved = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $this->assertSame('9604/SP-HRD/IX/2026', $approved->letter_number);
        $this->assertSame('9604', SuratPeringatan::findOrFail($approved->sp_report_id)->no_sp);
        $this->assertSame('hr-test', $approved->reviewed_by);
        $this->assertSame(WarningLetterRequest::APPROVED, $approved->status);
        $path = $approved->letter_snapshot['signature_path'];
        $signature = Storage::get($path);
        Storage::put('master-signature.png', 'changed');
        ElectronicContractFirstPartySignature::query()->update(['signer_name' => 'NEW NAME']);
        Employee::where('nik', '001234567')->update(['nama_karyawan' => 'NEW EMPLOYEE NAME']);
        $this->assertSame($signature, Storage::get($path));
        $this->assertSame('HR PENGUJIAN', $approved->fresh()->letter_snapshot['signer_name']);
        $this->assertSame('KARYAWAN UJI', $approved->fresh()->letter_snapshot['employee']['name']);
    }

    public function test_duplicate_approval_does_not_allocate_or_write_twice(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        try { $this->service->review($letter, ['decision' => 'approve'], $this->actor(true)); $this->fail('Duplicate approval must fail.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('approval', $exception->errors()); }
        $this->assertSame(1, SuratPeringatan::count());
        $this->assertSame(1, (int) DB::table('warning_letter_sequences')->value('last_number'));
        $this->assertCount(1, Storage::allFiles('private/warning-letters/signatures'));
    }

    public function test_rejection_keeps_reason_and_does_not_issue_a_letter(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        $letter = $this->service->review($letter, ['decision' => 'reject', 'reason' => 'Perlu bukti kejadian.'], $this->actor(true));
        $this->assertSame(WarningLetterRequest::REJECTED, $letter->status);
        $this->assertSame('Perlu bukti kejadian.', $letter->rejection_reason);
        $this->assertNull($letter->number_sequence);
        $this->assertSame(0, SuratPeringatan::count());
    }

    public function test_missing_signature_leaves_request_pending_without_number(): void
    {
        Storage::delete('master-signature.png');
        $letter = $this->service->submit($this->payload(), $this->actor());
        try { $this->service->review($letter, ['decision' => 'approve'], $this->actor(true)); $this->fail('Missing signature must fail.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('approval', $exception->errors()); }
        $this->assertSame(WarningLetterRequest::PENDING, $letter->fresh()->status);
        $this->assertSame(0, SuratPeringatan::count());
    }

    public function test_download_before_approval_and_out_of_scope_access_are_blocked(): void
    {
        $letter = $this->service->submit($this->payload(['nik' => '009999999']), $this->actor(true));
        $this->assertFalse(Gate::forUser($this->actor())->allows('view', $letter));
        $this->assertFalse(Gate::forUser($this->actor(true, false))->allows('view', $letter));
        $this->actingAs($this->actor(true))->getJson(route('warning-letter-requests.download', $letter))->assertStatus(409);
        $this->actingAs($this->actor())->getJson(route('warning-letter-requests.download', $letter))->assertForbidden();
    }

    public function test_http_validation_and_issued_legacy_record_protection(): void
    {
        $this->actingAs($this->actor())->postJson(route('warning-letter-requests.store'), $this->payload(['tgl_mulai' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('tgl_mulai');
        $letter = $this->service->submit($this->payload(), $this->actor());
        $this->actingAs($this->actor(true))->postJson(route('warning-letter-requests.review', $letter), ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $letter = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $this->actingAs($this->actor(true))->deleteJson(route('surat-peringatan.destroy', $letter->sp_report_id))->assertStatus(409);
        $this->actingAs($this->actor(true))->putJson(route('surat-peringatan.update', $letter->sp_report_id), ['tgl_mulai' => '2026-09-11', 'tgl_berakhir' => '2027-03-11', 'level_sp' => 'SP1', 'keterangan' => 'Attempt change'])->assertStatus(409);
        $this->assertSame('SP2', SuratPeringatan::findOrFail($letter->sp_report_id)->level_sp);
    }

    public function test_pdf_renders_bilingual_letter_and_cjk_glyphs(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        $letter = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $pdf = $this->service->pdf($letter);
        $bytes = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringContainsString('/Subtype /Image', $bytes, 'Signature must be embedded in the issued PDF.');
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('SpNotoSansSC', 'normal');
        $this->assertNotNull($font);
        $this->assertTrue($pdf->getDomPDF()->getCanvas()->font_supports_char($font, '警'));
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) { mkdir($directory, 0755, true); }
        file_put_contents($directory . '/warning-letter-qa.pdf', $bytes);
    }

    public function test_failed_report_write_rolls_back_number_and_signature(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        DB::unprepared("CREATE TRIGGER fail_sp BEFORE INSERT ON sp_report BEGIN SELECT RAISE(ABORT, 'simulated failure'); END;");
        try { $this->service->review($letter, ['decision' => 'approve'], $this->actor(true)); $this->fail('Write must fail.'); }
        catch (\Illuminate\Database\QueryException $exception) { $this->assertStringContainsString('simulated failure', $exception->getMessage()); }
        $this->assertSame(WarningLetterRequest::PENDING, $letter->fresh()->status);
        $this->assertSame(0, (int) DB::table('warning_letter_sequences')->value('last_number'));
        $this->assertCount(0, Storage::allFiles('private/warning-letters/signatures'));
    }

    public function test_import_cannot_overwrite_or_reuse_an_issued_number(): void
    {
        $letter = $this->service->submit($this->payload(), $this->actor());
        $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $row = ['nik' => '001234567', 'no_sp' => '1', 'level_sp' => 'SP3', 'tgl_mulai' => '2026-09-11', 'tgl_berakhir' => '2027-03-11', 'keterangan' => 'Overwrite attempt', 'pelapor' => 'Importer'];
        (new \App\Imports\ImportSuratPeringatan())->collection(collect([collect($row), collect(array_merge($row, ['nik' => '009999999']))]));
        $this->assertSame(1, SuratPeringatan::count());
        $this->assertSame('SP2', SuratPeringatan::first()->level_sp);
    }

    public function test_long_description_pdf_keeps_multiple_pages(): void
    {
        $letter = $this->service->submit($this->payload(['keterangan' => str_repeat("Keterangan panjang untuk pengujian tata letak surat. 工作记录。\n", 45)]), $this->actor());
        $letter = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $pdf = $this->service->pdf($letter);
        $bytes = $pdf->output();
        $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringContainsString('/Subtype /Image', $bytes);
        file_put_contents(storage_path('framework/testing/warning-letter-long-qa.pdf'), $bytes);
    }

    public function test_new_legacy_import_advances_the_locked_counter(): void
    {
        // SQLite requires an explicit conflict target for the existing MySQL upsert.
        Schema::table('sp_report', function (Blueprint $table) { $table->unique(['nik_karyawan', 'no_sp']); });
        $row = collect(['nik' => '001234567', 'no_sp' => '9700', 'level_sp' => 'SP1',
            'tgl_mulai' => 46000, 'tgl_berakhir' => 46180, 'keterangan' => 'Historical import', 'pelapor' => 'Import']);
        (new \App\Imports\ImportSuratPeringatan())->collection(collect([$row]));
        $this->assertSame(9700, (int) DB::table('warning_letter_sequences')->value('last_number'));
        $letter = $this->service->submit($this->payload(), $this->actor());
        $this->travelTo(now()->setDate(2027, 1, 2));
        $letter = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $this->assertSame('9701/SP-HRD/I/2027', $letter->letter_number);
    }

    public function test_request_views_render_with_pending_and_approved_states(): void
    {
        $directory = storage_path('framework/testing/warning-ui');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory . '/layouts');
        $bootstrap = file_get_contents(public_path('assets/css/bootstrap.min.css'));
        $css = file_get_contents(public_path('assets/css/admin-warning-letters.css'));
        file_put_contents($directory . '/layouts/app.blade.php', '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>' . $bootstrap . $css . 'body{background:#f3f5f7}.page-inner{max-width:1240px;margin:auto;padding:30px 16px}.card{border:0;border-radius:12px}.card-body{padding:24px}</style></head><body>@yield("content")</body></html>');
        app('view')->getFinder()->prependLocation($directory);
        app('view')->share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->actingAs($this->actor(true));
        $signer = $this->service->masterSigner();
        $html = view('admin.surat-peringatan.requests.create', ['token' => (string) Str::uuid(), 'signer' => $signer])->render();
        $this->assertStringContainsString('Nama HOD', $html);
        file_put_contents($directory . '/create.html', $html);
        $letter = $this->service->submit($this->payload(), $this->actor());
        $pending = view('admin.surat-peringatan.requests.show', compact('letter', 'signer'))->render();
        $this->assertStringContainsString('Simpan keputusan', $pending);
        $this->assertStringNotContainsString('data-warning-download', $pending);
        file_put_contents($directory . '/pending.html', $pending);
        $letter = $this->service->review($letter, ['decision' => 'approve'], $this->actor(true));
        $approved = view('admin.surat-peringatan.requests.show', compact('letter', 'signer'))->render();
        $this->assertStringContainsString('data-warning-download', $approved);
        $this->assertStringNotContainsString('Simpan keputusan', $approved);
        file_put_contents($directory . '/approved.html', $approved);
        $letters = WarningLetterRequest::paginate(20);
        $index = view('admin.surat-peringatan.requests.index', compact('letters'))->render();
        $this->assertStringContainsString('1/SP-HRD/IX/2026', $index);
        file_put_contents($directory . '/index.html', $index);
    }

    public function test_validity_is_six_calendar_months_and_clamps_month_end(): void
    {
        foreach (['2026-09-11' => '2027-03-11', '2026-08-31' => '2027-02-28', '2027-08-31' => '2028-02-29', '2026-03-31' => '2026-09-30'] as $start => $expected) {
            $letter = $this->service->submit($this->payload(['tgl_mulai' => $start, 'tgl_berakhir' => '2099-12-31']), $this->actor());
            $this->assertSame($expected, $letter->tgl_berakhir->format('Y-m-d'));
        }
    }

    public function test_http_submit_calculates_end_date_without_trusting_client(): void
    {
        $data = $this->payload(['tgl_mulai' => '2027-08-31', 'tgl_berakhir' => '2099-12-31']);
        $this->actingAs($this->actor())->post(route('warning-letter-requests.store'), $data)->assertRedirect();
        $letter = WarningLetterRequest::where('submission_token', $data['submission_token'])->firstOrFail();
        $this->assertSame('2028-02-29', $letter->tgl_berakhir->format('Y-m-d'));

        $data = $this->payload(['tgl_mulai' => '2026-09-11']);
        unset($data['tgl_berakhir']);
        $this->actingAs($this->actor())->post(route('warning-letter-requests.store'), $data)->assertRedirect();
        $this->assertSame('2027-03-11', WarningLetterRequest::where('submission_token', $data['submission_token'])->firstOrFail()->tgl_berakhir->format('Y-m-d'));
    }

    public function test_super_admin_with_empty_account_name_can_submit_and_review_using_employee_name(): void
    {
        $actor = $this->actor(true, true, 'Super Admin');
        $actor->name = null;
        $actor->nik_karyawan = '001234567';
        $data = $this->payload();
        $this->actingAs($actor)->post(route('warning-letter-requests.store'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $letter = WarningLetterRequest::where('submission_token', $data['submission_token'])->firstOrFail();
        $this->assertSame('KARYAWAN UJI', $letter->created_by_name);
        $this->assertSame((string) $actor->id, $letter->created_by);
        $letter = $this->service->review($letter, ['decision' => 'approve'], $actor);
        $this->assertSame('KARYAWAN UJI', $letter->reviewed_by_name);
    }

    public function test_missing_account_and_employee_names_use_explicit_account_identifier(): void
    {
        $actor = $this->actor(true);
        $actor->name = '   ';
        $actor->nik_karyawan = null;
        $letter = $this->service->submit($this->payload(), $actor);
        $this->assertSame('Akun hr-test', $letter->created_by_name);
        $letter = $this->service->review($letter, ['decision' => 'reject', 'reason' => 'Data belum lengkap.'], $actor);
        $this->assertSame('Akun hr-test', $letter->reviewed_by_name);
    }

    public function test_employee_without_login_account_can_be_selected_submitted_and_approved(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('nik_karyawan')->nullable();
        });
        DB::table('users')->insert([
            ['id' => 'admin-test', 'name' => 'Admin Uji', 'nik_karyawan' => null],
            ['id' => 'hr-test', 'name' => 'HR Uji', 'nik_karyawan' => null],
        ]);
        $data = $this->payload();
        $this->assertFalse(User::where('nik_karyawan', $data['nik'])->exists());
        $this->actingAs($this->actor())->getJson(route('warning-letter-requests.employee', ['nik' => $data['nik']]))
            ->assertOk()->assertJsonPath('data.nik', $data['nik'])->assertJsonPath('data.name', 'KARYAWAN UJI');
        $this->post(route('warning-letter-requests.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $letter = WarningLetterRequest::where('submission_token', $data['submission_token'])->firstOrFail();
        $this->assertSame(WarningLetterRequest::PENDING, $letter->status);
        $this->actingAs($this->actor(true))->post(route('warning-letter-requests.review', $letter), ['decision' => 'approve'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(WarningLetterRequest::APPROVED, $letter->fresh()->status);
        $this->assertSame($data['nik'], SuratPeringatan::findOrFail($letter->fresh()->sp_report_id)->nik_karyawan);
        $this->assertFalse(User::where('nik_karyawan', $data['nik'])->exists());
        $this->assertSame(2, User::count());
    }

    public function test_employee_search_is_limited_to_admin_division_and_does_not_require_employee_accounts(): void
    {
        $response = $this->actingAs($this->actor())
            ->getJson(route('warning-letter-requests.employees', ['q' => 'KARYAWAN']));

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(
            ['001234567', '001234568'],
            collect($response->json('data'))->pluck('nik')->sort()->values()->all()
        );
    }

    public function test_hod_can_search_all_employees_in_their_department(): void
    {
        $actor = $this->actor(false, true, 'HOD');
        $actor->authorized_departemen_ids = [1];
        $actor->authorized_divisi_ids = [];
        $actor->setRelation('employee', null);

        $response = $this->actingAs($actor)
            ->getJson(route('warning-letter-requests.employees', ['q' => 'KARYAWAN']));

        $response->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(
            ['001234567', '001234568', '001234569'],
            collect($response->json('data'))->pluck('nik')->sort()->values()->all()
        );
    }

    public function test_super_admin_searches_all_employees_and_wildcards_do_not_return_everything(): void
    {
        $actor = $this->actor(false, true, 'Super Admin');
        $this->actingAs($actor)
            ->getJson(route('warning-letter-requests.employees', ['q' => '00']))
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($actor)
            ->getJson(route('warning-letter-requests.employees', ['q' => '%_']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
