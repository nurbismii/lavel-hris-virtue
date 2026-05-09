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
            ->whereIn('permission_role', ['HOD', 'Admin Divisi'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);

                if (!is_array($menus)) {
                    return;
                }

                $menus = array_values(array_filter($menus, function ($menu) {
                    return $menu !== 'import_history';
                }));

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode($menus)]);
            });
    }

    public function down(): void
    {
        // Keep the restriction. Import history contains HR operational audit data.
    }
};
