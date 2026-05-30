<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_sla_escalation_logs')) {
            return;
        }

        Schema::create('approval_sla_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('stage', 20);
            $table->string('approvable_type');
            $table->string('approvable_id', 64);
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->timestamp('sla_started_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('escalated_by', 36)->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['approvable_type', 'approvable_id', 'stage', 'escalation_level'],
                'approval_sla_unique_escalation'
            );
            $table->index(['module', 'stage'], 'approval_sla_module_stage_index');
            $table->index(['due_at', 'escalation_level'], 'approval_sla_due_level_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_sla_escalation_logs');
    }
};
