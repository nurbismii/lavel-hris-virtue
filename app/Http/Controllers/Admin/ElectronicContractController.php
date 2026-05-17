<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\ActivateOnboardingContractRequest;
use App\Http\Requests\ElectronicContract\ImportPkwtContractExcelRequest;
use App\Http\Requests\ElectronicContract\StoreManualSignedContractRequest;
use App\Http\Requests\ElectronicContract\StoreFirstPartySignatureRequest;
use App\Http\Requests\ElectronicContract\StoreEmployeeContractRequest;
use App\Imports\ImportPkwtOneContracts;
use App\Jobs\DeleteImportedFile;
use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\ImportHistory;
use App\Models\OnboardingCandidate;
use App\Models\VhireSyncLog;
use App\Services\ElectronicContracts\ElectronicContractAuditService;
use App\Services\ElectronicContracts\ElectronicContractService;
use App\Services\ImportHistory\ImportHistoryService;
use App\Services\Vhire\VhireOnboardingContractService;
use App\Services\Vhire\VhireSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class ElectronicContractController extends Controller
{
    public function index(Request $request, ElectronicContractService $service)
    {
        $query = EmployeeContract::query()
            ->with(['employee:nik,nama_karyawan,posisi,departemen_id,divisi_id', 'onboardingCandidate:id,nama,candidate_code,no_ktp,employee_nik', 'template:id,name,contract_type'])
            ->latest('created_at');
        $quickFilterOptions = $this->contractQuickFilterOptions();
        $quickFilter = (string) $request->input('quick_filter', 'all');

        if (!array_key_exists($quickFilter, $quickFilterOptions)) {
            $quickFilter = 'all';
        }

        $this->applyContractQuickFilter($query, $quickFilter);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nik', 'like', $search . '%')
                    ->orWhere('candidate_name', 'like', '%' . $search . '%')
                    ->orWhere('candidate_code', 'like', '%' . $search . '%')
                    ->orWhere('vhire_candidate_id', 'like', '%' . $search . '%')
                    ->orWhere('pkwt_number', 'like', '%' . $search . '%')
                    ->orWhere('contract_number', 'like', '%' . $search . '%')
                    ->orWhere('addendum_number', 'like', '%' . $search . '%')
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('nama_karyawan', 'like', '%' . $search . '%');
                    });
            });
        }

        return view('admin.electronic-contracts.index', [
            'contracts' => $query->paginate(20)->appends($request->query()),
            'typeOptions' => ContractTemplate::typeOptions(),
            'statusOptions' => EmployeeContract::statusOptions(),
            'signingMethodOptions' => EmployeeContract::signingMethodOptions(),
            'quickFilterOptions' => $quickFilterOptions,
            'filters' => array_merge(
                $request->only(['status', 'contract_type', 'search']),
                ['quick_filter' => $quickFilter]
            ),
            'firstPartySignature' => $service->firstPartySignature(),
            'canManageFirstPartySignature' => $request->user()
                && $request->user()->hasRole(['Super Admin', 'HR'])
                && $request->user()->hasMenuAccess('electronic_contract_first_party_signature'),
        ]);
    }

    public function create(Request $request)
    {
        $selectedEmployee = null;
        $selectedNik = old('nik');

        if (filled($selectedNik)) {
            $selectedEmployee = $this->buildSelectableEmployeeQuery($request)
                ->where('nik', $selectedNik)
                ->first();
        }

        return view('admin.electronic-contracts.create', [
            'selectedEmployee' => $selectedEmployee,
            'typeOptions' => ContractTemplate::typeOptions(),
            'signingMethodOptions' => EmployeeContract::signingMethodOptions(),
            'templates' => ContractTemplate::query()
                ->where('is_active', true)
                ->orderBy('contract_type')
                ->orderBy('name')
                ->get(['id', 'contract_type', 'name']),
            'clauseOptions' => ContractClause::query()
                ->where('is_active', true)
                ->orderBy('clause_key')
                ->pluck('name', 'clause_key')
                ->all(),
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $perPage = 20;

        if (strlen($term) < 2) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $employees = $this->buildSelectableEmployeeQuery($request)
            ->where(function ($query) use ($term) {
                $like = '%' . $term . '%';

                $query->where('nik', 'like', $like)
                    ->orWhere('nama_karyawan', 'like', $like)
                    ->orWhere('posisi', 'like', $like);
            })
            ->orderBy('nama_karyawan')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'results' => $employees
                ->take($perPage)
                ->map(fn(Employee $employee) => [
                    'id' => $employee->nik,
                    'text' => trim($employee->nama_karyawan . ' - ' . $employee->nik . ' | ' . ($employee->posisi ?: '-')),
                    'employee' => [
                        'nik' => $employee->nik,
                        'name' => $employee->nama_karyawan,
                        'position' => $employee->posisi,
                        'gender' => $employee->jenis_kelamin,
                        'marital_status' => null,
                        'address' => $employee->alamat_domisili ?: $employee->alamat_ktp,
                    ],
                ])
                ->values(),
            'pagination' => [
                'more' => $employees->count() > $perPage,
            ],
        ]);
    }

    public function store(
        StoreEmployeeContractRequest $request,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $employee = $this->buildSelectableEmployeeQuery($request)
            ->where('nik', $request->input('nik'))
            ->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'nik' => 'Karyawan tidak tersedia dalam scope akses Anda.',
            ]);
        }

        try {
            $contract = $service->createContract($request->validated(), $request->user());
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'nik' => 'Nomor adendum bentrok dengan data yang sudah ada. Muat ulang halaman dan coba lagi.',
                ]);
            }

            throw $exception;
        }

        $audit->record($contract, 'contract_created', $request, [
            'contract_type' => $contract->contract_type,
            'display_number' => $contract->display_number,
        ]);

        toast()->success('Success', 'Kontrak berhasil dibuat dan siap diproses sesuai metode tanda tangan.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    public function importPkwtVhire(
        ImportPkwtContractExcelRequest $request,
        ImportHistoryService $importHistoryService
    ) {
        $uploadedFile = $request->file('file');
        $history = null;

        try {
            $filePath = $uploadedFile->store('imports/pkwt-contracts');
            $history = $importHistoryService->createQueued([
                'import_type' => ImportHistory::TYPE_PKWT_ONE_CONTRACT,
                'module' => 'electronic_contract',
                'source' => ImportHistory::SOURCE_EXCEL,
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => config('filesystems.default'),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'created_by' => (string) $request->user()->id,
                'summary' => [
                    'sync_target' => 'vhire',
                    'signing_method' => $request->input('signing_method'),
                ],
            ]);

            Excel::queueImport(
                new ImportPkwtOneContracts(
                    optional($history)->id,
                    $request->input('signing_method'),
                    (string) $request->user()->id,
                    (string) $request->user()->name
                ),
                storage_path('app/' . $filePath)
            )->chain([
                new DeleteImportedFile($filePath),
            ]);

            toast()->success('Success', 'Import PKWT 1 sedang diproses. Kontrak akan dikirim ke V-Hire berdasarkan No KTP.');
            return redirect()->route('electronic-contracts.index', ['quick_filter' => 'vhire']);
        } catch (Throwable $exception) {
            $importHistoryService->markFailed(optional($history)->id, $exception);
            report($exception);

            toast()->error('Error', 'Import PKWT 1 gagal dijalankan. Periksa format Excel dan coba lagi.');
            return back();
        }
    }

    public function show(Request $request, EmployeeContract $contract, ElectronicContractService $service)
    {
        $contract->loadMissing(['employee', 'onboardingCandidate', 'template', 'signature']);

        return view('admin.electronic-contracts.show', [
            'contract' => $contract,
            'html' => $service->renderContractHtmlForDisplay($contract, $contract->signature),
            'firstPartySignature' => $service->firstPartySignature(),
            'firstPartySignaturePreview' => $service->storedSignatureImageSrc($service->firstPartySignaturePathForContract($contract)),
            'canManageFirstPartySignature' => $request->user()
                && $request->user()->hasRole(['Super Admin', 'HR'])
                && $request->user()->hasMenuAccess('electronic_contract_first_party_signature'),
            'auditLogs' => $contract->auditLogs()->latest()->limit(20)->get(),
            'manualVerificationStatusOptions' => EmployeeContract::manualVerificationStatusOptions(),
            'latestVhireSyncLogs' => VhireSyncLog::query()
                ->where(function ($query) use ($contract) {
                    $query->where(function ($subQuery) use ($contract) {
                        $subQuery->where('related_type', EmployeeContract::class)
                            ->where('related_id', $contract->id);
                    });

                    if ($contract->onboarding_candidate_id) {
                        $query->orWhere(function ($subQuery) use ($contract) {
                            $subQuery->where('related_type', \App\Models\OnboardingCandidate::class)
                                ->where('related_id', $contract->onboarding_candidate_id);
                        });
                    }
                })
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function editFirstPartySignature(ElectronicContractService $service)
    {
        $signature = $service->firstPartySignature();

        return view('admin.electronic-contracts.first-party-signature', [
            'signature' => $signature,
            'signaturePreview' => $signature
                ? $service->storedSignatureImageSrc($signature->signature_path)
                : null,
        ]);
    }

    public function storeFirstPartySignature(
        StoreFirstPartySignatureRequest $request,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        $oldSignaturePath = optional($service->firstPartySignature())->signature_path;
        $mode = $request->input('signature_mode');
        $signaturePath = $mode === 'upload'
            ? $service->saveMasterFirstPartySignatureUpload($request->file('signature_file'))
            : $service->saveMasterFirstPartySignatureImage($request->input('signature_data'));

        $signature = $service->saveMasterFirstPartySignature(
            $signaturePath,
            $mode === 'upload' ? 'uploaded' : 'drawn',
            $request->user()
        );

        if (
            $oldSignaturePath &&
            $oldSignaturePath !== $signaturePath &&
            Storage::exists($oldSignaturePath) &&
            !EmployeeContract::query()->where('first_party_signature_path', $oldSignaturePath)->exists()
        ) {
            Storage::delete($oldSignaturePath);
        }

        $audit->record(null, 'contract_master_first_party_signature_saved', $request, [
            'signature_id' => $signature->id,
            'signature_source' => $signature->signature_source,
        ]);

        toast()->success('Success', 'Tanda tangan master Pihak Pertama berhasil disimpan dan akan dipakai untuk kontrak berikutnya.');
        return redirect()->route('electronic-contracts.first-party-signature.edit');
    }

    public function preview(
        Request $request,
        EmployeeContract $contract,
        ElectronicContractAuditService $audit,
        ElectronicContractService $service
    )
    {
        $audit->record($contract, 'contract_previewed_admin', $request);
        $contract->loadMissing(['employee', 'signature']);

        return view('contracts.pdf', [
            'contract' => $contract,
            'html' => $service->renderContractHtmlForPdf($contract, $contract->signature),
            'signature' => $contract->signature,
        ]);
    }

    public function pdf(
        Request $request,
        EmployeeContract $contract,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $audit->record($contract, 'contract_pdf_opened_admin', $request);
        $contract->loadMissing(['employee', 'signature']);

        if ($contract->pdf_path && File::isFile(Storage::path($contract->pdf_path))) {
            return response()->file(Storage::path($contract->pdf_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    $this->buildPdfFilename($contract)
                ),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $pdf = Pdf::loadView('contracts.pdf', [
            'contract' => $contract,
            'html' => $service->renderContractHtmlForPdf($contract, $contract->signature),
            'signature' => $contract->signature,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream($this->buildPdfFilename($contract));
    }

    public function cancel(
        Request $request,
        EmployeeContract $contract,
        ElectronicContractAuditService $audit,
        VhireSyncService $syncService
    )
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        if ($contract->status === EmployeeContract::STATUS_SIGNED) {
            toast()->warning('Peringatan', 'Kontrak yang sudah ditandatangani tidak bisa dibatalkan dari fitur ini.');
            return back();
        }

        $contract->update([
            'status' => EmployeeContract::STATUS_CANCELLED,
            'signature_status' => EmployeeContract::SIGNATURE_STATUS_CANCELLED,
            'updated_by' => $request->user()->id,
        ]);
        $audit->record($contract, 'contract_cancelled', $request);

        if ($contract->vhire_candidate_id) {
            $syncService->queueContractSync($contract->fresh(), $request->user());
        }

        toast()->success('Success', 'Kontrak elektronik berhasil dibatalkan.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    public function storeManualSignedContract(
        StoreManualSignedContractRequest $request,
        EmployeeContract $contract,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit,
        VhireSyncService $syncService
    ) {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        $oldFilePath = $contract->manual_signed_file_path;
        $contract = $service->storeManualSignedContract(
            $contract,
            $request->file('manual_signed_file'),
            $request->user(),
            $request->input('manual_verification_status'),
            $request->input('manual_note')
        );

        $audit->record($contract, 'contract_manual_signed_file_uploaded', $request, [
            'old_file_path' => $oldFilePath,
            'new_file_path' => $contract->manual_signed_file_path,
            'manual_verification_status' => $contract->manual_verification_status,
        ]);

        if ($contract->vhire_candidate_id) {
            $syncService->queueContractSync($contract, $request->user());
        }

        toast()->success('Success', 'Kontrak manual berhasil diunggah dan dicatat di HRIS.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    public function manualSignedFile(Request $request, EmployeeContract $contract, ElectronicContractAuditService $audit)
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);
        abort_unless($contract->manual_signed_file_path && Storage::exists($contract->manual_signed_file_path), 404);

        $audit->record($contract, 'contract_manual_signed_file_opened', $request);

        return response()->file(Storage::path($contract->manual_signed_file_path), [
            'Content-Type' => $contract->manual_signed_mime_type ?: 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $this->buildManualFilename($contract)
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retryVhireSync(
        Request $request,
        EmployeeContract $contract,
        VhireSyncService $syncService,
        ElectronicContractAuditService $audit
    )
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);
        abort_unless($contract->onboarding_candidate_id || $contract->vhire_candidate_id || $contract->no_ktp, 422, 'Kontrak ini tidak terhubung dengan flow PKWT V-Hire.');

        $syncService->queueContractSync($contract->fresh(['onboardingCandidate']), $request->user());
        $audit->record($contract, 'vhire_contract_sync_retry_queued', $request);

        toast()->success('Success', 'Retry sync kontrak ke V-Hire sudah dimasukkan ke queue.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    public function activateVhireCandidate(
        ActivateOnboardingContractRequest $request,
        EmployeeContract $contract,
        VhireOnboardingContractService $service
    ) {
        $service->activateContract($contract, $request->input('employee_nik'), $request);

        toast()->success('Success', 'Kandidat berhasil ditautkan ke NIK HRIS dan update aktivasi dikirim ke V-Hire.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    private function buildSelectableEmployeeQuery(Request $request)
    {
        return $request->user()->applyEmployeeScope(
            Employee::query()
                ->select([
                    'nik',
                    'nama_karyawan',
                    'posisi',
                    'jenis_kelamin',
                    'alamat_domisili',
                    'alamat_ktp',
                    'departemen_id',
                    'divisi_id',
                ])
        );
    }

    private function buildPdfFilename(EmployeeContract $contract): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-]+/', '-', $contract->display_number);
        $employeeKey = $contract->nik ?: ($contract->candidate_code ?: 'candidate-' . $contract->id);

        return trim('Kontrak-' . $employeeKey . '-' . $number, '-') . '.pdf';
    }

    private function buildManualFilename(EmployeeContract $contract): string
    {
        $extension = pathinfo((string) $contract->manual_signed_file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $number = preg_replace('/[^A-Za-z0-9\-]+/', '-', $contract->display_number);
        $employeeKey = $contract->nik ?: ($contract->candidate_code ?: 'candidate-' . $contract->id);

        return trim('Kontrak-Manual-' . $employeeKey . '-' . $number, '-') . '.' . $extension;
    }

    private function contractQuickFilterOptions(): array
    {
        return [
            'all' => 'Semua Kontrak',
            'vhire' => 'PKWT V-Hire',
            'waiting_signature' => 'Menunggu TTD',
            'manual' => 'Manual',
            'failed_sync' => 'Gagal Sync',
            'waiting_activation' => 'Menunggu Aktivasi NIK',
        ];
    }

    private function applyContractQuickFilter($query, string $quickFilter): void
    {
        if ($quickFilter === 'vhire') {
            $query->where(function ($vhireQuery) {
                $vhireQuery->whereNotNull('vhire_candidate_id')
                    ->orWhereNotNull('onboarding_candidate_id');
            });
            return;
        }

        if ($quickFilter === 'waiting_signature') {
            $query->where('signature_status', EmployeeContract::SIGNATURE_STATUS_WAITING);
            return;
        }

        if ($quickFilter === 'manual') {
            $query->where('signing_method', EmployeeContract::SIGNING_METHOD_MANUAL);
            return;
        }

        if ($quickFilter === 'failed_sync') {
            $query->whereExists(function ($syncQuery) {
                $syncQuery->select(DB::raw(1))
                    ->from('vhire_sync_logs')
                    ->where('vhire_sync_logs.status', VhireSyncLog::STATUS_FAILED)
                    ->where(function ($relatedQuery) {
                        $relatedQuery
                            ->where(function ($contractLogQuery) {
                                $contractLogQuery->where('vhire_sync_logs.related_type', EmployeeContract::class)
                                    ->whereColumn('vhire_sync_logs.related_id', 'employee_contracts.id');
                            })
                            ->orWhere(function ($candidateLogQuery) {
                                $candidateLogQuery->where('vhire_sync_logs.related_type', OnboardingCandidate::class)
                                    ->whereColumn('vhire_sync_logs.related_id', 'employee_contracts.onboarding_candidate_id');
                            });
                    });
            });
            return;
        }

        if ($quickFilter === 'waiting_activation') {
            $query->where(function ($vhireQuery) {
                $vhireQuery->whereNotNull('vhire_candidate_id')
                    ->orWhereNotNull('onboarding_candidate_id');
            })
                ->where(function ($activationQuery) {
                    $activationQuery->whereNull('nik')
                        ->orWhere('nik', '');
                })
                ->where('status', '!=', EmployeeContract::STATUS_CANCELLED);
        }
    }
}
