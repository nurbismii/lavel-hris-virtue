<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const REQUEST_TYPE_CORRECTION = 'correction';
    public const REQUEST_TYPE_PARTIAL_PERMISSION = 'partial_permission';
    public const PARTIAL_LATE_ARRIVAL = 'late_arrival';
    public const PARTIAL_SICK = 'sick';
    public const PARTIAL_HALF_DAY = 'half_day';
    public const PARTIAL_EARLY_LEAVE = 'early_leave';
    public const PARTIAL_OTHER = 'other';
    public const PERIOD_MORNING = 'morning';
    public const PERIOD_AFTERNOON = 'afternoon';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'requested_jam_masuk' => 'datetime',
        'requested_jam_istirahat' => 'datetime',
        'requested_jam_kembali_istirahat' => 'datetime',
        'requested_jam_pulang' => 'datetime',
        'change_status_presensi' => 'boolean',
        'old_values' => 'array',
        'applied_values' => 'array',
        'delegate_processed_at' => 'datetime',
        'hod_processed_at' => 'datetime',
        'hrd_processed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik')->select([
            'nik',
            'nama_karyawan',
            'area_kerja',
            'departemen_id',
            'divisi_id',
        ]);
    }

    public function presensi()
    {
        return $this->belongsTo(Presensi::class, 'presensi_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hodProcessor()
    {
        return $this->belongsTo(User::class, 'hod_processed_by');
    }

    public function delegateProcessor()
    {
        return $this->belongsTo(User::class, 'delegate_processed_by');
    }

    public function hrdProcessor()
    {
        return $this->belongsTo(User::class, 'hrd_processed_by');
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public static function statusPresensiOptions(): array
    {
        return [
            'Cuti Tahunan' => __('self_service.attendance_correction.daily_status_options.annual_leave'),
            'Cuti Roster' => __('self_service.attendance_correction.daily_status_options.roster_leave'),
            'Izin Berbayar' => __('self_service.attendance_correction.daily_status_options.paid_permission'),
            'Izin Tidak Berbayar' => __('self_service.attendance_correction.daily_status_options.unpaid_permission'),
            'Libur Nasional' => __('self_service.attendance_correction.daily_status_options.national_holiday'),
            'Off' => __('self_service.attendance_correction.daily_status_options.off'),
        ];
    }

    public static function requestTypeOptions(): array
    {
        return [
            self::REQUEST_TYPE_CORRECTION => __('self_service.attendance_correction.request_types.correction'),
            self::REQUEST_TYPE_PARTIAL_PERMISSION => __('self_service.attendance_correction.request_types.partial_permission'),
        ];
    }

    public static function partialPermissionOptions(): array
    {
        return [
            self::PARTIAL_LATE_ARRIVAL => __('self_service.attendance_correction.partial_options.late_arrival'),
            self::PARTIAL_SICK => __('self_service.attendance_correction.partial_options.sick'),
            self::PARTIAL_HALF_DAY => __('self_service.attendance_correction.partial_options.half_day'),
            self::PARTIAL_EARLY_LEAVE => __('self_service.attendance_correction.partial_options.early_leave'),
            self::PARTIAL_OTHER => __('self_service.attendance_correction.partial_options.other'),
        ];
    }

    public static function halfDayPeriodOptions(): array
    {
        return [
            self::PERIOD_MORNING => __('self_service.attendance_correction.half_day_periods.morning'),
            self::PERIOD_AFTERNOON => __('self_service.attendance_correction.half_day_periods.afternoon'),
        ];
    }

    public static function statusLabel(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return __('self_service.status.approved');
            case self::STATUS_REJECTED:
                return __('self_service.status.rejected');
            default:
                return __('self_service.status.pending');
        }
    }

    public static function statusBadgeClass(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return 'bg-success';
            case self::STATUS_REJECTED:
                return 'bg-danger';
            default:
                return 'bg-warning text-dark';
        }
    }

    public function getHodStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status_hod);
    }

    public function getDelegateStatusLabelAttribute(): string
    {
        if ($this->delegate_status === null) {
            return __('self_service.status.no_delegate');
        }

        return self::statusLabel($this->delegate_status);
    }

    public function getHrdStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status_hrd);
    }

    public function getOverallStatusLabelAttribute(): string
    {
        if ($this->delegate_status !== null && (int) $this->delegate_status === self::STATUS_REJECTED) {
            return __('self_service.status.rejected_delegate');
        }

        if ($this->delegate_status !== null && (int) $this->delegate_status === self::STATUS_PENDING) {
            return __('self_service.status.pending_delegate');
        }

        if ((int) $this->status_hod === self::STATUS_REJECTED) {
            return __('self_service.status.rejected_hod');
        }

        if ((int) $this->status_hod === self::STATUS_PENDING) {
            return __('self_service.status.waiting_hod');
        }

        if ((int) $this->status_hrd === self::STATUS_REJECTED) {
            return __('self_service.status.rejected_hr');
        }

        if ((int) $this->status_hrd === self::STATUS_PENDING) {
            return __('self_service.status.waiting_hr');
        }

        return __('self_service.status.completed');
    }

    public function getOverallBadgeClassAttribute(): string
    {
        if (($this->delegate_status !== null && (int) $this->delegate_status === self::STATUS_REJECTED)
            || (int) $this->status_hod === self::STATUS_REJECTED
            || (int) $this->status_hrd === self::STATUS_REJECTED) {
            return 'bg-danger';
        }

        if ((int) $this->status_hod === self::STATUS_APPROVED && (int) $this->status_hrd === self::STATUS_APPROVED) {
            return 'bg-success';
        }

        return 'bg-warning text-dark';
    }

    public function getRequestTypeLabelAttribute(): string
    {
        return self::requestTypeOptions()[$this->request_type ?: self::REQUEST_TYPE_CORRECTION] ?? __('self_service.attendance_correction.request_types.correction');
    }

    public function getPartialPermissionLabelAttribute(): ?string
    {
        if (($this->request_type ?: self::REQUEST_TYPE_CORRECTION) !== self::REQUEST_TYPE_PARTIAL_PERMISSION) {
            return null;
        }

        return self::partialPermissionOptions()[$this->partial_permission_type] ?? $this->partial_permission_type;
    }

    public function getPartialPermissionPeriodLabelAttribute(): ?string
    {
        if (!$this->partial_permission_period) {
            return null;
        }

        return self::halfDayPeriodOptions()[$this->partial_permission_period] ?? $this->partial_permission_period;
    }

    public function requestedChanges(): array
    {
        $changes = [];

        if (($this->request_type ?: self::REQUEST_TYPE_CORRECTION) === self::REQUEST_TYPE_PARTIAL_PERMISSION) {
            $changes[__('self_service.attendance_correction.change_labels.type')] = $this->request_type_label;
            $changes[__('self_service.attendance_correction.change_labels.category')] = $this->partial_permission_label ?: '-';

            if ($this->partial_permission_period_label) {
                $changes[__('self_service.attendance_correction.change_labels.period')] = $this->partial_permission_period_label;
            }
        }

        foreach ([
            'jam_masuk' => __('self_service.attendance_correction.clock_in'),
            'jam_istirahat' => __('self_service.attendance_correction.break_start'),
            'jam_kembali_istirahat' => __('self_service.attendance_correction.break_end'),
            'jam_pulang' => __('self_service.attendance_correction.clock_out'),
        ] as $column => $label) {
            $attribute = 'requested_' . $column;

            if ($this->{$attribute}) {
                $changes[$label] = $this->{$attribute}->format('H:i');
            }
        }

        if ($this->change_status_presensi) {
            $changes[__('self_service.attendance_correction.change_labels.daily_status')] = $this->requested_status_presensi ?: __('self_service.attendance_correction.normal_attendance');
        }

        return $changes;
    }
}
