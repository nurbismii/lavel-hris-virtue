<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_ASSIGNMENT_UNIQUE = 'eala_employee_start_unique';
    private const NEW_ASSIGNMENT_UNIQUE = 'eala_employee_location_start_unique';
    private const ASSIGNMENT_LOCATION_PERIOD_INDEX = 'eala_employee_location_period_idx';
    private const LOG_LOCATION_CREATED_INDEX = 'log_presensi_location_created_index';
    private const VERIFICATION_LOCATION_DATE_INDEX = 'presensi_verifications_location_date_index';

    public function up(): void
    {
        $this->updateAssignmentIndexes();
        $this->addGpsLogLocationColumns();
        $this->addVerificationLocationColumns();
    }

    public function down(): void
    {
        if (Schema::hasTable('presensi_verifications')) {
            $this->dropIndex('presensi_verifications', self::VERIFICATION_LOCATION_DATE_INDEX);
            $this->dropColumns('presensi_verifications', [
                'lokasi_absen_id',
                'attendance_location_name',
                'distance_meter',
                'gps_accuracy_meter',
            ]);
        }

        if (Schema::hasTable('log_presensi')) {
            $this->dropIndex('log_presensi', self::LOG_LOCATION_CREATED_INDEX);
            $this->dropColumns('log_presensi', [
                'lokasi_absen_id',
                'distance_meter',
            ]);
        }

        if (Schema::hasTable('employee_attendance_location_assignments')) {
            $this->dropIndex('employee_attendance_location_assignments', self::ASSIGNMENT_LOCATION_PERIOD_INDEX);
            $this->dropIndex('employee_attendance_location_assignments', self::NEW_ASSIGNMENT_UNIQUE, 'unique');
            $this->dropColumns('employee_attendance_location_assignments', ['assignment_mode']);

            if (!$this->hasDuplicateEmployeeStartAssignments()) {
                $this->addUnique(
                    'employee_attendance_location_assignments',
                    self::OLD_ASSIGNMENT_UNIQUE,
                    ['employee_nik', 'effective_from']
                );
            }
        }
    }

    private function updateAssignmentIndexes(): void
    {
        $table = 'employee_attendance_location_assignments';

        if (
            !Schema::hasTable($table)
            || !Schema::hasColumn($table, 'employee_nik')
            || !Schema::hasColumn($table, 'lokasi_absen_id')
            || !Schema::hasColumn($table, 'effective_from')
        ) {
            return;
        }

        if (!Schema::hasColumn($table, 'assignment_mode')) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('assignment_mode', 20)->default('replace');
            });
        }

        $this->dropIndex($table, self::OLD_ASSIGNMENT_UNIQUE, 'unique');
        $this->addUnique($table, self::NEW_ASSIGNMENT_UNIQUE, ['employee_nik', 'lokasi_absen_id', 'effective_from']);

        if (Schema::hasColumn($table, 'effective_until')) {
            $this->addIndex($table, self::ASSIGNMENT_LOCATION_PERIOD_INDEX, [
                'employee_nik',
                'lokasi_absen_id',
                'effective_from',
                'effective_until',
            ]);
        }
    }

    private function addGpsLogLocationColumns(): void
    {
        if (!Schema::hasTable('log_presensi')) {
            return;
        }

        Schema::table('log_presensi', function (Blueprint $table) {
            if (!Schema::hasColumn('log_presensi', 'lokasi_absen_id')) {
                $table->unsignedBigInteger('lokasi_absen_id')->nullable();
            }

            if (!Schema::hasColumn('log_presensi', 'distance_meter')) {
                $table->decimal('distance_meter', 10, 2)->nullable();
            }
        });

        $this->addIndex('log_presensi', self::LOG_LOCATION_CREATED_INDEX, ['lokasi_absen_id', 'created_at']);
    }

    private function addVerificationLocationColumns(): void
    {
        if (!Schema::hasTable('presensi_verifications')) {
            return;
        }

        Schema::table('presensi_verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('presensi_verifications', 'lokasi_absen_id')) {
                $table->unsignedBigInteger('lokasi_absen_id')->nullable();
            }

            if (!Schema::hasColumn('presensi_verifications', 'attendance_location_name')) {
                $table->string('attendance_location_name', 150)->nullable();
            }

            if (!Schema::hasColumn('presensi_verifications', 'distance_meter')) {
                $table->decimal('distance_meter', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('presensi_verifications', 'gps_accuracy_meter')) {
                $table->decimal('gps_accuracy_meter', 10, 2)->nullable();
            }
        });

        $this->addIndex('presensi_verifications', self::VERIFICATION_LOCATION_DATE_INDEX, ['lokasi_absen_id', 'tanggal']);
    }

    private function addIndex(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    private function addUnique(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->unique($columns, $index);
        });
    }

    private function dropIndex(string $table, string $index, string $type = 'index'): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index, $type) {
            if ($type === 'unique') {
                $table->dropUnique($index);
                return;
            }

            $table->dropIndex($index);
        });
    }

    private function dropColumns(string $table, array $columns): void
    {
        $existingColumns = array_values(array_filter($columns, function ($column) use ($table) {
            return Schema::hasColumn($table, $column);
        }));

        if (empty($existingColumns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn($existingColumns);
        });
    }

    private function hasDuplicateEmployeeStartAssignments(): bool
    {
        return DB::table('employee_attendance_location_assignments')
            ->select('employee_nik', 'effective_from')
            ->whereNotNull('employee_nik')
            ->whereNotNull('effective_from')
            ->groupBy('employee_nik', 'effective_from')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM `' . $table . '`'))->contains(function ($row) use ($index) {
                return (string) ($row->Key_name ?? '') === $index;
            });
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))->contains(function ($row) use ($index) {
                return (string) ($row->name ?? '') === $index;
            });
        }

        return false;
    }
};
