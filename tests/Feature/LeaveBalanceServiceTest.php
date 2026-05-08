<?php

namespace Tests\Feature;

use App\Exceptions\LeaveBalanceException;
use App\Models\Cuti;
use App\Models\Employee;
use App\Models\LeaveBalanceLedger;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveBalance\LeaveBalanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
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

    public function test_manual_credit_updates_ledger_and_employee_balance(): void
    {
        $employee = $this->seedEmployee(3);
        $actor = $this->makeHrUser();

        $ledger = app(LeaveBalanceService::class)->recordManualEntry($employee, [
            'entry_type' => LeaveBalanceLedger::TYPE_ANNUAL_GRANT,
            'amount' => 12,
            'period_year' => 2026,
            'transaction_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'expires_at' => '2026-12-31',
            'note' => 'Hak cuti tahunan 2026.',
        ], $actor);

        $this->assertSame(3.0, (float) $ledger->balance_before);
        $this->assertSame(15.0, (float) $ledger->balance_after);
        $this->assertDatabaseHas('employees', [
            'nik' => 'EMP001',
            'sisa_cuti' => 15,
        ]);
        $this->assertDatabaseHas('leave_balance_ledgers', [
            'employee_nik' => 'EMP001',
            'entry_type' => LeaveBalanceLedger::TYPE_ANNUAL_GRANT,
            'direction' => LeaveBalanceLedger::DIRECTION_CREDIT,
        ]);
    }

    public function test_debit_cannot_make_balance_negative(): void
    {
        $employee = $this->seedEmployee(1);

        $this->expectException(LeaveBalanceException::class);

        app(LeaveBalanceService::class)->recordManualEntry($employee, [
            'entry_type' => LeaveBalanceLedger::TYPE_EXPIRED,
            'amount' => 2,
            'period_year' => 2026,
            'transaction_date' => '2026-12-31',
            'note' => 'Expired carry-over.',
        ], $this->makeHrUser());
    }

    public function test_usage_for_approved_cuti_is_idempotent(): void
    {
        $employee = $this->seedEmployee(5);
        $actor = $this->makeHrUser();
        $cuti = Cuti::create([
            'id' => 99,
            'nik_karyawan' => 'EMP001',
            'tipe' => 'CUTI',
            'jumlah' => 2,
            'tanggal_mulai' => '2026-05-10',
            'tanggal_berakhir' => '2026-05-11',
            'status_hod' => 1,
            'status_hrd' => 1,
        ]);

        app(LeaveBalanceService::class)->recordUsageForApprovedCuti($cuti, $employee, $actor);
        app(LeaveBalanceService::class)->recordUsageForApprovedCuti($cuti, $employee->fresh(), $actor);

        $this->assertSame(1, LeaveBalanceLedger::where('entry_type', LeaveBalanceLedger::TYPE_USAGE)->count());
        $this->assertDatabaseHas('employees', [
            'nik' => 'EMP001',
            'sisa_cuti' => 3,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->decimal('sisa_cuti', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->string('tipe');
            $table->decimal('jumlah', 8, 2)->default(1);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->timestamps();
        });

        Schema::create('leave_balance_ledgers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->date('transaction_date');
            $table->date('effective_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('entry_type', 40);
            $table->string('direction', 10);
            $table->decimal('amount', 8, 2);
            $table->decimal('balance_before', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamps();
        });
    }

    private function seedEmployee(int $balance): Employee
    {
        return Employee::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Karyawan Test',
            'sisa_cuti' => $balance,
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
}
