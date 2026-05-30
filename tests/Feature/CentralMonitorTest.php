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
    }

    public function test_staff_user_cannot_open_central_monitor(): void
    {
        $user = $this->makeUser('user-staff', 'Staff User', 'Staff', ['central_monitor']);

        $this->actingAs($user)
            ->get(route('central-monitor.index'))
            ->assertForbidden();
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
