<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Support\SafeExceptionLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class AuditTrailService
{
    private const REDACTED = '[redacted]';

    public function record(array $data): ?AuditTrail
    {
        try {
            $request = request();
            $actor = $data['actor'] ?? $request->user();

            if ($actor instanceof User) {
                $data['actor_id'] = $data['actor_id'] ?? (string) $actor->id;
                $data['actor_name'] = $data['actor_name'] ?? $actor->name;
                $data['actor_role'] = $data['actor_role'] ?? $actor->display_role_name;
            }

            unset($data['actor']);

            $data['event'] = Str::limit((string) ($data['event'] ?? 'system.changed'), 80, '');
            $data['module'] = Str::limit((string) ($data['module'] ?? 'system'), 80, '');
            $data['ip_address'] = $data['ip_address'] ?? optional($request)->ip();
            $data['user_agent'] = Str::limit((string) ($data['user_agent'] ?? optional($request)->userAgent()), 255, '');
            $data['note'] = isset($data['note']) ? Str::limit((string) $data['note'], 500, '') : null;

            foreach (['old_values', 'new_values', 'metadata'] as $jsonField) {
                if (array_key_exists($jsonField, $data)) {
                    $data[$jsonField] = $this->sanitizeArray((array) $data[$jsonField]);
                }
            }

            foreach ([
                'auditable_type' => 120,
                'auditable_id' => 64,
                'reference_table' => 80,
                'reference_id' => 64,
                'employee_nik' => 32,
                'actor_id' => 36,
                'actor_name' => 150,
                'actor_role' => 100,
            ] as $field => $limit) {
                if (isset($data[$field])) {
                    $data[$field] = Str::limit((string) $data[$field], $limit, '');
                }
            }

            return AuditTrail::create($data);
        } catch (Throwable $exception) {
            app(SafeExceptionLogger::class)->warning('audit_trail.record', $exception);

            return null;
        }
    }

    public function recordApproval(
        Model $model,
        string $referenceTable,
        string $stage,
        int $action,
        User $actor,
        ?string $note = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): ?AuditTrail {
        $eventStatus = $action === 1 ? 'approved' : 'rejected';
        $referenceId = $model->getKey();

        return $this->record([
            'event' => 'approval.' . $stage . '.' . $eventStatus,
            'module' => 'approval',
            'auditable_type' => get_class($model),
            'auditable_id' => $referenceId !== null ? (string) $referenceId : null,
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId !== null ? (string) $referenceId : null,
            'employee_nik' => $model->getAttribute('nik_karyawan'),
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => array_merge([
                'stage' => $stage,
                'action' => $action,
                'action_label' => $eventStatus,
            ], $metadata),
            'note' => $note,
        ]);
    }

    private function sanitizeArray(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = self::REDACTED;
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeArray($value)
                : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/password|remember_token|token|secret|base64|binary/i', $key) === 1;
    }
}
