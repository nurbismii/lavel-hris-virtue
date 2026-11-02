<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_salary_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 100);
            $table->date('effective_from');
            $table->decimal('basic_salary', 18, 2);
            $table->decimal('fixed_allowance', 18, 2)->default(0);
            $table->decimal('non_fixed_allowance', 18, 2)->default(0);
            $table->string('reason', 255);
            $table->string('reference', 150);
            $table->unsignedBigInteger('import_history_id');
            $table->unsignedInteger('row_number');
            $table->string('approved_by', 36);
            $table->timestamp('approved_at');
            $table->timestamps();
            // Date lookup and revisions use this order; approved records are append-only.
            $table->index(['employee_nik', 'effective_from', 'id'], 'salary_history_effective_index');
            $table->unique(['import_history_id', 'row_number'], 'salary_history_import_row_unique');
        });

        Schema::table('import_histories', function (Blueprint $table) {
            $table->string('salary_upload_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            $table->dropUnique(['salary_upload_key']);
            $table->dropColumn('salary_upload_key');
        });
        Schema::dropIfExists('employee_salary_histories');
    }
};
