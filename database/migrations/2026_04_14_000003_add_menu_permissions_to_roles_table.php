<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (!Schema::hasColumn('roles', 'menu_permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->json('menu_permissions')->nullable()->after('description');
            });
        }

        $hasDescription = Schema::hasColumn('roles', 'description');
        $hasStatus = Schema::hasColumn('roles', 'status');
        $hasCreatedAt = Schema::hasColumn('roles', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('roles', 'updated_at');
        $now = now();

        foreach (config('access.roles', []) as $roleName => $meta) {
            $existing = DB::table('roles')->where('permission_role', $roleName)->first();

            if ($existing) {
                continue;
            }

            $payload = [
                'permission_role' => $roleName,
                'menu_permissions' => json_encode(config("access.default_menu_permissions.{$roleName}", [])),
            ];

            if ($hasDescription) {
                $payload['description'] = $meta['description'] ?? null;
            }

            if ($hasStatus) {
                $payload['status'] = 1;
            }

            if ($hasCreatedAt) {
                $payload['created_at'] = $now;
            }

            if ($hasUpdatedAt) {
                $payload['updated_at'] = $now;
            }

            DB::table('roles')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'menu_permissions')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('menu_permissions');
        });
    }
};
