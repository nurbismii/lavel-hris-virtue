<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppendRosterOffMenuToRoles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $roles = DB::table('roles')
            ->whereIn('permission_role', ['Super Admin', 'Administrator', 'Staff Roster', 'User Roster'])
            ->get(['id', 'menu_permissions']);

        foreach ($roles as $role) {
            $menus = json_decode($role->menu_permissions ?: '[]', true);
            $menus = is_array($menus) ? $menus : [];

            if (!in_array('off_roster', $menus, true)) {
                $menus[] = 'off_roster';
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $roles = DB::table('roles')->get(['id', 'menu_permissions']);

        foreach ($roles as $role) {
            $menus = json_decode($role->menu_permissions ?: '[]', true);
            $menus = is_array($menus) ? $menus : [];
            $menus = array_values(array_filter($menus, fn($menu) => $menu !== 'off_roster'));

            DB::table('roles')
                ->where('id', $role->id)
                ->update(['menu_permissions' => json_encode($menus)]);
        }
    }
}
