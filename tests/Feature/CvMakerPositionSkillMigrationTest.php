<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerPositionSkillMigrationTest extends TestCase
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

        Schema::create('cv_maker_progress_statuses', function ($table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32)->unique();
            $table->string('cv_job_title')->nullable();
        });
    }

    public function test_migration_imports_validated_position_skill_master(): void
    {
        require_once database_path('migrations/2026_09_04_000002_add_cv_position_skill_filter.php');
        require_once database_path('migrations/2026_09_05_000001_add_managerial_category_to_cv_position_master.php');

        (new \AddCvPositionSkillFilter())->up();
        (new \AddManagerialCategoryToCvPositionMaster())->up();

        $this->assertSame(478, DB::table('cv_maker_position_skill_categories')->count());
        $this->assertSame(411, DB::table('cv_maker_position_skill_categories')->where('skill_category', 'skilled')->count());
        $this->assertSame(67, DB::table('cv_maker_position_skill_categories')->where('skill_category', 'non_skilled')->count());
        $this->assertSame('non_skilled', DB::table('cv_maker_position_skill_categories')
            ->where('normalized_position', 'CREW SEMEN 水泥仓泵操作工')
            ->value('skill_category'));
        $this->assertSame(234, DB::table('cv_maker_position_skill_categories')->where('managerial_category', 'managerial')->count());
        $this->assertSame(244, DB::table('cv_maker_position_skill_categories')->where('managerial_category', 'non_managerial')->count());
        $this->assertSame('managerial', DB::table('cv_maker_position_skill_categories')
            ->where('normalized_position', 'KOORDINATOR BATCHING PLANT 搅拌站大班长')
            ->value('managerial_category'));
        $this->assertTrue(Schema::hasColumn('cv_maker_progress_statuses', 'cv_position'));
        $this->assertTrue(Schema::hasColumn('cv_maker_progress_statuses', 'cv_position_normalized'));
    }
}
