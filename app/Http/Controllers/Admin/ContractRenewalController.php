<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Http\Requests\ContractRenewal\AssessContractRenewalRequest;
use App\Http\Requests\ContractRenewal\BulkStoreContractRenewalRequest;
use App\Http\Requests\ContractRenewal\DelegateContractRenewalRequest;
use App\Http\Requests\ContractRenewal\ReviseTerminatedContractRenewalRequest;
use App\Http\Requests\ContractRenewal\StoreContractRenewalRequest;
use App\Http\Requests\ElectronicContract\ImportContractHistoryRequest;
use App\Imports\ImportEmployeeContractHistories;
use App\Jobs\DeleteImportedFile;
use App\Models\EmployeeContractHistory;
use App\Models\EmployeeContractRenewal;
use App\Models\ImportHistory;
use App\Models\User;
use App\Services\ContractRenewals\ContractMonitoringDashboardService;
use App\Services\ContractRenewals\ContractRenewalService;
use App\Services\ImportHistory\ImportHistoryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ContractRenewalController extends Controller
{
    public function dashboard(
        Request $request,
        ContractRenewalService $renewalService,
        ContractMonitoringDashboardService $dashboardService
    ) {
        abort_unless($renewalService->canAccessIndex($request->user()), 403, 'Anda tidak memiliki akses ke monitoring kontrak.');

        $validated = $request->validate([
            'area' => ['nullable', 'string', 'max:40'],
            'departemen_id' => ['nullable', 'string', 'max:40'],
            'divisi_id' => ['nullable', 'string', 'max:40'],
            'days' => ['nullable', 'integer', 'min:7', 'max:180'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters = $renewalService->resolveOrganizationFilters($request->user(), $validated);
        $filterOptions = $renewalService->organizationFilterOptions($request->user(), $filters);
        $days = (int) ($validated['days'] ?? 30);
        $days = max(7, min($days > 0 ? $days : 30, 180));
        $search = mb_substr(trim((string) ($validated['search'] ?? '')), 0, 100);

        return view('admin.contract-renewals.dashboard', [
            'dashboard' => $dashboardService->dashboard($request->user(), array_merge($filters, [
                'days' => $days,
                'search' => $search,
            ])),
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'days' => $days,
            'search' => $search,
            'statusOptions' => EmployeeContractRenewal::statusLabels(),
        ]);
    }

    public function index(Request $request, ContractRenewalService $service)
    {
        abort_unless($service->canAccessIndex($request->user()), 403, 'Anda tidak memiliki akses ke perpanjangan kontrak.');

        $days = (int) $request->input('days', 30);
        $days = max(1, min($days, 90));
        $status = $request->input('status');
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 100);
        $canManageWorkflow = $service->canManageWorkflow($request->user());
        $filters = $service->resolveOrganizationFilters($request->user(), $request->only([
            'area',
            'departemen_id',
            'divisi_id',
        ]));
        $filterOptions = $service->organizationFilterOptions($request->user(), $filters);

        if ($canManageWorkflow) {
            $upcomingHistoriesQuery = $service->applyOrganizationFilters(
                $service->upcomingHistoriesQuery($request->user(), $days),
                $filters
            );
            $service->applyEmployeeSearch($upcomingHistoriesQuery, $search);

            $upcomingHistories = $upcomingHistoriesQuery
                ->orderBy('employee_contract_histories.contract_end_date')
                ->orderBy('employee_contract_histories.nik')
                ->paginate(20, ['*'], 'upcoming_page')
                ->appends($request->query());
        } else {
            $upcomingHistories = new LengthAwarePaginator([], 0, 20, 1, [
                'path' => $request->url(),
                'pageName' => 'upcoming_page',
            ]);
        }

        $renewalsQuery = $service->applyOrganizationFilters(
            $service->renewalsQuery($request->user()),
            $filters
        );
        $service->applyEmployeeSearch($renewalsQuery, $search);

        if ($status && array_key_exists($status, EmployeeContractRenewal::statusLabels())) {
            $renewalsQuery->where('status', $status);
        }

        $renewals = $renewalsQuery
            ->paginate(30, ['*'], 'renewal_page')
            ->appends($request->query());

        $delegateCandidateCache = [];
        $delegateOptions = $renewals
            ->getCollection()
            ->filter(fn(EmployeeContractRenewal $renewal) => $canManageWorkflow && in_array($renewal->status, [
                EmployeeContractRenewal::STATUS_PENDING_DELEGATION,
                EmployeeContractRenewal::STATUS_WAITING_DELEGATE_ASSESSMENT,
            ], true))
            ->mapWithKeys(function (EmployeeContractRenewal $renewal) use ($service, $request, &$delegateCandidateCache) {
                $employee = $renewal->employee;
                $cacheKey = $employee
                    ? ((string) $employee->departemen_id . '|' . (string) $employee->divisi_id)
                    : 'renewal|' . $renewal->id;

                if (!array_key_exists($cacheKey, $delegateCandidateCache)) {
                    $delegateCandidateCache[$cacheKey] = $service->delegateCandidates($renewal, $request->user(), null, 500);
                }

                return [
                    $renewal->id => $delegateCandidateCache[$cacheKey],
                ];
            });

        $historyNiks = collect($upcomingHistories->items())
            ->pluck('nik')
            ->merge($renewals->getCollection()->pluck('employee_nik'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $contractHistoryMap = $service->contractHistoriesForNiks($historyNiks);

        return view('admin.contract-renewals.index', [
            'days' => $days,
            'status' => $status,
            'search' => $search,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'canManageRenewalWorkflow' => $canManageWorkflow,
            'upcomingHistories' => $upcomingHistories,
            'renewals' => $renewals,
            'delegateOptions' => $delegateOptions,
            'contractHistoryMap' => $contractHistoryMap,
            'statusOptions' => EmployeeContractRenewal::statusLabels(),
        ]);
    }

    public function importHistory(
        ImportContractHistoryRequest $request,
        ImportHistoryService $importHistoryService
    ) {
        $uploadedFile = $request->file('file');
        $history = null;

        try {
            $filePath = $uploadedFile->store('imports/contract-histories');
            $history = $importHistoryService->createQueued([
                'import_type' => ImportHistory::TYPE_CONTRACT_HISTORY,
                'module' => 'contract_renewal',
                'source' => ImportHistory::SOURCE_EXCEL,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => config('filesystems.default'),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
                'summary' => [
                    'source_format' => 'vertical_contract_history',
                ],
            ]);

            Excel::queueImport(
                new ImportEmployeeContractHistories(
                    optional($history)->id,
                    (string) $request->user()->id
                ),
                storage_path('app/' . $filePath)
            )->chain([
                new DeleteImportedFile($filePath),
            ]);

            toast()->success('Success', 'Import history kontrak sedang diproses. Pantau hasilnya di menu History Import.');
            return redirect()->route('contract-renewals.index');
        } catch (Throwable $exception) {
            $importHistoryService->markFailed(optional($history)->id, $exception);
            report($exception);

            toast()->error('Error', 'Import history kontrak gagal dijalankan. Periksa format Excel dan coba lagi.');
            return back();
        }
    }

    public function store(StoreContractRenewalRequest $request, ContractRenewalService $service)
    {
        $history = EmployeeContractHistory::query()
            ->with('employee')
            ->whereKey($request->input('history_id'))
            ->firstOrFail();

        try {
            $renewal = $service->createFromHistory($history, $request->user());
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        }

        toast()->success('Success', 'Pengajuan perpanjangan kontrak berhasil dibuat. Lanjutkan dengan memilih delegasi penilaian.');
        return redirect()->route('contract-renewals.index', ['status' => $renewal->status]);
    }

    public function bulkStore(BulkStoreContractRenewalRequest $request, ContractRenewalService $service)
    {
        if ($request->input('bulk_action') === 'hod_direct' && !$request->user()->hasRole(['Super Admin', 'HOD'])) {
            abort(403, 'Penilaian HOD kolektif hanya tersedia untuk HOD.');
        }

        $assessmentMonths = $request->input('bulk_action') === 'hod_direct'
            ? (int) $request->input('assessment_months')
            : null;

        $summary = $service->bulkCreateFromHistories(
            $request->input('history_ids', []),
            $request->user(),
            $assessmentMonths,
            $request->input('assessment_note')
        );

        $message = "Diproses {$summary['total']} kontrak. Workflow baru: {$summary['created']}, sudah ada: {$summary['existing']}";

        if ($assessmentMonths !== null) {
            $decisionLabel = EmployeeContractRenewal::assessmentDecisionLabel($assessmentMonths);
            $message .= ", dinilai HOD ({$decisionLabel}): {$summary['assessed']}";
        }

        if ($summary['failed'] > 0) {
            $message .= ", gagal: {$summary['failed']}.";
            toast()->warning('Sebagian Berhasil', $message . ' ' . implode(' | ', $summary['errors']));
        } else {
            toast()->success('Success', $message . '.');
        }

        return redirect()->route('contract-renewals.index', $request->only([
            'area',
            'departemen_id',
            'divisi_id',
            'days',
            'status',
            'search',
        ]));
    }

    public function delegate(
        DelegateContractRenewalRequest $request,
        EmployeeContractRenewal $renewal,
        ContractRenewalService $service
    ) {
        $delegate = User::query()->whereKey($request->input('delegate_user_id'))->firstOrFail();

        try {
            $service->delegate($renewal, $delegate, $request->user());
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        }

        toast()->success('Success', 'Delegasi penilaian perpanjangan kontrak berhasil disimpan.');
        return back();
    }

    public function assess(
        AssessContractRenewalRequest $request,
        EmployeeContractRenewal $renewal,
        ContractRenewalService $service
    ) {
        try {
            if ($request->input('assessment_mode') === 'hod_direct') {
                $service->assessDirectlyByHod(
                    $renewal,
                    (int) $request->input('assessment_months'),
                    $request->input('assessment_note'),
                    $request->user()
                );
            } else {
                $service->assess(
                    $renewal,
                    (int) $request->input('assessment_months'),
                    $request->input('assessment_note'),
                    $request->user()
                );
            }
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        }

        $message = $request->input('assessment_mode') === 'hod_direct'
            ? 'Penilaian HOD berhasil disimpan. Pengajuan masuk ke approval HRD.'
            : 'Penilaian perpanjangan kontrak berhasil dikirim ke HOD.';

        toast()->success('Success', $message);
        return back();
    }

    public function hodProcess(
        ProcessApprovalRequest $request,
        EmployeeContractRenewal $renewal,
        ContractRenewalService $service
    ) {
        try {
            DB::transaction(function () use ($request, $renewal, $service) {
                $service->processHod(
                    $renewal,
                    (int) $request->input('action'),
                    $request->input('note'),
                    $request->user()
                );
            });
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        }

        toast()->success('Success', 'Approval HOD perpanjangan kontrak berhasil diproses.');
        return back();
    }

    public function hrdProcess(
        ProcessApprovalRequest $request,
        EmployeeContractRenewal $renewal,
        ContractRenewalService $service
    ) {
        $processedRenewal = null;

        try {
            $processedRenewal = $service->processHrd(
                $renewal,
                (int) $request->input('action'),
                $request->input('note'),
                $request->user()
            );
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);
            toast()->error('Gagal', 'Approval HRD gagal diproses. Periksa data workflow dan coba lagi.');
            return back();
        }

        if ((int) $request->input('action') === EmployeeContractRenewal::APPROVAL_APPROVED) {
            $message = $processedRenewal && $processedRenewal->status === EmployeeContractRenewal::STATUS_CONTRACT_TERMINATED
                ? 'Approval HRD berhasil. Workflow ditutup sebagai PUTUS KONTRAK dan notifikasi dikirim ke karyawan.'
                : 'Approval HRD berhasil. Kontrak elektronik sudah dibuat dan dikirim ke karyawan.';
        } else {
            $message = 'Approval HRD berhasil diproses.';
        }

        toast()->success('Success', $message);
        return back();
    }

    public function reviseTermination(
        ReviseTerminatedContractRenewalRequest $request,
        EmployeeContractRenewal $renewal,
        ContractRenewalService $service
    ) {
        try {
            $service->reviseTerminationToRenewal(
                $renewal,
                (int) $request->input('assessment_months'),
                $request->input('revision_note'),
                $request->user()
            );
        } catch (ValidationException $exception) {
            toast()->error('Gagal', $this->firstValidationMessage($exception));
            return back()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);
            toast()->error('Gagal', 'Revisi putus kontrak gagal diproses. Periksa data karyawan dan template adendum.');
            return back();
        }

        toast()->success('Success', 'Revisi putus kontrak berhasil. Status karyawan dikoreksi bila diperlukan, kontrak elektronik dibuat, dan karyawan mendapat notifikasi.');
        return back();
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (!empty($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'Data tidak valid. Periksa input dan coba lagi.';
    }
}
