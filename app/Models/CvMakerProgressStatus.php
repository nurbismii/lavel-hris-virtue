<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerProgressStatus extends Model
{
    public const REVIEW_UNREVIEWED = 'unreviewed';
    public const REVIEW_IN_PROGRESS = 'in_review';
    public const REVIEW_NEEDS_CONFIRMATION = 'needs_employee_confirmation';
    public const REVIEW_COMPLETED = 'completed';

    protected $fillable = [
        'employee_nik',
        'cv_user_id',
        'cv_profile_id',
        'cv_status',
        'cv_job_title',
        'cv_position',
        'cv_position_normalized',
        'current_step',
        'current_step_key',
        'current_step_label',
        'completed_step_count',
        'total_step_count',
        'is_complete',
        'needs_reminder',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
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
        'reviewed_at' => 'datetime',
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

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public static function reviewLabels(): array
    {
        return [
            self::REVIEW_UNREVIEWED => 'Belum diperiksa',
            self::REVIEW_IN_PROGRESS => 'Sedang diperiksa',
            self::REVIEW_NEEDS_CONFIRMATION => 'Perlu konfirmasi karyawan',
            self::REVIEW_COMPLETED => 'Selesai diperiksa',
        ];
    }
}
