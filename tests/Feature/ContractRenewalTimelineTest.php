<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\EmployeeContractHistory;
use App\Services\ContractRenewals\ContractRenewalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractRenewalTimelineTest extends TestCase
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

    public function test_contract_timeline_keeps_addendums_with_same_contract_number_separate(): void
    {
        foreach ([
            ['sequence' => 0, 'type' => ContractTemplate::TYPE_PKWT_1, 'raw' => 'PKWT 1', 'end' => '2026-01-31'],
            ['sequence' => 1, 'type' => ContractTemplate::TYPE_ADDENDUM_PKWT, 'raw' => 'ADENDUM 1', 'end' => '2026-04-30'],
            ['sequence' => 2, 'type' => ContractTemplate::TYPE_ADDENDUM_PKWT, 'raw' => 'ADENDUM 2', 'end' => '2026-07-31'],
        ] as $history) {
            EmployeeContractHistory::create([
                'nik' => '260100001',
                'employee_name' => 'Karyawan A',
                'contract_number' => 'PKWT-SAMA-001',
                'entry_date' => '2025-10-01',
                'history_sequence' => $history['sequence'],
                'history_type' => $history['type'],
                'raw_history_type' => $history['raw'],
                'duration_months' => 3,
                'duration_label' => '3 bulan',
                'contract_end_date' => $history['end'],
            ]);
        }

        $timeline = app(ContractRenewalService::class)
            ->contractHistoriesForNiks(['260100001'])
            ->get('260100001');

        $this->assertCount(3, $timeline);
        $this->assertSame(['PKWT 1', 'ADENDUM 1', 'ADENDUM 2'], $timeline->pluck('raw_type')->all());
        $this->assertSame([0, 1, 2], $timeline->pluck('sequence')->all());
    }

    private function createSchema(): void
    {
        Schema::create('employee_contract_histories', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 100);
            $table->string('employee_name', 180)->nullable();
            $table->string('marital_status', 80)->nullable();
            $table->string('employee_status', 120)->nullable();
            $table->string('contract_number', 150)->nullable();
            $table->date('entry_date')->nullable();
            $table->unsignedSmallInteger('history_sequence')->default(0);
            $table->string('history_type', 40);
            $table->string('raw_history_type', 80);
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->string('duration_label', 80)->nullable();
            $table->date('contract_end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 100)->nullable();
            $table->string('contract_type', 40);
            $table->string('status', 30)->default('ready');
            $table->string('contract_number', 120)->nullable();
            $table->string('pkwt_number', 120)->nullable();
            $table->string('contract_duration', 120)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('first_extension_duration', 120)->nullable();
            $table->date('first_extension_end_date')->nullable();
            $table->unsignedInteger('addendum_sequence')->nullable();
            $table->string('addendum_number', 150)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }
}
