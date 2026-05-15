<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\StoreFirstPartySignatureRequest;
use App\Http\Requests\ElectronicContract\StoreEmployeeContractRequest;
use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\ElectronicContracts\ElectronicContractAuditService;
use App\Services\ElectronicContracts\ElectronicContractService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ElectronicContractController extends Controller
{
    public function index(Request $request, ElectronicContractService $service)
    {
        $query = EmployeeContract::query()
            ->with(['employee:nik,nama_karyawan,posisi,departemen_id,divisi_id', 'template:id,name,contract_type'])
            ->latest('created_at');

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
            'filters' => $request->only(['status', 'contract_type', 'search']),
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

        toast()->success('Success', 'Kontrak elektronik berhasil dibuat dan siap ditandatangani karyawan.');
        return redirect()->route('electronic-contracts.show', $contract);
    }

    public function show(Request $request, EmployeeContract $contract, ElectronicContractService $service)
    {
        $contract->loadMissing(['employee', 'template', 'signature']);

        return view('admin.electronic-contracts.show', [
            'contract' => $contract,
            'html' => $service->renderContractHtmlForDisplay($contract, $contract->signature),
            'firstPartySignature' => $service->firstPartySignature(),
            'firstPartySignaturePreview' => $service->storedSignatureImageSrc($service->firstPartySignaturePathForContract($contract)),
            'canManageFirstPartySignature' => $request->user()
                && $request->user()->hasRole(['Super Admin', 'HR'])
                && $request->user()->hasMenuAccess('electronic_contract_first_party_signature'),
            'auditLogs' => $contract->auditLogs()->latest()->limit(20)->get(),
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

    public function cancel(Request $request, EmployeeContract $contract, ElectronicContractAuditService $audit)
    {
        abort_unless($request->user()->hasRole(['Super Admin', 'HR']), 403);

        if ($contract->status === EmployeeContract::STATUS_SIGNED) {
            toast()->warning('Peringatan', 'Kontrak yang sudah ditandatangani tidak bisa dibatalkan dari fitur ini.');
            return back();
        }

        $contract->update([
            'status' => EmployeeContract::STATUS_CANCELLED,
            'updated_by' => $request->user()->id,
        ]);
        $audit->record($contract, 'contract_cancelled', $request);

        toast()->success('Success', 'Kontrak elektronik berhasil dibatalkan.');
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

        return trim('Kontrak-' . $contract->nik . '-' . $number, '-') . '.pdf';
    }
}
