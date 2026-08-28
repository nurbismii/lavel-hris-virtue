<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_KEY = 'roster_schedule';

    public function up(): void
    {
        $this->updateMenus(true);
    }

    public function down(): void
    {
        $this->updateMenus(false);
    }

    private function updateMenus(bool $append): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        DB::table('roles')
            ->whereIn('permission_role', ['HR', 'Super Admin', 'Administrator'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) use ($append) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);
                $menus = is_array($menus) ? $menus : [];

                if ($append && !in_array(self::MENU_KEY, $menus, true)) {
                    $menus[] = self::MENU_KEY;
                }

                if (!$append) {
                    $menus = array_values(array_filter($menus, fn($menu) => $menu !== self::MENU_KEY));
                }

                DB::table('roles')->where('id', $role->id)->update([
                    'menu_permissions' => json_encode(array_values(array_unique($menus))),
                ]);
            });
    }
};
