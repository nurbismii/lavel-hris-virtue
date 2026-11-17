<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('import_histories', 'severance_process_key')) {
            Schema::table('import_histories', function (Blueprint $table) {
                $table->string('severance_process_key', 64)->nullable()->unique();
            });
        }
        if (Schema::hasTable('severance_requests')) { return; }
        Schema::create('severance_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_history_id');
            $table->unsignedInteger('row_number');
            $table->string('employee_nik', 100);
            $table->string('employee_name', 200);
            $table->longText('source_metadata')->nullable();
            $table->date('entry_date');
            $table->date('calculation_start');
            $table->date('termination_date');
            $table->string('article', 10)->nullable();
            $table->text('reason')->nullable();
            $table->decimal('return_fare', 15, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->string('created_by', 36);
            $table->longText('salary_snapshot')->nullable();
            $table->string('payroll_approved_by', 36)->nullable();
            $table->timestamp('payroll_approved_at')->nullable();
            $table->decimal('leave_days', 7, 2)->nullable();
            $table->string('hi_approved_by', 36)->nullable();
            $table->timestamp('hi_approved_at')->nullable();
            $table->longText('calculation')->nullable();
            $table->string('calculation_hash', 64)->nullable();
            $table->longText('decisions')->nullable();
            $table->timestamps();
            $table->foreign('import_history_id')->references('id')->on('import_histories')->restrictOnDelete();
            $table->unique(['import_history_id', 'row_number'], 'severance_batch_row_unique');
            $table->unique(['employee_nik', 'termination_date'], 'severance_employee_end_unique');
            $table->index(['status', 'id']);
            $table->index(['created_by', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('severance_requests');
        Schema::table('import_histories', function (Blueprint $table) {
            $table->dropUnique(['severance_process_key']);
            $table->dropColumn('severance_process_key');
        });
    }
};
