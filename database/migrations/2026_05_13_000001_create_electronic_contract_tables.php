<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('contract_type', 40);
            $table->string('name', 150);
            $table->longText('letterhead_html')->nullable();
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();

            $table->index(['contract_type', 'is_active']);
        });

        Schema::create('contract_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('clause_key', 40);
            $table->string('name', 150);
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();

            $table->unique('clause_key');
            $table->index('is_active');
        });

        Schema::create('contract_template_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_template_id')->nullable();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('uploaded_by', 64)->nullable();
            $table->timestamps();

            $table->foreign('contract_template_id')
                ->references('id')
                ->on('contract_templates')
                ->onDelete('set null');
            $table->index('uploaded_by');
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 100);
            $table->unsignedBigInteger('contract_template_id');
            $table->string('contract_type', 40);
            $table->string('status', 30)->default('ready');
            $table->string('contract_number', 120)->nullable();
            $table->string('contract_code', 120)->nullable();
            $table->string('pkwt_number', 120);
            $table->string('gender', 30)->nullable();
            $table->string('marital_status', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('position', 150)->nullable();
            $table->string('contract_duration', 120)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('first_extension_duration', 120)->nullable();
            $table->date('first_extension_end_date')->nullable();
            $table->decimal('salary', 18, 2)->default(0);
            $table->decimal('meal_allowance', 18, 2)->default(0);
            $table->unsignedInteger('addendum_sequence')->nullable();
            $table->string('addendum_number', 150)->nullable();
            $table->string('clause_key', 40)->nullable();
            $table->longText('rendered_html')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->string('pdf_hash', 128)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();

            $table->foreign('contract_template_id')
                ->references('id')
                ->on('contract_templates')
                ->onDelete('restrict');
            $table->index(['nik', 'contract_type']);
            $table->index(['status', 'created_at']);
            $table->index('pkwt_number');
            $table->unique('addendum_number');
            $table->unique(['nik', 'contract_type', 'addendum_sequence'], 'employee_contract_addendum_sequence_unique');
        });

        Schema::create('employee_contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_contract_id');
            $table->string('nik', 100);
            $table->string('signed_by_user_id', 64);
            $table->string('signature_path', 500);
            $table->timestamp('signed_at');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('document_hash', 128)->nullable();
            $table->text('consent_text');
            $table->timestamps();

            $table->foreign('employee_contract_id')
                ->references('id')
                ->on('employee_contracts')
                ->onDelete('cascade');
            $table->unique('employee_contract_id');
            $table->index(['nik', 'signed_at']);
        });

        Schema::create('electronic_contract_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_contract_id')->nullable();
            $table->string('nik', 100)->nullable();
            $table->string('event', 80);
            $table->string('actor_user_id', 64)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->foreign('employee_contract_id')
                ->references('id')
                ->on('employee_contracts')
                ->onDelete('set null');
            $table->index(['event', 'created_at']);
            $table->index(['nik', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_contract_audit_logs');
        Schema::dropIfExists('employee_contract_signatures');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('contract_template_assets');
        Schema::dropIfExists('contract_clauses');
        Schema::dropIfExists('contract_templates');
    }
};
