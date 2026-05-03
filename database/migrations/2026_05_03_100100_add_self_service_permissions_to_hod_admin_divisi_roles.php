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

        $selfServiceMenus = [
            'dashboard_karyawan',
            'slip_gaji_user',
            'cuti',
            'izin',
            'presensi',
            'lembur',
        ];

        DB::table('roles')
            ->whereIn('permission_role', ['HOD', 'Admin Divisi'])
            ->orderBy('id')
            ->get(['id', 'menu_permissions'])
            ->each(function ($role) use ($selfServiceMenus) {
                $menus = json_decode($role->menu_permissions ?: '[]', true);

                if (!is_array($menus)) {
                    $menus = [];
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'menu_permissions' => json_encode(array_values(array_unique(array_merge($menus, $selfServiceMenus)))),
                    ]);
            });
    }

    public function down(): void
    {
        // Menu permissions are intentionally preserved on rollback to avoid removing admin-managed access.
    }
};
