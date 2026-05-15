<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU_KEY = 'electronic_contract_first_party_signature';

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $this->forceAllMenus(['Super Admin', 'Administrator']);
        $this->appendMenu(['HR'], self::MENU_KEY);
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        $this->removeMenu(['HR'], self::MENU_KEY);
    }

    private function appendMenu(array $roleNames, string $menuKey): void
    {
        DB::table('roles')
            ->whereIn('permission_role', $roleNames)
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) use ($menuKey) {
                $menus = $this->decodeMenus($role->menu_permissions);

                if (!in_array($menuKey, $menus, true)) {
                    $menus[] = $menuKey;
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode(array_values(array_unique($menus)))]);
            });
    }

    private function forceAllMenus(array $roleNames): void
    {
        $allMenus = array_keys(config('access.menus', []));

        DB::table('roles')
            ->whereIn('permission_role', $roleNames)
            ->update(['menu_permissions' => json_encode($allMenus)]);
    }

    private function removeMenu(array $roleNames, string $menuKey): void
    {
        DB::table('roles')
            ->whereIn('permission_role', $roleNames)
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) use ($menuKey) {
                $menus = array_values(array_filter(
                    $this->decodeMenus($role->menu_permissions),
                    fn($menu) => $menu !== $menuKey
                ));

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update(['menu_permissions' => json_encode($menus)]);
            });
    }

    private function decodeMenus($value): array
    {
        $menus = json_decode((string) $value, true);

        return is_array($menus) ? $menus : [];
    }
};
