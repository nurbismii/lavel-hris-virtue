<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\CvMaker\CvMakerCompareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvMakerCompareController extends Controller
{
    private const ALLOWED_ROLES = [
        'Super Admin',
        'HR',
        'HOD',
        'Manager',
        'Supervisor',
        'Admin Divisi',
    ];

    public function index(Request $request, CvMakerCompareService $service)
    {
        $this->authorizeAccess($request->user());

        $scopeQuery = $request->user()->applyEmployeeScope(Employee::query());
        $departemenIds = (clone $scopeQuery)->select('departemen_id')->distinct()->pluck('departemen_id')->filter();
        $divisiIds = (clone $scopeQuery)->select('divisi_id')->distinct()->pluck('divisi_id')->filter();
        $areaCodes = (clone $scopeQuery)->select('area_kerja')->distinct()->pluck('area_kerja')->filter();

        return view('admin.cv-maker-compare.index', [
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::whereIn('kode_perusahaan', $areaCodes)->get(),
            'integrationAvailable' => $service->isConfigured(),
        ]);
    }

    public function data(Request $request, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        return response()->json($service->datatable($request, $request->user()));
    }

    public function previewUpdate(Request $request, string $nik, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        $employee = $this->scopedEmployee($request, $nik);
        $preview = $service->previewUpdateForEmployee($employee);

        return response()->json($this->hideRawChangeValues($preview), $preview['success'] ? 200 : 422);
    }

    public function updateHris(Request $request, string $nik, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        $employee = $this->scopedEmployee($request, $nik);
        $result = $service->updateHrisFromCv($employee, $request->user());

        return response()->json($this->hideRawChangeValues($result), $result['success'] ? 200 : 422);
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless(
            $user->hasRole(self::ALLOWED_ROLES) && $user->hasMenuAccess('cv_maker_compare'),
            403,
            'Compare CV Maker hanya tersedia untuk role pengelola data karyawan.'
        );
    }

    private function scopedEmployee(Request $request, string $nik): Employee
    {
        return $request->user()
            ->applyEmployeeScope(Employee::query(), 'employees')
            ->where('employees.nik', $nik)
            ->with([
                'departemen',
                'divisi',
                'provinsi',
                'kabupaten',
                'kecamatan',
                'kelurahan',
            ])
            ->firstOrFail();
    }

    private function hideRawChangeValues(array $payload): array
    {
        if (!isset($payload['changes']) || !is_array($payload['changes'])) {
            return $payload;
        }

        $payload['changes'] = array_map(function (array $change) {
            unset($change['old_raw'], $change['new_raw']);

            return $change;
        }, $payload['changes']);

        return $payload;
    }
}
