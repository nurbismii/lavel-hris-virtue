<?php

namespace Tests\Feature;

use App\Models\{Employee, Perusahaan, Role, User};
use App\Services\Audit\AuditTrailService;
use App\Services\Recovery\DeletedDataService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class DeletedDataWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'cache.default'=>'array']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->mock(AuditTrailService::class)->shouldReceive('record')->andReturnNull();
        Schema::create('perusahaan', function (Blueprint $t) { $t->id(); $t->string('nama_perusahaan'); $t->string('kode_perusahaan'); $t->timestamps(); });
        Schema::create('departemens', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('perusahaan_id'); $t->string('departemen'); $t->timestamps(); });
        Schema::create('divisis', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('departemen_id'); $t->string('nama_divisi'); $t->timestamps(); });
        Schema::create('employees', function (Blueprint $t) { $t->string('nik')->primary(); $t->string('nama_karyawan'); $t->unsignedBigInteger('departemen_id')->nullable(); $t->unsignedBigInteger('divisi_id')->nullable(); $t->string('photo_path')->nullable(); $t->string('no_ktp')->nullable(); $t->string('status_resign')->nullable(); $t->timestamps(); });
        DB::table('employees')->insert(['nik'=>'00001','nama_karyawan'=>'Karyawan Sintetis','departemen_id'=>1,'divisi_id'=>1,'photo_path'=>'employee-documents/00001/photo/test.jpg']);
        (require database_path('migrations/2026_11_11_000001_add_recoverable_deletions.php'))->up();
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('permission_role'); $t->text('menu_permissions'); $t->timestamps(); });
        Schema::create('users', function (Blueprint $t) { $t->string('id')->primary(); $t->string('name'); $t->unsignedBigInteger('role_id'); $t->string('nik_karyawan')->nullable(); $t->timestamps(); });
        Schema::create('organization_positions', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('perusahaan_id'); $t->unsignedBigInteger('departemen_id'); $t->unsignedBigInteger('divisi_id')->nullable(); });
        Schema::create('attendance_evidence', function (Blueprint $t) { $t->id(); $t->string('employee_nik'); });
        Schema::create('notifications', function (Blueprint $t) { $t->string('id')->primary(); $t->string('notifiable_type'); $t->string('notifiable_id'); $t->string('type'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        DB::table('perusahaan')->insert(['id'=>1,'nama_perusahaan'=>'Perusahaan Uji','kode_perusahaan'=>'VDNI']);
        DB::table('departemens')->insert(['id'=>1,'perusahaan_id'=>1,'departemen'=>'Departemen Uji']);
        DB::table('divisis')->insert(['id'=>1,'departemen_id'=>1,'nama_divisi'=>'Divisi Uji']);
        DB::table('attendance_evidence')->insert(['employee_nik'=>'00001']);
    }

    protected function tearDown(): void { DB::disconnect('sqlite'); parent::tearDown(); }

    private function actor(string $role = 'HR', array $menus = ['data_karyawan', 'perusahaan']): User
    {
        $r = Role::create(['permission_role'=>$role,'menu_permissions'=>$menus]);
        return User::create(['id'=>'recovery-'.$r->id,'name'=>'Penguji','role_id'=>$r->id]);
    }

    public function test_employee_delete_and_repeated_restore_preserve_identity_documents_and_history(): void
    {
        $hr = $this->actor(); $this->actingAs($hr);
        $this->deleteJson(route('karyawan.destroy', '00001'))->assertOk();
        $this->deleteJson(route('karyawan.destroy', '00001'))->assertOk();
        $this->assertSame(0, Employee::count());
        $this->assertSame(1, Employee::withTrashed()->count());
        $deleted = Employee::onlyTrashed()->first();
        $this->assertSame($hr->id, $deleted->deleted_by);
        $this->assertSame(1, DB::table('attendance_evidence')->count());
        $this->get(route('deleted-data.index', ['type'=>'karyawan']))->assertOk()->assertSee('Karyawan Sintetis')->assertDontSee('employee-documents');
        $restore = route('deleted-data.restore', ['type'=>'karyawan','id'=>'00001']);
        $this->postJson($restore)->assertOk(); $this->postJson($restore)->assertOk();
        $this->assertSame('00001', Employee::first()->nik);
        $this->assertSame('employee-documents/00001/photo/test.jpg', Employee::first()->photo_path);
        $this->assertNull(Employee::first()->deleted_by);
    }

    public function test_used_organization_masters_cannot_be_deleted_and_empty_masters_can_be_restored(): void
    {
        $this->actingAs($this->actor());
        $this->deleteJson(route('perusahaan.destroy', 1))->assertUnprocessable();
        $this->deleteJson(route('departemen.destroy', 1))->assertUnprocessable();
        $this->deleteJson(route('divisi.destroy', 1))->assertUnprocessable();
        $empty = Perusahaan::create(['nama_perusahaan'=>'Kosong','kode_perusahaan'=>'KOSONG']);
        $this->delete(route('perusahaan.destroy', $empty))->assertRedirect();
        $this->assertFalse(Perusahaan::whereKey($empty->id)->exists());
        $this->postJson(route('deleted-data.restore', ['type'=>'perusahaan','id'=>$empty->id]))->assertOk();
        $this->assertTrue(Perusahaan::whereKey($empty->id)->exists());
    }

    public function test_recovery_requires_role_and_each_modules_menu_permission(): void
    {
        $this->actingAs($this->actor('HOD'));
        $this->get(route('deleted-data.index'))->assertForbidden();
        $this->deleteJson(route('karyawan.destroy', '00001'))->assertForbidden();
        $this->actingAs($this->actor('HR', ['perusahaan']));
        $this->get(route('deleted-data.index', ['type'=>'karyawan']))->assertForbidden();
        $this->postJson(route('deleted-data.restore', ['type'=>'karyawan','id'=>'00001']))->assertForbidden();
        $this->postJson(route('deleted-data.restore', ['type'=>'unknown','id'=>'00001']))->assertNotFound();
    }

    public function test_restoring_an_active_employee_cannot_duplicate_an_active_identity(): void
    {
        $this->actingAs($this->actor());
        Employee::whereKey('00001')->update(['no_ktp'=>'0000000000000001','status_resign'=>'AKTIF']);
        $this->deleteJson(route('karyawan.destroy', '00001'))->assertOk();
        Employee::create(['nik'=>'00002','nama_karyawan'=>'Pegawai lain','no_ktp'=>'0000000000000001','status_resign'=>'AKTIF']);
        $this->postJson(route('deleted-data.restore', ['type'=>'karyawan','id'=>'00001']))->assertUnprocessable();
        $this->assertSame(1, Employee::onlyTrashed()->count());
    }
}
