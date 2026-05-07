<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_trails')) {
            return;
        }

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event', 80);
            $table->string('module', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->string('reference_table', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('employee_nik', 32)->nullable();
            $table->string('actor_id', 36)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_role', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->longText('metadata')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['employee_nik', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['actor_name', 'created_at']);
            $table->index(['reference_table', 'reference_id']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
