<?php

namespace Tests\Feature;

use App\Http\Controllers\Approval\CutiApprovalController;
use App\Http\Controllers\Approval\IzinApprovalController;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApprovalTypeScopeTest extends TestCase
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
        $this->seedData();
    }

    public function test_izin_cannot_be_processed_through_cuti_hrd_endpoint(): void
    {
        Notification::fake();

        DB::table('cuti_izin')->insert([
            'id' => 10,
            'nik_karyawan' => 'EMP001',
            'tipe' => 'PAID',
            'jumlah' => 2,
            'status_hod' => 1,
            'status_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CutiApprovalController::class)->hrdProcess(
                $this->approvalRequest($this->makeHrUser(), 1),
                10
            );

            $this->fail('Izin must not be processable through the cuti approval endpoint.');
        } catch (ModelNotFoundException $exception) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('employees', [
            'nik' => 'EMP001',
            'sisa_cuti' => 5,
        ]);
        $this->assertDatabaseHas('cuti_izin', [
            'id' => 10,
            'status_hrd' => 0,
        ]);
    }

    public function test_cuti_cannot_be_processed_through_izin_hrd_endpoint(): void
    {
        Notification::fake();

        DB::table('cuti_izin')->insert([
            'id' => 11,
            'nik_karyawan' => 'EMP001',
            'tipe' => 'CUTI',
            'jumlah' => 2,
            'status_hod' => 1,
            'status_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(IzinApprovalController::class)->hrdProcess(
                $this->approvalRequest($this->makeHrUser(), 1),
                11
            );

            $this->fail('Cuti must not be processable through the izin approval endpoint.');
        } catch (ModelNotFoundException $exception) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('employees', [
            'nik' => 'EMP001',
            'sisa_cuti' => 5,
        ]);
        $this->assertDatabaseHas('cuti_izin', [
            'id' => 11,
            'status_hrd' => 0,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->integer('sisa_cuti')->default(0);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->string('tipe');
            $table->integer('jumlah')->default(1);
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Karyawan Test',
            'departemen_id' => 1,
            'divisi_id' => 1,
            'sisa_cuti' => 5,
        ]);

        DB::table('users')->insert([
            'id' => 'user-EMP001',
            'name' => 'Karyawan Test',
            'email' => 'employee@example.test',
            'nik_karyawan' => 'EMP001',
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

    private function approvalRequest(User $user, int $action): ProcessApprovalRequest
    {
        $request = ProcessApprovalRequest::create('/approval/hr/leave/1', 'POST', [
            'action' => $action,
        ]);
        $this->prepareRequest($request, $user);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request;
    }

    private function prepareRequest(Request $request, User $user): void
    {
        $request->setUserResolver(fn($guard = null) => $user);
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);
        $this->be($user);
        $this->app->instance('request', $request);
    }
}
