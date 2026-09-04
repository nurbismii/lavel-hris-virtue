<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Presensi\BulkAssignAttendanceLocationRequest;
use App\Http\Requests\Presensi\SaveAttendanceLocationRequest;
use App\Models\Divisi;
use App\Models\EmployeeAttendanceLocationAssignment;
use App\Models\LokasiAbsen;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\Presensi\AttendanceLocationBulkAssignmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SettingLokasiPresensiController extends Controller
{
    public function index(Request $request, AttendanceLocationBulkAssignmentService $bulkAssignmentService)
    {
        $locationColumns = ['id', 'divisi_id', 'lat', 'long', 'radius', 'created_at'];

        if (Schema::hasColumn('lokasi_absens', 'nama_lokasi')) {
            array_splice($locationColumns, 1, 0, 'nama_lokasi');
        }

        $locationQuery = $this->locationQueryForUser($request->user())
            ->with('divisi.departemen.perusahaan')
            ->select($locationColumns)
            ->orderByDesc('id');

        if (Schema::hasTable('employee_attendance_location_assignments')) {
            $today = now()->toDateString();

            $locationQuery->withCount([
                'employeeLocationAssignments as active_employee_assignment_count' => function (Builder $query) use ($today) {
                    $query->activeAt($today);
                },
            ]);
        }

        $lokasi = $locationQuery->get();
        $divisions = $this->getScopedDivisions($request);
        $bulkPreview = $this->buildBulkPreview($request, $bulkAssignmentService, $lokasi);

        return view('admin.setting-lokasi.index', compact(
            'lokasi',
            'divisions',
            'bulkPreview'
        ));
    }

    public function create()
    {
        return view('admin.setting-lokasi.create');
    }

    public function store(SaveAttendanceLocationRequest $request)
    {
        $validated = $request->validated();

        LokasiAbsen::create([
            'nama_lokasi' => $validated['nama_lokasi'],
            'divisi_id' => null,
            'lat'       => $validated['lat'],
            'long'      => $validated['long'],
            'radius'    => $validated['radius'],
            'created_at' => now()
        ]);

        toast()->success('Berhasil', 'Lokasi presensi berhasil disimpan.');
        return redirect()->route('setting-lokasi-presensi.index');
    }

    public function edit(Request $request, $id)
    {
        $lokasi = $this->locationQueryForUser($request->user())
            ->with('divisi.departemen.perusahaan')
            ->where('id', $id)
            ->firstOrFail();

        return view('admin.setting-lokasi.edit', compact('lokasi'));
    }

    public function update(SaveAttendanceLocationRequest $request, $id)
    {
        $lokasi = $this->locationQueryForUser($request->user())
            ->where('id', $id)
            ->firstOrFail();

        $lokasi->update($request->validated());

        toast()->success('Berhasil', 'Lokasi presensi berhasil diperbarui.');
        return redirect()->route('setting-lokasi-presensi.index');
    }

    public function destroy(Request $request, $id)
    {
        $lokasi = $this->locationQueryForUser($request->user())
            ->where('id', $id)
            ->firstOrFail();

        if (
            Schema::hasTable('employee_attendance_location_assignments')
            && $lokasi->employeeLocationAssignments()->exists()
        ) {
            toast()->warning('Peringatan', 'Lokasi ini sudah dipakai assignment karyawan dan tidak dapat dihapus.');
            return redirect()->route('setting-lokasi-presensi.index');
        }

        $lokasi->delete();

        toast()->success('Berhasil', 'Lokasi presensi berhasil dihapus.');
        return redirect()->route('setting-lokasi-presensi.index');
    }

    public function bulkAssign(
        BulkAssignAttendanceLocationRequest $request,
        AttendanceLocationBulkAssignmentService $bulkAssignmentService
    ) {
        $validated = $request->validated();
        $location = $this->locationQueryForUser($request->user())
            ->find($validated['bulk_lokasi_absen_id']);

        abort_unless($location, 403, 'Lokasi presensi tidak termasuk dalam scope akses Anda.');

        $result = $bulkAssignmentService->assignByFilter(
            $request->user(),
            $location,
            $this->bulkFilters($validated, $bulkAssignmentService),
            Carbon::parse($validated['bulk_effective_from']),
            filled($validated['bulk_effective_until'] ?? null) ? Carbon::parse($validated['bulk_effective_until']) : null,
            $validated['bulk_note'] ?? null,
            $validated['bulk_assignment_mode'] ?? AttendanceLocationBulkAssignmentService::MODE_REPLACE
        );

        if (($result['assigned_count'] ?? 0) < 1) {
            $warningMessage = ($result['candidate_count'] ?? 0) > 0
                ? 'Semua karyawan target sudah memiliki lokasi presensi ini aktif pada periode tersebut.'
                : 'Tidak ada karyawan aktif yang cocok dengan filter assignment.';

            toast()->warning('Peringatan', $warningMessage);
            return redirect()->route('setting-lokasi-presensi.index');
        }

        $message = $result['assigned_count'] . ' assignment lokasi presensi berhasil disimpan.';

        if (($result['candidate_count'] ?? null) && (int) $result['candidate_count'] !== (int) $result['assigned_count']) {
            $message .= ' ' . ((int) $result['candidate_count'] - (int) $result['assigned_count']) . ' karyawan dilewati karena lokasi tersebut sudah aktif.';
        }

        toast()->success('Berhasil', $message);
        return redirect()->route('setting-lokasi-presensi.index');
    }

    private function buildBulkPreview(Request $request, AttendanceLocationBulkAssignmentService $bulkAssignmentService, $locations): ?array
    {
        if (!$request->boolean('bulk_preview')) {
            return null;
        }

        $validator = Validator::make(
            $request->all(),
            BulkAssignAttendanceLocationRequest::baseRules(false),
            BulkAssignAttendanceLocationRequest::customMessages()
        );

        BulkAssignAttendanceLocationRequest::validateFilterPresence($validator);
        $validated = $validator->validate();
        $selectedLocation = $locations->firstWhere('id', (int) $validated['bulk_lokasi_absen_id']);

        abort_unless($selectedLocation, 403, 'Lokasi presensi tidak termasuk dalam scope akses Anda.');

        $filters = $this->bulkFilters($validated, $bulkAssignmentService);
        $candidateQuery = $bulkAssignmentService
            ->candidateQuery($request->user(), $filters)
            ->where('employees.status_resign', 'AKTIF');
        $total = (clone $candidateQuery)->count('employees.nik');
        $employees = (clone $candidateQuery)
            ->orderBy('employees.nama_karyawan')
            ->limit(25)
            ->get();
        $requestedNiks = $filters['employee_niks'];
        $unmatchedNiks = [];

        if (!empty($requestedNiks)) {
            $matchedNiks = (clone $candidateQuery)
                ->pluck('employees.nik')
                ->map(fn($nik) => (string) $nik)
                ->all();
            $unmatchedNiks = array_values(array_diff($requestedNiks, $matchedNiks));
        }

        $currentAssignments = $this->currentAssignmentsFor($employees->pluck('nik')->all());

        return [
            'selected_location' => $selectedLocation,
            'total' => $total,
            'employees' => $employees,
            'current_assignments' => $currentAssignments,
            'filters' => $filters,
            'requested_niks' => $requestedNiks,
            'unmatched_niks' => $unmatchedNiks,
            'effective_from' => $validated['bulk_effective_from'],
            'effective_until' => $validated['bulk_effective_until'] ?? null,
            'assignment_mode' => $validated['bulk_assignment_mode'] ?? AttendanceLocationBulkAssignmentService::MODE_REPLACE,
            'note' => $validated['bulk_note'] ?? null,
        ];
    }

    private function currentAssignmentsFor(array $niks)
    {
        if (empty($niks) || !Schema::hasTable('employee_attendance_location_assignments')) {
            return collect();
        }

        return EmployeeAttendanceLocationAssignment::query()
            ->with('location.divisi')
            ->whereIn('employee_nik', $niks)
            ->activeAt(now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_nik');
    }

    private function bulkFilters(array $validated, AttendanceLocationBulkAssignmentService $bulkAssignmentService): array
    {
        return [
            'perusahaan_id' => $validated['bulk_perusahaan_id'] ?? null,
            'departemen_id' => $validated['bulk_departemen_id'] ?? null,
            'divisi_id' => $validated['bulk_divisi_id'] ?? null,
            'employee_niks' => $bulkAssignmentService->normalizeEmployeeNiks($validated['bulk_employee_niks'] ?? ''),
        ];
    }

    private function locationQueryForUser(User $user): Builder
    {
        $query = LokasiAbsen::query();

        if ($user->canAccessAllEmployees()) {
            return $query;
        }

        if ($user->isDepartmentScopedRole()) {
            $departemenIds = $user->scopedDepartmentIds();
            $divisiIds = $user->isHodRole() ? $user->scopedDivisionIds() : [];

            if (empty($departemenIds) && empty($divisiIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('divisi', function (Builder $divisionQuery) use ($departemenIds, $divisiIds) {
                $divisionQuery->where(function (Builder $scopeQuery) use ($departemenIds, $divisiIds) {
                    if (!empty($departemenIds)) {
                        $scopeQuery->whereIn('departemen_id', $departemenIds);
                    }

                    if (!empty($divisiIds)) {
                        $method = !empty($departemenIds) ? 'orWhereIn' : 'whereIn';
                        $scopeQuery->{$method}('id', $divisiIds);
                    }
                });
            });
        }

        if ($user->isDivisionScopedRole()) {
            $divisiIds = $user->scopedDivisionIds();

            return !empty($divisiIds)
                ? $query->whereIn('divisi_id', $divisiIds)
                : $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    private function getScopedDivisions(Request $request)
    {
        $user = $request->user();
        $query = Divisi::query()
            ->with('departemen.perusahaan')
            ->whereHas('departemen.perusahaan', function (Builder $companyQuery) {
                $companyQuery->whereIn('kode_perusahaan', Perusahaan::ORGANIZATION_COMPANY_CODES);
            })
            ->withCount(['karyawan as active_employee_count'])
            ->orderBy('nama_divisi');

        if ($user->canAccessAllEmployees()) {
            return $query->get();
        }

        if ($user->isDepartmentScopedRole()) {
            $departemenIds = $user->scopedDepartmentIds();
            $divisiIds = $user->isHodRole() ? $user->scopedDivisionIds() : [];

            if (empty($departemenIds) && empty($divisiIds)) {
                return collect();
            }

            return $query
                ->where(function (Builder $scopeQuery) use ($departemenIds, $divisiIds) {
                    if (!empty($departemenIds)) {
                        $scopeQuery->whereIn('departemen_id', $departemenIds);
                    }

                    if (!empty($divisiIds)) {
                        $method = !empty($departemenIds) ? 'orWhereIn' : 'whereIn';
                        $scopeQuery->{$method}('id', $divisiIds);
                    }
                })
                ->get();
        }

        if ($user->isDivisionScopedRole()) {
            $divisiIds = $user->scopedDivisionIds();

            return !empty($divisiIds)
                ? $query->whereIn('id', $divisiIds)->get()
                : collect();
        }

        return collect();
    }
}
