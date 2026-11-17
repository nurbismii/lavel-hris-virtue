<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeveranceRequest extends Model
{
    protected $guarded = [];
    protected $hidden = ['salary_snapshot', 'calculation', 'decisions', 'source_metadata'];
    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'calculation_start' => 'date:Y-m-d',
        'termination_date' => 'date:Y-m-d',
        'return_fare' => 'decimal:2',
        'leave_days' => 'decimal:2',
        'salary_snapshot' => 'array',
        'calculation' => 'array',
        'decisions' => 'array',
        'source_metadata' => 'array',
        'revision' => 'integer',
        'payroll_approved_at' => 'datetime',
        'hi_approved_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            'draft' => 'Draf / perlu revisi',
            'payroll_review' => 'Menunggu Supervisor Comben',
            'hi_review' => 'Menunggu Supervisor HI',
            'approved' => 'Disetujui final'
        ];
    }

    public function batch()
    {
        return $this->belongsTo(ImportHistory::class, 'import_history_id');
    }

    public function canApprovePayroll(User $actor): bool
    {
        return $actor->hasMenuAccess('severance_payroll')
            && ($actor->hasRole('Super Admin') || (string) $this->created_by !== (string) $actor->id);
    }

    public function canApproveHi(User $actor): bool
    {
        return $actor->hasMenuAccess('severance_hi')
            && ($actor->hasRole('Super Admin') || ((string) $this->created_by !== (string) $actor->id
                && (string) $this->payroll_approved_by !== (string) $actor->id));
    }

    public function scopeVisibleTo($query, User $actor)
    {
        if ($actor->hasRole('Super Admin')) {
            return $query;
        }
        return $query->where(function ($query) use ($actor) {
            $query->where('created_by', (string) $actor->id);
            if ($actor->hasMenuAccess(['severance_payroll', 'severance_hi'])) {
                $query->orWhere('status', '!=', 'draft');
            }
        });
    }
}
