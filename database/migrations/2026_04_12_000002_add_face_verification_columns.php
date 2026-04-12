<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFaceVerificationColumns extends Migration
{
    public function up()
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'face_reference_path')) {
                    $table->string('face_reference_path')->nullable()->after('bpjs_tk');
                }
            });
        }

        if (Schema::hasTable('absensis')) {
            Schema::table('absensis', function (Blueprint $table) {
                if (!Schema::hasColumn('absensis', 'face_selfie_path')) {
                    $table->string('face_selfie_path')->nullable()->after('device_info');
                }

                if (!Schema::hasColumn('absensis', 'face_verified')) {
                    $table->boolean('face_verified')->default(false)->after('face_selfie_path');
                }

                if (!Schema::hasColumn('absensis', 'face_verification_distance')) {
                    $table->decimal('face_verification_distance', 8, 6)->nullable()->after('face_verified');
                }

                if (!Schema::hasColumn('absensis', 'face_verified_at')) {
                    $table->timestamp('face_verified_at')->nullable()->after('face_verification_distance');
                }

                if (!Schema::hasColumn('absensis', 'face_verification_method')) {
                    $table->string('face_verification_method', 50)->nullable()->after('face_verified_at');
                }

                if (!Schema::hasColumn('absensis', 'face_verification_meta')) {
                    $table->text('face_verification_meta')->nullable()->after('face_verification_method');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'face_reference_path')) {
                    $table->dropColumn('face_reference_path');
                }
            });
        }

        if (Schema::hasTable('absensis')) {
            Schema::table('absensis', function (Blueprint $table) {
                $columns = [
                    'face_selfie_path',
                    'face_verified',
                    'face_verification_distance',
                    'face_verified_at',
                    'face_verification_method',
                    'face_verification_meta',
                ];

                $existingColumns = array_filter($columns, function ($column) {
                    return Schema::hasColumn('absensis', $column);
                });

                if (!empty($existingColumns)) {
                    $table->dropColumn($existingColumns);
                }
            });
        }
    }
}
