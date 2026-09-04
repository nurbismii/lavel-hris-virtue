<?php

namespace Tests\Feature;

use App\Models\CvMakerProgressHistory;
use App\Models\CvMakerProgressStatus;
use App\Services\CvMaker\CvMakerProgressSnapshotService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerProgressSnapshotStorageTest extends TestCase
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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createProgressSchema();
    }

    public function test_first_snapshot_records_status_and_history(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $result = $service->syncEmployeeProgress(
            'EMP001',
            $this->completeProfile(['updated_at' => '2026-07-01 08:00:00']),
            $this->relatedRowsMissingDiploma(),
            Carbon::parse('2026-07-02 08:00:01')
        );

        $this->assertTrue($result['written']);
        $this->assertSame(1, $result['history_created']);

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP001')->first();

        $this->assertNotNull($status);
        $this->assertSame('Supervisor Produksi', $status->cv_job_title);
        $this->assertSame('Crew Semen  水泥仓泵操作工', $status->cv_position);
        $this->assertSame('CREW SEMEN 水泥仓泵操作工', $status->cv_position_normalized);
        $this->assertSame(8, (int) $status->current_step);
        $this->assertSame('documents', $status->current_step_key);
        $this->assertTrue((bool) $status->needs_reminder);
        $this->assertFalse((bool) $status->is_complete);
        $this->assertSame(['personal', 'summary', 'education', 'experience', 'skills', 'certifications', 'extras'], $status->completed_steps);
        $this->assertSame(['documents'], $status->missing_steps);

        $history = CvMakerProgressHistory::query()->where('employee_nik', 'EMP001')->first();

        $this->assertSame(CvMakerProgressHistory::EVENT_SNAPSHOT_CREATED, $history->event_type);
        $this->assertNull($history->from_step);
        $this->assertSame(8, (int) $history->to_step);
        $this->assertNull($history->from_needs_reminder);
        $this->assertTrue((bool) $history->to_needs_reminder);
    }

    public function test_progress_change_records_history(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $service->syncEmployeeProgress(
            'EMP002',
            $this->completeProfile(['profile_summary' => null, 'updated_at' => '2026-07-02 07:00:00']),
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 08:00:00')
        );

        $service->syncEmployeeProgress(
            'EMP002',
            $this->completeProfile(['updated_at' => '2026-07-02 08:30:00']),
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 09:00:00')
        );

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP002')->first();
        $progressHistory = CvMakerProgressHistory::query()
            ->where('employee_nik', 'EMP002')
            ->where('event_type', CvMakerProgressHistory::EVENT_PROGRESS_CHANGED)
            ->first();

        $this->assertTrue((bool) $status->is_complete);
        $this->assertSame(8, (int) $status->current_step);
        $this->assertNotNull($progressHistory);
        $this->assertSame(2, (int) $progressHistory->from_step);
        $this->assertSame(8, (int) $progressHistory->to_step);
    }

    public function test_reminder_change_records_history(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $profile = $this->completeProfile([
            'profile_summary' => null,
            'updated_at' => '2026-07-01 08:00:00',
        ]);

        $service->syncEmployeeProgress(
            'EMP003',
            $profile,
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 07:59:59')
        );

        $service->syncEmployeeProgress(
            'EMP003',
            $profile,
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 08:00:01')
        );

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP003')->first();
        $reminderHistory = CvMakerProgressHistory::query()
            ->where('employee_nik', 'EMP003')
            ->where('event_type', CvMakerProgressHistory::EVENT_REMINDER_NEEDED)
            ->first();

        $this->assertTrue((bool) $status->needs_reminder);
        $this->assertNotNull($reminderHistory);
        $this->assertFalse((bool) $reminderHistory->from_needs_reminder);
        $this->assertTrue((bool) $reminderHistory->to_needs_reminder);
    }

    public function test_profile_discovery_after_no_profile_snapshot_records_initial_history(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $service->syncEmployeeProgress(
            'EMP005',
            null,
            [],
            Carbon::parse('2026-07-02 08:00:00'),
            false,
            false
        );

        $result = $service->syncEmployeeProgress(
            'EMP005',
            $this->completeProfile(['profile_summary' => null, 'updated_at' => '2026-07-02 09:00:00']),
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 09:05:00')
        );

        $status = CvMakerProgressStatus::query()->where('employee_nik', 'EMP005')->first();
        $history = CvMakerProgressHistory::query()->where('employee_nik', 'EMP005')->first();

        $this->assertSame(1, $result['history_created']);
        $this->assertSame(20, (int) $status->cv_profile_id);
        $this->assertSame(CvMakerProgressHistory::EVENT_SNAPSHOT_CREATED, $history->event_type);
        $this->assertNull($history->from_step);
        $this->assertSame(2, (int) $history->to_step);
        $this->assertStringContainsString('Profil CV Maker ditemukan', $history->message);
    }

    public function test_dry_run_does_not_write_snapshot(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $result = $service->syncEmployeeProgress(
            'EMP004',
            $this->completeProfile(),
            $this->completeRelatedRows(),
            Carbon::parse('2026-07-02 08:00:00'),
            true
        );

        $this->assertFalse($result['written']);
        $this->assertSame(0, CvMakerProgressStatus::query()->count());
        $this->assertSame(0, CvMakerProgressHistory::query()->count());
    }

    private function completeProfile(array $overrides = []): array
    {
        return array_merge([
            'user_id' => 7,
            'profile_id' => 20,
            'status' => 'draft',
            'job_title' => 'Supervisor Produksi',
            'position' => 'Crew Semen  水泥仓泵操作工',
            'full_name' => 'Siti Aminah',
            'birth_date' => '1992-05-15',
            'birth_place' => 'Kendari',
            'gender' => 'P',
            'marital_status' => 'Belum Kawin',
            'address' => 'Morosi',
            'phone' => '081234567891',
            'email' => 'siti@example.test',
            'profile_summary' => 'Administrasi HR yang teliti.',
            'technical_skills' => '["Microsoft Excel","Administrasi"]',
            'updated_at' => '2026-07-01 08:00:00',
        ], $overrides);
    }

    private function completeRelatedRows(): array
    {
        return [
            'educations' => [
                ['level' => 'SMA SEDERAJAT', 'institution' => 'SMAN 1 Kendari', 'major' => 'IPA', 'graduation_year' => 2010, 'updated_at' => '2026-07-01 08:00:00'],
            ],
            'experiences' => [
                [
                    'position' => 'Admin HR',
                    'company' => 'PT VDNI',
                    'department' => 'HR',
                    'division' => 'People Ops',
                    'start_month' => '2020-01-01',
                    'end_month' => null,
                    'is_current' => 1,
                    'responsibilities' => 'Mengelola administrasi karyawan.',
                    'updated_at' => '2026-07-01 08:00:00',
                ],
            ],
            'certifications' => [],
            'languages' => [],
            'projects' => [],
            'organizations' => [],
            'documents' => [
                ['type' => 'ktp', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
                ['type' => 'family_card', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
                ['type' => 'diploma', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
            ],
        ];
    }

    private function relatedRowsMissingDiploma(): array
    {
        $rows = $this->completeRelatedRows();
        $rows['documents'] = [
            ['type' => 'ktp', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
            ['type' => 'family_card', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
        ];

        return $rows;
    }

    private function createProgressSchema(): void
    {
        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32)->unique();
            $table->unsignedBigInteger('cv_user_id')->nullable();
            $table->unsignedBigInteger('cv_profile_id')->nullable();
            $table->string('cv_status', 40)->nullable();
            $table->string('cv_job_title')->nullable();
            $table->string('cv_position')->nullable();
            $table->string('cv_position_normalized')->nullable();
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
}
