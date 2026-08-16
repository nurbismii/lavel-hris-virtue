<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerFeatureMigrationTest extends TestCase
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

        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32)->unique();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->boolean('needs_reminder')->default(false);
            $table->timestamps();
        });
    }

    public function test_new_cv_maker_migrations_create_tracking_and_review_schema(): void
    {
        $reminderMigration = require database_path('migrations/2026_08_16_000001_create_cv_maker_reminder_tables.php');
        $reviewMigration = require database_path('migrations/2026_08_16_000002_add_review_workflow_to_cv_maker_progress_statuses.php');

        $reminderMigration->up();
        $reviewMigration->up();

        $this->assertTrue(Schema::hasTable('cv_maker_reminder_batches'));
        $this->assertTrue(Schema::hasTable('cv_maker_reminder_deliveries'));
        $this->assertTrue(Schema::hasColumns('cv_maker_progress_statuses', [
            'review_status', 'reviewed_by', 'reviewed_at', 'review_note',
        ]));
    }
}
