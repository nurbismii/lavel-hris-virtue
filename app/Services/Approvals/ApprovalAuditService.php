<?php

namespace App\Services\Approvals;

use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ApprovalAuditService
{
    public function payload(string $table, string $stage, int $action, User $approver, ?string $note = null): array
    {
        $payload = [];

        $processedByColumn = $stage . '_processed_by';
        $processedAtColumn = $stage . '_processed_at';
        $rejectionReasonColumn = $stage . '_rejection_reason';

        if ($this->hasColumn($table, $processedByColumn)) {
            $payload[$processedByColumn] = (string) $approver->id;
        }

        if ($this->hasColumn($table, $processedAtColumn)) {
            $payload[$processedAtColumn] = now();
        }

        if ($this->hasColumn($table, $rejectionReasonColumn)) {
            $payload[$rejectionReasonColumn] = $action === 2 ? $note : null;
        }

        return $payload;
    }

    public function record(
        string $table,
        Model $model,
        string $stage,
        int $action,
        User $approver,
        ?string $note = null,
        array $oldValues = []
    ): void {
        app(AuditTrailService::class)->recordApproval(
            $model,
            $table,
            $stage,
            $action,
            $approver,
            $note,
            $oldValues,
            $this->approvalValues($table, $model),
            [
                'table' => $table,
                'approval_stage' => $stage,
            ]
        );
    }

    public function approvalValues(string $table, Model $model): array
    {
        $values = [];

        foreach ($this->approvalColumns($table) as $column) {
            $values[$column] = $model->getAttribute($column);
        }

        return $values;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function approvalColumns(string $table): array
    {
        $commonColumns = [
            'hod_processed_by',
            'hod_processed_at',
            'hod_rejection_reason',
            'hrd_processed_by',
            'hrd_processed_at',
            'hrd_rejection_reason',
        ];

        if ($table === 'cuti_roster') {
            return array_merge([
                'status_pengajuan',
                'status_pengajuan_hrd',
            ], $commonColumns);
        }

        return array_merge([
            'status_hod',
            'status_hrd',
        ], $commonColumns);
    }
}
