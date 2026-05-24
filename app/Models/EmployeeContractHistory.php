<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContractHistory extends Model
{
    public const TYPE_PKWT_1 = 'pkwt_1';
    public const TYPE_ADDENDUM_PKWT = 'addendum_pkwt';
    public const TYPE_OTHER = 'other';

    protected $guarded = [];

    protected $casts = [
        'entry_date' => 'date',
        'contract_end_date' => 'date',
        'renewal_notice_sent_at' => 'datetime',
        'history_sequence' => 'integer',
        'duration_months' => 'integer',
        'source_import_history_id' => 'integer',
        'source_row_number' => 'integer',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PKWT_1 => 'PKWT 1',
            self::TYPE_ADDENDUM_PKWT => 'Adendum PKWT',
            self::TYPE_OTHER => 'Lainnya',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik', 'nik');
    }

    public function importHistory()
    {
        return $this->belongsTo(ImportHistory::class, 'source_import_history_id');
    }

    public function getHistoryTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->history_type] ?? $this->history_type;
    }
}
