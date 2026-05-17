<?php

namespace Tests\Feature;

use App\Jobs\SyncVhireOutbound;
use App\Imports\ImportPkwtOneContracts;
use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\OnboardingCandidate;
use App\Models\User;
use App\Models\VhireSyncLog;
use App\Services\ElectronicContracts\ElectronicContractService;
use App\Services\Vhire\VhireOnboardingContractService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VhireOnboardingContractIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('services.vhire.inbound_token', 'test-vhire-token');
        config()->set('services.vhire.base_url', 'https://vhire.test');
        config()->set('services.vhire.outbound_token', 'outbound-token');
        config()->set('queue.default', 'sync');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedTemplate();
    }

    public function test_vhire_candidate_import_is_idempotent_and_creates_electronic_contract(): void
    {
        Queue::fake();

        $payload = $this->candidatePayload();

        $this->postJson('/api/hris/onboarding-candidates', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.signing_method', EmployeeContract::SIGNING_METHOD_ELECTRONIC)
            ->assertJsonPath('data.visible_in_vhire', true);

        $this->postJson('/api/hris/onboarding-candidates', $payload, $this->headers())
            ->assertOk();

        $this->assertSame(1, OnboardingCandidate::count());
        $this->assertSame(1, EmployeeContract::count());

        $contract = EmployeeContract::first();
        $this->assertSame('VH-2026-000123', $contract->candidate_code);
        $this->assertSame(EmployeeContract::SIGNATURE_STATUS_WAITING, $contract->signature_status);
        $this->assertTrue((bool) $contract->visible_in_vhire);
        $this->assertNull($contract->nik);

        Queue::assertPushed(SyncVhireOutbound::class);
    }

    public function test_vhire_candidate_rejects_invalid_ktp(): void
    {
        $payload = $this->candidatePayload(['no_ktp' => '12345']);

        $this->postJson('/api/hris/onboarding-candidates', $payload, $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('no_ktp');

        $this->assertSame(0, OnboardingCandidate::count());
    }

    public function test_signature_callback_is_idempotent(): void
    {
        Queue::fake();

        $this->postJson('/api/hris/onboarding-candidates', $this->candidatePayload(), $this->headers())
            ->assertOk();

        $contract = EmployeeContract::first();
        $callbackPayload = [
            'hris_contract_id' => $contract->id,
            'kode_kontrak' => $contract->contract_code,
            'no_pkwt' => $contract->pkwt_number,
            'vhire_candidate_id' => $contract->vhire_candidate_id,
            'candidate_code' => $contract->candidate_code,
            'no_ktp' => $contract->no_ktp,
            'signature_status' => EmployeeContract::SIGNATURE_STATUS_SIGNED,
            'status_tanda_tangan' => 'Sudah ditandatangani',
            'signed_at' => '2026-05-16 09:00:00',
            'signed_by_source' => 'vhire',
        ];

        $this->postJson('/api/hris/contracts/' . $contract->id . '/signature-status', $callbackPayload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.signature_status', EmployeeContract::SIGNATURE_STATUS_SIGNED);

        $this->postJson('/api/hris/contracts/' . $contract->id . '/signature-status', $callbackPayload, $this->headers())
            ->assertOk();

        $contract = $contract->fresh();
        $this->assertSame(EmployeeContract::STATUS_SIGNED, $contract->status);
        $this->assertSame('vhire', $contract->signed_by_source);
        $this->assertSame(1, EmployeeContract::count());
    }

    public function test_manual_upload_marks_contract_as_manual_archive(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->postJson('/api/hris/onboarding-candidates', $this->candidatePayload([
            'signing_method' => EmployeeContract::SIGNING_METHOD_MANUAL,
        ]), $this->headers())->assertOk();

        $contract = EmployeeContract::first();
        $file = UploadedFile::fake()->create('signed-contract.pdf', 120, 'application/pdf');

        $updated = app(ElectronicContractService::class)->storeManualSignedContract(
            $contract,
            $file,
            $this->fakeActor(),
            EmployeeContract::MANUAL_VERIFICATION_VERIFIED,
            'Sudah diverifikasi HR.'
        );

        $this->assertSame(EmployeeContract::SIGNING_METHOD_MANUAL, $updated->signing_method);
        $this->assertSame(EmployeeContract::STATUS_SIGNED, $updated->status);
        $this->assertFalse((bool) $updated->visible_in_vhire);
        Storage::disk('local')->assertExists($updated->manual_signed_file_path);
    }

    public function test_activation_links_employee_nik_and_queues_vhire_activation(): void
    {
        Queue::fake();

        $this->postJson('/api/hris/onboarding-candidates', $this->candidatePayload(), $this->headers())
            ->assertOk();

        DB::table('employees')->insert([
            'nik' => 'EMP-0001',
            'nama_karyawan' => 'ARNOL',
            'no_ktp' => '7471072206970101',
            'posisi' => 'SAFETY MEDIS',
        ]);

        $contract = EmployeeContract::first();
        $request = Request::create('/admin/kontrak-elektronik/' . $contract->id . '/activate-vhire-candidate', 'POST');

        app(VhireOnboardingContractService::class)->activateContract($contract, 'EMP-0001', $request);

        $candidate = OnboardingCandidate::first();
        $contract = $contract->fresh();

        $this->assertSame(OnboardingCandidate::STATUS_ACTIVATED, $candidate->onboarding_status);
        $this->assertSame('EMP-0001', $candidate->employee_nik);
        $this->assertSame('EMP-0001', $contract->nik);
        $this->assertFalse((bool) $contract->visible_in_vhire);
        $this->assertTrue(VhireSyncLog::where('operation', VhireSyncLog::OPERATION_ACTIVATION_SYNC)->exists());
        Queue::assertPushed(SyncVhireOutbound::class);
    }

    public function test_excel_pkwt_import_creates_contract_and_sends_to_vhire_by_ktp(): void
    {
        Queue::fake();

        $import = new ImportPkwtOneContracts(
            null,
            EmployeeContract::SIGNING_METHOD_ELECTRONIC,
            'hr-test',
            'HR Test'
        );

        $import->collection(collect([
            collect([
                'no_ktp' => '7471072206970101',
                'nama' => 'ARNOL',
                'kode_kontrak' => '167016',
                'no_pkwt' => '02-167016/VDNI/HRD/PKWT/XII/2025',
                'jenis_kelamin' => 'LAKI-LAKI',
                'status_pernikahan' => 'BELUM MENIKAH',
                'alamat' => 'JL. LALONGGIDA',
                'jabatan' => 'SAFETY MEDIS',
                'lama_kontrak' => 2,
                'tanggal_mulai_kontrak' => '2026-05-16',
                'tanggal_berakhir_kontrak' => '2026-07-16',
                'gaji' => 3073600,
                'uang_makan' => 17500,
            ]),
        ]));

        $candidate = OnboardingCandidate::first();
        $contract = EmployeeContract::first();

        $this->assertNull($candidate->vhire_candidate_id);
        $this->assertSame('EXCEL-PKWT1-167016', $candidate->candidate_code);
        $this->assertSame('7471072206970101', $candidate->no_ktp);
        $this->assertSame('02-167016/VDNI/HRD/PKWT/XII/2025', $contract->pkwt_number);
        $this->assertSame('167016', $contract->contract_code);
        $this->assertTrue((bool) $contract->visible_in_vhire);
        Queue::assertPushed(SyncVhireOutbound::class);
    }

    public function test_excel_pkwt_import_reads_reguler_formula_format(): void
    {
        Queue::fake([SyncVhireOutbound::class]);

        $path = storage_path('framework/testing/pkwt-reguler-format.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('REGULER');
        $sheet->fromArray([
            [
                'NO',
                'NO. KTP',
                'NAMA',
                'KODE KONTRAK',
                'NO PKWT',
                'JENIS KELAMIN',
                'STATUS PERNIKAHAN',
                'ALAMAT',
                'JABATAN',
                "LAMA \nKONTRAK",
                "TANGGAL MULAI\n KONTRAK",
                'TANGGAL BERAKHIR KONTRAK',
                'GAJI',
                'UANG MAKAN',
                'HM',
                'TUNJANGAN JABATAN',
                'KETERANGAN KONTRAK',
            ],
        ], null, 'A1');
        $sheet->setCellValue('A2', '=ROW()-1');
        $sheet->setCellValueExplicit('B2', '7471072206970101', DataType::TYPE_STRING);
        $sheet->setCellValue('C2', 'ARNOL');
        $sheet->setCellValue('D2', 167016);
        $sheet->setCellValue('E2', '="02-"&D2&"/VDNI/HRD/PKWT/"&ROMAN(MONTH(K2))&"/"&YEAR(K2)');
        $sheet->setCellValue('F2', 'LAKI-LAKI');
        $sheet->setCellValue('G2', 'BELUM MENIKAH');
        $sheet->setCellValue('H2', 'JL. LALONGGIDA');
        $sheet->setCellValue('I2', 'SAFETY MEDIS');
        $sheet->setCellValue('J2', 2);
        $sheet->setCellValue('K2', ExcelDate::PHPToExcel(\Carbon\Carbon::create(2025, 12, 23)));
        $sheet->setCellValue('L2', ExcelDate::PHPToExcel(\Carbon\Carbon::create(2026, 2, 23)));
        $sheet->setCellValue('M2', 'Rp 3,073,600');
        $sheet->setCellValue('N2', 'Rp 17,500');

        (new Xlsx($spreadsheet))->save($path);

        try {
            $loadedSheet = IOFactory::load($path)->getSheetByName('REGULER');
            $import = new ImportPkwtOneContracts(
                null,
                EmployeeContract::SIGNING_METHOD_ELECTRONIC,
                'hr-test',
                'HR Test'
            );

            $import->collection(collect([
                collect([
                    'no_ktp' => $loadedSheet->getCell('B2')->getCalculatedValue(),
                    'nama' => $loadedSheet->getCell('C2')->getCalculatedValue(),
                    'kode_kontrak' => $loadedSheet->getCell('D2')->getCalculatedValue(),
                    'no_pkwt' => $loadedSheet->getCell('E2')->getCalculatedValue(),
                    'jenis_kelamin' => $loadedSheet->getCell('F2')->getCalculatedValue(),
                    'status_pernikahan' => $loadedSheet->getCell('G2')->getCalculatedValue(),
                    'alamat' => $loadedSheet->getCell('H2')->getCalculatedValue(),
                    'jabatan' => $loadedSheet->getCell('I2')->getCalculatedValue(),
                    'lama_kontrak' => $loadedSheet->getCell('J2')->getCalculatedValue(),
                    'tanggal_mulai_kontrak' => $loadedSheet->getCell('K2')->getCalculatedValue(),
                    'tanggal_berakhir_kontrak' => $loadedSheet->getCell('L2')->getCalculatedValue(),
                    'gaji' => $loadedSheet->getCell('M2')->getCalculatedValue(),
                    'uang_makan' => $loadedSheet->getCell('N2')->getCalculatedValue(),
                ]),
            ]));
        } finally {
            @unlink($path);
        }

        $candidate = OnboardingCandidate::first();
        $contract = EmployeeContract::first();

        $this->assertSame('7471072206970101', $candidate->no_ktp);
        $this->assertSame('EXCEL-PKWT1-167016', $candidate->candidate_code);
        $this->assertSame('02-167016/VDNI/HRD/PKWT/XII/2025', $contract->pkwt_number);
        $this->assertSame('2025-12-23', substr((string) $candidate->tanggal_mulai_kerja, 0, 10));
        $this->assertSame('2026-02-23', substr((string) $candidate->tanggal_akhir_kontrak, 0, 10));
        $this->assertSame(3073600.0, (float) $candidate->gaji);
        $this->assertSame(17500.0, (float) $candidate->uang_makan);
        Queue::assertPushed(SyncVhireOutbound::class);
    }

    private function candidatePayload(array $overrides = []): array
    {
        return array_merge([
            'vhire_candidate_id' => 'vhire-123',
            'candidate_code' => 'VH-2026-000123',
            'no_ktp' => '7471072206970101',
            'nama' => 'ARNOL',
            'jenis_kelamin' => 'LAKI-LAKI',
            'status_pernikahan' => 'BELUM MENIKAH',
            'alamat' => 'JL. LALONGGIDA',
            'jabatan' => 'SAFETY MEDIS',
            'tanggal_mulai_kerja' => '2026-05-16',
            'tanggal_akhir_kontrak' => '2026-07-16',
            'departemen' => 'HSE',
            'lokasi' => 'Morosi',
            'recruitment_status' => 'accepted',
            'onboarding_status' => 'proses_tanda_tangan_kontrak',
            'contract_duration_value' => 2,
            'contract_duration_unit' => 'bulan',
            'signing_method' => EmployeeContract::SIGNING_METHOD_ELECTRONIC,
            'source_updated_at' => '2026-05-16 08:00:00',
        ], $overrides);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer test-vhire-token',
            'Idempotency-Key' => 'test-idempotency-key',
        ];
    }

    private function fakeActor()
    {
        return new User(['id' => 'hr-test', 'name' => 'HR Test']);
    }

    private function seedTemplate(): void
    {
        DB::table('contract_templates')->insert([
            'id' => 1,
            'contract_type' => ContractTemplate::TYPE_PKWT_1,
            'name' => 'PKWT 1 Test',
            'letterhead_html' => '',
            'body_html' => '<p>{{nama_karyawan}} - {{no_ktp}} - {{durasi_kontrak}}</p>',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik', 100)->primary();
            $table->string('nama_karyawan')->nullable();
            $table->string('no_ktp', 32)->nullable();
            $table->string('posisi')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('alamat_domisili')->nullable();
            $table->string('alamat_ktp')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('contract_type', 40);
            $table->string('name', 150);
            $table->longText('letterhead_html')->nullable();
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('vhire_candidate_id', 120)->nullable()->unique();
            $table->string('candidate_code', 120)->unique();
            $table->string('no_ktp', 32);
            $table->string('nama', 180);
            $table->string('jenis_kelamin', 30)->nullable();
            $table->string('status_pernikahan', 60)->nullable();
            $table->text('alamat')->nullable();
            $table->string('jabatan', 180)->nullable();
            $table->date('tanggal_mulai_kerja')->nullable();
            $table->date('tanggal_akhir_kontrak')->nullable();
            $table->string('departemen', 180)->nullable();
            $table->string('lokasi', 180)->nullable();
            $table->string('kode_kontrak', 120)->nullable();
            $table->string('no_pkwt', 120)->nullable();
            $table->decimal('gaji', 18, 2)->nullable();
            $table->decimal('uang_makan', 18, 2)->nullable();
            $table->string('recruitment_status', 80)->nullable();
            $table->string('onboarding_status', 80)->default('pending');
            $table->unsignedInteger('contract_duration_value')->nullable();
            $table->string('contract_duration_unit', 20)->nullable();
            $table->string('signing_method', 20)->default('electronic');
            $table->string('employee_nik', 100)->nullable();
            $table->timestamp('activated_as_employee_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_payload_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('onboarding_candidate_id')->nullable();
            $table->string('vhire_candidate_id', 120)->nullable();
            $table->string('candidate_code', 120)->nullable();
            $table->string('no_ktp', 32)->nullable();
            $table->string('nik', 100)->nullable();
            $table->string('employee_nik', 100)->nullable();
            $table->string('candidate_name', 180)->nullable();
            $table->unsignedBigInteger('contract_template_id');
            $table->string('contract_type', 40);
            $table->string('status', 30)->default('ready');
            $table->string('contract_number', 120)->nullable();
            $table->string('contract_code', 120)->nullable();
            $table->string('pkwt_number', 120);
            $table->string('gender', 30)->nullable();
            $table->string('marital_status', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('position', 150)->nullable();
            $table->string('departemen', 180)->nullable();
            $table->string('lokasi', 180)->nullable();
            $table->string('contract_duration', 120)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->unsignedInteger('duration_value')->nullable();
            $table->string('duration_unit', 20)->nullable();
            $table->decimal('salary', 18, 2)->default(0);
            $table->decimal('meal_allowance', 18, 2)->default(0);
            $table->longText('rendered_html')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_hash', 128)->nullable();
            $table->string('signing_method', 20)->default('electronic');
            $table->string('signature_status', 30)->default('waiting_signature');
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_source', 40)->nullable();
            $table->boolean('visible_in_vhire')->default(true);
            $table->string('manual_signed_file_path', 500)->nullable();
            $table->string('manual_signed_original_name', 255)->nullable();
            $table->string('manual_signed_mime_type', 120)->nullable();
            $table->unsignedBigInteger('manual_signed_file_size')->nullable();
            $table->string('manual_uploaded_by', 64)->nullable();
            $table->timestamp('manual_uploaded_at')->nullable();
            $table->string('manual_verification_status', 30)->nullable();
            $table->text('manual_note')->nullable();
            $table->timestamp('vhire_contract_synced_at')->nullable();
            $table->timestamp('vhire_activation_synced_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('vhire_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 20);
            $table->string('operation', 80);
            $table->string('method', 12)->default('POST');
            $table->string('endpoint', 500);
            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->longText('request_payload_summary')->nullable();
            $table->longText('response_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('electronic_contract_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_contract_id')->nullable();
            $table->string('nik', 100)->nullable();
            $table->string('event', 80);
            $table->string('actor_user_id', 64)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }
}
