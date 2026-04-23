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

        $allMenus = array_keys(config('access.menus', []));

        DB::table('roles')
            ->where('permission_role', 'Super Admin')
            ->update([
                'menu_permissions' => json_encode($allMenus),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $defaultMenus = config('access.default_menu_permissions.Super Admin', []);

        DB::table('roles')
            ->where('permission_role', 'Super Admin')
            ->update([
                'menu_permissions' => json_encode($defaultMenus),
            ]);
    }
};
