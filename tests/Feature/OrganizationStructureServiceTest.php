<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\OrganizationPosition;
use App\Models\Role;
use App\Models\User;
use App\Services\Organization\OrganizationStructureService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationStructureServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createLegacySchema();

        $migration = require database_path('migrations/2026_08_13_000001_create_organization_structure_master.php');
        $migration->up();
        $positionNameMigration = require database_path('migrations/2026_08_13_000003_add_position_name_to_organization_positions.php');
        $positionNameMigration->up();

        $this->seedLegacyOrganization();
    }

    public function test_initial_excel_levels_and_titles_are_seeded(): void
    {
        $this->assertSame(8, JobLevel::query()->count());
        $this->assertSame(14, JobTitle::query()->count());
        $this->assertDatabaseHas('job_titles', [
            'code' => 'WAKIL_KEPALA_DEPT_GA',
            'job_level_id' => JobLevel::query()->where('rank', 8)->value('id'),
        ]);
        $this->assertDatabaseHas('job_title_aliases', [
            'normalized_alias' => 'WAKIL KEPALA PRODUKSI 副科长',
        ]);
    }

    public function test_hierarchy_rejects_cycle_and_parent_with_lower_or_equal_level(): void
    {
        $service = app(OrganizationStructureService::class);
        $root = $service->savePosition($this->positionPayload('ROOT', 'WAKIL_KEPALA_DEPT_GA'), $this->actor());
        $child = $service->savePosition(
            $this->positionPayload('CHILD', 'SUPERVISOR', $root->id),
            $this->actor()
        );

        try {
            $service->savePosition(
                array_merge($this->positionPayload('ROOT', 'WAKIL_KEPALA_DEPT_GA'), [
                    'parent_position_id' => $child->id,
                ]),
                $this->actor(),
                $root
            );
            $this->fail('Hierarchy cycle should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_position_id', $exception->errors());
        }

        $lowParent = $service->savePosition($this->positionPayload('LOW', 'ADMIN'), $this->actor());

        $this->expectException(ValidationException::class);
        $service->savePosition(
            $this->positionPayload('INVALID_CHILD', 'SUPERVISOR', $lowParent->id),
            $this->actor()
        );
    }

    public function test_assignment_updates_employee_master_and_sets_unambiguous_supervisor(): void
    {
        $service = app(OrganizationStructureService::class);
        $root = $service->savePosition($this->positionPayload('ROOT', 'WAKIL_KEPALA_DEPT_GA'), $this->actor());
        $child = $service->savePosition($this->positionPayload('CHILD', 'SUPERVISOR', $root->id), $this->actor());

        $service->assignEmployee($root, 'SUP001', '2026-08-01', $this->actor());
        $assignment = $service->assignEmployee($child, 'EMP001', '2026-08-02', $this->actor());

        $employee = Employee::query()->whereKey('EMP001')->firstOrFail();

        $this->assertSame($child->id, (int) $employee->organization_position_id);
        $this->assertSame((int) $child->job_title_id, (int) $employee->job_title_id);
        $this->assertSame('SUP001', $employee->reports_to_nik);
        $this->assertSame('Supervisor Test Position', $employee->posisi);
        $this->assertStringContainsString('SUPERVISOR', $employee->jabatan);
        $this->assertSame(EmployeePositionAssignment::STATUS_ACTIVE, $assignment->status);
        $this->assertDatabaseHas('audit_trails', [
            'event' => 'organization.employee_position.assigned',
            'employee_nik' => 'EMP001',
        ]);
    }

    public function test_position_rejects_company_outside_vdni_and_vdnip(): void
    {
        DB::table('perusahaan')->insert([
            'id' => 2,
            'kode_perusahaan' => 'OSS',
            'nama_perusahaan' => 'PT OSS',
        ]);
        DB::table('departemens')->insert([
            'id' => 20,
            'perusahaan_id' => 2,
            'departemen' => 'Departemen OSS',
        ]);

        $payload = array_merge($this->positionPayload('OSS_POSITION', 'ADMIN'), [
            'perusahaan_id' => 2,
            'departemen_id' => 20,
            'divisi_id' => null,
        ]);

        try {
            app(OrganizationStructureService::class)->savePosition($payload, $this->actor());
            $this->fail('Perusahaan di luar VDNI dan VDNIP harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('perusahaan_id', $exception->errors());
        }

        $this->assertDatabaseMissing('organization_positions', ['code' => 'OSS_POSITION']);
    }

    private function createLegacySchema(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_perusahaan');
            $table->string('nama_perusahaan');
        });

        Schema::create('departemens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('perusahaan_id');
            $table->string('departemen');
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('departemen_id');
            $table->string('nama_divisi');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik', 32)->primary();
            $table->string('nama_karyawan');
            $table->string('status_resign')->nullable();
            $table->string('posisi')->nullable();
            $table->string('jabatan')->nullable();
            $table->unsignedBigInteger('departemen_id')->nullable();
            $table->unsignedBigInteger('divisi_id')->nullable();
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

    private function seedLegacyOrganization(): void
    {
        DB::table('perusahaan')->insert(['id' => 1, 'kode_perusahaan' => 'VDNI', 'nama_perusahaan' => 'PT VDNI']);
        DB::table('departemens')->insert(['id' => 10, 'perusahaan_id' => 1, 'departemen' => 'General Affair']);
        DB::table('divisis')->insert(['id' => 100, 'departemen_id' => 10, 'nama_divisi' => 'Operasional']);

        Employee::create([
            'nik' => 'SUP001',
            'nama_karyawan' => 'Supervisor Test',
            'status_resign' => 'AKTIF',
            'jabatan' => 'WAKIL KEPALA DEPARTEMEN GENERAL AFFAIR',
            'departemen_id' => 10,
            'divisi_id' => 100,
        ]);
        Employee::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Employee Test',
            'status_resign' => 'AKTIF',
            'jabatan' => 'SUPERVISOR',
            'departemen_id' => 10,
            'divisi_id' => 100,
        ]);
    }

    private function positionPayload(string $code, string $jobTitleCode, ?int $parentId = null): array
    {
        return [
            'code' => $code,
            'position_name' => $code === 'CHILD' ? 'Supervisor Test Position' : $code . ' Position',
            'perusahaan_id' => 1,
            'departemen_id' => 10,
            'divisi_id' => 100,
            'job_title_id' => JobTitle::query()->where('code', $jobTitleCode)->value('id'),
            'job_level_id' => null,
            'parent_position_id' => $parentId,
            'planned_headcount' => 1,
            'sort_order' => 0,
            'is_active' => true,
            'effective_from' => '2026-08-01',
            'effective_until' => null,
            'notes' => null,
        ];
    }

    private function actor(): User
    {
        $user = new User();
        $user->id = 'user-hr';
        $user->name = 'HR Test';
        $user->email = 'hr@example.test';
        $user->setRelation('role', new Role(['permission_role' => 'HR']));

        return $user;
    }
}
