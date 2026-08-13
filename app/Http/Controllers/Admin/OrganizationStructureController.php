<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AssignEmployeePositionRequest;
use App\Http\Requests\Organization\UpsertJobLevelRequest;
use App\Http\Requests\Organization\UpsertJobTitleRequest;
use App\Http\Requests\Organization\UpsertOrganizationPositionRequest;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\OrganizationPosition;
use App\Models\Perusahaan;
use App\Services\Organization\OrganizationStructureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationStructureController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManager($request);

        $departmentId = $request->filled('departemen_id') ? (int) $request->input('departemen_id') : null;

        return view('admin.organization-structure.index', [
            'levels' => JobLevel::query()
                ->withCount(['jobTitles', 'organizationPositions'])
                ->orderByDesc('rank')
                ->get(),
            'jobTitles' => JobTitle::query()
                ->with(['level', 'aliases'])
                ->withCount(['employees', 'organizationPositions'])
                ->when($request->filled('job_title_search'), function (Builder $query) use ($request) {
                    $keyword = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('job_title_search'))) . '%';
                    $query->where(function (Builder $search) use ($keyword) {
                        $search->where('code', 'like', $keyword)
                            ->orWhere('name', 'like', $keyword)
                            ->orWhere('name_zh', 'like', $keyword);
                    });
                })
                ->orderBy('name')
                ->paginate(30, ['*'], 'job_title_page')
                ->withQueryString(),
            'positions' => OrganizationPosition::query()
                ->with([
                    'perusahaan:id,kode_perusahaan,nama_perusahaan',
                    'departemen:id,perusahaan_id,departemen',
                    'divisi:id,departemen_id,nama_divisi',
                    'jobTitle.level',
                    'levelOverride',
                    'parent.jobTitle',
                    'activeAssignments.employee:nik,nama_karyawan',
                ])
                ->whereHas('perusahaan', fn(Builder $query) => $query->organizationCompanies())
                ->when($departmentId, fn(Builder $query) => $query->where('departemen_id', $departmentId))
                ->orderBy('departemen_id')
                ->orderBy('sort_order')
                ->paginate(30, ['*'], 'position_page')
                ->withQueryString(),
            'companies' => Perusahaan::query()->organizationCompanies()->orderBy('kode_perusahaan')->get(),
            'departments' => Departemen::query()
                ->with('perusahaan')
                ->whereHas('perusahaan', fn(Builder $query) => $query->organizationCompanies())
                ->orderBy('departemen')
                ->get(),
            'divisions' => Divisi::query()
                ->whereHas('departemen.perusahaan', fn(Builder $query) => $query->organizationCompanies())
                ->orderBy('nama_divisi')
                ->get(),
            'activeLevels' => JobLevel::query()->where('is_active', true)->orderByDesc('rank')->get(),
            'activeJobTitles' => JobTitle::query()->with('level')->where('is_active', true)->orderBy('name')->get(),
            'availableParents' => OrganizationPosition::query()
                ->with(['jobTitle.level', 'levelOverride', 'departemen'])
                ->whereHas('perusahaan', fn(Builder $query) => $query->organizationCompanies())
                ->where('is_active', true)
                ->orderBy('departemen_id')
                ->orderBy('sort_order')
                ->get(),
            'editLevel' => $request->filled('edit_level') ? JobLevel::findOrFail($request->input('edit_level')) : null,
            'editJobTitle' => $request->filled('edit_job_title')
                ? JobTitle::with('aliases')->findOrFail($request->input('edit_job_title'))
                : null,
            'editPosition' => $request->filled('edit_position')
                ? OrganizationPosition::findOrFail($request->input('edit_position'))
                : null,
            'selectedDepartmentId' => $departmentId,
        ]);
    }

    public function storeLevel(UpsertJobLevelRequest $request, OrganizationStructureService $service)
    {
        $service->saveLevel($request->payload(), $request->user());
        toast()->success('Berhasil', 'Level jabatan berhasil ditambahkan.');

        return redirect()->route('organization-structure.index', ['section' => 'levels']);
    }

    public function updateLevel(
        UpsertJobLevelRequest $request,
        JobLevel $jobLevel,
        OrganizationStructureService $service
    ) {
        $service->saveLevel($request->payload(), $request->user(), $jobLevel);
        toast()->success('Berhasil', 'Level jabatan berhasil diperbarui.');

        return redirect()->route('organization-structure.index', ['section' => 'levels']);
    }

    public function storeJobTitle(UpsertJobTitleRequest $request, OrganizationStructureService $service)
    {
        $service->saveJobTitle($request->payload(), $request->aliases(), $request->user());
        toast()->success('Berhasil', 'Master jabatan berhasil ditambahkan.');

        return redirect()->route('organization-structure.index', ['section' => 'job-titles']);
    }

    public function updateJobTitle(
        UpsertJobTitleRequest $request,
        JobTitle $jobTitle,
        OrganizationStructureService $service
    ) {
        $service->saveJobTitle($request->payload(), $request->aliases(), $request->user(), $jobTitle);
        toast()->success('Berhasil', 'Master jabatan berhasil diperbarui.');

        return redirect()->route('organization-structure.index', ['section' => 'job-titles']);
    }

    public function storePosition(UpsertOrganizationPositionRequest $request, OrganizationStructureService $service)
    {
        $service->savePosition($request->payload(), $request->user());
        toast()->success('Berhasil', 'Posisi organisasi berhasil ditambahkan.');

        return redirect()->route('organization-structure.index', ['section' => 'positions']);
    }

    public function updatePosition(
        UpsertOrganizationPositionRequest $request,
        OrganizationPosition $organizationPosition,
        OrganizationStructureService $service
    ) {
        $service->savePosition($request->payload(), $request->user(), $organizationPosition);
        toast()->success('Berhasil', 'Posisi organisasi berhasil diperbarui.');

        return redirect()->route('organization-structure.index', ['section' => 'positions']);
    }

    public function assignEmployee(
        AssignEmployeePositionRequest $request,
        OrganizationPosition $organizationPosition,
        OrganizationStructureService $service
    ) {
        $service->assignEmployee(
            $organizationPosition,
            $request->input('employee_nik'),
            $request->input('effective_from'),
            $request->user(),
            $request->input('reference_number'),
            $request->input('notes')
        );

        toast()->success('Berhasil', 'Karyawan berhasil ditempatkan pada posisi organisasi.');

        return redirect()->route('organization-structure.index', ['section' => 'assignments']);
    }

    public function chart(Request $request, OrganizationStructureService $service)
    {
        $departments = $this->departmentsFor($request);
        $selectedDepartmentId = $request->filled('departemen_id')
            ? (int) $request->input('departemen_id')
            : optional($departments->first())->id;

        abort_if($selectedDepartmentId && !$departments->contains('id', $selectedDepartmentId), 403);

        return view('admin.organization-structure.chart', [
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDepartment' => $departments->firstWhere('id', $selectedDepartmentId),
            'chartRoots' => $selectedDepartmentId ? $service->chartForDepartment($selectedDepartmentId) : collect(),
        ]);
    }

    public function searchEmployees(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'departemen_id' => ['nullable', 'integer', 'exists:departemens,id'],
            'divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
        ]);
        $keyword = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($validated['q'])) . '%';

        $employees = Employee::query()
            ->select(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departemen_id', 'divisi_id'])
            ->where('status_resign', 'AKTIF')
            ->whereHas('departemen.perusahaan', fn(Builder $query) => $query->organizationCompanies())
            ->when(!empty($validated['departemen_id']), fn(Builder $query) => $query->where('departemen_id', $validated['departemen_id']))
            ->when(!empty($validated['divisi_id']), fn(Builder $query) => $query->where('divisi_id', $validated['divisi_id']))
            ->where(function (Builder $query) use ($keyword) {
                $query->where('nik', 'like', $keyword)->orWhere('nama_karyawan', 'like', $keyword);
            })
            ->orderBy('nama_karyawan')
            ->limit(20)
            ->get()
            ->map(function (Employee $employee) {
                return [
                    'id' => (string) $employee->nik,
                    'text' => $employee->nik . ' - ' . $employee->nama_karyawan,
                    'job_title' => $employee->jabatan ?: $employee->posisi,
                ];
            });

        return response()->json(['success' => true, 'data' => $employees]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);
    }

    private function departmentsFor(Request $request)
    {
        $query = Departemen::query()
            ->with('perusahaan')
            ->whereHas('perusahaan', fn(Builder $companyQuery) => $companyQuery->organizationCompanies())
            ->orderBy('departemen');

        if ($request->user()->canAccessAllEmployees()) {
            return $query->get();
        }

        $departmentIds = $request->user()->scopedDepartmentIds();

        if ($request->user()->isDivisionScopedRole()) {
            $departmentIds = Divisi::query()
                ->whereIn('id', $request->user()->scopedDivisionIds())
                ->pluck('departemen_id')
                ->filter()
                ->all();
        }

        return $query->whereIn('id', $departmentIds)->get();
    }
}
