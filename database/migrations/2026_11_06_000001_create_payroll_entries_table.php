<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            $table->string('payroll_upload_key',64)->nullable()->unique();
        });
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_history_id')->index();
            $table->unsignedBigInteger('employer_id');
            $table->string('employee_nik',100);
            $table->char('period',7);
            $table->char('tax_period',7);
            $table->longText('snapshot');
            $table->string('snapshot_hash',64);
            $table->string('finalized_by',36);
            $table->timestamp('finalized_at');
            $table->unique(['employer_id','period','employee_nik'],'payroll_employee_period_unique');
            $table->unique(['employer_id','tax_period','employee_nik'],'payroll_employee_tax_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::table('import_histories', function (Blueprint $table) {
            $table->dropUnique(['payroll_upload_key']); $table->dropColumn('payroll_upload_key');
        });
    }
};
