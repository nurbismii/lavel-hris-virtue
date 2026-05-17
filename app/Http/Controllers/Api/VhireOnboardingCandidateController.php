<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vhire\StoreOnboardingCandidateRequest;
use App\Services\Vhire\VhireOnboardingContractService;

class VhireOnboardingCandidateController extends Controller
{
    public function store(StoreOnboardingCandidateRequest $request, VhireOnboardingContractService $service)
    {
        $result = $service->receiveCandidate($request->validated(), $request);
        $candidate = $result['candidate'];
        $contract = $result['contract'];

        return response()->json([
            'success' => true,
            'message' => 'Kandidat onboarding berhasil diterima dan kontrak PKWT 1 diproses di HRIS.',
            'data' => [
                'onboarding_candidate_id' => $candidate->id,
                'hris_contract_id' => $contract->id,
                'vhire_candidate_id' => $candidate->vhire_candidate_id,
                'candidate_code' => $candidate->candidate_code,
                'contract_status' => $contract->status,
                'signature_status' => $contract->signature_status,
                'signing_method' => $contract->signing_method,
                'visible_in_vhire' => (bool) $contract->visible_in_vhire,
            ],
        ]);
    }
}
