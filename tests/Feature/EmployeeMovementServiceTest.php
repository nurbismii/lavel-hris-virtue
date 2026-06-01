<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\Role;
use App\Models\User;
use App\Services\Karyawan\EmployeeMovementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeMovementServiceTest extends TestCase
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
        $this->seedOrganization();
    }

    public function test_hod_submission_waits_for_hrd_before_updating_employee(): void
    {
        $service = app(EmployeeMovementService::class);

        $movement = $service->submit([
            'employee_nik' => 'EMP001',
            'movement_type' => EmployeeMovement::TYPE_PROMOTION,
            'effective_date' => '2026-05-31',
            'new_posisi' => 'Senior Staff',
            'new_jabatan' => 'Senior Operator',
            'reference_number' => 'SK/HR/001',
            'reason' => 'Hasil evaluasi kinerja semester.',
        ], $this->makeHodUser());

        $employee = Employee::query()->whereKey('EMP001')->first();

        $this->assertSame('Staff', $employee->posisi);
        $this->assertSame(EmployeeMovement::STATUS_PENDING_HRD, $movement->status);
        $this->assertSame(EmployeeMovement::APPROVAL_APPROVED, (int) $movement->hod_status);
        $this->assertSame(EmployeeMovement::APPROVAL_PENDING, (int) $movement->hrd_status);

        $movement = $service->processHrd(
            $movement,
            $this->makeHrUser(),
            EmployeeMovement::APPROVAL_APPROVED
        );

        $employee = Employee::query()->whereKey('EMP001')->first();

        $this->assertSame('Senior Staff', $employee->posisi);
        $this->assertSame('Senior Operator', $employee->jabatan);
        $this->assertSame(10, (int) $employee->departemen_id);
        $this->assertSame(101, (int) $employee->divisi_id);
        $this->assertSame(EmployeeMovement::STATUS_APPROVED, $movement->status);
        $this->assertSame(EmployeeMovement::APPROVAL_APPROVED, (int) $movement->hrd_status);

        $this->assertDatabaseHas('audit_trails', [
            'event' => 'employee.movement.submitted',
            'module' => 'employee_movement',
            'employee_nik' => 'EMP001',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'event' => 'employee.movement.applied',
            'module' => 'employee_movement',
            'employee_nik' => 'EMP001',
        ]);
    }

    public function test_delegate_submission_requires_hod_approval_before_hrd(): void
    {
        $this->seedMovementDelegation();

        $service = app(EmployeeMovementService::class);
        $movement = $service->submit([
            'employee_nik' => 'EMP001',
            'movement_type' => EmployeeMovement::TYPE_MUTATION,
            'effective_date' => '2026-05-31',
            'new_divisi_id' => 201,
            'reason' => 'Kebutuhan operasional departemen finance.',
        ], $this->makeDelegateUser());

        $employee = Employee::query()->whereKey('EMP001')->first();

        $this->assertSame(EmployeeMovement::STATUS_PENDING_HOD, $movement->status);
        $this->assertSame(10, (int) $employee->departemen_id);
        $this->assertSame(101, (int) $employee->divisi_id);
        $this->assertSame(20, (int) $movement->new_departemen_id);
        $this->assertSame(201, (int) $movement->new_divisi_id);

        $movement = $service->processHod(
            $movement,
            $this->makeHodUser(),
            EmployeeMovement::APPROVAL_APPROVED
        );

        $this->assertSame(EmployeeMovement::STATUS_PENDING_HRD, $movement->status);

        $service->processHrd(
            $movement,
            $this->makeHrUser(),
            EmployeeMovement::APPROVAL_APPROVED
        );

        $employee = Employee::query()->whereKey('EMP001')->first();

        $this->assertSame(20, (int) $employee->departemen_id);
        $this->assertSame(201, (int) $employee->divisi_id);
        $this->assertSame('Staff', $employee->posisi);
    }

    public function test_no_change_is_rejected_on_submission(): void
    {
        $this->expectException(ValidationException::class);

        app(EmployeeMovementService::class)->submit([
            'employee_nik' => 'EMP001',
            'movement_type' => EmployeeMovement::TYPE_MUTATION,
            'effective_date' => '2026-05-31',
            'new_departemen_id' => 10,
            'new_divisi_id' => 101,
            'reason' => 'Tidak ada perubahan.',
        ], $this->makeHodUser());
    }

    public function test_hrd_approval_rejects_stale_employee_snapshot(): void
    {
        $service = app(EmployeeMovementService::class);

        $movement = $service->submit([
            'employee_nik' => 'EMP001',
            'movement_type' => EmployeeMovement::TYPE_PROMOTION,
            'effective_date' => '2026-05-31',
            'new_posisi' => 'Senior Staff',
            'reason' => 'Hasil evaluasi kinerja semester.',
        ], $this->makeHodUser());

        Employee::query()
            ->whereKey('EMP001')
            ->update(['posisi' => 'Lead Staff']);

        $this->expectException(ValidationException::class);

        $service->processHrd(
            $movement,
            $this->makeHrUser(),
            EmployeeMovement::APPROVAL_APPROVED
        );
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->string('role_id')->nullable();
            $table->json('authorized_divisi_ids')->nullable();
            $table->json('authorized_departemen_ids')->nullable();
        });

        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen');
            $table->unsignedInteger('perusahaan_id')->nullable();
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('departemen_id')->nullable();
            $table->string('nama_divisi');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('status_resign')->nullable();
            $table->string('posisi')->nullable();
            $table->string('jabatan')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hod_user_id', 36);
            $table->string('delegate_user_id', 36);
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('module', 50);
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 36)->nullable();
            $table->string('updated_by', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->string('movement_type', 20);
            $table->date('effective_date');
            $table->string('status', 20)->default('pending_hod');
            $table->string('old_posisi')->nullable();
            $table->string('new_posisi')->nullable();
            $table->string('old_jabatan')->nullable();
            $table->string('new_jabatan')->nullable();
            $table->unsignedInteger('old_departemen_id')->nullable();
            $table->unsignedInteger('new_departemen_id')->nullable();
            $table->unsignedInteger('old_divisi_id')->nullable();
            $table->unsignedInteger('new_divisi_id')->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->string('reason', 1000);
            $table->string('created_by_user_id', 36)->nullable();
            $table->unsignedTinyInteger('hod_status')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hod_rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('hrd_status')->default(0);
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->string('hrd_rejection_reason', 500)->nullable();
            $table->string('applied_by_user_id', 36)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event', 80);
            $table->string('module', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->string('reference_table', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('employee_nik', 32)->nullable();
            $table->string('actor_id', 36)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_role', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->longText('metadata')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    private function seedOrganization(): void
    {
        DB::table('departemens')->insert([
            ['id' => 10, 'departemen' => 'Produksi', 'perusahaan_id' => 1],
            ['id' => 20, 'departemen' => 'Finance', 'perusahaan_id' => 1],
        ]);

        DB::table('divisis')->insert([
            ['id' => 101, 'departemen_id' => 10, 'nama_divisi' => 'Smelter A'],
            ['id' => 201, 'departemen_id' => 20, 'nama_divisi' => 'Payroll'],
        ]);

        Employee::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Karyawan Test',
            'status_resign' => 'AKTIF',
            'posisi' => 'Staff',
            'jabatan' => 'Operator',
            'departemen_id' => 10,
            'divisi_id' => 101,
        ]);
    }

    private function seedMovementDelegation(): void
    {
        DB::table('approval_delegations')->insert([
            'hod_user_id' => 'user-hod',
            'delegate_user_id' => 'user-delegate',
            'departemen_id' => 10,
            'divisi_id' => null,
            'module' => ApprovalDelegation::MODULE_EMPLOYEE_MOVEMENT,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeHrUser(): User
    {
        $user = new User();
        $user->id = 'user-hr';
        $user->name = 'HR User';
        $user->email = 'hr@example.test';
        $user->setRelation('role', new Role(['permission_role' => 'HR']));

        return $user;
    }

    private function makeHodUser(): User
    {
        $user = new User();
        $user->id = 'user-hod';
        $user->name = 'HOD User';
        $user->email = 'hod@example.test';
        $user->authorized_departemen_ids = [10];
        $user->authorized_divisi_ids = [];
        $user->setRelation('role', new Role(['permission_role' => 'HOD']));

        return $user;
    }

    private function makeDelegateUser(): User
    {
        $user = new User();
        $user->id = 'user-delegate';
        $user->name = 'Delegate User';
        $user->email = 'delegate@example.test';
        $user->nik_karyawan = 'EMP999';
        $user->setRelation('role', new Role(['permission_role' => 'Staff']));

        return $user;
    }
}
