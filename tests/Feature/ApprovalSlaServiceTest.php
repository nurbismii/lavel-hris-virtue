<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalSlaService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApprovalSlaServiceTest extends TestCase
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
        config()->set('approval_sla.stages.hod.hours', 24);
        config()->set('approval_sla.warning_percent', 80);
        config()->set('approval_sla.critical_multiplier', 2);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        $this->createSchema();
        $this->seedBaseData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pending_items_marks_hod_request_as_breached(): void
    {
        $this->insertCuti([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $items = app(ApprovalSlaService::class)->pendingItems([
            'module' => ApprovalDelegation::MODULE_CUTI,
            'stage' => 'hod',
            'status' => 'all',
        ]);

        $this->assertCount(1, $items);
        $this->assertSame(ApprovalSlaService::STATUS_BREACHED, $items->first()['sla_status']);
        $this->assertSame('HOD', $items->first()['stage_label']);
        $this->assertSame(25.0, $items->first()['age_hours']);
    }

    public function test_escalation_logs_once_and_skips_duplicate(): void
    {
        Notification::fake();

        $this->insertCuti([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $service = app(ApprovalSlaService::class);
        $actor = User::query()->where('id', 'hr-user')->first();

        $firstRun = $service->escalateOverdue($actor, 50);

        $this->assertSame(1, $firstRun['checked']);
        $this->assertSame(1, $firstRun['created']);
        $this->assertSame(0, $firstRun['skipped']);
        $this->assertDatabaseCount('approval_sla_escalation_logs', 1);
        Notification::assertSentTo($actor, StatusPengajuanNotification::class);

        $secondRun = $service->escalateOverdue($actor, 50);

        $this->assertSame(1, $secondRun['checked']);
        $this->assertSame(0, $secondRun['created']);
        $this->assertSame(1, $secondRun['skipped']);
        $this->assertDatabaseCount('approval_sla_escalation_logs', 1);
        Notification::assertSentTimes(StatusPengajuanNotification::class, 1);
    }

    public function test_dashboard_route_renders_for_hr_user(): void
    {
        $this->insertCuti([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $response = $this
            ->actingAs(User::query()->where('id', 'hr-user')->first())
            ->get(route('approval-sla.index', [
                'module' => ApprovalDelegation::MODULE_CUTI,
                'stage' => 'hod',
            ]));

        $response->assertOk();
        $response->assertSee('SLA Approval');
        $response->assertSee('Employee Test');
    }

    private function insertCuti(array $overrides = []): void
    {
        DB::table('cuti_izin')->insert(array_merge([
            'nik_karyawan' => 'EMP001',
            'tanggal_mulai' => '2026-05-29',
            'tanggal_berakhir' => '2026-05-30',
            'tipe' => 'CUTI',
            'status_hod' => 0,
            'status_hrd' => 0,
            'delegate_status' => null,
            'delegate_processed_at' => null,
            'hod_processed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function seedBaseData(): void
    {
        DB::table('roles')->insert([
            'id' => 1,
            'permission_role' => 'HR',
            'description' => 'HR',
            'menu_permissions' => json_encode(['approval_sla']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 'hr-user',
            'name' => 'HR User',
            'email' => 'hr@example.test',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'status' => 'aktif',
            'nik_karyawan' => 'EMP001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departemens')->insert([
            'id' => 10,
            'departemen' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('divisis')->insert([
            'id' => 20,
            'departemen_id' => 10,
            'nama_divisi' => 'People Ops',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Employee Test',
            'area_kerja' => 'VDNI',
            'status_resign' => 'AKTIF',
            'departemen_id' => 10,
            'divisi_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
            $table->string('description')->nullable();
            $table->longText('menu_permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('area_kerja')->nullable();
            $table->string('status_resign')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->integer('sisa_cuti')->nullable();
            $table->string('posisi')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('face_reference_path')->nullable();
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
            $table->timestamps();
        });

        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen')->nullable();
            $table->timestamps();
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('departemen_id')->nullable();
            $table->string('nama_divisi')->nullable();
            $table->timestamps();
        });

        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->unsignedTinyInteger('delegate_status')->nullable();
            $table->timestamp('delegate_processed_at')->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_sla_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('stage', 20);
            $table->string('approvable_type');
            $table->string('approvable_id', 64);
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->timestamp('sla_started_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalated_by', 36)->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['approvable_type', 'approvable_id', 'stage', 'escalation_level'],
                'approval_sla_unique_escalation'
            );
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
}
