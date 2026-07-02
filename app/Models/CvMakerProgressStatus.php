<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerProgressStatus extends Model
{
    protected $fillable = [
        'employee_nik',
        'cv_user_id',
        'cv_profile_id',
        'cv_status',
        'current_step',
        'current_step_key',
        'current_step_label',
        'completed_step_count',
        'total_step_count',
        'is_complete',
        'needs_reminder',
        'reminder_reason',
        'last_activity_at',
        'last_synced_at',
        'completed_steps',
        'missing_steps',
        'metadata',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'completed_step_count' => 'integer',
        'total_step_count' => 'integer',
        'is_complete' => 'boolean',
        'needs_reminder' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'completed_steps' => 'array',
        'missing_steps' => 'array',
        'metadata' => 'array',
    ];

    public function histories()
    {
        return $this->hasMany(CvMakerProgressHistory::class, 'cv_maker_progress_status_id');
    }
}
