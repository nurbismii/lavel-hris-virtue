<?php

namespace App\Models;

use Carbon\Carbon;
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
        'manual_submitted_at' => 'datetime',
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

    public function manualSubmitter()
    {
        return $this->belongsTo(User::class, 'manual_submitted_by', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOverduePending(?Carbon $today = null): bool
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        return $this->is_active
            && $this->realization_type === self::REALIZATION_PENDING
            && $this->off_start
            && $this->off_start->copy()->startOfDay()->lt($today);
    }

    public function scopePriorityForToday(Builder $query, Carbon $today): Builder
    {
        $date = $today->copy()->startOfDay()->toDateString();

        return $query
            ->orderByRaw(
                'CASE WHEN off_start < ? AND realization_type = ? THEN 0 '
                . 'WHEN off_start >= ? THEN 1 ELSE 2 END',
                [$date, self::REALIZATION_PENDING, $date]
            )
            ->orderByRaw(
                'CASE WHEN off_start < ? AND realization_type = ? THEN off_start END DESC',
                [$date, self::REALIZATION_PENDING]
            )
            ->orderByRaw('CASE WHEN off_start >= ? THEN off_start END ASC', [$date])
            ->orderByRaw('CASE WHEN off_start < ? AND realization_type <> ? THEN off_start END DESC', [
                $date,
                self::REALIZATION_PENDING,
            ])
            ->orderBy('employee_nik')
            ->orderBy('id');
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
