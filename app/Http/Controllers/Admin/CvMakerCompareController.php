<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CvMaker\UpdateHrisFromCvRequest;
use App\Http\Requests\CvMaker\StoreReminderBatchRequest;
use App\Http\Requests\CvMaker\UpdateReviewStatusRequest;
use App\Http\Requests\CvMaker\CorrectHrisFieldRequest;
use App\Models\CvMakerReminderBatch;
use App\Models\CvMakerProgressStatus;
use App\Models\CvMakerPositionSkillCategory;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\CvMaker\CvMakerCompareService;
use App\Services\CvMaker\CvMakerApiClient;
use App\Services\CvMaker\CvMakerReminderService;
use App\Services\CvMaker\CvMakerReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $scopedEmployeeNiks = (clone $scopeQuery)->select('employees.nik');
        $jobTitles = CvMakerProgressStatus::query()
            ->whereIn('employee_nik', $scopedEmployeeNiks)
            ->whereNotNull('cv_job_title')
            ->where('cv_job_title', '<>', '')
            ->select('cv_job_title')
            ->distinct()
            ->orderBy('cv_job_title')
            ->pluck('cv_job_title')
            ->map(fn($jobTitle) => trim((string) $jobTitle))
            ->filter()
            ->unique()
            ->values();

        return view('admin.cv-maker-compare.index', [
            'departemens' => Departemen::with('perusahaan')->whereIn('id', $departemenIds)->orderBy('departemen')->get(),
            'divisis' => Divisi::whereIn('id', $divisiIds)->orderBy('nama_divisi')->get(),
            'areas' => Perusahaan::query()
                ->whereIn('kode_perusahaan', $areaCodes)
                ->whereIn('kode_perusahaan', Perusahaan::ORGANIZATION_COMPANY_CODES)
                ->orderBy('kode_perusahaan')
                ->get(),
            'jobTitles' => $jobTitles,
            'hrisJobTitles' => CvMakerCompareService::hrisJobTitlePrefixes(),
            'skillCategories' => CvMakerPositionSkillCategory::labels(),
            'integrationAvailable' => $service->isConfigured(),
        ]);
    }

    public function data(Request $request, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        return response()->json($service->datatable($request, $request->user()));
    }

    public function positions(Request $request): JsonResponse
    {
        $this->authorizeAccess($request->user());

        $keyword = trim(Str::substr((string) $request->query('q', ''), 0, 100));
        $page = max(1, min((int) $request->query('page', 1), 100));
        $perPage = 30;
        $query = $request->user()
            ->applyEmployeeScope(Employee::query(), 'employees')
            ->whereIn('employees.area_kerja', Perusahaan::ORGANIZATION_COMPANY_CODES)
            ->where('employees.status_resign', 'AKTIF')
            ->whereNotNull('employees.posisi')
            ->where('employees.posisi', '<>', '');

        if ($keyword !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
            $query->where('employees.posisi', 'like', $like);
        }

        $positions = $query
            ->select('employees.posisi')
            ->distinct()
            ->orderBy('employees.posisi')
            ->skip(($page - 1) * $perPage)
            ->take($perPage + 1)
            ->pluck('employees.posisi')
            ->map(fn($position) => trim((string) $position))
            ->filter()
            ->unique()
            ->values();
        $hasMore = $positions->count() > $perPage;

        return response()->json([
            'results' => $positions->take($perPage)
                ->map(fn($position) => ['id' => $position, 'text' => $position])
                ->values(),
            'pagination' => ['more' => $hasMore],
        ]);
    }

    public function storeReminderBatch(StoreReminderBatchRequest $request, CvMakerReminderService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());
        $batch = $service->createBatch($request, $request->user());

        return response()->json([
            'success' => true,
            'message' => $batch->pending_count > 0
                ? 'Reminder masuk antrean dan akan dikirim bertahap.'
                : 'Batch selesai tanpa email yang perlu dikirim.',
            'data' => $service->statusPayload($batch)['data'],
            'status_url' => route('cv-maker-compare.reminders.status', $batch),
        ], 202);
    }

    public function reminderBatchStatus(Request $request, CvMakerReminderBatch $batch, CvMakerReminderService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());
        abort_unless((string) $batch->requested_by === (string) $request->user()->id, 403);

        return response()->json($service->statusPayload($batch));
    }

    public function show(Request $request, string $nik, CvMakerCompareService $service)
    {
        $this->authorizeAccess($request->user());

        $employee = $this->scopedEmployee($request, $nik);

        return view('admin.cv-maker-compare.show', [
            'employee' => $employee,
            'detail' => $service->detailForEmployee($employee),
            'integrationAvailable' => $service->isConfigured(),
        ]);
    }

    public function previewUpdate(Request $request, string $nik, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        $employee = $this->scopedEmployee($request, $nik);
        $preview = $service->previewUpdateForEmployee($employee);

        return response()->json($this->hideRawChangeValues($preview), $preview['success'] ? 200 : 422);
    }

    public function updateHris(UpdateHrisFromCvRequest $request, string $nik, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());

        $employee = $this->scopedEmployee($request, $nik);
        $result = $service->updateHrisFromCv(
            $employee,
            $request->user(),
            $request->input('selected_fields', []),
            $request->input('selected_sections', []),
            $request->boolean('include_organization')
        );

        return response()->json($this->hideRawChangeValues($result), $result['success'] ? 200 : 422);
    }

    public function updateReviewStatus(UpdateReviewStatusRequest $request, string $nik, CvMakerReviewService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());
        $this->scopedEmployee($request, $nik);
        $progress = $service->update(
            $nik,
            $request->input('review_status'),
            $request->input('review_note'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Status pemeriksaan berhasil diperbarui.',
            'data' => [
                'review_status' => $progress->review_status,
                'review_label' => \App\Models\CvMakerProgressStatus::reviewLabels()[$progress->review_status] ?? $progress->review_status,
            ],
        ]);
    }

    public function correctField(CorrectHrisFieldRequest $request, string $nik, CvMakerCompareService $service): JsonResponse
    {
        $this->authorizeAccess($request->user());
        $employee = $this->scopedEmployee($request, $nik);
        $result = $service->correctHrisField(
            $employee,
            $request->user(),
            $request->input('field_key'),
            $request->input('value')
        );

        return response()->json($result);
    }

    public function document(Request $request, string $nik, int $document, CvMakerCompareService $service, CvMakerApiClient $client)
    {
        $this->authorizeAccess($request->user());
        $employee = $this->scopedEmployee($request, $nik);
        $detail = $service->detailForEmployee($employee);
        $allowed = collect(data_get($detail, 'vitae.documents', []))->contains(fn($item) => (int) ($item['id'] ?? 0) === $document);
        abort_unless($allowed, 404);

        return $this->proxyPrivateFile(
            $client->file('api/internal/vpeople/documents/' . $document, $service->hashNik((string) $employee->nik)),
            'dokumen-vitae-' . $document
        );
    }

    public function photo(Request $request, string $nik, int $profile, CvMakerCompareService $service, CvMakerApiClient $client)
    {
        $this->authorizeAccess($request->user());
        $employee = $this->scopedEmployee($request, $nik);
        $detail = $service->detailForEmployee($employee);
        abort_unless(
            (int) data_get($detail, 'cv_profile.profile_id', 0) === $profile
                && (bool) data_get($detail, 'vitae.profile.photo_available', false),
            404
        );

        return $this->proxyPrivateFile(
            $client->file('api/internal/vpeople/profiles/' . $profile . '/photo', $service->hashNik((string) $employee->nik)),
            'foto-vitae-' . $employee->nik
        );
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
                'jobTitle.level',
                'organizationPosition.levelOverride',
                'organizationPosition.jobTitle.level',
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

        if (isset($payload['related_changes']) && is_array($payload['related_changes'])) {
            $payload['related_changes'] = array_map(function (array $change) {
                unset($change['rows'], $change['profile_id']);

                return $change;
            }, $payload['related_changes']);
        }

        return $payload;
    }

    private function proxyPrivateFile($upstream, string $fallbackName)
    {
        abort_unless($upstream, 404);
        $contentType = $upstream->header('Content-Type') ?: 'application/octet-stream';
        $disposition = $upstream->header('Content-Disposition') ?: 'inline; filename="' . $fallbackName . '"';

        return response($upstream->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
            'Content-Length' => strlen($upstream->body()),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
