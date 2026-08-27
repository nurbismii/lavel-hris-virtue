<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RosterScheduleHistory extends Model
{
    public const CLASSIFICATION_PLANNED = 'planned';
    public const CLASSIFICATION_CUTI = 'cuti_roster';
    public const CLASSIFICATION_INSENTIF = 'insentif';
    public const CLASSIFICATION_NOT_APPLICABLE = 'not_applicable';
    public const CLASSIFICATION_NEED_REVIEW = 'need_review';

    public const REVIEW_NOT_REQUIRED = 'not_required';
    public const REVIEW_PENDING = 'pending';
    public const REVIEW_CONFIRMED = 'confirmed';

    protected $guarded = [];

    protected $casts = [
        'scheduled_off_start' => 'date',
        'scheduled_off_end' => 'date',
        'imported_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(RosterSchedule::class, 'roster_schedule_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public static function classificationOptions(): array
    {
        return [
            self::CLASSIFICATION_PLANNED => 'Jadwal Roster',
            self::CLASSIFICATION_CUTI => 'Cuti Roster',
            self::CLASSIFICATION_INSENTIF => 'Insentif',
            self::CLASSIFICATION_NOT_APPLICABLE => 'Tidak Berlaku',
            self::CLASSIFICATION_NEED_REVIEW => 'Perlu Review',
        ];
    }

    public function getClassificationLabelAttribute(): string
    {
        return static::classificationOptions()[$this->classification] ?? 'Tidak diketahui';
    }

    public function getPeriodLabelAttribute(): string
    {
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

        return $this->period_year . ' / ' . ($roman[(int) $this->period_number] ?? $this->period_number);
    }
}
