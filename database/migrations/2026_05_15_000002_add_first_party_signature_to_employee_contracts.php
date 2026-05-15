<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contracts')) {
            return;
        }

        Schema::table('employee_contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contracts', 'first_party_signature_path')) {
                $table->string('first_party_signature_path', 500)->nullable()->after('signed_at');
            }

            if (!Schema::hasColumn('employee_contracts', 'first_party_signature_source')) {
                $table->string('first_party_signature_source', 30)->nullable()->after('first_party_signature_path');
            }

            if (!Schema::hasColumn('employee_contracts', 'first_party_signed_by_user_id')) {
                $table->string('first_party_signed_by_user_id', 64)->nullable()->after('first_party_signature_source');
            }

            if (!Schema::hasColumn('employee_contracts', 'first_party_signed_at')) {
                $table->timestamp('first_party_signed_at')->nullable()->after('first_party_signed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_contracts')) {
            return;
        }

        Schema::table('employee_contracts', function (Blueprint $table) {
            if (Schema::hasColumn('employee_contracts', 'first_party_signed_at')) {
                $table->dropColumn('first_party_signed_at');
            }

            if (Schema::hasColumn('employee_contracts', 'first_party_signed_by_user_id')) {
                $table->dropColumn('first_party_signed_by_user_id');
            }

            if (Schema::hasColumn('employee_contracts', 'first_party_signature_source')) {
                $table->dropColumn('first_party_signature_source');
            }

            if (Schema::hasColumn('employee_contracts', 'first_party_signature_path')) {
                $table->dropColumn('first_party_signature_path');
            }
        });
    }
};
