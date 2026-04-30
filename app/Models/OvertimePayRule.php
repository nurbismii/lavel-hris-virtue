<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimePayRule extends Model
{
    protected $table = 'overtime_pay_rules';

    protected $guarded = [];

    protected $casts = [
        'hour_from' => 'integer',
        'hour_to' => 'integer',
        'multiplier' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const SCHEDULE_ANY = 'any';
    public const SCHEDULE_FIVE_TWO = 'five_two';
    public const SCHEDULE_SIX_ONE = 'six_one';

    public const DAY_WORKDAY = 'workday';
    public const DAY_OFF_OR_HOLIDAY = 'off_or_holiday';
    public const DAY_SHORTEST_WORKDAY_HOLIDAY = 'shortest_workday_holiday';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getScheduleTypeLabelAttribute(): string
    {
        return static::scheduleTypeOptions()[$this->schedule_type] ?? $this->schedule_type;
    }

    public function getDayTypeLabelAttribute(): string
    {
        return static::dayTypeOptions()[$this->day_type] ?? $this->day_type;
    }

    public function getHourRangeLabelAttribute(): string
    {
        if ((int) $this->hour_from === (int) $this->hour_to) {
            return 'Jam ke-' . $this->hour_from;
        }

        if ($this->hour_to === null) {
            return 'Mulai jam ke-' . $this->hour_from;
        }

        return 'Jam ' . $this->hour_from . '-' . $this->hour_to;
    }

    public static function scheduleTypeOptions(): array
    {
        return [
            self::SCHEDULE_FIVE_TWO => '5:2 - 5 hari kerja, 2 hari istirahat',
            self::SCHEDULE_SIX_ONE => '6:1 - 6 hari kerja, 1 hari istirahat',
        ];
    }

    public static function ruleScheduleTypeOptions(): array
    {
        return [
            self::SCHEDULE_ANY => 'Semua pola',
            self::SCHEDULE_FIVE_TWO => '5:2',
            self::SCHEDULE_SIX_ONE => '6:1',
        ];
    }

    public static function dayTypeOptions(): array
    {
        return [
            self::DAY_WORKDAY => 'Hari kerja - kelebihan jam',
            self::DAY_OFF_OR_HOLIDAY => 'Hari off / tanggal merah',
            self::DAY_SHORTEST_WORKDAY_HOLIDAY => 'Tanggal merah pada hari kerja terpendek 6:1',
        ];
    }
}
