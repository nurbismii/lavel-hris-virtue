<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OvertimeOrder extends Model
{
    protected $table = 'overtime_orders';

    protected $guarded = [];

    protected $casts = [
        'overtime_date' => 'date:Y-m-d',
        'employee_response_at' => 'datetime',
    ];

    public const TYPE_OFF = 'OFF';
    public const TYPE_HOLIDAY = 'HOLIDAY';
    public const TYPE_EXTRA_HOURS = 'EXTRA_HOURS';

    public const RESPONSE_PENDING = 'PENDING';
    public const RESPONSE_ACCEPTED = 'ACCEPTED';
    public const RESPONSE_REJECTED = 'REJECTED';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function employeeUser()
    {
        return $this->belongsTo(User::class, 'nik_karyawan', 'nik_karyawan');
    }

    public function getTypeLabelAttribute(): string
    {
        switch ($this->overtime_type) {
            case self::TYPE_OFF:
                return __('self_service.overtime.types.off');
            case self::TYPE_HOLIDAY:
                return __('self_service.overtime.types.holiday');
            case self::TYPE_EXTRA_HOURS:
                return __('self_service.overtime.types.extra_hours');
            default:
                return $this->overtime_type ?? '-';
        }
    }

    public function getResponseLabelAttribute(): string
    {
        switch ($this->employee_response_status) {
            case self::RESPONSE_ACCEPTED:
                return __('self_service.overtime.responses.accepted');
            case self::RESPONSE_REJECTED:
                return __('self_service.overtime.responses.rejected');
            case self::RESPONSE_PENDING:
            default:
                return __('self_service.status.pending_response');
        }
    }

    public function getResponseBadgeClassAttribute(): string
    {
        switch ($this->employee_response_status) {
            case self::RESPONSE_ACCEPTED:
                return 'success';
            case self::RESPONSE_REJECTED:
                return 'danger';
            case self::RESPONSE_PENDING:
            default:
                return 'warning';
        }
    }

    public function getTimeRangeTextAttribute(): string
    {
        if (!$this->start_time && !$this->end_time) {
            return '-';
        }

        $overnightLabel = '';

        if ($this->start_time && $this->end_time) {
            $start = Carbon::parse($this->start_time);
            $end = Carbon::parse($this->end_time);
            $overnightLabel = $end->lessThanOrEqualTo($start) ? __('self_service.overtime.overnight_suffix') : '';
        }

        return trim($this->formatTime($this->start_time) . ' - ' . $this->formatTime($this->end_time) . $overnightLabel);
    }

    public function isPastDate(): bool
    {
        return $this->overtime_date
            ? $this->overtime_date->toDateString() < now()->toDateString()
            : false;
    }

    public function scopeAccepted($query)
    {
        return $query->where('employee_response_status', self::RESPONSE_ACCEPTED);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('overtime_date', [
            Carbon::parse($startDate)->toDateString(),
            Carbon::parse($endDate)->toDateString(),
        ]);
    }

    private function formatTime(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '--:--';
    }
}
