<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VhireSyncLog extends Model
{
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const OPERATION_ONBOARDING_CANDIDATE = 'onboarding_candidate_received';
    public const OPERATION_CONTRACT_SYNC = 'contract_sync_to_vhire';
    public const OPERATION_SIGNATURE_CALLBACK = 'signature_status_callback';
    public const OPERATION_ACTIVATION_SYNC = 'candidate_activation_sync';

    protected $guarded = [];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
