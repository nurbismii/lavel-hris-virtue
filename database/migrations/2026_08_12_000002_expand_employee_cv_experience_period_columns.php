<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandEmployeeCvExperiencePeriodColumns extends Migration
{
    public function up()
    {
        if (Schema::hasTable('employee_cv_experiences')) {
            DB::statement('ALTER TABLE employee_cv_experiences MODIFY start_month VARCHAR(10) NULL');
            DB::statement('ALTER TABLE employee_cv_experiences MODIFY end_month VARCHAR(10) NULL');
        }
    }

    public function down()
    {
        // Tidak dipersempit kembali agar tanggal YYYY-MM-DD yang sudah tersimpan tidak terpotong.
    }
}
