<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerProgressHistory extends Model
{
    public const EVENT_SNAPSHOT_CREATED = 'snapshot_created';
    public const EVENT_PROGRESS_CHANGED = 'progress_changed';
    public const EVENT_REMINDER_NEEDED = 'reminder_needed';
    public const EVENT_REMINDER_CLEARED = 'reminder_cleared';

    protected $fillable = [
        'cv_maker_progress_status_id',
        'employee_nik',
        'event_type',
        'from_step',
        'to_step',
        'from_needs_reminder',
        'to_needs_reminder',
        'cv_status',
        'last_activity_at',
        'message',
        'metadata',
    ];

    protected $casts = [
        'from_step' => 'integer',
        'to_step' => 'integer',
        'from_needs_reminder' => 'boolean',
        'to_needs_reminder' => 'boolean',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function status()
    {
        return $this->belongsTo(CvMakerProgressStatus::class, 'cv_maker_progress_status_id');
    }
}
