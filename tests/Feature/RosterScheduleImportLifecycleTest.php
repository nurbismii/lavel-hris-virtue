<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Models\Roster;
use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
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

        $this->createModelContractTables();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('roster_schedule_histories');
        Schema::dropIfExists('roster_schedules');
        Schema::dropIfExists('cuti_roster');
        Schema::dropIfExists('import_histories');
        Schema::dropIfExists('roles');
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    private function createModelContractTables(): void
    {
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

    private function runMigration(string $filename, string $direction): void
    {
        $migration = require database_path('migrations/' . $filename);

        $migration->{$direction}();
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

    public function test_real_roster_foundation_and_lifecycle_migrations_are_reversible_on_sqlite(): void
    {
        Schema::drop('cuti_roster');
        Schema::drop('roster_schedules');
        Schema::drop('import_histories');

        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cuti_roster', function (Blueprint $table) {
            $table->id();
        });

        $scheduleMigration = '2026_08_27_000001_create_roster_schedules_table.php';
        $historyMigration = '2026_08_27_000003_create_roster_schedule_histories_table.php';
        $lifecycleMigration = '2026_08_27_000004_add_roster_import_lifecycle_columns.php';

        $this->runMigration($scheduleMigration, 'up');
        $this->runMigration($historyMigration, 'up');
        $this->runMigration($lifecycleMigration, 'up');

        $this->assertTrue(Schema::hasTable('roster_schedules'));
        $this->assertTrue(Schema::hasTable('roster_schedule_histories'));
        $this->assertTrue(Schema::hasColumns('import_histories', [
            'file_checksum',
            'failure_file_path',
            'expires_at',
            'confirmed_at',
            'confirmed_by',
        ]));
        $this->assertTrue(Schema::hasColumn('cuti_roster', 'roster_schedule_id'));

        $cutiRosterIndexes = array_map(function ($index) {
            return $index->name;
        }, DB::select("PRAGMA index_list('cuti_roster')"));
        $importHistoryIndexes = array_map(function ($index) {
            return $index->name;
        }, DB::select("PRAGMA index_list('import_histories')"));

        $this->assertContains('cuti_roster_roster_schedule_id_index', $cutiRosterIndexes);
        $this->assertContains('import_histories_expires_at_index', $importHistoryIndexes);

        $this->runMigration($lifecycleMigration, 'down');
        $this->runMigration($historyMigration, 'down');
        $this->runMigration($scheduleMigration, 'down');

        $this->assertFalse(Schema::hasColumn('cuti_roster', 'roster_schedule_id'));
        $this->assertFalse(Schema::hasColumns('import_histories', [
            'file_checksum',
            'failure_file_path',
            'expires_at',
            'confirmed_at',
            'confirmed_by',
        ]));
        $this->assertFalse(Schema::hasTable('roster_schedule_histories'));
        $this->assertFalse(Schema::hasTable('roster_schedules'));
    }

    public function test_roster_schedule_foundation_migration_fails_without_touching_an_existing_table(): void
    {
        Schema::drop('roster_schedules');
        Schema::create('roster_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('marker');
        });
        DB::table('roster_schedules')->insert(['marker' => 'pre-existing-schedule']);

        try {
            $this->runMigration('2026_08_27_000001_create_roster_schedules_table.php', 'up');
            $this->fail('The migration must fail when roster_schedules already exists.');
        } catch (QueryException $exception) {
            $this->assertTrue(Schema::hasTable('roster_schedules'));
            $this->assertSame('pre-existing-schedule', DB::table('roster_schedules')->value('marker'));
        }
    }

    public function test_roster_schedule_history_foundation_migration_fails_without_touching_an_existing_table(): void
    {
        Schema::create('roster_schedule_histories', function (Blueprint $table) {
            $table->id();
            $table->string('marker');
        });
        DB::table('roster_schedule_histories')->insert(['marker' => 'pre-existing-history']);

        try {
            $this->runMigration('2026_08_27_000003_create_roster_schedule_histories_table.php', 'up');
            $this->fail('The migration must fail when roster_schedule_histories already exists.');
        } catch (QueryException $exception) {
            $this->assertTrue(Schema::hasTable('roster_schedule_histories'));
            $this->assertSame('pre-existing-history', DB::table('roster_schedule_histories')->value('marker'));
        }
    }

    public function test_roster_menu_permission_is_available_to_hr_and_migration_updates_existing_role(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('permission_role');
            $table->longText('menu_permissions')->nullable();
        });
        $roleId = DB::table('roles')->insertGetId([
            'permission_role' => 'HR',
            'menu_permissions' => json_encode(['dashboard_admin']),
        ]);

        $this->assertContains('roster_schedule', config('access.default_menu_permissions.HR'));
        $this->runMigration('2026_08_27_000002_append_roster_schedule_menu_to_hr_roles.php', 'up');
        $menus = json_decode((string) DB::table('roles')->where('id', $roleId)->value('menu_permissions'), true);
        $this->assertContains('roster_schedule', $menus);

        $this->runMigration('2026_08_27_000002_append_roster_schedule_menu_to_hr_roles.php', 'down');
        $menus = json_decode((string) DB::table('roles')->where('id', $roleId)->value('menu_permissions'), true);
        $this->assertNotContains('roster_schedule', $menus);
    }
}
