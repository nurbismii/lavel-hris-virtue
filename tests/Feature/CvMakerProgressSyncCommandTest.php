<?php

namespace Tests\Feature;

use App\Models\CvMakerProgressStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerProgressSyncCommandTest extends TestCase
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
        config()->set('database.connections.cv_maker', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('services.cv_maker.connection', 'cv_maker');
        config()->set('services.cv_maker.nik_hash_key', 'test-key');

        DB::purge('sqlite');
        DB::purge('cv_maker');
        DB::reconnect('sqlite');
        DB::reconnect('cv_maker');

        $this->createHrisSchema();
        $this->createCvMakerSchema();
    }

    public function test_command_syncs_active_employee_progress_from_cv_maker(): void
    {
        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Employee Test',
            'status_resign' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedCvMakerDraft('EMP001');

        $exitCode = Artisan::call('cv-maker:sync-progress', [
            '--limit' => 10,
            '--chunk' => 5,
            '--now' => '2026-07-02 08:00:01',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('CV Maker progress sync: checked=1, synced=1', Artisan::output());

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP001')->first();

        $this->assertNotNull($status);
        $this->assertSame(8, (int) $status->current_step);
        $this->assertSame('documents', $status->current_step_key);
        $this->assertTrue((bool) $status->needs_reminder);
        $this->assertFalse((bool) $status->is_complete);
    }

    public function test_dry_run_does_not_write_status_rows(): void
    {
        DB::table('employees')->insert([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Employee Test 2',
            'status_resign' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedCvMakerDraft('EMP002');

        $exitCode = Artisan::call('cv-maker:sync-progress', [
            '--limit' => 10,
            '--chunk' => 5,
            '--now' => '2026-07-02 08:00:01',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, CvMakerProgressStatus::query()->count());
        $this->assertStringContainsString('dry-run', Artisan::output());
    }

    public function test_command_marks_employee_without_cv_profile_as_checked_without_history_noise(): void
    {
        DB::table('employees')->insert([
            'nik' => 'EMP999',
            'nama_karyawan' => 'Employee Without CV',
            'status_resign' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('cv-maker:sync-progress', [
            '--limit' => 10,
            '--chunk' => 5,
            '--now' => '2026-07-02 08:00:01',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('checked=1, synced=0, skipped_no_profile=1', Artisan::output());

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP999')->first();

        $this->assertNotNull($status);
        $this->assertNull($status->cv_profile_id);
        $this->assertFalse((bool) $status->needs_reminder);
        $this->assertFalse((bool) $status->is_complete);
        $this->assertSame(0, DB::table('cv_maker_progress_histories')->count());
    }

    private function seedCvMakerDraft(string $nik): void
    {
        $connection = DB::connection('cv_maker');
        $hash = hash_hmac('sha256', $nik, 'test-key');

        $connection->table('users')->insert([
            'id' => 100,
            'name' => 'Employee Test',
            'email' => strtolower($nik) . '@example.test',
            'vpeople_nik_hash' => $hash,
            'created_at' => '2026-07-01 08:00:00',
            'updated_at' => '2026-07-01 08:00:00',
        ]);

        $connection->table('cv_profiles')->insert([
            'id' => 200,
            'user_id' => 100,
            'status' => 'draft',
            'full_name' => 'Employee Test',
            'birth_date' => '1992-05-15',
            'birth_place' => 'Kendari',
            'gender' => 'P',
            'marital_status' => 'Belum Kawin',
            'address' => 'Morosi',
            'phone' => '081234567891',
            'email' => strtolower($nik) . '@example.test',
            'profile_summary' => 'Administrasi HR yang teliti.',
            'technical_skills' => '["Microsoft Excel"]',
            'updated_at' => '2026-07-01 08:00:00',
            'created_at' => '2026-07-01 08:00:00',
        ]);

        $connection->table('cv_educations')->insert([
            'cv_profile_id' => 200,
            'level' => 'SMA SEDERAJAT',
            'institution' => 'SMAN 1 Kendari',
            'major' => 'IPA',
            'graduation_year' => 2010,
            'updated_at' => '2026-07-01 08:00:00',
            'created_at' => '2026-07-01 08:00:00',
        ]);

        $connection->table('cv_experiences')->insert([
            'cv_profile_id' => 200,
            'position' => 'Admin HR',
            'company' => 'PT VDNI',
            'department' => 'HR',
            'division' => 'People Ops',
            'start_month' => '2020-01-01',
            'end_month' => null,
            'is_current' => 1,
            'responsibilities' => 'Mengelola administrasi karyawan.',
            'updated_at' => '2026-07-01 08:00:00',
            'created_at' => '2026-07-01 08:00:00',
        ]);

        $connection->table('cv_documents')->insert([
            ['cv_profile_id' => 200, 'type' => 'ktp', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00', 'created_at' => '2026-07-01 08:00:00'],
            ['cv_profile_id' => 200, 'type' => 'family_card', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00', 'created_at' => '2026-07-01 08:00:00'],
        ]);
    }

    private function createHrisSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('status_resign')->nullable();
            $table->timestamps();
        });

        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32)->unique();
            $table->unsignedBigInteger('cv_user_id')->nullable();
            $table->unsignedBigInteger('cv_profile_id')->nullable();
            $table->string('cv_status', 40)->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('current_step_key', 40)->nullable();
            $table->string('current_step_label', 80)->nullable();
            $table->unsignedTinyInteger('completed_step_count')->default(0);
            $table->unsignedTinyInteger('total_step_count')->default(8);
            $table->boolean('is_complete')->default(false);
            $table->boolean('needs_reminder')->default(false);
            $table->string('reminder_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('completed_steps')->nullable();
            $table->json('missing_steps')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cv_maker_progress_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cv_maker_progress_status_id')->nullable();
            $table->string('employee_nik', 32);
            $table->string('event_type', 40);
            $table->unsignedTinyInteger('from_step')->nullable();
            $table->unsignedTinyInteger('to_step')->nullable();
            $table->boolean('from_needs_reminder')->nullable();
            $table->boolean('to_needs_reminder')->nullable();
            $table->string('cv_status', 40)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function createCvMakerSchema(): void
    {
        $schema = Schema::connection('cv_maker');

        $schema->create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('vpeople_nik_hash')->nullable();
            $table->timestamps();
        });

        $schema->create('cv_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->nullable();
            $table->string('full_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('profile_summary', 300)->nullable();
            $table->text('technical_skills')->nullable();
            $table->timestamps();
        });

        $schema->create('cv_educations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cv_profile_id');
            $table->string('level')->nullable();
            $table->string('institution')->nullable();
            $table->string('major')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->timestamps();
        });

        $schema->create('cv_experiences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cv_profile_id');
            $table->string('position')->nullable();
            $table->string('company')->nullable();
            $table->string('department')->nullable();
            $table->string('division')->nullable();
            $table->date('start_month')->nullable();
            $table->date('end_month')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('responsibilities')->nullable();
            $table->timestamps();
        });

        foreach (['cv_certifications', 'cv_languages', 'cv_projects', 'cv_organizations', 'cv_emergency_contacts'] as $tableName) {
            $schema->create($tableName, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('cv_profile_id');
                $table->string('name')->nullable();
                $table->string('issuer')->nullable();
                $table->integer('year')->nullable();
                $table->string('language')->nullable();
                $table->string('level')->nullable();
                $table->string('organization_name')->nullable();
                $table->string('role')->nullable();
                $table->integer('start_year')->nullable();
                $table->integer('end_year')->nullable();
                $table->string('phone')->nullable();
                $table->string('relationship')->nullable();
                $table->timestamps();
            });
        }

        $schema->create('cv_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cv_profile_id');
            $table->string('type');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }
}
