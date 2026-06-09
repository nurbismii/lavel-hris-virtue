<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HodApprovalDashboardTest extends TestCase
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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedOrganization();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hod_dashboard_shows_pending_hod_and_hrd_items_inside_scope(): void
    {
        Carbon::setTestNow('2026-06-09 08:00:00');

        $hod = $this->makeUser('hod-user', 'HOD User', 'HOD', ['approval_hod'], 'HOD001', 10, 100);

        DB::table('employees')->insert([
            [
                'nik' => 'EMP001',
                'nama_karyawan' => 'Karyawan Dalam Scope',
                'status_resign' => 'AKTIF',
                'area_kerja' => 'VDNI',
                'departemen_id' => 10,
                'divisi_id' => 100,
                'sisa_cuti' => 7,
            ],
            [
                'nik' => 'EMP999',
                'nama_karyawan' => 'Karyawan Luar Scope',
                'status_resign' => 'AKTIF',
                'area_kerja' => 'VDNI',
                'departemen_id' => 99,
                'divisi_id' => 999,
                'sisa_cuti' => 7,
            ],
        ]);

        DB::table('cuti_izin')->insert([
            [
                'id' => 1,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-06-08',
                'tanggal_mulai' => '2026-06-10',
                'tanggal_berakhir' => '2026-06-10',
                'jumlah' => 1,
                'tipe' => 'CUTI',
                'status_hod' => 0,
                'status_hrd' => 0,
                'created_at' => '2026-06-08 07:00:00',
                'updated_at' => '2026-06-08 07:00:00',
            ],
            [
                'id' => 2,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-06-08',
                'tanggal_mulai' => '2026-06-11',
                'tanggal_berakhir' => '2026-06-11',
                'jumlah' => 1,
                'tipe' => 'PAID',
                'status_hod' => 1,
                'status_hrd' => 0,
                'created_at' => '2026-06-08 08:00:00',
                'updated_at' => '2026-06-08 08:00:00',
            ],
            [
                'id' => 3,
                'nik_karyawan' => 'EMP999',
                'tanggal' => '2026-06-08',
                'tanggal_mulai' => '2026-06-12',
                'tanggal_berakhir' => '2026-06-12',
                'jumlah' => 1,
                'tipe' => 'CUTI',
                'status_hod' => 0,
                'status_hrd' => 0,
                'created_at' => '2026-06-08 07:00:00',
                'updated_at' => '2026-06-08 07:00:00',
            ],
        ]);

        $this->actingAs($hod)
            ->get(route('approval.hod.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Approval HOD')
            ->assertSee('Karyawan Dalam Scope')
            ->assertSee('Menunggu HRD')
            ->assertDontSee('Karyawan Luar Scope');
    }

    public function test_user_without_hod_menu_cannot_open_dashboard(): void
    {
        $user = $this->makeUser('staff-user', 'Staff User', 'Staff', [], 'STAFF001', 10, 100);

        $this->actingAs($user)
            ->get(route('approval.hod.dashboard'))
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
            $table->json('authorized_divisi_ids')->nullable();
            $table->json('authorized_departemen_ids')->nullable();
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

        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->decimal('jumlah', 8, 2)->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
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

    private function seedOrganization(): void
    {
        DB::table('departemens')->insert([
            ['id' => 10, 'departemen' => 'Operasional', 'perusahaan_id' => 1],
            ['id' => 99, 'departemen' => 'Luar Scope', 'perusahaan_id' => 1],
        ]);
        DB::table('divisis')->insert([
            ['id' => 100, 'nama_divisi' => 'Produksi', 'departemen_id' => 10],
            ['id' => 999, 'nama_divisi' => 'Lainnya', 'departemen_id' => 99],
        ]);
    }

    private function makeUser(
        string $id,
        string $name,
        string $roleName,
        array $menus,
        string $nik,
        int $departemenId,
        int $divisiId
    ): User {
        $role = Role::query()->create([
            'permission_role' => $roleName,
            'menu_permissions' => $menus,
        ]);

        DB::table('employees')->insert([
            'nik' => $nik,
            'nama_karyawan' => $name,
            'status_resign' => 'AKTIF',
            'area_kerja' => 'VDNI',
            'departemen_id' => $departemenId,
            'divisi_id' => $divisiId,
            'sisa_cuti' => 0,
        ]);

        return User::query()->create([
            'id' => $id,
            'name' => $name,
            'email' => $id . '@example.test',
            'nik_karyawan' => $nik,
            'role_id' => $role->id,
            'status' => 'aktif',
        ]);
    }
}
