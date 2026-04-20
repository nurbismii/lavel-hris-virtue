<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployeeDocumentPathsColumns extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('face_reference_path');
            }

            if (!Schema::hasColumn('employees', 'ktp_path')) {
                $table->string('ktp_path')->nullable()->after('photo_path');
            }

            if (!Schema::hasColumn('employees', 'kk_path')) {
                $table->string('kk_path')->nullable()->after('ktp_path');
            }

            if (!Schema::hasColumn('employees', 'sim_path')) {
                $table->string('sim_path')->nullable()->after('kk_path');
            }

            if (!Schema::hasColumn('employees', 'sio_path')) {
                $table->string('sio_path')->nullable()->after('sim_path');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('employees', 'photo_path') ? 'photo_path' : null,
                Schema::hasColumn('employees', 'ktp_path') ? 'ktp_path' : null,
                Schema::hasColumn('employees', 'kk_path') ? 'kk_path' : null,
                Schema::hasColumn('employees', 'sim_path') ? 'sim_path' : null,
                Schema::hasColumn('employees', 'sio_path') ? 'sio_path' : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
