<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'employees_leave_balance_scope_idx';

    public function up(): void
    {
        if (
            !Schema::hasTable('employees')
            || !Schema::hasColumn('employees', 'status_resign')
            || !Schema::hasColumn('employees', 'area_kerja')
            || $this->indexExists()
        ) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->index(['status_resign', 'area_kerja'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !$this->indexExists()) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    private function indexExists(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return !empty(DB::select('SHOW INDEX FROM `employees` WHERE Key_name = ?', [self::INDEX_NAME]));
    }
};
