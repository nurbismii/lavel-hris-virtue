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
            ->whereIn('permission_role', ['HR', 'HOD', 'Manager', 'Supervisor', 'Staff', 'Admin Divisi'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);

                if (!is_array($menus)) {
                    $menus = [];
                }

                $menus = collect($menus)
                    ->reject(fn($menu) => in_array($menu, ['roster', 'off_roster'], true))
                    ->values()
                    ->all();

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode($menus)]);
            });
    }

    public function down(): void
    {
        // Do not restore roster permissions automatically; assign Staff Roster as an additional role instead.
    }
};
