<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_corrections')) {
            return;
        }

        if (!Schema::hasColumn('attendance_corrections', 'request_type')) {
            Schema::table('attendance_corrections', function (Blueprint $table) {
                $table->string('request_type', 40)->default('correction')->after('tanggal');
            });
        }

        if (!Schema::hasColumn('attendance_corrections', 'partial_permission_type')) {
            Schema::table('attendance_corrections', function (Blueprint $table) {
                $table->string('partial_permission_type', 40)->nullable()->after('request_type');
            });
        }

        if (!Schema::hasColumn('attendance_corrections', 'partial_permission_period')) {
            Schema::table('attendance_corrections', function (Blueprint $table) {
                $table->string('partial_permission_period', 20)->nullable()->after('partial_permission_type');
            });
        }

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->index(['request_type', 'partial_permission_type'], 'attendance_corrections_partial_type_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_corrections')) {
            return;
        }

        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropIndex('attendance_corrections_partial_type_index');
        });

        Schema::table('attendance_corrections', function (Blueprint $table) {
            foreach (['partial_permission_period', 'partial_permission_type', 'request_type'] as $column) {
                if (Schema::hasColumn('attendance_corrections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
