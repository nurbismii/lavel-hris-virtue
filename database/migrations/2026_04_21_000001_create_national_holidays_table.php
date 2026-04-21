<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNationalHolidaysTable extends Migration
{
    public function up()
    {
        Schema::create('national_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('holiday_name', 150);
            $table->string('created_by', 36)->nullable()->index();
            $table->string('updated_by', 36)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('national_holidays');
    }
}
