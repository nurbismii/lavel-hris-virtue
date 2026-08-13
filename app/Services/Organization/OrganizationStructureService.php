<?php

namespace App\Services\Organization;

use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\JobTitleAlias;
use App\Models\OrganizationPosition;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationStructureService
{
    private $auditTrail;

    public function __construct(AuditTrailService $auditTrail)
    {
        $this->auditTrail = $auditTrail;
    }

    public function saveLevel(array $data, User $actor, ?JobLevel $level = null): JobLevel
    {
        return DB::transaction(function () use ($data, $actor, $level) {
            $level = $level ?: new JobLevel();
            $oldValues = $level->exists ? $level->toArray() : [];
            $level->fill($data)->save();

            $this->record(
                $level->wasRecentlyCreated ? 'organization.job_level.created' : 'organization.job_level.updated',
                $level,
                $actor,
                $oldValues,
                $level->fresh()->toArray()
            );

            return $level->fresh();
        });
    }

    public function saveJobTitle(array $data, array $aliases, User $actor, ?JobTitle $jobTitle = null): JobTitle
    {
        return DB::transaction(function () use ($data, $aliases, $actor, $jobTitle) {
            $jobTitle = $jobTitle ?: new JobTitle();
            $normalizedName = $this->normalizeTitle($data['name']);

            $duplicate = JobTitle::query()
                ->where('normalized_name', $normalizedName)
                ->when($jobTitle->exists, fn($query) => $query->where('id', '<>', $jobTitle->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'Nama jabatan sudah terdaftar pada master.',
                ]);
            }

            $oldValues = $jobTitle->exists
                ? array_merge($jobTitle->toArray(), ['aliases' => $jobTitle->aliases()->pluck('alias')->all()])
                : [];

            $jobTitle->fill(array_merge($data, ['normalized_name' => $normalizedName]))->save();
            $this->syncAliases($jobTitle, array_merge([$data['name'], $jobTitle->display_name], $aliases));

            $newValues = array_merge($jobTitle->fresh()->toArray(), [
                'aliases' => $jobTitle->aliases()->pluck('alias')->all(),
            ]);

            $this->record(
                $jobTitle->wasRecentlyCreated ? 'organization.job_title.created' : 'organization.job_title.updated',
                $jobTitle,
                $actor,
                $oldValues,
                $newValues
            );

            return $jobTitle->fresh(['level', 'aliases']);
        });
    }

    public function savePosition(array $data, User $actor, ?OrganizationPosition $position = null): OrganizationPosition
    {
        return DB::transaction(function () use ($data, $actor, $position) {
            $position = $position ?: new OrganizationPosition();
            $oldValues = $position->exists ? $position->toArray() : [];

            $this->guardPositionScope($data);
            $this->guardHierarchy($data, $position);

            $position->fill($data)->save();

            $this->record(
                $position->wasRecentlyCreated ? 'organization.position.created' : 'organization.position.updated',
                $position,
                $actor,
                $oldValues,
                $position->fresh()->toArray()
            );

            return $position->fresh(['jobTitle.level', 'levelOverride', 'parent']);
        });
    }

    public function assignEmployee(
        OrganizationPosition $position,
        string $employeeNik,
        string $effectiveFrom,
        User $actor,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): EmployeePositionAssignment {
        return DB::transaction(function () use ($position, $employeeNik, $effectiveFrom, $actor, $referenceNumber, $notes) {
            $position = OrganizationPosition::query()->whereKey($position->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->whereKey($employeeNik)->lockForUpdate()->firstOrFail();

            if (!$position->is_active) {
                throw ValidationException::withMessages(['organization_position_id' => 'Posisi organisasi tidak aktif.']);
            }

            $this->guardAllowedCompany($position->perusahaan_id);

            if ((string) $employee->departemen_id !== (string) $position->departemen_id) {
                throw ValidationException::withMessages(['employee_nik' => 'Departemen karyawan tidak sesuai dengan posisi organisasi.']);
            }

            if ($position->divisi_id && (string) $employee->divisi_id !== (string) $position->divisi_id) {
                throw ValidationException::withMessages(['employee_nik' => 'Divisi karyawan tidak sesuai dengan posisi organisasi.']);
            }

            $activeAssignments = EmployeePositionAssignment::query()
                ->where('employee_nik', $employee->nik)
                ->where('status', EmployeePositionAssignment::STATUS_ACTIVE)
                ->lockForUpdate()
                ->get();

            foreach ($activeAssignments as $activeAssignment) {
                $activeAssignment->update([
                    'status' => EmployeePositionAssignment::STATUS_ENDED,
                    'effective_until' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                    'ended_by_user_id' => (string) $actor->id,
                ]);
            }

            $assignment = EmployeePositionAssignment::create([
                'employee_nik' => (string) $employee->nik,
                'organization_position_id' => $position->id,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'status' => EmployeePositionAssignment::STATUS_ACTIVE,
                'source' => 'vpeople',
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'created_by_user_id' => (string) $actor->id,
            ]);

            $supervisorNik = $this->supervisorNikFor($position);
            $employee->forceFill([
                'job_title_id' => $position->job_title_id,
                'organization_position_id' => $position->id,
                'reports_to_nik' => $supervisorNik,
                'jabatan' => optional($position->jobTitle)->display_name ?: $employee->jabatan,
                'posisi' => $position->display_name,
            ])->save();

            $this->record(
                'organization.employee_position.assigned',
                $assignment,
                $actor,
                ['previous_assignment_ids' => $activeAssignments->pluck('id')->all()],
                $assignment->fresh()->toArray(),
                (string) $employee->nik
            );

            return $assignment->fresh(['employee', 'organizationPosition.jobTitle.level']);
        });
    }

    public function chartForDepartment(int $departmentId): Collection
    {
        $positions = OrganizationPosition::query()
            ->where('departemen_id', $departmentId)
            ->where('is_active', true)
            ->currentlyEffective()
            ->with([
                'jobTitle.level',
                'levelOverride',
                'divisi:id,nama_divisi,departemen_id',
                'activeAssignments.employee:nik,nama_karyawan,status_resign',
            ])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $byParent = $positions->groupBy(function (OrganizationPosition $position) use ($positions) {
            return $position->parent_position_id && $positions->contains('id', $position->parent_position_id)
                ? (string) $position->parent_position_id
                : 'root';
        });

        $attachChildren = function (OrganizationPosition $position) use (&$attachChildren, $byParent) {
            $children = ($byParent[(string) $position->id] ?? collect())
                ->map(function (OrganizationPosition $child) use (&$attachChildren) {
                    $child->setRelation('chartChildren', $attachChildren($child));

                    return $child;
                })
                ->values();

            return $children;
        };

        return ($byParent['root'] ?? collect())
            ->map(function (OrganizationPosition $position) use (&$attachChildren) {
                $position->setRelation('chartChildren', $attachChildren($position));

                return $position;
            })
            ->values();
    }

    public function normalizeTitle(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    private function syncAliases(JobTitle $jobTitle, array $aliases): void
    {
        $normalizedAliases = collect($aliases)
            ->map(fn($alias) => trim((string) $alias))
            ->filter()
            ->mapWithKeys(fn($alias) => [$this->normalizeTitle($alias) => $alias]);

        foreach ($normalizedAliases as $normalized => $alias) {
            $existing = JobTitleAlias::query()->where('normalized_alias', $normalized)->first();

            if ($existing && (int) $existing->job_title_id !== (int) $jobTitle->id) {
                throw ValidationException::withMessages([
                    'aliases' => "Alias {$alias} sudah dipakai oleh jabatan lain.",
                ]);
            }

            JobTitleAlias::updateOrCreate(
                ['normalized_alias' => $normalized],
                ['job_title_id' => $jobTitle->id, 'alias' => $alias]
            );
        }

        $jobTitle->aliases()->whereNotIn('normalized_alias', $normalizedAliases->keys()->all())->delete();
    }

    private function guardPositionScope(array $data): void
    {
        $this->guardAllowedCompany($data['perusahaan_id']);

        $department = DB::table('departemens')->where('id', $data['departemen_id'])->first();

        if (!$department || (string) $department->perusahaan_id !== (string) $data['perusahaan_id']) {
            throw ValidationException::withMessages(['departemen_id' => 'Departemen tidak sesuai dengan perusahaan.']);
        }

        if (!empty($data['divisi_id'])) {
            $validDivision = DB::table('divisis')
                ->where('id', $data['divisi_id'])
                ->where('departemen_id', $data['departemen_id'])
                ->exists();

            if (!$validDivision) {
                throw ValidationException::withMessages(['divisi_id' => 'Divisi tidak sesuai dengan departemen.']);
            }
        }
    }

    private function guardAllowedCompany($companyId): void
    {
        $isAllowed = DB::table('perusahaan')
            ->where('id', $companyId)
            ->whereIn('kode_perusahaan', Perusahaan::ORGANIZATION_COMPANY_CODES)
            ->exists();

        if (!$isAllowed) {
            throw ValidationException::withMessages([
                'perusahaan_id' => 'Struktur organisasi hanya tersedia untuk perusahaan VDNI dan VDNIP.',
            ]);
        }
    }

    private function guardHierarchy(array $data, OrganizationPosition $position): void
    {
        if (empty($data['parent_position_id'])) {
            return;
        }

        $parent = OrganizationPosition::query()
            ->with(['jobTitle.level', 'levelOverride'])
            ->findOrFail($data['parent_position_id']);

        if ($position->exists && (int) $parent->id === (int) $position->id) {
            throw ValidationException::withMessages(['parent_position_id' => 'Posisi tidak dapat menjadi atasannya sendiri.']);
        }

        if ((string) $parent->departemen_id !== (string) $data['departemen_id']) {
            throw ValidationException::withMessages(['parent_position_id' => 'Atasan struktural harus berada pada departemen yang sama.']);
        }

        if ($position->exists && $this->isDescendant($parent, $position->id)) {
            throw ValidationException::withMessages(['parent_position_id' => 'Hubungan atasan membentuk siklus organisasi.']);
        }

        $jobTitle = JobTitle::query()->with('level')->findOrFail($data['job_title_id']);
        $childLevel = !empty($data['job_level_id'])
            ? JobLevel::findOrFail($data['job_level_id'])
            : $jobTitle->level;
        $parentLevel = $parent->effective_level;

        if ($childLevel && $parentLevel && (int) $parentLevel->rank <= (int) $childLevel->rank) {
            throw ValidationException::withMessages([
                'parent_position_id' => 'Level atasan harus lebih tinggi daripada level posisi bawahan.',
            ]);
        }
    }

    private function isDescendant(OrganizationPosition $candidate, int $positionId): bool
    {
        $visited = [];

        while ($candidate->parent_position_id) {
            if (isset($visited[$candidate->id])) {
                return true;
            }

            $visited[$candidate->id] = true;

            if ((int) $candidate->parent_position_id === $positionId) {
                return true;
            }

            $candidate = OrganizationPosition::find($candidate->parent_position_id);

            if (!$candidate) {
                break;
            }
        }

        return false;
    }

    private function supervisorNikFor(OrganizationPosition $position): ?string
    {
        if (!$position->parent_position_id) {
            return null;
        }

        $supervisors = EmployeePositionAssignment::query()
            ->activeOn()
            ->where('organization_position_id', $position->parent_position_id)
            ->limit(2)
            ->pluck('employee_nik');

        return $supervisors->count() === 1 ? (string) $supervisors->first() : null;
    }

    private function record(
        string $event,
        $model,
        User $actor,
        array $oldValues,
        array $newValues,
        ?string $employeeNik = null
    ): void {
        $this->auditTrail->record([
            'event' => $event,
            'module' => 'organization_structure',
            'auditable_type' => get_class($model),
            'auditable_id' => (string) $model->getKey(),
            'reference_table' => $model->getTable(),
            'reference_id' => (string) $model->getKey(),
            'employee_nik' => $employeeNik,
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
