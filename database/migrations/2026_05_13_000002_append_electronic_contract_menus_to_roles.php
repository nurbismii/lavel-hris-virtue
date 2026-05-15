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
            ->orderBy('id')
            ->get(['id', 'permission_role', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);

                if (!is_array($menus)) {
                    $menus = [];
                }

                $roleName = strtolower((string) $role->permission_role);

                if (in_array($roleName, ['super admin', 'administrator', 'hr'], true)) {
                    $menus[] = 'electronic_contract_admin';
                    $menus[] = 'electronic_contract_user';
                }

                if (in_array($roleName, ['staff', 'user', 'staff roster', 'user roster', 'hod', 'manager', 'supervisor', 'admin divisi'], true)) {
                    $menus[] = 'electronic_contract_user';
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
            });
    }

    public function down(): void
    {
        // Preserve manually adjusted menu permissions.
    }
};
