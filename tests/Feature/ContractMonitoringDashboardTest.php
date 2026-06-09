<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractRenewal;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractMonitoringDashboardTest extends TestCase
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

    public function test_hr_user_can_monitor_contracts(): void
    {
        Carbon::setTestNow('2026-06-09 08:00:00');

        $user = $this->makeUser('hr-user', 'HR User', 'HR', ['contract_renewal']);

        DB::table('perusahaan')->insert([
            'id' => 1,
            'kode_perusahaan' => 'VDNI',
            'nama_perusahaan' => 'PT VDNI',
        ]);
        DB::table('departemens')->insert([
            'id' => 10,
            'departemen' => 'HRD',
            'perusahaan_id' => 1,
        ]);
        DB::table('divisis')->insert([
            'id' => 100,
            'nama_divisi' => 'HR Operations',
            'departemen_id' => 10,
        ]);
        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Karyawan Monitoring',
            'status_resign' => 'AKTIF',
            'area_kerja' => 'VDNI',
            'departemen_id' => 10,
            'divisi_id' => 100,
            'posisi' => 'Operator',
        ]);
        DB::table('contract_templates')->insert([
            'id' => 1,
            'name' => 'PKWT 1',
            'contract_type' => ContractTemplate::TYPE_PKWT_1,
        ]);
        DB::table('employee_contracts')->insert([
            'id' => 1,
            'nik' => 'EMP001',
            'employee_nik' => 'EMP001',
            'contract_template_id' => 1,
            'contract_type' => ContractTemplate::TYPE_PKWT_1,
            'status' => EmployeeContract::STATUS_READY,
            'signing_method' => EmployeeContract::SIGNING_METHOD_ELECTRONIC,
            'signature_status' => EmployeeContract::SIGNATURE_STATUS_WAITING,
            'contract_number' => 'PKWT-MON-001',
            'contract_end_date' => Carbon::today()->addDays(14)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_contract_histories')->insert([
            'id' => 1,
            'nik' => 'EMP001',
            'employee_name' => 'Karyawan Monitoring',
            'contract_number' => 'PKWT-MON-001',
            'history_sequence' => 0,
            'history_type' => ContractTemplate::TYPE_PKWT_1,
            'raw_history_type' => 'PKWT 1',
            'duration_months' => 3,
            'duration_label' => '3 bulan',
            'contract_end_date' => Carbon::today()->addDays(14)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_contract_renewals')->insert([
            'id' => 1,
            'employee_nik' => 'EMP001',
            'current_contract_history_id' => 1,
            'current_contract_id' => 1,
            'status' => EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL,
            'current_contract_end_date' => Carbon::today()->addDays(14)->toDateString(),
            'assessment_months' => 3,
            'assessment_note' => 'Perpanjang kontrak',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('contract-renewals.dashboard', ['search' => 'Monitoring']))
            ->assertOk()
            ->assertSee('Monitoring Kontrak')
            ->assertSee('Karyawan Monitoring')
            ->assertSee('PKWT-MON-001')
            ->assertSee('Menunggu Approval HRD');
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
            $table->json('menu_permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan')->nullable();
            $table->string('status_resign')->nullable();
            $table->string('area_kerja')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('posisi')->nullable();
            $table->integer('sisa_cuti')->default(0);
            $table->string('photo_path')->nullable();
            $table->string('face_reference_path')->nullable();
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
        });

        Schema::create('perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_perusahaan')->nullable();
            $table->string('nama_perusahaan')->nullable();
        });

        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen')->nullable();
            $table->unsignedInteger('perusahaan_id')->nullable();
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_divisi')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('contract_type')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik', 100)->nullable();
            $table->string('employee_nik', 100)->nullable();
            $table->unsignedInteger('contract_template_id')->nullable();
            $table->string('contract_type', 40)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('signing_method', 30)->nullable();
            $table->string('signature_status', 40)->nullable();
            $table->string('contract_number', 150)->nullable();
            $table->string('pkwt_number', 150)->nullable();
            $table->string('addendum_number', 150)->nullable();
            $table->string('candidate_name')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('first_extension_end_date')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contract_signatures', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('employee_contract_id');
            $table->string('signed_by_user_id')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contract_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik', 100);
            $table->string('employee_name', 180)->nullable();
            $table->string('contract_number', 150)->nullable();
            $table->unsignedSmallInteger('history_sequence')->default(0);
            $table->string('history_type', 40);
            $table->string('raw_history_type', 80);
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->string('duration_label', 80)->nullable();
            $table->date('contract_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contract_renewals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_nik', 100);
            $table->unsignedInteger('current_contract_history_id')->nullable();
            $table->unsignedInteger('current_contract_id')->nullable();
            $table->unsignedInteger('generated_contract_id')->nullable();
            $table->string('delegate_user_id')->nullable();
            $table->string('delegated_by_user_id')->nullable();
            $table->string('assessed_by_user_id')->nullable();
            $table->string('status', 60);
            $table->date('current_contract_end_date')->nullable();
            $table->integer('assessment_months')->nullable();
            $table->text('assessment_note')->nullable();
            $table->timestamps();
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

    private function makeUser(string $id, string $name, string $roleName, array $menus): User
    {
        $role = Role::query()->create([
            'permission_role' => $roleName,
            'menu_permissions' => $menus,
        ]);

        DB::table('employees')->insert([
            'nik' => $id . '-nik',
            'nama_karyawan' => $name,
            'status_resign' => 'AKTIF',
            'area_kerja' => 'VDNI',
            'sisa_cuti' => 0,
        ]);

        return User::query()->create([
            'id' => $id,
            'name' => $name,
            'email' => $id . '@example.test',
            'nik_karyawan' => $id . '-nik',
            'role_id' => $role->id,
            'status' => 'aktif',
        ]);
    }
}
