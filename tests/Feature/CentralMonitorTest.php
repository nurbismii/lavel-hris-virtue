<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentralMonitorTest extends TestCase
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
        config()->set('cache.default', 'array');
    }

    public function test_hr_user_can_open_central_monitor(): void
    {
        $user = $this->makeUser('user-hr', 'HR User', 'HR', ['central_monitor']);

        $response = $this->actingAs($user)
            ->get(route('central-monitor.index'));

        $response->assertOk();
        $response->assertSee('Monitor Terpusat');
        $response->assertSee('Approval &amp; SLA', false);
        $response->assertSee('Presensi &amp; Closing', false);
        $response->assertSee('id="appSidebar"', false);
        $response->assertSee('data-app-menu-toggle', false);
    }

    public function test_staff_user_cannot_open_central_monitor(): void
    {
        $user = $this->makeUser('user-staff', 'Staff User', 'Staff', ['central_monitor']);

        $this->actingAs($user)
            ->get(route('central-monitor.index'))
            ->assertForbidden();
    }

    public function test_restricted_roles_cannot_use_hr_monitor_even_with_menu_permission(): void
    {
        foreach (['HOD', 'Admin Divisi', 'Staff'] as $index => $role) {
            $user = $this->makeUser('restricted-' . $index, 'Test Account', $role, ['central_monitor']);
            $this->actingAs($user)->get(route('central-monitor.index'))->assertForbidden();
        }
        $user = $this->makeUser('hr-no-menu', 'Test HR', 'HR', []);
        $this->actingAs($user)->get(route('central-monitor.index'))->assertForbidden();
    }

    public function test_organization_forms_validate_writes_and_restore_old_values(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan');
        });
        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('perusahaan_id');
            $table->string('departemen');
            $table->string('kepala_dept');
            $table->string('no_telp_departemen', 16);
            $table->string('status_pengeluaran');
            $table->timestamps();
        });
        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('departemen_id');
            $table->string('nama_divisi');
            $table->timestamps();
        });
        DB::table('perusahaan')->insert(['id' => 1, 'nama_perusahaan' => 'Perusahaan Uji']);
        $user = $this->makeUser('organization-hr', 'Test HR', 'HR', ['perusahaan']);
        $this->actingAs($user);
        $valid = ['perusahaan_id' => 1, 'departemen' => 'Departemen Uji', 'kepala_dept' => 'Kepala Uji', 'no_telp_departemen' => '+628123456789', 'status_pengeluaran' => 'PRODUKSI 生产'];
        $this->postJson(route('departemen.store'), array_merge($valid, ['perusahaan_id' => 999, 'no_telp_departemen' => str_repeat('1', 17), 'status_pengeluaran' => 'INVALID']))
            ->assertUnprocessable()->assertJsonValidationErrors(['perusahaan_id', 'no_telp_departemen', 'status_pengeluaran']);
        $this->assertSame(0, DB::table('departemens')->count());
        $this->post(route('departemen.store'), array_merge($valid, ['id' => 999]))->assertRedirect();
        $department = DB::table('departemens')->first();
        $this->assertSame(1, $department->id);
        $this->postJson(route('divisi.store'), ['departemen_id' => 999, 'nama_divisi' => ''])->assertUnprocessable()->assertJsonValidationErrors(['departemen_id', 'nama_divisi']);
        $this->assertSame(0, DB::table('divisis')->count());
        $this->post(route('divisi.store'), ['departemen_id' => $department->id, 'nama_divisi' => 'Divisi Uji', 'id' => 999])->assertRedirect();
        $this->assertDatabaseHas('divisis', ['id' => 1, 'nama_divisi' => 'Divisi Uji']);

        $this->withSession(['_old_input' => ['departemen' => '<script>unsafe</script>', 'kepala_dept' => 'Kepala Uji', 'status_pengeluaran' => 'NON PRODUKSI 生产 非生产', 'departemen_id' => 1, 'nama_divisi' => 'Divisi Baru']]);
        $departmentForm = $this->get(route('departemen.create', 1))->assertOk()->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertSee('value="NON PRODUKSI 生产 非生产" selected', false);
        $divisionForm = $this->get(route('divisi.create', 1))->assertOk()->assertSee('value="1" selected', false)->assertSee('value="Divisi Baru"', false);
        foreach ([$departmentForm, $divisionForm] as $response) {
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//form[@data-submit-lock]//input[not(@type="hidden")] | //form[@data-submit-lock]//select') as $field) {
                $this->assertNotEmpty($field->getAttribute('id'));
                $this->assertSame(1, $xpath->query('//label[@for="' . $field->getAttribute('id') . '"]')->length);
            }
        }
        $staff = $this->makeUser('organization-staff', 'Test Staff', 'Staff', []);
        $this->actingAs($staff)->postJson(route('departemen.store'), $valid)->assertForbidden();
        $this->postJson(route('divisi.store'), ['departemen_id' => 1, 'nama_divisi' => 'Tidak Diizinkan'])->assertForbidden();
    }

    public function test_account_form_keeps_inactive_status_and_validation_input(): void
    {
        $hr = $this->makeUser('account-hr', 'Test HR', 'HR', ['data_user']);
        $target = $this->makeUser('account-target', 'Test Staff', 'Staff', []);
        $target->update(['status' => 'tidak aktif']);
        $this->actingAs($hr)->get(route('user.edit', $target->nik_karyawan))->assertOk()
            ->assertSee('value="tidak aktif" selected', false)
            ->assertSee('id="account-name"', false);
        $this->withSession(['_old_input' => ['email' => 'changed@example.test', 'status' => 'aktif']]);
        $this->get(route('user.edit', $target->nik_karyawan))->assertOk()
            ->assertSee('value="changed@example.test"', false)->assertSee('value="aktif" selected', false);
    }

    public function test_period_summary_is_reused_for_a_minute(): void
    {
        $this->partialMock(\App\Services\Presensi\AttendancePeriodLockService::class, function ($mock) {
            $mock->shouldReceive('buildSummary')->once()->andReturn(['active_employees' => 1]);
        });
        $user = $this->makeUser('cached-hr', 'HR User', 'HR', ['central_monitor']);
        $first = app(\App\Services\Monitoring\CentralMonitorService::class)->dashboard($user);
        $second = app(\App\Services\Monitoring\CentralMonitorService::class)->dashboard($user);
        $this->assertFalse($first['has_errors']);
        $this->assertFalse($second['has_errors']);
    }

    public function test_unavailable_summary_is_not_presented_as_a_normal_zero(): void
    {
        $this->partialMock(\App\Services\Presensi\AttendancePeriodLockService::class, function ($mock) {
            $mock->shouldReceive('buildSummary')->once()->andThrow(new \RuntimeException('Unavailable test summary'));
        });
        $user = $this->makeUser('unavailable-hr', 'HR User', 'HR', ['central_monitor']);
        $this->actingAs($user)->get(route('central-monitor.index'))
            ->assertOk()->assertSee('Ringkasan monitor belum tersedia.')
            ->assertDontSee('Status operasional')->assertDontSee('Unavailable test summary');
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
            $table->integer('sisa_cuti')->default(0);
            $table->string('posisi')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('face_reference_path')->nullable();
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
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
