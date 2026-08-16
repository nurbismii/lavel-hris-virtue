<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerReminderBatch extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL_FAILED = 'partial_failed';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'filters' => 'array',
        'total_count' => 'integer',
        'pending_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(CvMakerReminderDelivery::class, 'batch_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }
}
