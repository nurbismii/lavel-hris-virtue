<?php

namespace App\Services\Approvals;

use Illuminate\Database\Eloquent\Builder;

class ApprovalListService
{
    public function paginate(Builder $query, array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->whereHas('employee', function (Builder $employee) use ($search) {
                $employee->where(function (Builder $match) use ($search) {
                    $match->where('nik', 'like', $search . '%')
                        ->orWhere('nama_karyawan', 'like', '%' . $search . '%');
                });
            });
        }

        return $query->orderByDesc($query->getModel()->getQualifiedKeyName())
            ->paginate(25)->withQueryString();
    }
}
