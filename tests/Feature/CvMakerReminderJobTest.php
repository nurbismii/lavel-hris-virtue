<?php

namespace Tests\Feature;

use App\Jobs\SendCvMakerReminderEmail;
use App\Models\CvMakerProgressStatus;
use App\Models\CvMakerReminderBatch;
use App\Models\CvMakerReminderDelivery;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\CvMakerProgressReminderNotification;
use App\Services\CvMaker\CvMakerCompareService;
use App\Services\CvMaker\CvMakerReminderService;
use App\Services\CvMaker\CvMakerReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class CvMakerReminderJobTest extends TestCase
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
        $this->createSchema();
    }

    public function test_job_sends_reminder_and_completes_batch(): void
    {
        Notification::fake();
        $user = User::create([
            'id' => 'user-1',
            'name' => 'Budi',
            'email' => 'budi@example.test',
            'nik_karyawan' => 'EMP001',
            'password' => 'secret',
        ]);
        CvMakerProgressStatus::create([
            'employee_nik' => 'EMP001',
            'cv_profile_id' => 10,
            'current_step' => 4,
            'current_step_label' => 'Pengalaman',
            'needs_reminder' => true,
        ]);
        $batch = CvMakerReminderBatch::create($this->batchPayload());
        $delivery = CvMakerReminderDelivery::create([
            'batch_id' => $batch->id,
            'employee_nik' => 'EMP001',
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => CvMakerReminderDelivery::STATUS_PENDING,
        ]);
        $service = new CvMakerReminderService(new CvMakerCompareService());

        (new SendCvMakerReminderEmail($delivery->id))->handle($service);

        $this->assertSame(CvMakerReminderDelivery::STATUS_SENT, $delivery->fresh()->status);
        $this->assertSame(CvMakerReminderBatch::STATUS_COMPLETED, $batch->fresh()->status);
        $this->assertSame(1, $batch->fresh()->sent_count);
        Notification::assertSentTo($user, CvMakerProgressReminderNotification::class);
    }

    public function test_batch_creation_queues_only_valid_recipients_and_is_idempotent(): void
    {
        Queue::fake();
        $actor = User::create([
            'id' => 'admin-batch', 'name' => 'HR', 'email' => 'hr@example.test', 'password' => 'secret',
        ]);
        foreach (['EMP010', 'EMP011'] as $nik) {
            Employee::create(['nik' => $nik, 'status_resign' => 'AKTIF']);
            CvMakerProgressStatus::create([
                'employee_nik' => $nik,
                'cv_profile_id' => 100,
                'current_step' => 8,
                'current_step_label' => 'Dokumen',
                'needs_reminder' => true,
            ]);
        }
        User::create([
            'id' => 'user-valid', 'name' => 'Valid', 'email' => 'valid@example.test',
            'nik_karyawan' => 'EMP010', 'password' => 'secret',
        ]);
        User::create([
            'id' => 'user-invalid', 'name' => 'Invalid', 'email' => null,
            'nik_karyawan' => 'EMP011', 'password' => 'secret',
        ]);
        $compareService = new class extends CvMakerCompareService {
            public function filteredEmployeeQuery(Request $request, User $user): Builder
            {
                return Employee::query();
            }
        };
        $service = new CvMakerReminderService($compareService);
        $request = Request::create('/reminders', 'POST', [
            'idempotency_key' => '2f1b13c2-9b27-47c8-9dc4-f590bc44a5bd',
            'selection_mode' => 'selected',
            'employee_niks' => ['EMP010', 'EMP011'],
            'status_resign' => 'AKTIF',
        ]);

        $batch = $service->createBatch($request, $actor);
        $sameBatch = $service->createBatch($request, $actor);

        $this->assertSame($batch->id, $sameBatch->id);
        $this->assertSame(2, $batch->total_count);
        $this->assertSame(1, $batch->pending_count);
        $this->assertSame(1, $batch->skipped_count);
        $this->assertSame(1, CvMakerReminderBatch::query()->count());
        Queue::assertPushed(SendCvMakerReminderEmail::class, 1);
    }

    public function test_job_skips_email_when_reminder_is_no_longer_needed(): void
    {
        Notification::fake();
        $user = User::create([
            'id' => 'user-2',
            'name' => 'Siti',
            'email' => 'siti@example.test',
            'nik_karyawan' => 'EMP002',
            'password' => 'secret',
        ]);
        CvMakerProgressStatus::create([
            'employee_nik' => 'EMP002',
            'cv_profile_id' => 11,
            'current_step' => 8,
            'current_step_label' => 'Dokumen',
            'needs_reminder' => false,
        ]);
        $batch = CvMakerReminderBatch::create($this->batchPayload(['batch_uuid' => 'batch-2', 'idempotency_key' => 'idem-2']));
        $delivery = CvMakerReminderDelivery::create([
            'batch_id' => $batch->id,
            'employee_nik' => 'EMP002',
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => CvMakerReminderDelivery::STATUS_PENDING,
        ]);
        $service = new CvMakerReminderService(new CvMakerCompareService());

        (new SendCvMakerReminderEmail($delivery->id))->handle($service);

        $this->assertSame(CvMakerReminderDelivery::STATUS_SKIPPED, $delivery->fresh()->status);
        $this->assertSame(CvMakerReminderBatch::STATUS_COMPLETED, $batch->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_review_workflow_records_reviewer_and_note(): void
    {
        $actor = User::create([
            'id' => 'admin-review',
            'name' => 'HR Reviewer',
            'email' => 'hr@example.test',
            'password' => 'secret',
        ]);
        CvMakerProgressStatus::create([
            'employee_nik' => 'EMP003',
            'current_step' => 3,
            'needs_reminder' => false,
            'review_status' => CvMakerProgressStatus::REVIEW_UNREVIEWED,
        ]);

        $progress = (new CvMakerReviewService())->update(
            'EMP003',
            CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION,
            'Ijazah perlu dikonfirmasi.',
            $actor
        );

        $this->assertSame(CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION, $progress->review_status);
        $this->assertSame($actor->id, $progress->reviewed_by);
        $this->assertSame('Ijazah perlu dikonfirmasi.', $progress->review_note);
        $this->assertNotNull($progress->reviewed_at);
    }

    private function batchPayload(array $overrides = []): array
    {
        return array_merge([
            'batch_uuid' => 'batch-1',
            'idempotency_key' => 'idem-1',
            'requested_by' => 'admin-1',
            'selection_mode' => 'selected',
            'status' => CvMakerReminderBatch::STATUS_QUEUED,
            'total_count' => 1,
        ], $overrides);
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('status_resign')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_nik')->unique();
            $table->unsignedBigInteger('cv_profile_id')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('current_step_label')->nullable();
            $table->boolean('needs_reminder')->default(false);
            $table->string('review_status')->default('unreviewed');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });
        Schema::create('cv_maker_reminder_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->string('batch_uuid')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('requested_by');
            $table->string('selection_mode');
            $table->string('status');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('filters')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cv_maker_reminder_deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->string('employee_nik');
            $table->string('user_id')->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('current_step')->nullable();
            $table->string('current_step_label')->nullable();
            $table->string('status');
            $table->string('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }
}
