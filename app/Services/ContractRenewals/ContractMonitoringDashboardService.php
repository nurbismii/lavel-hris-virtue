<?php

namespace App\Services\ContractRenewals;

use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractRenewal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ContractMonitoringDashboardService
{
    private ContractRenewalService $renewalService;

    public function __construct(ContractRenewalService $renewalService)
    {
        $this->renewalService = $renewalService;
    }

    public function dashboard(User $user, array $filters = []): array
    {
        $days = $this->normalizeDays($filters['days'] ?? 30);
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 100);
        $organizationFilters = [
            'area' => $filters['area'] ?? null,
            'departemen_id' => $filters['departemen_id'] ?? null,
            'divisi_id' => $filters['divisi_id'] ?? null,
        ];

        $contractQuery = $this->contractQuery($user, $organizationFilters, $search);
        $renewalQuery = $this->renewalQuery($user, $organizationFilters, $search);
        $upcomingQuery = $this->upcomingHistoriesQuery($user, $organizationFilters, $search, $days);
        $waitingSignatureQuery = $this->waitingSignatureQuery(clone $contractQuery);

        return [
            'period' => [
                'days' => $days,
                'label' => $days . ' hari ke depan',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->addDays($days)->toDateString(),
                'updated_at' => now()->format('d M Y H:i'),
            ],
            'summary' => [
                'total_contracts' => (clone $contractQuery)->count(),
                'waiting_signature' => (clone $waitingSignatureQuery)->count(),
                'signed_this_month' => $this->signedThisMonthCount(clone $contractQuery),
                'unsigned_overdue' => $this->unsignedOverdueCount(clone $waitingSignatureQuery),
                'upcoming_without_workflow' => (clone $upcomingQuery)->count(),
                'renewal_workflows' => (clone $renewalQuery)->count(),
                'pending_hod' => $this->renewalStatusCount(clone $renewalQuery, EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL),
                'pending_hrd' => $this->renewalStatusCount(clone $renewalQuery, EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL),
            ],
            'signature_status_counts' => $this->signatureStatusCounts(clone $contractQuery),
            'contract_type_counts' => $this->contractTypeCounts(clone $contractQuery),
            'renewal_status_counts' => $this->renewalStatusCounts(clone $renewalQuery),
            'waiting_signature_contracts' => $this->waitingSignatureContracts(clone $waitingSignatureQuery),
            'upcoming_histories' => $this->upcomingHistories(clone $upcomingQuery),
            'pending_hod_renewals' => $this->pendingRenewals(clone $renewalQuery, EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL),
            'pending_hrd_renewals' => $this->pendingRenewals(clone $renewalQuery, EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL),
        ];
    }

    private function contractQuery(User $user, array $filters, string $search): Builder
    {
        $query = EmployeeContract::query()
            ->with([
                'employee:nik,nama_karyawan,area_kerja,departemen_id,divisi_id,posisi',
                'employee.departemen:id,departemen',
                'employee.divisi:id,nama_divisi',
                'template:id,name,contract_type',
            ]);

        if (!$user->canAccessAllEmployees()) {
            $user->applyEmployeeRelationScope($query, 'employee');
        }

        if ($this->hasOrganizationFilter($filters)) {
            $this->renewalService->applyOrganizationFilters($query, $filters);
        }

        if ($search !== '') {
            $nameLike = '%' . $search . '%';
            $nikLike = $search . '%';

            $query->where(function (Builder $searchQuery) use ($nameLike, $nikLike) {
                $searchQuery->where('nik', 'like', $nikLike)
                    ->orWhere('employee_nik', 'like', $nikLike)
                    ->orWhere('contract_number', 'like', $nameLike)
                    ->orWhere('pkwt_number', 'like', $nameLike)
                    ->orWhere('addendum_number', 'like', $nameLike)
                    ->orWhereHas('employee', function (Builder $employeeQuery) use ($nameLike, $nikLike) {
                        $employeeQuery->where('nik', 'like', $nikLike)
                            ->orWhere('nama_karyawan', 'like', $nameLike);
                    });
            });
        }

        return $query;
    }

    private function renewalQuery(User $user, array $filters, string $search): Builder
    {
        $query = $this->renewalService->applyOrganizationFilters(
            $this->renewalService->renewalsQuery($user),
            $filters
        );
        $this->renewalService->applyEmployeeSearch($query, $search);

        return $query;
    }

    private function upcomingHistoriesQuery(User $user, array $filters, string $search, int $days): Builder
    {
        $query = $this->renewalService->applyOrganizationFilters(
            $this->renewalService->upcomingHistoriesQuery($user, $days),
            $filters
        );
        $this->renewalService->applyEmployeeSearch($query, $search);

        return $query;
    }

    private function waitingSignatureQuery(Builder $query): Builder
    {
        return $query
            ->where('status', EmployeeContract::STATUS_READY)
            ->where('signing_method', EmployeeContract::SIGNING_METHOD_ELECTRONIC)
            ->where('signature_status', EmployeeContract::SIGNATURE_STATUS_WAITING)
            ->whereDoesntHave('signature');
    }

    private function signedThisMonthCount(Builder $query): int
    {
        return (int) $query
            ->where('signature_status', EmployeeContract::SIGNATURE_STATUS_SIGNED)
            ->whereBetween('signed_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->count();
    }

    private function unsignedOverdueCount(Builder $query): int
    {
        return (int) $query
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', Carbon::today())
            ->count();
    }

    private function renewalStatusCount(Builder $query, string $status): int
    {
        return (int) $query->where('status', $status)->count();
    }

    private function signatureStatusCounts(Builder $query): array
    {
        return $query
            ->reorder()
            ->select('signature_status', DB::raw('COUNT(*) as total'))
            ->groupBy('signature_status')
            ->pluck('total', 'signature_status')
            ->map(fn($total) => (int) $total)
            ->all();
    }

    private function contractTypeCounts(Builder $query): array
    {
        return $query
            ->reorder()
            ->select('contract_type', DB::raw('COUNT(*) as total'))
            ->groupBy('contract_type')
            ->pluck('total', 'contract_type')
            ->map(fn($total) => (int) $total)
            ->all();
    }

    private function renewalStatusCounts(Builder $query): array
    {
        return $query
            ->reorder()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn($total) => (int) $total)
            ->all();
    }

    private function waitingSignatureContracts(Builder $query)
    {
        return $query
            ->orderByRaw('COALESCE(contract_end_date, first_extension_end_date, created_at) asc')
            ->orderBy('id')
            ->limit(12)
            ->get()
            ->map(fn(EmployeeContract $contract) => [
                'contract' => $contract,
                'due_date' => $this->contractDueDate($contract),
                'days_to_due' => $this->daysTo($this->contractDueDate($contract)),
            ]);
    }

    private function upcomingHistories(Builder $query)
    {
        return $query
            ->orderBy('employee_contract_histories.contract_end_date')
            ->orderBy('employee_contract_histories.nik')
            ->limit(12)
            ->get()
            ->map(fn($history) => [
                'history' => $history,
                'days_to_due' => $this->daysTo($history->contract_end_date),
            ]);
    }

    private function pendingRenewals(Builder $query, string $status)
    {
        return $query
            ->where('status', $status)
            ->orderBy('current_contract_end_date')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    private function contractDueDate(EmployeeContract $contract): ?Carbon
    {
        $date = $contract->contract_end_date ?: $contract->first_extension_end_date;

        return $date ? Carbon::parse($date) : null;
    }

    private function daysTo($date): ?int
    {
        return $date ? Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false) : null;
    }

    private function normalizeDays($days): int
    {
        $days = (int) $days;

        return max(7, min($days > 0 ? $days : 30, 180));
    }

    private function hasOrganizationFilter(array $filters): bool
    {
        return filled($filters['area'] ?? null)
            || filled($filters['departemen_id'] ?? null)
            || filled($filters['divisi_id'] ?? null);
    }

    public function contractTypeLabel(?string $type): string
    {
        return ContractTemplate::typeOptions()[$type] ?? ($type ?: '-');
    }
}
