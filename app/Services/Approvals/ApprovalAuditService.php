<?php

namespace App\Services\Approvals;

use App\Models\User;
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

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }
}
