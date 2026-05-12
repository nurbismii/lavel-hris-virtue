<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\StoreApprovalDelegationRequest;
use App\Models\ApprovalDelegation;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\User;
use App\Services\Approvals\ApprovalDelegationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ApprovalDelegationController extends Controller
{
    private const ALLOWED_COMPANY_CODES = ['VDNI', 'VDNIP'];

    public function index(Request $request, ApprovalDelegationService $service)
    {
        $this->authorizeManage($request->user());

        $filters = $request->validate([
            'departemen_id' => ['nullable', 'string'],
            'divisi_id' => ['nullable', 'string'],
        ]);

        if (filled($filters['divisi_id'] ?? null)) {
            $divisionDepartmentId = $service->divisionDepartmentId((string) $filters['divisi_id']);

            if ($divisionDepartmentId) {
                $filters['departemen_id'] = $divisionDepartmentId;
            }
        }

        $departemens = $this->departmentsFor($request->user());
        $divisis = $this->divisionsFor($request->user(), $filters['departemen_id'] ?? null);

        $delegations = ApprovalDelegation::query()
            ->with(['hod:id,name,nik_karyawan', 'delegate.employee', 'departemen', 'divisi'])
            ->when(!$request->user()->hasRole('Super Admin'), function (Builder $query) use ($request) {
                $query->where('hod_user_id', (string) $request->user()->id);
            })
            ->latest('is_active')
            ->latest('updated_at')
            ->paginate(50)
            ->withQueryString();

        return view('approval.delegations.index', [
            'delegations' => $delegations,
            'departemens' => $departemens,
            'divisis' => $divisis,
            'filters' => $filters,
            'modules' => $service->availableModules(),
        ]);
    }

    public function store(StoreApprovalDelegationRequest $request, ApprovalDelegationService $service)
    {
        $this->authorizeManage($request->user());

        $data = $request->validated();
        $modules = collect($data['modules'])
            ->unique()
            ->values();

        if ($modules->contains(ApprovalDelegation::MODULE_ALL)) {
            $modules = collect([ApprovalDelegation::MODULE_ALL]);
        }

        $divisiId = filled($data['divisi_id'] ?? null) ? (string) $data['divisi_id'] : null;
        $departemenId = filled($data['departemen_id'] ?? null) ? (string) $data['departemen_id'] : null;

        if ($divisiId) {
            $departemenId = $service->divisionDepartmentId($divisiId);
        }

        if (!$this->scopeBelongsToAllowedCompany($departemenId, $divisiId)) {
            toast()->warning('Peringatan', 'Delegasi approval hanya tersedia untuk departemen/divisi perusahaan VDNI dan VDNIP.');
            return back()->withInput();
        }

        if (!$service->canManageScope($request->user(), $departemenId, $divisiId)) {
            toast()->warning('Peringatan', 'Scope delegasi tidak berada dalam akses HOD Anda.');
            return back()->withInput();
        }

        $delegate = User::query()
            ->with('employee')
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->findOrFail($data['delegate_user_id']);

        if ((string) $delegate->id === (string) $request->user()->id
            || (string) $delegate->nik_karyawan === (string) $request->user()->nik_karyawan) {
            toast()->warning('Peringatan', 'HOD tidak bisa menunjuk dirinya sendiri sebagai delegasi.');
            return back()->withInput();
        }

        if (!$delegate->employee || !$service->scopeMatchesEmployee($delegate->employee, $departemenId, $divisiId)) {
            toast()->warning('Peringatan', 'Karyawan delegasi harus berada pada departemen atau divisi yang dipilih.');
            return back()->withInput();
        }

        foreach ($modules as $module) {
            ApprovalDelegation::updateOrCreate([
                'hod_user_id' => (string) $request->user()->id,
                'delegate_user_id' => (string) $delegate->id,
                'departemen_id' => $departemenId,
                'divisi_id' => $divisiId,
                'module' => $module,
            ], [
                'is_active' => true,
                'created_by' => (string) $request->user()->id,
                'updated_by' => (string) $request->user()->id,
            ]);
        }

        toast()->success('Berhasil', 'Delegasi approval berhasil disimpan untuk ' . $modules->count() . ' modul.');
        return redirect()->route('approval.delegations.index', $request->only(['departemen_id', 'divisi_id']));
    }

    public function candidates(Request $request, ApprovalDelegationService $service)
    {
        $this->authorizeManage($request->user());

        $data = $request->validate([
            'departemen_id' => ['nullable', 'string'],
            'divisi_id' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $divisiId = filled($data['divisi_id'] ?? null) ? (string) $data['divisi_id'] : null;
        $departemenId = filled($data['departemen_id'] ?? null) ? (string) $data['departemen_id'] : null;

        if ($divisiId) {
            $departemenId = $service->divisionDepartmentId($divisiId);
        }

        if (blank($departemenId) && blank($divisiId)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        if (!$this->scopeBelongsToAllowedCompany($departemenId, $divisiId)) {
            return response()->json([
                'success' => false,
                'message' => 'Delegasi approval hanya tersedia untuk departemen/divisi perusahaan VDNI dan VDNIP.',
            ], 422);
        }

        if (!$service->canManageScope($request->user(), $departemenId, $divisiId)) {
            abort(403, 'Scope delegasi tidak berada dalam akses HOD Anda.');
        }

        $candidates = $this->candidateUsers($request->user(), $departemenId, $divisiId, $data['q'] ?? null)
            ->map(function (User $candidate) {
                $employeeName = optional($candidate->employee)->nama_karyawan ?: $candidate->name;
                $nik = $candidate->nik_karyawan ?: optional($candidate->employee)->nik;

                return [
                    'id' => (string) $candidate->id,
                    'label' => trim($employeeName . ($nik ? ' - ' . $nik : '')),
                    'text' => trim($employeeName . ($nik ? ' - ' . $nik : '')),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $candidates,
            'results' => $candidates->map(fn($candidate) => [
                'id' => $candidate['id'],
                'text' => $candidate['text'],
            ])->values(),
            'limited' => $candidates->count() >= 500,
        ]);
    }

    public function toggle(Request $request, ApprovalDelegation $delegation)
    {
        $this->authorizeManage($request->user());

        if (!$request->user()->hasRole('Super Admin')
            && (string) $delegation->hod_user_id !== (string) $request->user()->id) {
            abort(403, 'Anda hanya bisa mengubah delegasi milik Anda sendiri.');
        }

        $delegation->update([
            'is_active' => !$delegation->is_active,
            'updated_by' => (string) $request->user()->id,
        ]);

        toast()->success('Berhasil', $delegation->is_active ? 'Delegasi diaktifkan.' : 'Delegasi dinonaktifkan.');
        return back();
    }

    private function authorizeManage(User $user): void
    {
        abort_unless(
            $user->hasRole(['Super Admin', 'HOD']) && $user->hasMenuAccess('approval_hod'),
            403,
            'Menu delegasi approval hanya tersedia untuk HOD.'
        );
    }

    private function departmentsFor(User $user)
    {
        $departmentIds = collect($user->scopedDepartmentIds());

        if (!$user->hasRole('Super Admin')) {
            $departmentIds = $departmentIds
                ->merge(
                    Divisi::query()
                        ->whereIn('id', $user->scopedDivisionIds())
                        ->pluck('departemen_id')
                )
                ->filter()
                ->map(fn($id) => (string) $id)
                ->unique()
                ->values();
        }

        $divisionIds = collect($user->scopedDivisionIds())
            ->map(fn($id) => (string) $id)
            ->all();

        return Departemen::query()
            ->with(['divisi' => function ($query) use ($user, $divisionIds) {
                $query
                    ->when(!$user->hasRole('Super Admin'), fn($query) => $query->whereIn('id', $divisionIds))
                    ->orderBy('nama_divisi');
            }])
            ->whereHas('perusahaan', function (Builder $query) {
                $this->allowedCompanyQuery($query);
            })
            ->when(!$user->hasRole('Super Admin'), function (Builder $query) use ($departmentIds) {
                $query->whereIn('id', $departmentIds->all());
            })
            ->orderBy('departemen')
            ->get();
    }

    private function divisionsFor(User $user, ?string $departemenId)
    {
        return Divisi::query()
            ->with('departemen')
            ->whereHas('departemen.perusahaan', function (Builder $query) {
                $this->allowedCompanyQuery($query);
            })
            ->when(filled($departemenId), fn(Builder $query) => $query->where('departemen_id', $departemenId))
            ->when(!$user->hasRole('Super Admin'), function (Builder $query) use ($user) {
                $query->whereIn('id', $user->scopedDivisionIds());
            })
            ->orderBy('nama_divisi')
            ->get();
    }

    private function candidateUsers(User $user, ?string $departemenId, ?string $divisiId, ?string $search = null)
    {
        if (blank($departemenId) && blank($divisiId)) {
            return collect();
        }

        $keyword = trim((string) $search);
        $likeKeyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword) . '%';

        return User::query()
            ->with('employee.divisi.departemen')
            ->where('id', '!=', (string) $user->id)
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->whereHas('employee', function (Builder $employeeQuery) use ($departemenId, $divisiId) {
                $employeeQuery
                    ->where('status_resign', 'AKTIF')
                    ->where(function (Builder $query) {
                        $query
                            ->whereHas('departemen.perusahaan', function (Builder $companyQuery) {
                                $this->allowedCompanyQuery($companyQuery);
                            })
                            ->orWhereHas('divisi.departemen.perusahaan', function (Builder $companyQuery) {
                                $this->allowedCompanyQuery($companyQuery);
                            });
                    })
                    ->when(filled($divisiId), fn(Builder $query) => $query->where('divisi_id', $divisiId))
                    ->when(blank($divisiId) && filled($departemenId), fn(Builder $query) => $query->where('departemen_id', $departemenId));
            })
            ->when($keyword !== '', function (Builder $query) use ($likeKeyword) {
                $query->where(function (Builder $searchQuery) use ($likeKeyword) {
                    $searchQuery
                        ->where('name', 'like', $likeKeyword)
                        ->orWhere('nik_karyawan', 'like', $likeKeyword)
                        ->orWhereHas('employee', function (Builder $employeeQuery) use ($likeKeyword) {
                            $employeeQuery
                                ->where('nama_karyawan', 'like', $likeKeyword)
                                ->orWhere('nik', 'like', $likeKeyword);
                        });
                });
            })
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    private function scopeBelongsToAllowedCompany(?string $departemenId, ?string $divisiId): bool
    {
        if (filled($divisiId)) {
            return Divisi::query()
                ->whereKey($divisiId)
                ->whereHas('departemen.perusahaan', function (Builder $query) {
                    $this->allowedCompanyQuery($query);
                })
                ->exists();
        }

        if (filled($departemenId)) {
            return Departemen::query()
                ->whereKey($departemenId)
                ->whereHas('perusahaan', function (Builder $query) {
                    $this->allowedCompanyQuery($query);
                })
                ->exists();
        }

        return false;
    }

    private function allowedCompanyQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $companyQuery) {
            $companyQuery
                ->whereIn('kode_perusahaan', self::ALLOWED_COMPANY_CODES)
                ->orWhereIn('nama_perusahaan', self::ALLOWED_COMPANY_CODES);
        });
    }
}
