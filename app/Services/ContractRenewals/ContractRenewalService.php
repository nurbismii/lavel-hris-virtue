<?php

namespace App\Services\ContractRenewals;

use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractHistory;
use App\Models\EmployeeContractRenewal;
use App\Models\Perusahaan;
use App\Models\Resign;
use App\Models\User;
use App\Notifications\ContractRenewalReadyNotification;
use App\Notifications\ContractRenewalTerminatedNotification;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Audit\AuditTrailService;
use App\Services\ElectronicContracts\ElectronicContractService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContractRenewalService
{
    public const ALLOWED_COMPANY_CODES = ['VDNI', 'VDNIP'];
    private const TERMINATION_STATUS = 'PUTUS KONTRAK';

    private ElectronicContractService $contractService;
    private AuditTrailService $auditTrail;
    private ContractRenewalSalaryService $salaryService;

    public function __construct(
        ElectronicContractService $contractService,
        AuditTrailService $auditTrail,
        ContractRenewalSalaryService $salaryService
    ) {
        $this->contractService = $contractService;
        $this->auditTrail = $auditTrail;
        $this->salaryService = $salaryService;
    }

    public function upcomingHistoriesQuery(User $user, int $days = 30): Builder
    {
        $today = Carbon::today();
        $until = $today->copy()->addDays(max(1, min($days, 180)));

        $latestEndSubquery = EmployeeContractHistory::query()
            ->select('nik', DB::raw('MAX(contract_end_date) as latest_contract_end_date'))
            ->whereNotNull('contract_end_date')
            ->whereIn('history_type', [ContractTemplate::TYPE_PKWT_1, ContractTemplate::TYPE_ADDENDUM_PKWT])
            ->groupBy('nik');

        $query = EmployeeContractHistory::query()
            ->with(['employee.departemen', 'employee.divisi'])
            ->select('employee_contract_histories.*')
            ->joinSub($latestEndSubquery, 'latest_contract_histories', function ($join) {
                $join->on('employee_contract_histories.nik', '=', 'latest_contract_histories.nik')
                    ->on('employee_contract_histories.contract_end_date', '=', 'latest_contract_histories.latest_contract_end_date');
            })
            ->whereBetween('employee_contract_histories.contract_end_date', [
                $today->format('Y-m-d'),
                $until->format('Y-m-d'),
            ])
            ->whereHas('employee')
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('employee_contract_renewals')
                    ->whereColumn('employee_contract_renewals.employee_nik', 'employee_contract_histories.nik')
                    ->whereColumn('employee_contract_renewals.current_contract_end_date', 'employee_contract_histories.contract_end_date');
            });

        return $user->applyEmployeeRelationScope($query, 'employee');
    }

    public function renewalsQuery(User $user): Builder
    {
        $query = EmployeeContractRenewal::query()
            ->with([
                'employee.departemen',
                'employee.divisi',
                'currentHistory',
                'delegate.employee',
                'assessedBy.employee',
                'generatedContract.template',
            ])
            ->latest('created_at');

        if ($user->canAccessAllEmployees()) {
            return $query;
        }

        return $query->where(function (Builder $visibilityQuery) use ($user) {
            $visibilityQuery->where('delegate_user_id', (string) $user->id);

            if ($this->canManageWorkflow($user)) {
                $visibilityQuery->orWhere(function (Builder $scopedQuery) use ($user) {
                    $user->applyEmployeeRelationScope($scopedQuery, 'employee');
                });
            }
        });
    }

    public function canManageWorkflow(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi'])
            && $user->hasMenuAccess('contract_renewal');
    }

    public function canAccessIndex(User $user): bool
    {
        if ($this->canManageWorkflow($user)) {
            return true;
        }

        return EmployeeContractRenewal::query()
            ->where('delegate_user_id', (string) $user->id)
            ->exists();
    }

    public function resolveOrganizationFilters(User $user, array $input): array
    {
        $area = $this->normalizeArea($input['area'] ?? null);
        $departemenId = $this->normalizeId($input['departemen_id'] ?? null);
        $divisiId = $this->normalizeId($input['divisi_id'] ?? null);

        $departmentIds = $this->availableDepartmentIds($user, $area);

        if ($departemenId && !$departmentIds->contains($departemenId)) {
            $departemenId = null;
        }

        $divisionIds = $this->availableDivisionIds($user, $area, $departemenId);

        if ($divisiId && !$divisionIds->contains($divisiId)) {
            $divisiId = null;
        }

        return [
            'area' => $area,
            'departemen_id' => $departemenId,
            'divisi_id' => $divisiId,
        ];
    }

    public function organizationFilterOptions(User $user, array $filters): array
    {
        $companyNames = Perusahaan::query()
            ->whereIn('kode_perusahaan', self::ALLOWED_COMPANY_CODES)
            ->pluck('nama_perusahaan', 'kode_perusahaan');

        $areas = collect(self::ALLOWED_COMPANY_CODES)
            ->map(function (string $code) use ($companyNames) {
                $name = $companyNames->get($code);

                return [
                    'code' => $code,
                    'label' => $name ? "{$code} - {$name}" : $code,
                ];
            })
            ->values();

        $departemenIds = $this->availableDepartmentIds($user, $filters['area'] ?? null);
        $divisiIds = $this->availableDivisionIds(
            $user,
            $filters['area'] ?? null,
            $filters['departemen_id'] ?? null
        );

        return [
            'areas' => $areas,
            'departemens' => Departemen::query()
                ->with('perusahaan')
                ->whereIn('id', $departemenIds)
                ->orderBy('departemen')
                ->get(),
            'divisis' => Divisi::query()
                ->with('departemen.perusahaan')
                ->whereIn('id', $divisiIds)
                ->orderBy('nama_divisi')
                ->get(),
        ];
    }

    public function applyOrganizationFilters(Builder $query, array $filters, string $relation = 'employee'): Builder
    {
        return $query->whereHas($relation, function (Builder $employeeQuery) use ($filters) {
            $employeeQuery->whereIn('area_kerja', self::ALLOWED_COMPANY_CODES);

            if (filled($filters['area'] ?? null)) {
                $employeeQuery->where('area_kerja', $filters['area']);
            }

            if (filled($filters['departemen_id'] ?? null)) {
                $employeeQuery->where('departemen_id', $filters['departemen_id']);
            }

            if (filled($filters['divisi_id'] ?? null)) {
                $employeeQuery->where('divisi_id', $filters['divisi_id']);
            }
        });
    }

    public function applyEmployeeSearch(Builder $query, ?string $search, string $relation = 'employee'): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $search = mb_substr($search, 0, 100);
        $nameLike = '%' . $search . '%';
        $nikLike = $search . '%';

        return $query->whereHas($relation, function (Builder $employeeQuery) use ($nameLike, $nikLike) {
            $employeeQuery->where(function (Builder $searchQuery) use ($nameLike, $nikLike) {
                $searchQuery->where('nik', 'like', $nikLike)
                    ->orWhere('nama_karyawan', 'like', $nameLike);
            });
        });
    }

    public function contractHistoriesForNiks(array $niks): Collection
    {
        $niks = collect($niks)
            ->map(fn($nik) => trim((string) $nik))
            ->filter()
            ->unique()
            ->values();

        if ($niks->isEmpty()) {
            return collect();
        }

        $timelineByNik = collect();

        EmployeeContractHistory::query()
            ->whereIn('nik', $niks->all())
            ->orderBy('nik')
            ->orderBy('history_sequence')
            ->orderBy('contract_end_date')
            ->orderBy('id')
            ->get()
            ->each(function (EmployeeContractHistory $history) use ($timelineByNik) {
                $this->pushContractTimelineItem($timelineByNik, $this->normalizeContractHistoryItem($history));
            });

        EmployeeContract::query()
            ->whereIn('nik', $niks->all())
            ->whereIn('contract_type', [ContractTemplate::TYPE_PKWT_1, ContractTemplate::TYPE_ADDENDUM_PKWT])
            ->orderBy('nik')
            ->orderBy('contract_start_date')
            ->orderBy('contract_end_date')
            ->orderBy('id')
            ->get()
            ->each(function (EmployeeContract $contract) use ($timelineByNik) {
                $this->pushContractTimelineItem($timelineByNik, $this->normalizeElectronicContractTimelineItem($contract));
            });

        return $timelineByNik->map(function (Collection $items) {
            return $items
                ->sortBy(fn(array $item) => sprintf(
                    '%02d-%04d-%s-%010d',
                    (int) $item['type_order'],
                    (int) $item['sequence'],
                    $item['sort_date'] ?: '9999-12-31',
                    (int) $item['id']
                ))
                ->values();
        });
    }

    private function pushContractTimelineItem(Collection $timelineByNik, array $item): void
    {
        $nik = $item['nik'];
        $items = $timelineByNik->get($nik, collect());
        $matchedIndex = $items->search(fn(array $existing) => $this->contractTimelineItemsMatch($existing, $item));

        if ($matchedIndex === false) {
            $items->push($item);
            $timelineByNik->put($nik, $items);
            return;
        }

        $existing = $items->get($matchedIndex);

        foreach (['number', 'start_date', 'end_date', 'duration_label', 'signed_at', 'status_label', 'source_contract_id'] as $key) {
            if (blank($existing[$key] ?? null) && filled($item[$key] ?? null)) {
                $existing[$key] = $item[$key];
            }
        }

        if (filled($item['signed_at'] ?? null)) {
            $existing['signed_at'] = $item['signed_at'];
        }

        if (filled($item['status_label'] ?? null)) {
            $existing['status_label'] = $item['status_label'];
        }

        if (filled($item['source_contract_id'] ?? null)) {
            $existing['source_contract_id'] = $item['source_contract_id'];
        }

        $sources = collect(explode('+', (string) $existing['source']))
            ->push((string) $item['source'])
            ->filter()
            ->unique()
            ->values()
            ->implode('+');
        $existing['source'] = $sources;
        $items->put($matchedIndex, $existing);
        $timelineByNik->put($nik, $items);
    }

    private function contractTimelineItemsMatch(array $existing, array $item): bool
    {
        if ((string) $existing['nik'] !== (string) $item['nik']) {
            return false;
        }

        if ((string) $existing['history_type'] !== (string) $item['history_type']) {
            return false;
        }

        if ((int) $existing['sequence'] !== (int) $item['sequence']) {
            return false;
        }

        $existingEndDate = $this->dateKey($existing['end_date'] ?? null);
        $itemEndDate = $this->dateKey($item['end_date'] ?? null);

        if ($existingEndDate !== null && $itemEndDate !== null) {
            return $existingEndDate === $itemEndDate;
        }

        return filled($existing['number'] ?? null)
            && filled($item['number'] ?? null)
            && (string) $existing['number'] === (string) $item['number'];
    }

    private function normalizeContractHistoryItem(EmployeeContractHistory $history): array
    {
        return [
            'id' => $history->id,
            'nik' => $history->nik,
            'source' => 'history',
            'source_contract_id' => null,
            'history_type' => $history->history_type,
            'type_order' => $this->contractTimelineTypeOrder($history->history_type),
            'type_label' => $history->history_type_label,
            'raw_type' => $history->raw_history_type ?: $history->history_type_label,
            'sequence' => (int) $history->history_sequence,
            'number' => $history->contract_number,
            'start_date' => $history->entry_date,
            'end_date' => $history->contract_end_date,
            'signed_at' => null,
            'duration_label' => $history->duration_label,
            'status_label' => null,
            'sort_date' => $this->dateKey($history->contract_end_date) ?: $this->dateKey($history->entry_date),
        ];
    }

    private function normalizeElectronicContractTimelineItem(EmployeeContract $contract): array
    {
        $isAddendum = $contract->contract_type === ContractTemplate::TYPE_ADDENDUM_PKWT;
        $endDate = $isAddendum
            ? ($contract->first_extension_end_date ?: $contract->contract_end_date)
            : $contract->contract_end_date;

        return [
            'id' => $contract->id,
            'nik' => $contract->nik,
            'source' => 'electronic',
            'source_contract_id' => $contract->id,
            'history_type' => $contract->contract_type,
            'type_order' => $this->contractTimelineTypeOrder($contract->contract_type),
            'type_label' => $contract->type_label,
            'raw_type' => $isAddendum
                ? 'ADENDUM ' . ((int) $contract->addendum_sequence ?: '-')
                : 'PKWT 1',
            'sequence' => $isAddendum ? (int) $contract->addendum_sequence : 0,
            'number' => $contract->display_number,
            'start_date' => $contract->contract_start_date,
            'end_date' => $endDate,
            'signed_at' => $contract->signed_at,
            'duration_label' => $isAddendum ? $contract->first_extension_duration : $contract->contract_duration,
            'status_label' => $contract->status_label,
            'sort_date' => $this->dateKey($endDate) ?: $this->dateKey($contract->contract_start_date),
        ];
    }

    private function contractTimelineTypeOrder(?string $type): int
    {
        if ($type === ContractTemplate::TYPE_PKWT_1) {
            return 0;
        }

        if ($type === ContractTemplate::TYPE_ADDENDUM_PKWT) {
            return 1;
        }

        return 2;
    }

    private function dateKey($date): ?string
    {
        return $date ? Carbon::parse($date)->format('Y-m-d') : null;
    }

    public function createFromHistory(EmployeeContractHistory $history, User $actor): EmployeeContractRenewal
    {
        $history->loadMissing('employee');

        $this->assertCanManageEmployee($actor, $history->employee);

        if (!$history->contract_end_date) {
            throw ValidationException::withMessages([
                'history_id' => 'Tanggal akhir kontrak pada history ini belum tersedia.',
            ]);
        }

        return DB::transaction(function () use ($history, $actor) {
            $existing = EmployeeContractRenewal::query()
                ->where('employee_nik', $history->nik)
                ->whereDate('current_contract_end_date', $history->contract_end_date)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return EmployeeContractRenewal::create([
                'employee_nik' => $history->nik,
                'current_contract_history_id' => $history->id,
                'current_contract_number' => $history->contract_number,
                'current_contract_end_date' => $history->contract_end_date,
                'status' => EmployeeContractRenewal::STATUS_PENDING_DELEGATION,
                'created_by' => (string) $actor->id,
                'updated_by' => (string) $actor->id,
            ]);
        });
    }

    public function bulkCreateFromHistories(array $historyIds, User $actor, ?int $assessmentMonths = null, ?string $assessmentNote = null): array
    {
        $summary = [
            'total' => count(array_unique($historyIds)),
            'created' => 0,
            'existing' => 0,
            'assessed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        EmployeeContractHistory::query()
            ->with('employee')
            ->whereIn('id', array_unique($historyIds))
            ->orderBy('contract_end_date')
            ->orderBy('nik')
            ->get()
            ->each(function (EmployeeContractHistory $history) use ($actor, $assessmentMonths, $assessmentNote, &$summary) {
                try {
                    $existing = EmployeeContractRenewal::query()
                        ->where('employee_nik', $history->nik)
                        ->whereDate('current_contract_end_date', $history->contract_end_date)
                        ->exists();

                    $renewal = $this->createFromHistory($history, $actor);

                    if ($existing) {
                        $summary['existing']++;
                    } else {
                        $summary['created']++;
                    }

                    if ($assessmentMonths !== null) {
                        $this->assessDirectlyByHod($renewal->fresh(['employee']), $assessmentMonths, $assessmentNote, $actor);
                        $summary['assessed']++;
                    }
                } catch (ValidationException $exception) {
                    $summary['failed']++;
                    $this->appendBulkError($summary, $history, $this->firstValidationError($exception));
                } catch (\Throwable $exception) {
                    $summary['failed']++;
                    report($exception);
                    $this->appendBulkError($summary, $history, 'Gagal diproses. Periksa log aplikasi untuk detail teknis.');
                }
            });

        return $summary;
    }

    public function delegate(EmployeeContractRenewal $renewal, User $delegate, User $actor): EmployeeContractRenewal
    {
        $renewal->loadMissing('employee');
        $this->assertCanManageEmployee($actor, $renewal->employee);
        $this->assertValidDelegate($renewal, $delegate, $actor);

        if (!in_array($renewal->status, [
            EmployeeContractRenewal::STATUS_PENDING_DELEGATION,
            EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT,
        ], true)) {
            throw ValidationException::withMessages([
                'delegate_user_id' => 'Pengajuan ini sudah tidak bisa didelegasikan ulang.',
            ]);
        }

        $renewal->update([
            'delegate_user_id' => (string) $delegate->id,
            'delegated_by_user_id' => (string) $actor->id,
            'delegated_at' => now(),
            'assessment_months' => null,
            'assessment_note' => null,
            'assessed_by_user_id' => null,
            'assessed_at' => null,
            'status' => EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT,
            'updated_by' => (string) $actor->id,
        ]);

        $delegate->notify(new StatusPengajuanNotification([
            'judul' => 'Penilaian Perpanjangan Kontrak',
            'pesan' => 'Anda ditunjuk untuk menilai perpanjangan kontrak ' . optional($renewal->employee)->nama_karyawan . '.',
            'url' => route('contract-renewals.index', ['status' => EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT]),
            'tipe' => 'Perpanjangan Kontrak',
        ]));

        return $renewal->fresh();
    }

    public function assess(EmployeeContractRenewal $renewal, int $months, ?string $note, User $actor): EmployeeContractRenewal
    {
        if ((string) $renewal->delegate_user_id !== (string) $actor->id) {
            throw ValidationException::withMessages([
                'assessment_months' => 'Pengajuan ini bukan antrean penilaian Anda.',
            ]);
        }

        if ($renewal->status !== EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT) {
            throw ValidationException::withMessages([
                'assessment_months' => 'Pengajuan ini sudah tidak menunggu penilaian delegasi.',
            ]);
        }

        $this->assertValidAssessmentDecision($months);

        $renewal->update([
            'assessment_months' => $months,
            'assessment_note' => $note,
            'assessed_by_user_id' => (string) $actor->id,
            'assessed_at' => now(),
            'status' => EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL,
            'updated_by' => (string) $actor->id,
        ]);

        $this->notifyHodUsers(
            $renewal->fresh(['employee']),
            $months === EmployeeContractRenewal::ASSESSMENT_TERMINATE_CONTRACT
                ? 'Keputusan putus kontrak menunggu approval HOD.'
                : 'Penilaian perpanjangan kontrak menunggu approval HOD.'
        );

        return $renewal->fresh();
    }

    public function assessDirectlyByHod(EmployeeContractRenewal $renewal, int $months, ?string $note, User $actor): EmployeeContractRenewal
    {
        $renewal->loadMissing('employee');

        if (!$actor->hasRole(['Super Admin', 'HOD'])) {
            abort(403, 'Penilaian langsung hanya tersedia untuk HOD.');
        }

        $this->assertCanManageEmployee($actor, $renewal->employee);

        if (!in_array($renewal->status, [
            EmployeeContractRenewal::STATUS_PENDING_DELEGATION,
            EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT,
        ], true)) {
            throw ValidationException::withMessages([
                'assessment_months' => 'Pengajuan ini sudah tidak bisa dinilai langsung oleh HOD.',
            ]);
        }

        $this->assertValidAssessmentDecision($months);

        $renewal->update([
            'delegate_user_id' => null,
            'delegated_by_user_id' => null,
            'delegated_at' => null,
            'assessment_months' => $months,
            'assessment_note' => $note,
            'assessed_by_user_id' => (string) $actor->id,
            'assessed_at' => now(),
            'hod_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
            'hod_approved_by_user_id' => (string) $actor->id,
            'hod_approved_at' => now(),
            'hod_rejection_reason' => null,
            'status' => EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL,
            'updated_by' => (string) $actor->id,
        ]);

        $this->notifyHrUsers(
            $renewal->fresh(['employee']),
            $months === EmployeeContractRenewal::ASSESSMENT_TERMINATE_CONTRACT
                ? 'Keputusan putus kontrak sudah disetujui HOD dan menunggu approval HRD.'
                : 'Perpanjangan kontrak sudah dinilai HOD dan menunggu approval HRD.'
        );

        return $renewal->fresh();
    }

    public function processHod(EmployeeContractRenewal $renewal, int $action, ?string $note, User $actor): EmployeeContractRenewal
    {
        $renewal->loadMissing('employee');

        if (!$actor->hasRole(['Super Admin', 'HOD'])) {
            abort(403, 'Approval HOD hanya tersedia untuk HOD.');
        }

        $this->assertCanManageEmployee($actor, $renewal->employee);

        if ($renewal->status !== EmployeeContractRenewal::STATUS_WAITING_HOD_APPROVAL) {
            throw ValidationException::withMessages([
                'action' => 'Pengajuan ini belum siap diproses HOD.',
            ]);
        }

        if ($action === EmployeeContractRenewal::APPROVAL_REJECTED) {
            $renewal->update([
                'hod_status' => EmployeeContractRenewal::APPROVAL_REJECTED,
                'hod_approved_by_user_id' => (string) $actor->id,
                'hod_approved_at' => now(),
                'hod_rejection_reason' => $note,
                'status' => EmployeeContractRenewal::STATUS_REJECTED_BY_HOD,
                'updated_by' => (string) $actor->id,
            ]);

            return $renewal->fresh();
        }

        $renewal->update([
            'hod_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
            'hod_approved_by_user_id' => (string) $actor->id,
            'hod_approved_at' => now(),
            'hod_rejection_reason' => null,
            'status' => EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL,
            'updated_by' => (string) $actor->id,
        ]);

        $this->notifyHrUsers(
            $renewal->fresh(['employee']),
            $renewal->isTerminationDecision()
                ? 'Keputusan putus kontrak menunggu approval HRD.'
                : 'Perpanjangan kontrak menunggu approval HRD.'
        );

        return $renewal->fresh();
    }

    public function processHrd(EmployeeContractRenewal $renewal, int $action, ?string $note, User $actor): EmployeeContractRenewal
    {
        if (!$actor->hasRole(['Super Admin', 'HR'])) {
            abort(403, 'Approval HRD hanya tersedia untuk HR.');
        }

        if ($renewal->status !== EmployeeContractRenewal::STATUS_WAITING_HRD_APPROVAL) {
            throw ValidationException::withMessages([
                'action' => 'Pengajuan ini belum siap diproses HRD.',
            ]);
        }

        if ($action === EmployeeContractRenewal::APPROVAL_REJECTED) {
            $renewal->update([
                'hrd_status' => EmployeeContractRenewal::APPROVAL_REJECTED,
                'hrd_approved_by_user_id' => (string) $actor->id,
                'hrd_approved_at' => now(),
                'hrd_rejection_reason' => $note,
                'status' => EmployeeContractRenewal::STATUS_REJECTED_BY_HRD,
                'updated_by' => (string) $actor->id,
            ]);

            return $renewal->fresh();
        }

        return DB::transaction(function () use ($renewal, $actor) {
            $lockedRenewal = EmployeeContractRenewal::query()
                ->whereKey($renewal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRenewal->isTerminationDecision()) {
                $lockedRenewal->update([
                    'hrd_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
                    'hrd_approved_by_user_id' => (string) $actor->id,
                    'hrd_approved_at' => now(),
                    'hrd_rejection_reason' => null,
                    'generated_contract_id' => null,
                    'employee_notified_at' => now(),
                    'status' => EmployeeContractRenewal::STATUS_CONTRACT_TERMINATED,
                    'updated_by' => (string) $actor->id,
                ]);

                $this->notifyEmployeeContractTerminated($lockedRenewal->fresh(['employee']));

                return $lockedRenewal->fresh(['generatedContract']);
            }

            if ($lockedRenewal->generated_contract_id) {
                return $lockedRenewal->fresh();
            }

            $contract = $this->generateElectronicAddendum($lockedRenewal, $actor);
            $this->recordGeneratedHistory($lockedRenewal, $contract, $actor);

            $lockedRenewal->update([
                'hrd_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
                'hrd_approved_by_user_id' => (string) $actor->id,
                'hrd_approved_at' => now(),
                'hrd_rejection_reason' => null,
                'generated_contract_id' => $contract->id,
                'employee_notified_at' => now(),
                'status' => EmployeeContractRenewal::STATUS_CONTRACT_GENERATED,
                'updated_by' => (string) $actor->id,
            ]);

            $this->notifyEmployeeContractReady($lockedRenewal->fresh(['employee', 'generatedContract']));

            return $lockedRenewal->fresh(['generatedContract']);
        });
    }

    public function reviseTerminationToRenewal(EmployeeContractRenewal $renewal, int $months, string $revisionNote, User $actor): EmployeeContractRenewal
    {
        if (!$actor->hasRole(['Super Admin', 'HR'])) {
            abort(403, 'Revisi putus kontrak hanya tersedia untuk HR.');
        }

        if ($months < 1 || $months > 12) {
            throw ValidationException::withMessages([
                'assessment_months' => 'Durasi perpanjangan hasil revisi hanya boleh 1 sampai 12 bulan.',
            ]);
        }

        $revisionNote = trim($revisionNote);

        if ($revisionNote === '') {
            throw ValidationException::withMessages([
                'revision_note' => 'Alasan revisi putus kontrak wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($renewal, $months, $revisionNote, $actor) {
            $lockedRenewal = EmployeeContractRenewal::query()
                ->with(['employee', 'currentHistory', 'generatedContract'])
                ->whereKey($renewal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRenewal->status !== EmployeeContractRenewal::STATUS_CONTRACT_TERMINATED
                || !$lockedRenewal->isTerminationDecision()) {
                throw ValidationException::withMessages([
                    'revision_note' => 'Hanya workflow PUTUS KONTRAK final yang bisa direvisi menjadi perpanjangan.',
                ]);
            }

            if ($lockedRenewal->generated_contract_id) {
                throw ValidationException::withMessages([
                    'revision_note' => 'Workflow ini sudah memiliki kontrak elektronik dan tidak bisa direvisi dari putus kontrak.',
                ]);
            }

            $this->assertCanManageEmployee($actor, $lockedRenewal->employee);

            $oldRenewalValues = $this->renewalAuditValues($lockedRenewal);
            $employeeSync = $this->restoreEmployeeForTerminationRevision($lockedRenewal, $revisionNote);

            $lockedRenewal->forceFill([
                'assessment_months' => $months,
                'assessment_note' => $revisionNote,
                'assessed_by_user_id' => (string) $actor->id,
                'assessed_at' => now(),
                'hod_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
                'hod_approved_by_user_id' => (string) $actor->id,
                'hod_approved_at' => now(),
                'hod_rejection_reason' => null,
                'hrd_status' => EmployeeContractRenewal::APPROVAL_APPROVED,
                'hrd_approved_by_user_id' => (string) $actor->id,
                'hrd_approved_at' => now(),
                'hrd_rejection_reason' => null,
                'termination_revised_at' => now(),
                'termination_revised_by_user_id' => (string) $actor->id,
                'termination_revision_note' => $revisionNote,
                'employee_status_sync_note' => 'Putus kontrak dibatalkan melalui revisi perpanjangan kontrak.',
                'updated_by' => (string) $actor->id,
            ]);

            $contract = $this->generateElectronicAddendum($lockedRenewal, $actor);
            $this->recordGeneratedHistory($lockedRenewal, $contract, $actor);

            $lockedRenewal->forceFill([
                'generated_contract_id' => $contract->id,
                'employee_notified_at' => now(),
                'status' => EmployeeContractRenewal::STATUS_CONTRACT_GENERATED,
            ])->save();

            $this->auditTrail->record([
                'event' => 'contract_renewal.termination_revised_to_renewal',
                'module' => 'contract_renewal',
                'auditable_type' => EmployeeContractRenewal::class,
                'auditable_id' => (string) $lockedRenewal->id,
                'reference_table' => 'employee_contract_renewals',
                'reference_id' => (string) $lockedRenewal->id,
                'employee_nik' => (string) $lockedRenewal->employee_nik,
                'actor' => $actor,
                'old_values' => [
                    'renewal' => $oldRenewalValues,
                    'employee' => $employeeSync['old_values'],
                ],
                'new_values' => [
                    'renewal' => $this->renewalAuditValues($lockedRenewal->fresh()),
                    'employee' => $employeeSync['new_values'],
                    'generated_contract_id' => $contract->id,
                ],
                'metadata' => [
                    'new_assessment_months' => $months,
                    'employee_status_restored' => $employeeSync['changed'],
                ],
                'note' => $revisionNote,
            ]);

            $this->notifyEmployeeContractReady($lockedRenewal->fresh(['employee', 'generatedContract']));

            return $lockedRenewal->fresh(['employee', 'generatedContract']);
        });
    }

    public function delegateCandidates(EmployeeContractRenewal $renewal, User $actor, ?string $term = null, int $limit = 50): Collection
    {
        $renewal->loadMissing('employee');
        $this->assertCanManageEmployee($actor, $renewal->employee);
        $employee = $renewal->employee;

        if (!$employee) {
            return collect();
        }

        if (blank($employee->departemen_id) && blank($employee->divisi_id)) {
            return collect();
        }

        return User::query()
            ->with('employee:nik,nama_karyawan,departemen_id,divisi_id,posisi,status_resign,area_kerja')
            ->whereNotNull('nik_karyawan')
            ->where('id', '!=', (string) $actor->id)
            ->when($actor->nik_karyawan, fn(Builder $query) => $query->where('nik_karyawan', '!=', $actor->nik_karyawan))
            ->where(function (Builder $statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->when($term, function (Builder $query) use ($term) {
                $like = '%' . trim($term) . '%';

                $query->where(function (Builder $searchQuery) use ($like) {
                    $searchQuery->where('name', 'like', $like)
                        ->orWhere('nik_karyawan', 'like', $like)
                        ->orWhereHas('employee', function (Builder $employeeQuery) use ($like) {
                            $employeeQuery->where('nama_karyawan', 'like', $like)
                                ->orWhere('posisi', 'like', $like);
                        });
                });
            })
            ->whereHas('employee', function (Builder $employeeQuery) use ($employee) {
                $employeeQuery
                    ->where('status_resign', 'AKTIF')
                    ->whereIn('area_kerja', self::ALLOWED_COMPANY_CODES)
                    ->where(function (Builder $scopeQuery) use ($employee) {
                        if (filled($employee->divisi_id)) {
                            $scopeQuery->orWhere('divisi_id', $employee->divisi_id);
                        }

                        if (filled($employee->departemen_id)) {
                            $scopeQuery->orWhere('departemen_id', $employee->departemen_id);
                        }
                    });
            })
            ->orderBy('name')
            ->limit(max(1, min($limit * 2, 500)))
            ->get()
            ->take(max(1, min($limit, 500)))
            ->values();
    }

    private function restoreEmployeeForTerminationRevision(EmployeeContractRenewal $renewal, string $revisionNote): array
    {
        $employee = $renewal->employee;

        if (!$employee) {
            return [
                'changed' => false,
                'old_values' => [],
                'new_values' => [],
            ];
        }

        $oldValues = $this->employeeStatusAuditValues($employee);
        $endDate = Carbon::parse($renewal->current_contract_end_date)->format('Y-m-d');
        $status = strtoupper(trim((string) $employee->status_resign));

        $this->markMatchingResignRecordAsRevised($renewal, $revisionNote);

        if ($status === '' || $status === 'AKTIF') {
            return [
                'changed' => false,
                'old_values' => $oldValues,
                'new_values' => $oldValues,
            ];
        }

        if ($status !== self::TERMINATION_STATUS) {
            throw ValidationException::withMessages([
                'revision_note' => 'Status karyawan sudah ' . $employee->status_resign . ', sehingga revisi otomatis tidak bisa mengubahnya menjadi AKTIF.',
            ]);
        }

        $employeeResignDate = $employee->tgl_resign
            ? Carbon::parse($employee->tgl_resign)->format('Y-m-d')
            : null;

        if ($employeeResignDate && $employeeResignDate !== $endDate) {
            throw ValidationException::withMessages([
                'revision_note' => 'Tanggal resign karyawan tidak sama dengan tanggal akhir kontrak workflow ini. Revisi perlu diproses manual oleh HRD.',
            ]);
        }

        $employee->forceFill([
            'status_resign' => 'AKTIF',
            'tgl_resign' => null,
            'alasan_resign' => null,
            'kategori_keluar' => null,
        ])->save();

        return [
            'changed' => true,
            'old_values' => $oldValues,
            'new_values' => $this->employeeStatusAuditValues($employee->fresh()),
        ];
    }

    private function markMatchingResignRecordAsRevised(EmployeeContractRenewal $renewal, string $revisionNote): void
    {
        if (!Schema::hasTable('resign')) {
            return;
        }

        $endDate = Carbon::parse($renewal->current_contract_end_date)->format('Y-m-d');

        $resign = Resign::query()
            ->where('nik_karyawan', $renewal->employee_nik)
            ->whereDate('tanggal_keluar', $endDate)
            ->whereRaw('UPPER(TRIM(COALESCE(tipe, ""))) = ?', [self::TERMINATION_STATUS])
            ->lockForUpdate()
            ->first();

        if (!$resign) {
            return;
        }

        $payload = [
            'alasan_keluar' => Str::limit('DIBATALKAN KARENA REVISI PERPANJANGAN KONTRAK: ' . $revisionNote, 500, ''),
        ];

        if (Schema::hasColumn('resign', 'flg_kirim')) {
            $payload['flg_kirim'] = 1;
        }

        $resign->forceFill($payload)->save();
    }

    private function employeeStatusAuditValues(?Employee $employee): array
    {
        if (!$employee) {
            return [];
        }

        return [
            'status_resign' => $employee->status_resign,
            'tgl_resign' => optional($employee->tgl_resign)->format('Y-m-d'),
            'alasan_resign' => $employee->alasan_resign,
            'kategori_keluar' => $employee->kategori_keluar,
        ];
    }

    private function renewalAuditValues(?EmployeeContractRenewal $renewal): array
    {
        if (!$renewal) {
            return [];
        }

        return [
            'status' => $renewal->status,
            'assessment_months' => $renewal->assessment_months,
            'assessment_note' => $renewal->assessment_note,
            'hod_status' => $renewal->hod_status,
            'hrd_status' => $renewal->hrd_status,
            'generated_contract_id' => $renewal->generated_contract_id,
            'employee_notified_at' => optional($renewal->employee_notified_at)->format('Y-m-d H:i:s'),
            'employee_status_synced_at' => optional($renewal->employee_status_synced_at)->format('Y-m-d H:i:s'),
            'employee_status_sync_note' => $renewal->employee_status_sync_note,
            'termination_revised_at' => optional($renewal->termination_revised_at)->format('Y-m-d H:i:s'),
            'termination_revised_by_user_id' => $renewal->termination_revised_by_user_id,
            'termination_revision_note' => $renewal->termination_revision_note,
        ];
    }

    private function generateElectronicAddendum(EmployeeContractRenewal $renewal, User $actor): EmployeeContract
    {
        $renewal->loadMissing(['employee', 'currentHistory']);
        $employee = $renewal->employee;

        if (!$renewal->isRenewalDecision()) {
            throw ValidationException::withMessages([
                'action' => 'Adendum elektronik hanya bisa dibuat untuk keputusan perpanjangan 1 sampai 12 bulan.',
            ]);
        }

        if (!$employee) {
            throw ValidationException::withMessages([
                'action' => 'Data karyawan untuk pengajuan ini tidak ditemukan.',
            ]);
        }

        $template = ContractTemplate::query()
            ->where('contract_type', ContractTemplate::TYPE_ADDENDUM_PKWT)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $currentEndDate = Carbon::parse($renewal->current_contract_end_date);
        $newEndDate = $currentEndDate->copy()->addMonthsNoOverflow((int) $renewal->assessment_months);
        $history = $renewal->currentHistory;

        return $this->contractService->createContract([
            'nik' => $renewal->employee_nik,
            'contract_type' => ContractTemplate::TYPE_ADDENDUM_PKWT,
            'contract_template_id' => $template->id,
            'signing_method' => EmployeeContract::SIGNING_METHOD_ELECTRONIC,
            'contract_number' => $renewal->current_contract_number,
            'contract_code' => 'RENEWAL-' . $renewal->id,
            'pkwt_number' => $renewal->current_contract_number ?: 'PKWT-' . $renewal->employee_nik,
            'gender' => $employee->jenis_kelamin,
            'marital_status' => $employee->status_pernikahan ?: optional($history)->marital_status,
            'address' => $employee->alamat_domisili ?: $employee->alamat_ktp,
            'position' => $employee->posisi,
            'contract_duration' => optional($history)->duration_label ?: null,
            'contract_start_date' => optional(optional($history)->entry_date)->format('Y-m-d') ?: optional($employee->entry_date)->format('Y-m-d'),
            'contract_end_date' => $currentEndDate->format('Y-m-d'),
            'first_extension_duration' => (int) $renewal->assessment_months . ' bulan',
            'first_extension_end_date' => $newEndDate->format('Y-m-d'),
            'salary' => $this->salaryService->resolveSalary($renewal),
            'meal_allowance' => 0,
            'clause_key' => ContractClause::KEY_CLAUSE_1,
        ], $actor);
    }

    private function recordGeneratedHistory(EmployeeContractRenewal $renewal, EmployeeContract $contract, User $actor): void
    {
        if (!$contract->addendum_sequence || !$contract->first_extension_end_date) {
            return;
        }

        EmployeeContractHistory::query()->updateOrCreate(
            [
                'nik' => $renewal->employee_nik,
                'history_sequence' => (int) $contract->addendum_sequence,
                'contract_number' => $contract->pkwt_number,
                'contract_end_date' => $contract->first_extension_end_date->format('Y-m-d'),
            ],
            [
                'employee_name' => optional($renewal->employee)->nama_karyawan,
                'marital_status' => $contract->marital_status,
                'employee_status' => 'PKWT',
                'entry_date' => optional($contract->contract_start_date)->format('Y-m-d'),
                'history_type' => ContractTemplate::TYPE_ADDENDUM_PKWT,
                'raw_history_type' => 'ADENDUM ' . $contract->addendum_sequence,
                'duration_months' => (int) $renewal->assessment_months,
                'duration_label' => (int) $renewal->assessment_months . ' bulan',
                'created_by' => (string) $actor->id,
            ]
        );
    }

    private function notifyEmployeeContractReady(EmployeeContractRenewal $renewal): void
    {
        $contract = $renewal->generatedContract;

        if (!$contract) {
            return;
        }

        $user = User::query()
            ->where('nik_karyawan', $renewal->employee_nik)
            ->first();

        if ($user) {
            $user->notify(new ContractRenewalReadyNotification($contract->id));
        }
    }

    private function notifyEmployeeContractTerminated(EmployeeContractRenewal $renewal): void
    {
        $user = User::query()
            ->where('nik_karyawan', $renewal->employee_nik)
            ->first();

        if ($user) {
            $user->notify(new ContractRenewalTerminatedNotification($renewal->id));
        }
    }

    private function notifyHodUsers(EmployeeContractRenewal $renewal, string $message): void
    {
        $this->notifyScopedApprovers($renewal, ['HOD', 'Super Admin'], 'Approval HOD Perpanjangan Kontrak', $message);
    }

    private function notifyHrUsers(EmployeeContractRenewal $renewal, string $message): void
    {
        $this->notifyScopedApprovers($renewal, ['HR', 'Super Admin'], 'Approval HRD Perpanjangan Kontrak', $message);
    }

    private function notifyScopedApprovers(EmployeeContractRenewal $renewal, array $roles, string $title, string $message): void
    {
        User::query()
            ->with('role')
            ->whereNotNull('role_id')
            ->get()
            ->filter(fn(User $user) => $user->hasRole($roles) && $user->hasMenuAccess('contract_renewal'))
            ->filter(function (User $user) use ($renewal) {
                if ($user->canAccessAllEmployees()) {
                    return true;
                }

                return $user->applyEmployeeScope(
                    Employee::query()->whereKey($renewal->employee_nik)
                )->exists();
            })
            ->each(function (User $user) use ($title, $message) {
                $user->notify(new StatusPengajuanNotification([
                    'judul' => $title,
                    'pesan' => $message,
                    'url' => route('contract-renewals.index'),
                    'tipe' => 'Perpanjangan Kontrak',
                ]));
            });
    }

    private function assertValidAssessmentDecision(int $months): void
    {
        if ($months < EmployeeContractRenewal::ASSESSMENT_TERMINATE_CONTRACT || $months > 12) {
            throw ValidationException::withMessages([
                'assessment_months' => 'Pilihan hanya boleh 1 sampai 12 bulan atau PUTUS KONTRAK.',
            ]);
        }
    }

    private function assertCanManageEmployee(User $user, ?Employee $employee): void
    {
        if (!$employee) {
            throw ValidationException::withMessages([
                'employee' => 'Data karyawan tidak ditemukan.',
            ]);
        }

        $exists = $user->applyEmployeeScope(
            Employee::query()->whereKey($employee->nik)
        )->exists();

        if (!$exists) {
            abort(403, 'Karyawan ini berada di luar scope akses Anda.');
        }
    }

    private function assertValidDelegate(EmployeeContractRenewal $renewal, User $delegate, User $actor): void
    {
        if ((string) $delegate->id === (string) $actor->id) {
            throw ValidationException::withMessages([
                'delegate_user_id' => 'Pilih delegasi selain diri sendiri.',
            ]);
        }

        if ($actor->nik_karyawan && (string) $delegate->nik_karyawan === (string) $actor->nik_karyawan) {
            throw ValidationException::withMessages([
                'delegate_user_id' => 'Pilih delegasi selain diri sendiri.',
            ]);
        }

        $available = $this->delegateCandidates($renewal, $actor, null, 100)
            ->contains(fn(User $candidate) => (string) $candidate->id === (string) $delegate->id);

        if (!$available) {
            throw ValidationException::withMessages([
                'delegate_user_id' => 'Delegasi tidak berada dalam scope departemen/divisi kontrak ini.',
            ]);
        }
    }

    private function normalizeArea($area): ?string
    {
        $area = trim((string) $area);

        return in_array($area, self::ALLOWED_COMPANY_CODES, true) ? $area : null;
    }

    private function normalizeId($id): ?string
    {
        $id = trim((string) $id);

        return $id !== '' ? $id : null;
    }

    private function scopedEmployeeQuery(User $user, ?string $area = null): Builder
    {
        return $user->applyEmployeeScope(Employee::query())
            ->whereIn('area_kerja', self::ALLOWED_COMPANY_CODES)
            ->when($area, fn(Builder $query) => $query->where('area_kerja', $area));
    }

    private function availableDepartmentIds(User $user, ?string $area = null): Collection
    {
        return $this->scopedEmployeeQuery($user, $area)
            ->select('departemen_id')
            ->distinct()
            ->pluck('departemen_id')
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values();
    }

    private function availableDivisionIds(User $user, ?string $area = null, ?string $departemenId = null): Collection
    {
        return $this->scopedEmployeeQuery($user, $area)
            ->when($departemenId, fn(Builder $query) => $query->where('departemen_id', $departemenId))
            ->select('divisi_id')
            ->distinct()
            ->pluck('divisi_id')
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values();
    }

    private function appendBulkError(array &$summary, EmployeeContractHistory $history, string $message): void
    {
        if (count($summary['errors']) >= 5) {
            return;
        }

        $summary['errors'][] = trim($history->nik . ' - ' . ($history->employee_name ?: optional($history->employee)->nama_karyawan) . ': ' . $message);
    }

    private function firstValidationError(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (!empty($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'Data tidak valid.';
    }
}
