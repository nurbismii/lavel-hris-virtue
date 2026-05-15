<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElectronicContract\SignEmployeeContractRequest;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Services\ElectronicContracts\ElectronicContractAuditService;
use App\Services\ElectronicContracts\ElectronicContractService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ElectronicContractController extends Controller
{
    private const CONSENT_TEXT = 'Saya telah membaca dan menyetujui isi kontrak elektronik ini. Tanda tangan ini diberikan secara sadar untuk dokumen dan nomor kontrak yang ditampilkan pada halaman ini.';

    public function index(Request $request)
    {
        abort_unless(filled($request->user()->nik_karyawan), 403, 'Akun belum terhubung dengan NIK karyawan.');

        return view('user.electronic-contracts.index', [
            'contracts' => EmployeeContract::query()
                ->with('template:id,name,contract_type')
                ->where('nik', $request->user()->nik_karyawan)
                ->latest('created_at')
                ->paginate(20),
        ]);
    }

    public function show(
        Request $request,
        EmployeeContract $contract,
        ElectronicContractAuditService $audit,
        ElectronicContractService $service
    ) {
        $this->authorizeEmployeeContract($request, $contract);
        $contract->loadMissing(['employee', 'template', 'signature']);
        $audit->record($contract, 'contract_viewed_employee', $request);

        return view('user.electronic-contracts.show', [
            'contract' => $contract,
            'html' => $service->renderContractHtmlForDisplay($contract, $contract->signature),
            'consentText' => self::CONSENT_TEXT,
        ]);
    }

    public function sign(
        SignEmployeeContractRequest $request,
        EmployeeContract $contract,
        ElectronicContractService $service,
        ElectronicContractAuditService $audit
    ) {
        $this->authorizeEmployeeContract($request, $contract);

        if (!$contract->isReadyForSignature()) {
            throw ValidationException::withMessages([
                'signature_data' => 'Kontrak ini sudah tidak dalam status menunggu tanda tangan.',
            ]);
        }

        $signaturePath = $service->saveSignatureImage($request->input('signature_data'), $contract);

        try {
            $signature = DB::transaction(function () use ($request, $contract, $signaturePath) {
                $lockedContract = EmployeeContract::query()
                    ->where('id', $contract->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$lockedContract->isReadyForSignature() || $lockedContract->signature()->exists()) {
                    throw ValidationException::withMessages([
                        'signature_data' => 'Kontrak ini sudah ditandatangani atau dibatalkan.',
                    ]);
                }

                return EmployeeContractSignature::create([
                    'employee_contract_id' => $lockedContract->id,
                    'nik' => $lockedContract->nik,
                    'signed_by_user_id' => $request->user()->id,
                    'signature_path' => $signaturePath,
                    'signed_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'consent_text' => self::CONSENT_TEXT,
                ]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'signature_data' => 'Kontrak ini sudah ditandatangani.',
                ]);
            }

            throw $exception;
        }

        $service->generateSignedPdf($contract->fresh(['employee', 'template']), $signature);
        $audit->record($contract->fresh(), 'contract_signed_employee', $request, [
            'signature_id' => $signature->id,
        ]);

        toast()->success('Success', 'Kontrak berhasil ditandatangani.');
        return redirect()->route('user-electronic-contracts.show', $contract);
    }

    private function authorizeEmployeeContract(Request $request, EmployeeContract $contract): void
    {
        abort_unless(
            $request->user() && $contract->nik === $request->user()->nik_karyawan,
            403,
            'Kontrak ini tidak tersedia untuk akun Anda.'
        );
    }
}
