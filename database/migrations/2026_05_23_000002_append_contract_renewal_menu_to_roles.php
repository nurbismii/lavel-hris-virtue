<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_KEY = 'contract_renewal';

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        DB::table('roles')
            ->orderBy('id')
            ->get(['id', 'permission_role', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);
                $menus = is_array($menus) ? $menus : [];
                $roleName = strtolower((string) $role->permission_role);

                if (in_array($roleName, ['super admin', 'administrator', 'hr', 'hod', 'admin divisi'], true)) {
                    $menus[] = self::MENU_KEY;
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        DB::table('roles')
            ->orderBy('id')
            ->get(['id', 'permission_role', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);
                $menus = is_array($menus) ? $menus : [];
                $menus = array_values(array_filter($menus, fn($menu) => $menu !== self::MENU_KEY));

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode($menus)]);
            });
    }
};
