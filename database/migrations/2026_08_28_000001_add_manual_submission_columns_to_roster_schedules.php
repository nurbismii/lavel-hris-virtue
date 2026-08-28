<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roster_schedules', function (Blueprint $table): void {
            $table->timestamp('manual_submitted_at')->nullable()->after('realization_type');
            $table->string('manual_submitted_by', 36)->nullable()->after('manual_submitted_at');
            $table->string('manual_reference_number', 100)->nullable()->after('manual_submitted_by');
            $table->string('manual_submission_note', 500)->nullable()->after('manual_reference_number');
            $table->index('manual_submitted_by', 'roster_schedules_manual_submitter_index');
            $table->index(
                ['is_active', 'realization_type', 'off_start'],
                'roster_schedules_priority_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('roster_schedules', function (Blueprint $table): void {
            $table->dropIndex('roster_schedules_manual_submitter_index');
            $table->dropIndex('roster_schedules_priority_index');
            $table->dropColumn([
                'manual_submitted_at',
                'manual_submitted_by',
                'manual_reference_number',
                'manual_submission_note',
            ]);
        });
    }
};
