<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterNationalHolidaysUserColumnsToString extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('national_holidays')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE national_holidays MODIFY created_by VARCHAR(36) NULL');
            DB::statement('ALTER TABLE national_holidays MODIFY updated_by VARCHAR(36) NULL');
        }
    }

    public function down()
    {
        if (!Schema::hasTable('national_holidays')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE national_holidays MODIFY created_by BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE national_holidays MODIFY updated_by BIGINT UNSIGNED NULL');
        }
    }
}
