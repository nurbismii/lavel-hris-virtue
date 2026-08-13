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
            ->whereIn('permission_role', ['Super Admin', 'Administrator', 'HR', 'HOD', 'Manager', 'Supervisor', 'Admin Divisi'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);
                $menus = is_array($menus) ? $menus : [];

                if (!in_array('organization_structure', $menus, true)) {
                    $menus[] = 'organization_structure';
                }

                DB::table('roles')->where('id', $role->id)->update([
                    'menu_permissions' => json_encode(array_values(array_unique($menus))),
                ]);
            });
    }

    public function down(): void
    {
        // Preserve manually adjusted role permissions.
    }
};
