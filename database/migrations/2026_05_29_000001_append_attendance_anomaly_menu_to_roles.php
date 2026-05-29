<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        DB::table('roles')
            ->whereIn('permission_role', ['Super Admin', 'Administrator', 'HR'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);

                if (!is_array($menus)) {
                    $menus = [];
                }

                if (!in_array('attendance_anomaly', $menus, true)) {
                    $menus[] = 'attendance_anomaly';
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
            });
    }

    public function down(): void
    {
        // Preserve menu permissions because admins may have adjusted role access manually.
    }
};
