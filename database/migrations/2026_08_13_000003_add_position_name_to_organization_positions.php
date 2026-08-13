<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('organization_positions', 'position_name')) {
            Schema::table('organization_positions', function (Blueprint $table) {
                $table->string('position_name')->nullable()->after('code');
                $table->index('position_name', 'org_positions_name_idx');
            });
        }

        DB::table('organization_positions')
            ->join('job_titles', 'organization_positions.job_title_id', '=', 'job_titles.id')
            ->whereNull('organization_positions.position_name')
            ->select(['organization_positions.id as position_id', 'job_titles.name'])
            ->chunkById(200, function ($positions) {
                foreach ($positions as $position) {
                    DB::table('organization_positions')
                        ->where('id', $position->position_id)
                        ->update(['position_name' => $position->name]);
                }
            }, 'organization_positions.id', 'position_id');
    }

    public function down()
    {
        if (Schema::hasColumn('organization_positions', 'position_name')) {
            Schema::table('organization_positions', function (Blueprint $table) {
                $table->dropIndex('org_positions_name_idx');
                $table->dropColumn('position_name');
            });
        }
    }
};
