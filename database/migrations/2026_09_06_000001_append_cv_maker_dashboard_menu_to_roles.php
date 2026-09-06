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

        DB::table('roles')->whereIn('permission_role', [
            'Super Admin', 'Administrator', 'HR', 'HOD', 'Manager', 'Supervisor', 'Admin Divisi',
        ])->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $menus = json_decode($role->menu_permissions ?? 'null', true);
                // Null uses config defaults; preserve explicit access restrictions.
                if (!is_array($menus) || !in_array('cv_maker_compare', $menus, true)
                    || in_array('cv_maker_dashboard', $menus, true)) {
                    continue;
                }
                $menus[] = 'cv_maker_dashboard';
                DB::table('roles')->where('id', $role->id)
                    ->where('menu_permissions', $role->menu_permissions)
                    ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
            }
        });
    }

    public function down(): void
    {
        // Preserve permissions that administrators may have edited after deployment.
    }
};
