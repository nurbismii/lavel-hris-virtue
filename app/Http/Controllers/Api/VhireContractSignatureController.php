<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vhire\UpdateSignatureStatusRequest;
use App\Models\EmployeeContract;
use App\Services\Vhire\VhireOnboardingContractService;

class VhireContractSignatureController extends Controller
{
    public function store(
        UpdateSignatureStatusRequest $request,
        EmployeeContract $contract,
        VhireOnboardingContractService $service
    ) {
        $contract = $service->updateSignatureStatus($contract, $request->validated(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Status tanda tangan kontrak berhasil diperbarui di HRIS.',
            'data' => [
                'hris_contract_id' => $contract->id,
                'status' => $contract->status,
                'signature_status' => $contract->signature_status,
                'signed_at' => optional($contract->signed_at)->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
