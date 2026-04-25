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

        $rolesToMenus = [
            'HR' => ['master_shift', 'pengaturan_shift'],
            'HOD' => ['master_shift', 'pengaturan_shift'],
            'Admin Divisi' => ['master_shift', 'pengaturan_shift'],
        ];

        foreach ($rolesToMenus as $roleName => $menusToAppend) {
            $role = DB::table('roles')->where('permission_role', $roleName)->first();

            if (!$role) {
                continue;
            }

            $defaultMenus = config("access.default_menu_permissions.{$roleName}", []);
            $currentMenus = $role->menu_permissions
                ? json_decode($role->menu_permissions, true)
                : $defaultMenus;
            $currentMenus = is_array($currentMenus) ? $currentMenus : [];
            $mergedMenus = array_values(array_unique(array_merge($currentMenus, $menusToAppend)));

            DB::table('roles')
                ->where('id', $role->id)
                ->update(['menu_permissions' => json_encode($mergedMenus)]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $menusToRemove = ['master_shift', 'pengaturan_shift'];
        $roles = DB::table('roles')->get(['id', 'menu_permissions']);

        foreach ($roles as $role) {
            $currentMenus = json_decode($role->menu_permissions ?: '[]', true);
            $currentMenus = is_array($currentMenus) ? $currentMenus : [];
            $filteredMenus = array_values(array_filter($currentMenus, fn($menu) => !in_array($menu, $menusToRemove, true)));

            DB::table('roles')
                ->where('id', $role->id)
                ->update(['menu_permissions' => json_encode($filteredMenus)]);
        }
    }
};
