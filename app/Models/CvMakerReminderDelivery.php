<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerReminderDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    protected $casts = [
        'current_step' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(CvMakerReminderBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
