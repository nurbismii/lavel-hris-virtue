<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('overtime_orders')) {
            return;
        }

        Schema::create('overtime_orders', function (Blueprint $table) {
            $table->id();
            $table->string('nik_karyawan', 100);
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('overtime_type', 30);
            $table->date('overtime_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('required_minutes')->nullable();
            $table->text('reason');
            $table->text('instruction_notes')->nullable();
            $table->string('employee_response_status', 20)->default('PENDING');
            $table->text('employee_response_notes')->nullable();
            $table->timestamp('employee_response_at')->nullable();
            $table->timestamps();

            $table->index(['nik_karyawan', 'overtime_date'], 'idx_overtime_orders_nik_date');
            $table->index(['employee_response_status', 'overtime_date'], 'idx_overtime_orders_response_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_orders');
    }
};
