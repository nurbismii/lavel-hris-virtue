<?php

namespace Tests\Feature;

use App\Http\Controllers\User\SlipgajiController;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSlipGajiSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.epayslip', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('epayslip');
        DB::reconnect('epayslip');

        Schema::connection('epayslip')->create('data_karyawans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik');
            $table->string('nama')->nullable();
            $table->string('no_ktp')->nullable();
            $table->string('nm_perusahaan')->nullable();
            $table->string('bpjs_ket')->nullable();
            $table->string('bpjs_tk')->nullable();
            $table->string('npwp')->nullable();
        });

        Schema::connection('epayslip')->create('komponen_gajis', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('data_karyawan_id');
            $table->string('periode');
            $table->unsignedBigInteger('tot_diterima')->default(0);
        });
    }

    public function test_user_pdf_route_uses_user_slip_gaji_controller(): void
    {
        $route = Route::getRoutes()->getByName('slipgaji.pdf');

        $this->assertSame(SlipgajiController::class . '@exportPdf', $route->getActionName());
        $this->assertNull(Route::getRoutes()->getByName('slipgaji.create'));
        $this->assertNull(Route::getRoutes()->getByName('slipgaji.store'));
    }

    public function test_user_can_open_only_their_own_slip_gaji(): void
    {
        $ownSlipId = $this->createSlipGaji('EMP001');
        $this->createSlipGaji('EMP999');

        $this->be($this->makeUser('EMP001'));

        $view = app(SlipgajiController::class)->show($ownSlipId);

        $this->assertSame($ownSlipId, $view->getData()['slip']->id);
        $this->assertSame('EMP001', $view->getData()['slip']->karyawan->nik);
    }

    public function test_user_cannot_open_another_employee_slip_gaji(): void
    {
        $this->createSlipGaji('EMP001');
        $otherSlipId = $this->createSlipGaji('EMP999');

        $this->be($this->makeUser('EMP001'));

        $this->expectException(ModelNotFoundException::class);

        app(SlipgajiController::class)->show($otherSlipId);
    }

    private function createSlipGaji(string $nik): int
    {
        $employeeId = DB::connection('epayslip')
            ->table('data_karyawans')
            ->insertGetId([
                'nik' => $nik,
                'nama' => 'Karyawan ' . $nik,
            ]);

        return DB::connection('epayslip')
            ->table('komponen_gajis')
            ->insertGetId([
                'data_karyawan_id' => $employeeId,
                'periode' => '2026-04',
                'tot_diterima' => 1000000,
            ]);
    }

    private function makeUser(string $nik): User
    {
        $user = new User();
        $user->id = 'user-' . $nik;
        $user->name = 'User ' . $nik;
        $user->email = strtolower($nik) . '@example.test';
        $user->nik_karyawan = $nik;

        return $user;
    }
}
