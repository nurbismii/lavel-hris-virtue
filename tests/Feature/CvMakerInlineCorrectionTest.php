<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\CvMaker\CvMakerCompareService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CvMakerInlineCorrectionTest extends TestCase
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

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan')->nullable();
            $table->string('no_ktp')->nullable();
            $table->date('tgl_lahir')->nullable();
            $table->string('status_resign')->nullable();
            $table->timestamps();
        });
    }

    public function test_inline_correction_updates_only_selected_employee_column(): void
    {
        $employee = Employee::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Nama Lama',
            'no_ktp' => '7401010101011234',
            'status_resign' => 'AKTIF',
        ]);
        $actor = new User(['id' => 'admin-1', 'name' => 'HR Reviewer']);

        $result = (new CvMakerCompareService())->correctHrisField($employee, $actor, 'name', 'Nama Baru');

        $this->assertTrue($result['updated']);
        $this->assertSame('Nama Baru', $employee->fresh()->nama_karyawan);
        $this->assertSame('7401010101011234', $employee->fresh()->no_ktp);
    }

    public function test_inline_correction_rejects_invalid_ktp_length(): void
    {
        $employee = Employee::create([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Budi',
            'status_resign' => 'AKTIF',
        ]);

        $this->expectException(ValidationException::class);

        (new CvMakerCompareService())->correctHrisField(
            $employee,
            new User(['id' => 'admin-1', 'name' => 'HR Reviewer']),
            'ktp_number',
            '1234'
        );
    }
}
