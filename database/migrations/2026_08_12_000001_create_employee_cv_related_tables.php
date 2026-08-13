<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeCvRelatedTables extends Migration
{
    public function up()
    {
        Schema::create('employee_cv_educations', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('level', 80)->nullable();
            $table->string('institution')->nullable();
            $table->string('major')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $this->finish($table, 'employee_cv_educations');
        });

        Schema::create('employee_cv_experiences', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('position')->nullable();
            $table->string('company')->nullable();
            $table->string('department')->nullable();
            $table->string('division')->nullable();
            $table->string('start_month', 10)->nullable();
            $table->string('end_month', 10)->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('responsibilities')->nullable();
            $this->finish($table, 'employee_cv_experiences');
        });

        Schema::create('employee_cv_organizations', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('organization_name')->nullable();
            $table->string('role')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $this->finish($table, 'employee_cv_organizations');
        });

        Schema::create('employee_cv_certifications', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('name')->nullable();
            $table->string('issuer')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedSmallInteger('valid_until_year')->nullable();
            $table->boolean('is_lifetime')->default(false);
            $table->string('type', 100)->nullable();
            $this->finish($table, 'employee_cv_certifications');
        });

        Schema::create('employee_cv_languages', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('language', 100)->nullable();
            $table->string('level', 100)->nullable();
            $this->finish($table, 'employee_cv_languages');
        });

        Schema::create('employee_cv_projects', function (Blueprint $table) {
            $this->baseColumns($table);
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $this->finish($table, 'employee_cv_projects');
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_cv_projects');
        Schema::dropIfExists('employee_cv_languages');
        Schema::dropIfExists('employee_cv_certifications');
        Schema::dropIfExists('employee_cv_organizations');
        Schema::dropIfExists('employee_cv_experiences');
        Schema::dropIfExists('employee_cv_educations');
    }

    private function baseColumns(Blueprint $table): void
    {
        $table->bigIncrements('id');
        $table->string('employee_nik', 32);
        $table->string('source', 32)->default('cv_maker');
        $table->unsignedBigInteger('source_record_id')->nullable();
        $table->unsignedBigInteger('cv_profile_id')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
    }

    private function finish(Blueprint $table, string $prefix): void
    {
        $table->timestamp('source_updated_at')->nullable();
        $table->timestamp('synced_at')->nullable();
        $table->timestamps();
        $table->index(['employee_nik', 'source'], substr($prefix, 0, 23) . '_nik_src_idx');
        $table->unique(['employee_nik', 'source', 'source_record_id'], substr($prefix, 0, 20) . '_source_unique');
    }
}
