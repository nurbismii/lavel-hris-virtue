<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RosterSchedule extends Model
{
    public const REALIZATION_PENDING = 'pending';
    public const REALIZATION_CUTI = 'cuti_roster';
    public const REALIZATION_INSENTIF = 'insentif';

    public const SOURCE_GENERATED = 'generated';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_IMPORT = 'import';

    protected $guarded = [];

    protected $casts = [
        'work_start' => 'date',
        'work_end' => 'date',
        'off_start' => 'date',
        'off_end' => 'date',
        'is_active' => 'boolean',
        'reminder_queued_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'reminder_failed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function histories()
    {
        return $this->hasMany(RosterScheduleHistory::class, 'roster_schedule_id');
    }

    public function applications()
    {
        return $this->hasMany(Roster::class, 'roster_schedule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function realizationOptions(): array
    {
        return [
            self::REALIZATION_PENDING => 'Menunggu pilihan',
            self::REALIZATION_CUTI => 'Cuti Roster',
            self::REALIZATION_INSENTIF => 'Insentif',
        ];
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_year . ' / ' . $this->toRoman((int) $this->period_number);
    }

    public function getRealizationLabelAttribute(): string
    {
        return static::realizationOptions()[$this->realization_type] ?? 'Tidak diketahui';
    }

    private function toRoman(int $number): string
    {
        return [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'][$number] ?? (string) $number;
    }
}
