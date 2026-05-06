<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalAuditColumnsToLeaveAndRosterTables extends Migration
{
    public function up()
    {
        $this->addAuditColumns('cuti_izin');
        $this->addAuditColumns('cuti_roster');
        $this->addAuditColumns('roster_off_requests');
    }

    public function down()
    {
        $this->dropAuditColumns('cuti_izin');
        $this->dropAuditColumns('cuti_roster');
        $this->dropAuditColumns('roster_off_requests', true);
    }

    private function addAuditColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            foreach (['hod', 'hrd'] as $stage) {
                if (!Schema::hasColumn($table, $stage . '_processed_by')) {
                    $tableBlueprint->string($stage . '_processed_by', 36)->nullable();
                }

                if (!Schema::hasColumn($table, $stage . '_processed_at')) {
                    $tableBlueprint->timestamp($stage . '_processed_at')->nullable();
                }

                if (!Schema::hasColumn($table, $stage . '_rejection_reason')) {
                    $tableBlueprint->string($stage . '_rejection_reason', 500)->nullable();
                }
            }
        });
    }

    private function dropAuditColumns(string $table, bool $processedColumnsMayExist = false): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $columns = [];

        foreach (['hod', 'hrd'] as $stage) {
            if (!$processedColumnsMayExist && Schema::hasColumn($table, $stage . '_processed_by')) {
                $columns[] = $stage . '_processed_by';
            }

            if (!$processedColumnsMayExist && Schema::hasColumn($table, $stage . '_processed_at')) {
                $columns[] = $stage . '_processed_at';
            }

            if (Schema::hasColumn($table, $stage . '_rejection_reason')) {
                $columns[] = $stage . '_rejection_reason';
            }
        }

        if (!empty($columns)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($columns) {
                $tableBlueprint->dropColumn($columns);
            });
        }
    }

}
