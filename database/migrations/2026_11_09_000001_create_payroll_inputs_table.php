<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_inputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employer_id');
            $table->string('employee_nik', 100);
            $table->char('period', 7);
            $table->json('data');
            $table->string('created_by', 36);
            $table->timestamps();
            $table->index(['employer_id', 'employee_nik', 'period', 'id'], 'payroll_inputs_employee_period');
            $table->index(['employer_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_inputs');
    }
};
