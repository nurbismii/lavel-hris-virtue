<?php

namespace App\Services\CvMaker;

use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\JobTitleAlias;
use App\Models\OrganizationPosition;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\Organization\OrganizationStructureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class CvMakerOrganizationSyncService
{
    private $organizationService;

    public function __construct(OrganizationStructureService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    public function preview(Employee $employee, array $cvProfile): array
    {
        $context = $this->resolveContext($employee, $cvProfile);

        if (!$context['eligible']) {
            return [
                'changes' => [],
                'skipped' => $context['reason'] ? [[
                    'label' => 'Struktur organisasi',
                    'reason' => $context['reason'],
                ]] : [],
            ];
        }

        if ($context['current']) {
            return ['changes' => [], 'skipped' => []];
        }

        if ($context['hierarchy_change']) {
            $action = 'Menyusun parent berdasarkan level ke ' . $context['suggested_parent']->display_name;
        } else {
            $action = $context['position']
                ? 'Menempatkan karyawan pada posisi organisasi yang sudah ada'
                : 'Membuat posisi organisasi dan menempatkan karyawan';
        }

        if (!$context['job_title']) {
            $action = 'Membuat master jabatan, posisi organisasi, dan menempatkan karyawan';
        }

        if (!$context['position'] && $context['suggested_parent']) {
            $action .= '; parent otomatis: ' . $context['suggested_parent']->display_name;
        }

        return [
            'changes' => [[
                'key' => 'organization_structure',
                'label' => 'Struktur organisasi',
                'old' => optional($employee->organizationPosition)->display_name ?: 'Belum ditempatkan',
                'new' => $context['position_name'] . ' — ' . $context['job_title_name'] . ' (' . $action . ')',
            ]],
            'skipped' => [],
        ];
    }

    public function sync(Employee $employee, array $cvProfile, User $actor): array
    {
        $context = $this->resolveContext($employee, $cvProfile);

        if (!$context['eligible']) {
            return ['synced' => false, 'reason' => $context['reason']];
        }

        if ($context['current']) {
            return [
                'synced' => false,
                'reason' => 'Penempatan organisasi sudah sesuai.',
                'position_id' => $context['position']->id,
            ];
        }

        $jobTitle = $context['job_title'] ?: $this->createJobTitle($context, $actor);
        $position = $context['position'] ?: $this->createPosition($context, $jobTitle, $actor);
        $hierarchyUpdated = false;

        if ($context['position'] && $context['hierarchy_change']) {
            $position = $this->setParent($position, $context['suggested_parent'], $actor);
            $hierarchyUpdated = true;
        }

        $assignment = null;

        if (!$context['placement_current']) {
            $assignment = $this->organizationService->assignEmployee(
                $position,
                (string) $employee->nik,
                $this->assignmentEffectiveDate($employee, $cvProfile),
                $actor,
                null,
                'Dibuat otomatis saat update hasil compare CV Maker (Vitae).'
            );
        }

        $reconciledPositionIds = $this->reconcileVitaeOrphans(
            $context['company_id'],
            $context['department_id'],
            $actor
        );

        return [
            'synced' => true,
            'position_id' => $position->id,
            'assignment_id' => $assignment ? $assignment->id : null,
            'created_job_title' => !$context['job_title'],
            'created_position' => !$context['position'],
            'hierarchy_updated' => $hierarchyUpdated || !empty($reconciledPositionIds),
            'reconciled_position_ids' => $reconciledPositionIds,
        ];
    }

    private function resolveContext(Employee $employee, array $cvProfile): array
    {
        $empty = [
            'eligible' => false,
            'reason' => null,
            'current' => false,
            'job_title' => null,
            'position' => null,
            'placement_current' => false,
            'hierarchy_change' => false,
            'suggested_parent' => null,
        ];

        if (!$this->schemaAvailable()) {
            return array_merge($empty, ['reason' => 'Tabel struktur organisasi belum tersedia. Jalankan migration terlebih dahulu.']);
        }

        $jobTitleName = trim((string) ($cvProfile['job_title'] ?? ''));
        $positionName = trim((string) ($cvProfile['position'] ?? '')) ?: $jobTitleName;

        if ($jobTitleName === '' || $positionName === '') {
            return array_merge($empty, ['reason' => 'Jabatan atau posisi Vitae masih kosong.']);
        }

        $employee->loadMissing(['departemen.perusahaan', 'organizationPosition.jobTitle']);
        $department = $employee->departemen;
        $company = $department ? $department->perusahaan : null;

        if (!$department || !$company) {
            return array_merge($empty, ['reason' => 'Perusahaan atau departemen karyawan belum valid di V-People.']);
        }

        if (!in_array($company->kode_perusahaan, Perusahaan::ORGANIZATION_COMPANY_CODES, true)) {
            return array_merge($empty, ['reason' => 'Struktur organisasi otomatis hanya berlaku untuk VDNI dan VDNIP.']);
        }

        $jobTitle = $this->findJobTitle($jobTitleName, $cvProfile);
        $level = $jobTitle ? $jobTitle->level : $this->findLevel($cvProfile);

        if (!$jobTitle && !$level) {
            return array_merge($empty, ['reason' => 'Level jabatan Vitae tidak dapat dipetakan ke master level V-People.']);
        }

        $position = $jobTitle
            ? $this->findPosition($employee, (int) $company->id, $jobTitle, $positionName)
            : null;

        if ($position && !$position->is_active) {
            return array_merge($empty, ['reason' => 'Posisi organisasi yang cocok sedang nonaktif dan tidak diaktifkan otomatis.']);
        }

        $activeAssignment = EmployeePositionAssignment::query()
            ->activeOn()
            ->where('employee_nik', (string) $employee->nik)
            ->first();
        $placementCurrent = $position
            && $activeAssignment
            && (int) $activeAssignment->organization_position_id === (int) $position->id;
        $suggestedParent = $this->findSuggestedParent(
            (int) $company->id,
            (int) $department->id,
            $employee->divisi_id ? (int) $employee->divisi_id : null,
            $level,
            $position ? (int) $position->id : null
        );
        $hierarchyChange = $position
            && !$position->parent_position_id
            && $suggestedParent;

        return [
            'eligible' => true,
            'reason' => null,
            'current' => (bool) $placementCurrent && !$hierarchyChange,
            'placement_current' => (bool) $placementCurrent,
            'hierarchy_change' => (bool) $hierarchyChange,
            'suggested_parent' => $suggestedParent,
            'job_title' => $jobTitle,
            'job_title_name' => $jobTitleName,
            'level' => $level,
            'position' => $position,
            'position_name' => $positionName,
            'company_id' => (int) $company->id,
            'department_id' => (int) $department->id,
            'division_id' => $employee->divisi_id ? (int) $employee->divisi_id : null,
        ];
    }

    private function findJobTitle(string $name, array $cvProfile): ?JobTitle
    {
        $normalized = $this->organizationService->normalizeTitle($name);
        $jobTitleId = isset($cvProfile['job_title_id']) && is_numeric($cvProfile['job_title_id'])
            ? (int) $cvProfile['job_title_id']
            : null;

        $byName = JobTitle::query()
            ->with('level')
            ->where('is_active', true)
            ->where('normalized_name', $normalized)
            ->first();

        if ($byName) {
            return $byName;
        }

        $alias = JobTitleAlias::query()
            ->with('jobTitle.level')
            ->where('normalized_alias', $normalized)
            ->first();

        if ($alias && optional($alias->jobTitle)->is_active) {
            return $alias->jobTitle;
        }

        if (!$jobTitleId) {
            return null;
        }

        $byId = JobTitle::query()->with('level')->where('is_active', true)->find($jobTitleId);

        if (!$byId) {
            return null;
        }

        return $this->organizationService->normalizeTitle($byId->name) === $normalized ? $byId : null;
    }

    private function findLevel(array $cvProfile): ?JobLevel
    {
        $code = trim((string) ($cvProfile['job_level_code'] ?? ''));

        if ($code !== '') {
            $level = JobLevel::query()->where('is_active', true)->where('code', $code)->first();

            if ($level) {
                return $level;
            }
        }

        if (isset($cvProfile['job_level_rank']) && is_numeric($cvProfile['job_level_rank'])) {
            return JobLevel::query()
                ->where('is_active', true)
                ->where('rank', (int) $cvProfile['job_level_rank'])
                ->first();
        }

        return null;
    }

    private function findPosition(Employee $employee, int $companyId, JobTitle $jobTitle, string $positionName): ?OrganizationPosition
    {
        $normalized = $this->organizationService->normalizeTitle($positionName);

        return OrganizationPosition::query()
            ->where('perusahaan_id', $companyId)
            ->where('departemen_id', $employee->departemen_id)
            ->where(function ($query) use ($employee) {
                $employee->divisi_id
                    ? $query->where('divisi_id', $employee->divisi_id)
                    : $query->whereNull('divisi_id');
            })
            ->where('job_title_id', $jobTitle->id)
            ->get()
            ->first(function (OrganizationPosition $position) use ($normalized) {
                return $this->organizationService->normalizeTitle($position->display_name) === $normalized;
            });
    }

    private function createJobTitle(array $context, User $actor): JobTitle
    {
        $code = 'VITAE_' . strtoupper(substr(sha1($this->organizationService->normalizeTitle($context['job_title_name'])), 0, 16));

        return $this->organizationService->saveJobTitle([
            'code' => $code,
            'name' => $context['job_title_name'],
            'name_zh' => null,
            'job_level_id' => $context['level']->id,
            'is_active' => true,
            'description' => 'Dibuat otomatis dari hasil compare CV Maker (Vitae).',
        ], [$context['job_title_name']], $actor);
    }

    private function createPosition(array $context, JobTitle $jobTitle, User $actor): OrganizationPosition
    {
        $identity = implode('|', [
            $context['company_id'],
            $context['department_id'],
            $context['division_id'] ?: 0,
            $jobTitle->id,
            $this->organizationService->normalizeTitle($context['position_name']),
        ]);

        return $this->organizationService->savePosition([
            'code' => 'VITAE_' . strtoupper(substr(sha1($identity), 0, 20)),
            'position_name' => $context['position_name'],
            'perusahaan_id' => $context['company_id'],
            'departemen_id' => $context['department_id'],
            'divisi_id' => $context['division_id'],
            'job_title_id' => $jobTitle->id,
            'job_level_id' => null,
            'parent_position_id' => $context['suggested_parent'] ? $context['suggested_parent']->id : null,
            'planned_headcount' => 1,
            'sort_order' => 0,
            'is_active' => true,
            'effective_from' => Carbon::today()->toDateString(),
            'effective_until' => null,
            'notes' => 'Dibuat otomatis dari hasil compare CV Maker (Vitae). Atasan struktural perlu diverifikasi HR.',
        ], $actor);
    }

    private function findSuggestedParent(
        int $companyId,
        int $departmentId,
        ?int $divisionId,
        ?JobLevel $childLevel,
        ?int $excludePositionId = null
    ): ?OrganizationPosition {
        if (!$childLevel) {
            return null;
        }

        $baseQuery = OrganizationPosition::query()
            ->where('perusahaan_id', $companyId)
            ->where('departemen_id', $departmentId)
            ->where('is_active', true)
            ->currentlyEffective()
            ->with(['jobTitle.level', 'levelOverride']);

        if ($excludePositionId) {
            $baseQuery->where('id', '<>', $excludePositionId);
        }

        if ($divisionId) {
            $sameDivision = $this->nearestUniqueHigherPosition(
                (clone $baseQuery)->where('divisi_id', $divisionId)->get(),
                (int) $childLevel->rank
            );

            if ($sameDivision['has_candidates']) {
                return $sameDivision['position'];
            }
        }

        $departmentLevel = $this->nearestUniqueHigherPosition(
            (clone $baseQuery)->whereNull('divisi_id')->get(),
            (int) $childLevel->rank
        );

        return $departmentLevel['position'];
    }

    private function nearestUniqueHigherPosition($positions, int $childRank): array
    {
        $eligible = $positions
            ->filter(function (OrganizationPosition $position) use ($childRank) {
                $level = $position->effective_level;

                return $level && (int) $level->rank > $childRank;
            });

        if ($eligible->isEmpty()) {
            return ['has_candidates' => false, 'position' => null];
        }

        $nearestRank = $eligible->min(function (OrganizationPosition $position) {
            return (int) $position->effective_level->rank;
        });
        $nearest = $eligible->filter(function (OrganizationPosition $position) use ($nearestRank) {
            return (int) $position->effective_level->rank === (int) $nearestRank;
        })->values();

        return [
            'has_candidates' => true,
            'position' => $nearest->count() === 1 ? $nearest->first() : null,
        ];
    }

    private function reconcileVitaeOrphans(int $companyId, int $departmentId, User $actor): array
    {
        $updatedIds = [];
        $positions = OrganizationPosition::query()
            ->where('perusahaan_id', $companyId)
            ->where('departemen_id', $departmentId)
            ->whereNull('parent_position_id')
            ->where('is_active', true)
            ->where('code', 'like', 'VITAE_%')
            ->currentlyEffective()
            ->with(['jobTitle.level', 'levelOverride'])
            ->get();

        foreach ($positions as $position) {
            $parent = $this->findSuggestedParent(
                $companyId,
                $departmentId,
                $position->divisi_id ? (int) $position->divisi_id : null,
                $position->effective_level,
                (int) $position->id
            );

            if (!$parent) {
                continue;
            }

            $this->setParent($position, $parent, $actor);
            $updatedIds[] = (int) $position->id;
        }

        return $updatedIds;
    }

    private function setParent(OrganizationPosition $position, OrganizationPosition $parent, User $actor): OrganizationPosition
    {
        return $this->organizationService->savePosition([
            'code' => $position->code,
            'position_name' => $position->position_name,
            'perusahaan_id' => $position->perusahaan_id,
            'departemen_id' => $position->departemen_id,
            'divisi_id' => $position->divisi_id,
            'job_title_id' => $position->job_title_id,
            'job_level_id' => $position->job_level_id,
            'parent_position_id' => $parent->id,
            'planned_headcount' => $position->planned_headcount,
            'sort_order' => $position->sort_order,
            'is_active' => $position->is_active,
            'effective_from' => $position->effective_from ? $position->effective_from->toDateString() : null,
            'effective_until' => $position->effective_until ? $position->effective_until->toDateString() : null,
            'notes' => $position->notes,
        ], $actor, $position);
    }

    private function assignmentEffectiveDate(Employee $employee, array $cvProfile): string
    {
        $hasActiveAssignment = EmployeePositionAssignment::query()
            ->activeOn()
            ->where('employee_nik', (string) $employee->nik)
            ->exists();

        if ($hasActiveAssignment) {
            return Carbon::today()->toDateString();
        }

        try {
            $sourceDate = !empty($cvProfile['current_job_entry_date'])
                ? Carbon::parse($cvProfile['current_job_entry_date'])
                : null;
        } catch (\Throwable $exception) {
            $sourceDate = null;
        }

        return $sourceDate && $sourceDate->lte(Carbon::today())
            ? $sourceDate->toDateString()
            : Carbon::today()->toDateString();
    }

    private function schemaAvailable(): bool
    {
        try {
            return Schema::hasTable('job_levels')
                && Schema::hasTable('job_titles')
                && Schema::hasTable('job_title_aliases')
                && Schema::hasTable('organization_positions')
                && Schema::hasTable('employee_position_assignments');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
