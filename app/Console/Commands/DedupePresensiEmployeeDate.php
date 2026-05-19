<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DedupePresensiEmployeeDate extends Command
{
    protected $signature = 'presensi:dedupe-employee-date
        {--apply : Terapkan cleanup. Tanpa opsi ini command hanya menampilkan laporan.}
        {--repair-zero-dates : Pulihkan absensis.tanggal 0000-00-00 dari kolom jam presensi sebelum dedupe.}
        {--limit=50 : Maksimal kombinasi karyawan/tanggal yang diproses per run.}
        {--nik= : Batasi ke satu NIK karyawan.}
        {--date= : Batasi ke satu tanggal format YYYY-MM-DD.}';

    protected $description = 'Report and safely merge duplicate absensis rows for the same employee and date.';

    private const BACKUP_TABLE = 'presensi_deduplication_backups';
    private const ZERO_DATE_BACKUP_TABLE = 'presensi_zero_date_repair_backups';

    private array $businessColumns = [
        'jam_masuk',
        'jam_istirahat',
        'jam_kembali_istirahat',
        'jam_pulang',
        'status_presensi',
        'partial_permission_type',
        'partial_permission_period',
        'partial_permission_correction_id',
    ];

    private array $latestValueColumns = [
        'ip_address',
        'user_agent',
        'device_info',
        'face_selfie_path',
        'face_verification_distance',
        'face_verified_at',
        'face_verification_method',
        'face_verification_meta',
        'security_score',
        'is_suspicious',
        'partial_permission_note',
    ];

    public function handle(): int
    {
        if (!$this->presensiTableReady()) {
            $this->error('Tabel absensis atau kolom nik_karyawan/tanggal tidak tersedia.');
            return 1;
        }

        $apply = (bool) $this->option('apply');
        $limit = max(1, min((int) $this->option('limit'), 500));
        $batchId = now()->format('YmdHis') . '-' . Str::lower(Str::random(8));

        $repairSummary = [
            'repaired' => 0,
            'skipped' => 0,
        ];

        if ((bool) $this->option('repair-zero-dates')) {
            $repairSummary = $this->repairZeroDates($apply, $limit, $batchId);
        }

        $groups = $this->duplicateGroups($limit);

        if ($groups->isEmpty()) {
            $this->info('Tidak ada duplikasi absensis untuk kombinasi nik_karyawan dan tanggal.');
            return $apply && $repairSummary['skipped'] > 0 ? 2 : 0;
        }

        if ($apply) {
            $this->ensureBackupTable();
        }

        $summary = [
            'groups' => $groups->count(),
            'merged' => 0,
            'skipped' => 0,
            'deleted_rows' => 0,
        ];

        foreach ($groups as $group) {
            $analysis = $this->analyzeGroup($group);
            $label = $group->nik_karyawan . ' / ' . $group->tanggal . ' (' . $group->rows_count . ' rows)';

            if (!empty($analysis['conflicts'])) {
                $summary['skipped']++;
                $this->warn('[SKIP] ' . $label . ' conflict: ' . implode(', ', $analysis['conflicts']));
                continue;
            }

            if (!$apply) {
                $this->line('[DRY]  ' . $label . ' bisa digabung. Keep ID: ' . $analysis['keeper_id'] . ', hapus: ' . implode(',', $analysis['delete_ids']));
                continue;
            }

            $result = $this->mergeGroup($group, $batchId);

            if (!$result['merged']) {
                $summary['skipped']++;
                $this->warn('[SKIP] ' . $label . ' conflict: ' . implode(', ', $result['conflicts']));
                continue;
            }

            $summary['merged']++;
            $summary['deleted_rows'] += $result['deleted_count'];
            $this->info('[OK]   ' . $label . ' digabung ke ID ' . $result['keeper_id'] . ', hapus ' . $result['deleted_count'] . ' row.');
        }

        if (!$apply) {
            $this->newLine();
            $this->info('Dry-run selesai. Jalankan dengan --apply untuk membersihkan grup yang tidak conflict.');
            return 0;
        }

        $this->newLine();
        $this->info('Cleanup selesai. Batch backup: ' . $batchId);
        $this->info('Grup digabung: ' . $summary['merged'] . ', grup dilewati: ' . $summary['skipped'] . ', row dihapus: ' . $summary['deleted_rows']);
        $this->info('Zero-date diperbaiki: ' . $repairSummary['repaired'] . ', zero-date dilewati: ' . $repairSummary['skipped']);

        return $summary['skipped'] > 0 || $repairSummary['skipped'] > 0 ? 2 : 0;
    }

    private function repairZeroDates(bool $apply, int $limit, string $batchId): array
    {
        if ($apply) {
            $this->ensureZeroDateBackupTable();
        }

        $rows = $this->zeroDateRows($limit);
        $summary = [
            'repaired' => 0,
            'skipped' => 0,
        ];

        if ($rows->isEmpty()) {
            $this->info('Tidak ada absensis dengan tanggal 0000-00-00 yang perlu diperbaiki.');
            return $summary;
        }

        foreach ($rows as $row) {
            $inference = $this->inferAttendanceDateFromTimes($row);
            $label = 'ID ' . $row->id . ' / ' . $row->nik_karyawan . ' / ' . $row->tanggal;

            if (!$inference['date']) {
                $summary['skipped']++;
                $this->warn('[SKIP-ZERO] ' . $label . ' tidak punya tanggal valid pada kolom jam presensi.');
                continue;
            }

            if (!empty($inference['conflict'])) {
                $summary['skipped']++;
                $this->warn('[SKIP-ZERO] ' . $label . ' tanggal antar kolom jam berbeda: ' . $inference['conflict']);
                continue;
            }

            if (!$apply) {
                $this->line('[DRY-ZERO] ' . $label . ' -> ' . $inference['date'] . ' dari ' . $inference['source']);
                continue;
            }

            $repaired = $this->applyZeroDateRepair((int) $row->id, $inference, $batchId);

            if ($repaired) {
                $summary['repaired']++;
                $this->info('[OK-ZERO] ' . $label . ' -> ' . $inference['date'] . ' dari ' . $inference['source']);
            } else {
                $summary['skipped']++;
                $this->warn('[SKIP-ZERO] ' . $label . ' dilewati karena data berubah saat proses berjalan.');
            }
        }

        if (!$apply) {
            $this->info('Dry-run zero-date selesai. Tambahkan --apply untuk menerapkan perbaikan tanggal.');
        }

        return $summary;
    }

    private function zeroDateRows(int $limit): Collection
    {
        $dateFilter = $this->option('date');

        if ($dateFilter && $dateFilter !== '0000-00-00') {
            return collect();
        }

        $query = DB::table('absensis')
            ->where('tanggal', '0000-00-00');

        if ($this->option('nik')) {
            $query->where('nik_karyawan', (string) $this->option('nik'));
        }

        return $query
            ->orderBy('nik_karyawan')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function applyZeroDateRepair(int $rowId, array $inference, string $batchId): bool
    {
        return DB::transaction(function () use ($rowId, $inference, $batchId) {
            $row = DB::table('absensis')
                ->where('id', $rowId)
                ->lockForUpdate()
                ->first();

            if (!$row || (string) $row->tanggal !== '0000-00-00') {
                return false;
            }

            $freshInference = $this->inferAttendanceDateFromTimes($row);

            if (
                !$freshInference['date']
                || !empty($freshInference['conflict'])
                || $freshInference['date'] !== $inference['date']
            ) {
                return false;
            }

            DB::table(self::ZERO_DATE_BACKUP_TABLE)->insert([
                'batch_id' => $batchId,
                'absensi_id' => (int) $row->id,
                'nik_karyawan' => (string) $row->nik_karyawan,
                'old_tanggal' => (string) $row->tanggal,
                'new_tanggal' => $freshInference['date'],
                'source_column' => $freshInference['source'],
                'before_row_json' => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);

            $updates = [
                'tanggal' => $freshInference['date'],
            ];

            if (Schema::hasColumn('absensis', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            DB::table('absensis')
                ->where('id', $row->id)
                ->update($updates);

            return true;
        });
    }

    private function inferAttendanceDateFromTimes($row): array
    {
        $timeColumns = array_values(array_intersect([
            'jam_masuk',
            'jam_istirahat',
            'jam_kembali_istirahat',
            'jam_pulang',
        ], Schema::getColumnListing('absensis')));
        $datesByColumn = [];

        foreach ($timeColumns as $column) {
            $date = $this->datePartFromDateTime($row->{$column} ?? null);

            if (!$date) {
                continue;
            }

            if ($column === 'jam_masuk') {
                return [
                    'date' => $date,
                    'source' => $column,
                    'conflict' => null,
                ];
            }

            $datesByColumn[$column] = $date;
        }

        $uniqueDates = array_values(array_unique(array_values($datesByColumn)));

        if (count($uniqueDates) === 1) {
            $sourceColumn = array_key_first($datesByColumn);

            return [
                'date' => $uniqueDates[0],
                'source' => $sourceColumn,
                'conflict' => null,
            ];
        }

        if (count($uniqueDates) > 1) {
            return [
                'date' => null,
                'source' => null,
                'conflict' => collect($datesByColumn)
                    ->map(fn($date, $column) => $column . '=' . $date)
                    ->implode(', '),
            ];
        }

        return [
            'date' => null,
            'source' => null,
            'conflict' => null,
        ];
    }

    private function datePartFromDateTime($value): ?string
    {
        $value = trim((string) $value);

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s|T)/', $value, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year < 1900 || !checkdate($month, $day, $year)) {
            return null;
        }

        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    private function presensiTableReady(): bool
    {
        return Schema::hasTable('absensis')
            && Schema::hasColumn('absensis', 'nik_karyawan')
            && Schema::hasColumn('absensis', 'tanggal');
    }

    private function duplicateGroups(int $limit): Collection
    {
        $query = DB::table('absensis')
            ->select('nik_karyawan', 'tanggal', DB::raw('COUNT(*) as rows_count'), DB::raw('MIN(id) as first_id'))
            ->whereNotNull('nik_karyawan')
            ->whereNotNull('tanggal');

        if ($this->option('nik')) {
            $query->where('nik_karyawan', (string) $this->option('nik'));
        }

        if ($this->option('date')) {
            $query->where('tanggal', (string) $this->option('date'));
        }

        return $query
            ->groupBy('nik_karyawan', 'tanggal')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('first_id')
            ->limit($limit)
            ->get();
    }

    private function analyzeGroup($group): array
    {
        $rows = $this->groupRows($group);
        $keeper = $this->chooseKeeper($rows);
        $deleteIds = $rows
            ->pluck('id')
            ->reject(fn($id) => (int) $id === (int) $keeper->id)
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

        return [
            'keeper_id' => (int) $keeper->id,
            'delete_ids' => $deleteIds,
            'conflicts' => array_merge(
                $this->businessConflicts($rows),
                $this->verificationConflicts($rows->pluck('id')->map(fn($id) => (int) $id)->all())
            ),
        ];
    }

    private function mergeGroup($group, string $batchId): array
    {
        return DB::transaction(function () use ($group, $batchId) {
            $rows = $this->groupRows($group, true);
            $keeper = $this->chooseKeeper($rows);
            $deleteRows = $rows
                ->reject(fn($row) => (int) $row->id === (int) $keeper->id)
                ->values();
            $deleteIds = $deleteRows->pluck('id')->map(fn($id) => (int) $id)->values()->all();
            $conflicts = array_merge(
                $this->businessConflicts($rows),
                $this->verificationConflicts($rows->pluck('id')->map(fn($id) => (int) $id)->all())
            );

            if (!empty($conflicts)) {
                return [
                    'merged' => false,
                    'keeper_id' => (int) $keeper->id,
                    'deleted_count' => 0,
                    'conflicts' => $conflicts,
                ];
            }

            $updates = $this->mergedUpdates($keeper, $rows);

            if (!empty($updates)) {
                DB::table('absensis')
                    ->where('id', $keeper->id)
                    ->update($updates);
            }

            foreach ($deleteRows as $row) {
                DB::table(self::BACKUP_TABLE)->insert([
                    'batch_id' => $batchId,
                    'nik_karyawan' => (string) $row->nik_karyawan,
                    'tanggal' => (string) $row->tanggal,
                    'kept_absensi_id' => (int) $keeper->id,
                    'deleted_absensi_id' => (int) $row->id,
                    'deleted_row_json' => json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                ]);
            }

            if (Schema::hasTable('presensi_verifications')) {
                DB::table('presensi_verifications')
                    ->whereIn('presensi_id', $deleteIds)
                    ->update(['presensi_id' => (int) $keeper->id]);
            }

            if (Schema::hasTable('attendance_corrections') && Schema::hasColumn('attendance_corrections', 'presensi_id')) {
                DB::table('attendance_corrections')
                    ->whereIn('presensi_id', $deleteIds)
                    ->update(['presensi_id' => (int) $keeper->id]);
            }

            DB::table('absensis')
                ->whereIn('id', $deleteIds)
                ->delete();

            return [
                'merged' => true,
                'keeper_id' => (int) $keeper->id,
                'deleted_count' => count($deleteIds),
                'conflicts' => [],
            ];
        });
    }

    private function groupRows($group, bool $lock = false): Collection
    {
        $query = DB::table('absensis')
            ->where('nik_karyawan', $group->nik_karyawan)
            ->where('tanggal', $group->tanggal)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function chooseKeeper(Collection $rows)
    {
        $verificationCounts = $this->verificationCounts($rows->pluck('id')->map(fn($id) => (int) $id)->all());

        return $rows
            ->sort(function ($left, $right) use ($verificationCounts) {
                $leftScore = $this->rowScore($left, $verificationCounts[(int) $left->id] ?? 0);
                $rightScore = $this->rowScore($right, $verificationCounts[(int) $right->id] ?? 0);

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->first();
    }

    private function rowScore($row, int $verificationCount): int
    {
        $score = $verificationCount * 3;

        foreach (['jam_masuk', 'jam_istirahat', 'jam_kembali_istirahat', 'jam_pulang', 'status_presensi'] as $column) {
            if (property_exists($row, $column) && !$this->blankValue($row->{$column})) {
                $score += 2;
            }
        }

        return $score;
    }

    private function businessConflicts(Collection $rows): array
    {
        $columns = array_intersect($this->businessColumns, Schema::getColumnListing('absensis'));
        $conflicts = [];

        foreach ($columns as $column) {
            if ($this->distinctValues($rows, $column)->count() > 1) {
                $conflicts[] = $column;
            }
        }

        return $conflicts;
    }

    private function verificationConflicts(array $presensiIds): array
    {
        if (!Schema::hasTable('presensi_verifications')) {
            return [];
        }

        $conflictingTypes = DB::table('presensi_verifications')
            ->select('attendance_type', DB::raw('COUNT(*) as rows_count'))
            ->whereIn('presensi_id', $presensiIds)
            ->groupBy('attendance_type')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('attendance_type')
            ->all();

        return collect($conflictingTypes)
            ->map(fn($type) => 'presensi_verifications:' . $type)
            ->values()
            ->all();
    }

    private function verificationCounts(array $presensiIds): array
    {
        if (!Schema::hasTable('presensi_verifications') || empty($presensiIds)) {
            return [];
        }

        return DB::table('presensi_verifications')
            ->select('presensi_id', DB::raw('COUNT(*) as rows_count'))
            ->whereIn('presensi_id', $presensiIds)
            ->groupBy('presensi_id')
            ->pluck('rows_count', 'presensi_id')
            ->map(fn($count) => (int) $count)
            ->all();
    }

    private function mergedUpdates($keeper, Collection $rows): array
    {
        $updates = [];
        $columns = Schema::getColumnListing('absensis');
        $latestRow = $this->latestRow($rows);

        foreach ($columns as $column) {
            if (in_array($column, ['id', 'nik_karyawan', 'tanggal'], true)) {
                continue;
            }

            if ($column === 'created_at') {
                $value = $this->minValue($rows, $column);
                if (!$this->blankValue($value) && (string) $value !== (string) $keeper->{$column}) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if ($column === 'updated_at') {
                $value = $this->maxValue($rows, $column);
                if (!$this->blankValue($value) && (string) $value !== (string) $keeper->{$column}) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if ($column === 'face_verified') {
                $updates[$column] = $rows->contains(fn($row) => !empty($row->{$column}));
                continue;
            }

            if ($column === 'status_absen') {
                $value = $this->mergedStatusAbsen($rows);
                if (!$this->blankValue($value) && (string) $value !== (string) $keeper->{$column}) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if (in_array($column, ['presensi_challenge_id', 'face_selfie_hash'], true)) {
                $distinct = $this->distinctValues($rows, $column);
                $value = $distinct->count() > 1 ? null : $distinct->first();
                if ((string) $value !== (string) ($keeper->{$column} ?? null)) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if (in_array($column, $this->latestValueColumns, true)) {
                $value = $this->firstNonBlank(collect([$latestRow]), $column) ?? $this->firstNonBlank($rows, $column);
                if (!$this->blankValue($value) && (string) $value !== (string) ($keeper->{$column} ?? null)) {
                    $updates[$column] = $value;
                }
                continue;
            }

            if (!$this->blankValue($keeper->{$column} ?? null)) {
                continue;
            }

            $distinct = $this->distinctValues($rows, $column);

            if ($distinct->count() === 1) {
                $updates[$column] = $distinct->first();
            }
        }

        return $updates;
    }

    private function latestRow(Collection $rows)
    {
        return $rows
            ->sortByDesc(function ($row) {
                return ($row->updated_at ?? '') . '-' . str_pad((string) $row->id, 12, '0', STR_PAD_LEFT);
            })
            ->first();
    }

    private function distinctValues(Collection $rows, string $column): Collection
    {
        return $rows
            ->filter(fn($row) => property_exists($row, $column) && !$this->blankValue($row->{$column}))
            ->map(fn($row) => (string) $row->{$column})
            ->unique()
            ->values();
    }

    private function firstNonBlank(Collection $rows, string $column)
    {
        foreach ($rows as $row) {
            if (property_exists($row, $column) && !$this->blankValue($row->{$column})) {
                return $row->{$column};
            }
        }

        return null;
    }

    private function minValue(Collection $rows, string $column)
    {
        return $this->distinctValues($rows, $column)->sort()->first();
    }

    private function maxValue(Collection $rows, string $column)
    {
        return $this->distinctValues($rows, $column)->sortDesc()->first();
    }

    private function mergedStatusAbsen(Collection $rows): ?string
    {
        $values = $this->distinctValues($rows, 'status_absen');

        foreach (['pending_review', 'rejected', 'verified'] as $status) {
            if ($values->contains($status)) {
                return $status;
            }
        }

        return $values->first();
    }

    private function blankValue($value): bool
    {
        return $value === null || $value === '';
    }

    private function ensureBackupTable(): void
    {
        if (Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        Schema::create(self::BACKUP_TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id', 40);
            $table->string('nik_karyawan', 100);
            $table->date('tanggal');
            $table->unsignedBigInteger('kept_absensi_id');
            $table->unsignedBigInteger('deleted_absensi_id');
            $table->longText('deleted_row_json');
            $table->timestamp('created_at')->nullable();

            $table->index('batch_id', 'presensi_dedupe_backups_batch_idx');
            $table->index(['nik_karyawan', 'tanggal'], 'presensi_dedupe_backups_nik_date_idx');
        });
    }

    private function ensureZeroDateBackupTable(): void
    {
        if (Schema::hasTable(self::ZERO_DATE_BACKUP_TABLE)) {
            return;
        }

        Schema::create(self::ZERO_DATE_BACKUP_TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id', 40);
            $table->unsignedBigInteger('absensi_id');
            $table->string('nik_karyawan', 100);
            $table->string('old_tanggal', 20);
            $table->date('new_tanggal');
            $table->string('source_column', 60);
            $table->longText('before_row_json');
            $table->timestamp('created_at')->nullable();

            $table->index('batch_id', 'presensi_zero_date_backups_batch_idx');
            $table->index(['nik_karyawan', 'new_tanggal'], 'presensi_zero_date_backups_nik_date_idx');
        });
    }
}
