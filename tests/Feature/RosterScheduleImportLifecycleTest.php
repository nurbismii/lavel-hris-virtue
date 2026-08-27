<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Models\Roster;
use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RosterScheduleImportLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('import_type');
            $table->string('status');
            $table->string('file_checksum')->nullable();
            $table->string('failure_file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->timestamps();
        });
        Schema::create('roster_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nik');
            $table->date('off_start');
            $table->timestamps();
        });
        Schema::create('cuti_roster', function (Blueprint $table) {
            $table->id();
            $table->string('nik_karyawan');
            $table->unsignedBigInteger('roster_schedule_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_roster_import_lifecycle_and_application_relation_are_cast_correctly(): void
    {
        $history = ImportHistory::create([
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION,
            'expires_at' => '2026-08-28 02:00:00',
        ]);
        $schedule = RosterSchedule::create(['employee_nik' => '16090940', 'off_start' => '2026-09-10']);
        $application = Roster::create(['nik_karyawan' => '16090940', 'roster_schedule_id' => $schedule->id]);

        $this->assertInstanceOf(Carbon::class, $history->expires_at);
        $this->assertSame($schedule->id, $schedule->applications()->firstOrFail()->roster_schedule_id);
        $this->assertSame($schedule->id, $application->schedule()->firstOrFail()->id);
        $this->assertSame(10240, config('roster.import.max_kb'));
        $this->assertSame(12, config('roster.import.retention_hours'));
        $this->assertSame('roster-imports', config('roster.import.directory'));
        $this->assertSame(2, config('roster.generate_years_ahead'));
    }
}
