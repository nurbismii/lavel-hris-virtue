<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_shift_assignments')) {
            return;
        }

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('shift_date');
            $table->string('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'shift_date']);
            $table->index('shift_date');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_assignments');
    }
};
